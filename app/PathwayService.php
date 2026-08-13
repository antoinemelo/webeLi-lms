<?php

declare(strict_types=1);

function new_entity_reference(string $prefix): string
{
    return strtoupper($prefix) . '-' . strtoupper(bin2hex(random_bytes(8)));
}

function unique_course_code(PDO $pdo, string $sourceCode): string
{
    $stem = trim((string) preg_replace('/[^A-Z0-9]+/', '-', strtoupper($sourceCode)), '-');
    $stem = substr($stem !== '' ? $stem : 'COURS', 0, 32) . '-COPIE';
    $candidate = $stem;
    $suffix = 1;
    $exists = $pdo->prepare('SELECT 1 FROM courses WHERE code=?');
    while (true) {
        $exists->execute([$candidate]);
        if (!$exists->fetchColumn()) return $candidate;
        $suffix++;
        $candidate = substr($stem, 0, 39 - strlen((string)$suffix)) . '-' . $suffix;
    }
}

/**
 * @return 'updated'|'invalid'|'duplicate'|'forbidden'
 */
function update_course_identity(PDO $pdo, int $courseId, int $teacherId, string $title, string $code): string
{
    if(!teacher_owns_course($pdo,$courseId,$teacherId))return 'forbidden';
    $title=trim($title);
    $code=strtoupper(trim($code));
    if($title===''||mb_strlen($title)>160||!preg_match('/^[A-Z0-9][A-Z0-9._-]{2,39}$/',$code))return 'invalid';
    $duplicate=$pdo->prepare('SELECT 1 FROM courses WHERE lower(code)=lower(?) AND id<>?');
    $duplicate->execute([$code,$courseId]);
    if($duplicate->fetchColumn())return 'duplicate';
    $update=$pdo->prepare('UPDATE courses SET title=?,code=? WHERE id=? AND teacher_id=?');
    $update->execute([$title,$code,$courseId,$teacherId]);
    return 'updated';
}

function page_objectives(PDO $pdo, int $pageId): array
{
    $query=$pdo->prepare('SELECT id,page_id,title,description,position FROM page_objectives WHERE page_id=? ORDER BY position,id');
    $query->execute([$pageId]);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function pathway_objectives(PDO $pdo, int $courseId): array
{
    $query=$pdo->prepare("SELECT MIN(po.id) AS id,po.title,MAX(po.description) AS description,MIN(pi.position) AS position,COUNT(DISTINCT pi.id) AS item_count
        FROM pathway_items pi JOIN page_objectives po ON po.page_id=pi.page_id
        WHERE pi.course_id=? GROUP BY lower(po.title) ORDER BY MIN(pi.position),po.title");
    $query->execute([$courseId]);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function copy_course(PDO $pdo, int $sourceId, int $teacherId, string $title, bool $resetDeadlines): ?int
{
    $sourceQuery = $pdo->prepare('SELECT * FROM courses WHERE id=?');
    $sourceQuery->execute([$sourceId]);
    $source = $sourceQuery->fetch(PDO::FETCH_ASSOC);
    if (!$source || !teacher_can_access_course($pdo,$sourceId,$teacherId) || trim($title) === '') return null;

    $pdo->beginTransaction();
    try {
        $code = unique_course_code($pdo, (string)$source['code']);
        $pdo->prepare('INSERT INTO courses(reference,title,code,description,teacher_id,accent,archived) VALUES(?,?,?,?,?,?,0)')
            ->execute([new_entity_reference('COURSE'),trim($title),$code,$source['description'],$teacherId,$source['accent']]);
        $newCourseId = (int)$pdo->lastInsertId();

        $skillMap = [];
        $skills = $pdo->prepare('SELECT * FROM course_skills WHERE course_id=? ORDER BY position,id');
        $skills->execute([$sourceId]);
        $insertSkill = $pdo->prepare('INSERT INTO course_skills(course_id,code,title,description,position) VALUES(?,?,?,?,?)');
        foreach ($skills->fetchAll(PDO::FETCH_ASSOC) as $skill) {
            $insertSkill->execute([$newCourseId,$skill['code'],$skill['title'],$skill['description'],$skill['position']]);
            $skillMap[(int)$skill['id']] = (int)$pdo->lastInsertId();
        }

        $rewardTypes = $pdo->prepare('SELECT * FROM reward_types WHERE course_id=? ORDER BY id');
        $rewardTypes->execute([$sourceId]);
        $insertReward = $pdo->prepare('INSERT INTO reward_types(course_id,name,icon,color,default_points,active) VALUES(?,?,?,?,?,?)');
        foreach ($rewardTypes->fetchAll(PDO::FETCH_ASSOC) as $reward) {
            $insertReward->execute([$newCourseId,$reward['name'],$reward['icon'],$reward['color'],$reward['default_points'],$reward['active']]);
        }

        $items = $pdo->prepare('SELECT * FROM pathway_items WHERE course_id=? ORDER BY position,id');
        $items->execute([$sourceId]);
        $insertItem = $pdo->prepare('INSERT INTO pathway_items(course_id,page_id,position,deadline,is_evaluation,instructions) VALUES(?,?,?,?,?,?)');
        $oldItemIds = [];
        $itemMap = [];
        foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $insertItem->execute([$newCourseId,$item['page_id'],$item['position'],$resetDeadlines?null:$item['deadline'],$item['is_evaluation'],$item['instructions']]);
            $oldItemId = (int)$item['id'];
            $oldItemIds[] = $oldItemId;
            $itemMap[$oldItemId] = (int)$pdo->lastInsertId();
        }

        $readSkills = $pdo->prepare('SELECT skill_id FROM item_skills WHERE pathway_item_id=?');
        $insertItemSkill = $pdo->prepare('INSERT INTO item_skills(pathway_item_id,skill_id) VALUES(?,?)');
        foreach ($oldItemIds as $oldItemId) {
            $readSkills->execute([$oldItemId]);
            foreach ($readSkills->fetchAll(PDO::FETCH_COLUMN) as $oldSkillId) {
                if (isset($skillMap[(int)$oldSkillId])) $insertItemSkill->execute([$itemMap[$oldItemId],$skillMap[(int)$oldSkillId]]);
            }
        }

        $pdo->commit();
        return $newCourseId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function remove_pathway_item(PDO $pdo, int $itemId, int $teacherId): ?int
{
    $query = $pdo->prepare('SELECT course_id FROM pathway_items WHERE id=?');
    $query->execute([$itemId]);
    $courseId = $query->fetchColumn();
    if ($courseId === false || !teacher_can_access_course($pdo,(int)$courseId,$teacherId)) return null;

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM edit_locks WHERE entity_type='pathway_item' AND entity_id=?")->execute([$itemId]);
        $pdo->prepare('DELETE FROM pathway_items WHERE id=?')->execute([$itemId]);
        $positions = $pdo->prepare('SELECT id FROM pathway_items WHERE course_id=? ORDER BY position,id');
        $positions->execute([$courseId]);
        $update = $pdo->prepare('UPDATE pathway_items SET position=? WHERE id=?');
        foreach ($positions->fetchAll(PDO::FETCH_COLUMN) as $index => $remainingId) {
            $update->execute([$index + 1,$remainingId]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
    return (int)$courseId;
}

function delete_unused_page(PDO $pdo, int $pageId, int $teacherId): bool
{
    $usage = $pdo->prepare('SELECT COUNT(*) FROM pathway_items WHERE page_id=? AND EXISTS(SELECT 1 FROM pages WHERE id=? AND owner_id=?)');
    $usage->execute([$pageId,$pageId,$teacherId]);
    if ((int)$usage->fetchColumn() > 0) return false;
    $pdo->beginTransaction();
    try{$pdo->prepare("DELETE FROM collaboration_comments WHERE subject_type='page' AND subject_id=?")->execute([$pageId]);$pdo->prepare("DELETE FROM edit_locks WHERE entity_type='page_metadata' AND entity_id=?")->execute([$pageId]);$pdo->prepare("DELETE FROM edit_locks WHERE entity_type='page_block' AND entity_id IN (SELECT id FROM page_blocks WHERE page_id=?)")->execute([$pageId]);$delete=$pdo->prepare('DELETE FROM pages WHERE id=? AND owner_id=?');$delete->execute([$pageId,$teacherId]);$pdo->commit();return $delete->rowCount()===1;}
    catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
}
