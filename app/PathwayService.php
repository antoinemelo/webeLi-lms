<?php

declare(strict_types=1);

function page_pathway_return_course(PDO $pdo,int $pageId,int $teacherId,int $courseId): ?int
{
    if($pageId<1||$courseId<1||!teacher_can_access_course($pdo,$courseId,$teacherId))return null;
    $query=$pdo->prepare('SELECT 1 FROM pathway_items pi JOIN courses c ON c.id=pi.course_id WHERE pi.page_id=? AND pi.course_id=? AND c.archived=0 LIMIT 1');
    $query->execute([$pageId,$courseId]);
    return $query->fetchColumn()?$courseId:null;
}

/** @return array{status:'updated'|'unchanged'|'invalid'|'missing'|'locked',course_id:?int} */
function reorder_pathway_item(PDO $pdo,int $itemId,int $targetPosition,int $teacherId): array
{
    $itemQuery=$pdo->prepare('SELECT id,course_id,position FROM pathway_items WHERE id=?');
    $itemQuery->execute([$itemId]);$item=$itemQuery->fetch(PDO::FETCH_ASSOC);
    if(!$item||!teacher_can_access_course($pdo,(int)$item['course_id'],$teacherId))return ['status'=>'missing','course_id'=>null];
    $courseId=(int)$item['course_id'];
    if($targetPosition<1)return ['status'=>'invalid','course_id'=>$courseId];
    $lock=acquire_edit_lock($pdo,'course_structure',$courseId,$teacherId);
    if(!$lock['ok'])return ['status'=>'locked','course_id'=>$courseId];
    try{
        $ordered=$pdo->prepare('SELECT id FROM pathway_items WHERE course_id=? ORDER BY position,id');
        $ordered->execute([$courseId]);$ids=array_map('intval',$ordered->fetchAll(PDO::FETCH_COLUMN));
        $currentIndex=array_search($itemId,$ids,true);
        if($currentIndex===false)return ['status'=>'missing','course_id'=>$courseId];
        $targetPosition=min($targetPosition,count($ids));
        if($currentIndex===$targetPosition-1)return ['status'=>'unchanged','course_id'=>$courseId];
        array_splice($ids,$currentIndex,1);
        array_splice($ids,$targetPosition-1,0,[$itemId]);
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE pathway_items SET position=-position WHERE course_id=?')->execute([$courseId]);
        $update=$pdo->prepare('UPDATE pathway_items SET position=? WHERE id=? AND course_id=?');
        foreach($ids as $index=>$id)$update->execute([$index+1,$id,$courseId]);
        $pdo->commit();
        return ['status'=>'updated','course_id'=>$courseId];
    }catch(Throwable $exception){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $exception;
    }finally{
        release_edit_locks($pdo,$teacherId,'course_structure',$courseId);
    }
}

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
function update_course_identity(PDO $pdo, int $courseId, int $teacherId, string $title, string $code, string $description): string
{
    if(!teacher_owns_course($pdo,$courseId,$teacherId))return 'forbidden';
    $title=trim($title);
    $code=strtoupper(trim($code));
    $description=trim($description);
    if($title===''||mb_strlen($title)>160||mb_strlen($description)>2000||!preg_match('/^[A-Z0-9][A-Z0-9._-]{2,39}$/',$code))return 'invalid';
    $duplicate=$pdo->prepare('SELECT 1 FROM courses WHERE lower(code)=lower(?) AND id<>?');
    $duplicate->execute([$code,$courseId]);
    if($duplicate->fetchColumn())return 'duplicate';
    $update=$pdo->prepare('UPDATE courses SET title=?,code=?,description=? WHERE id=? AND teacher_id=?');
    $update->execute([$title,$code,$description,$courseId,$teacherId]);
    return 'updated';
}

/**
 * @return array{status:'created'|'invalid'|'duplicate',course_id?:int}
 */
function create_pathway(PDO $pdo, int $teacherId, string $title, string $code, string $description): array
{
    $title=trim($title);
    $code=strtoupper(trim($code));
    $description=trim($description);
    if($title===''||mb_strlen($title)>160||mb_strlen($description)>2000||!preg_match('/^[A-Z0-9][A-Z0-9._-]{2,39}$/',$code))return ['status'=>'invalid'];
    $duplicate=$pdo->prepare('SELECT 1 FROM courses WHERE lower(code)=lower(?)');
    $duplicate->execute([$code]);
    if($duplicate->fetchColumn())return ['status'=>'duplicate'];

    $pdo->beginTransaction();
    try{
        $insert=$pdo->prepare('INSERT INTO courses(reference,title,code,description,teacher_id,accent) VALUES(?,?,?,?,?,?)');
        $insert->execute([new_entity_reference('COURSE'),$title,$code,$description,$teacherId,'#6d5dfc']);
        $courseId=(int)$pdo->lastInsertId();
        $reward=$pdo->prepare('INSERT INTO reward_types(course_id,name,icon,color,default_points) VALUES(?,?,?,?,?)');
        foreach([['Persévérance','🌱',1],['Curiosité','🔎',1],['Entraide','🤝',1],['Travail soigné','✨',1]] as [$name,$icon,$points])$reward->execute([$courseId,$name,$icon,'#6d5dfc',$points]);
        $pdo->commit();
        return ['status'=>'created','course_id'=>$courseId];
    }catch(Throwable $exception){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $exception;
    }
}

function page_objectives(PDO $pdo, int $pageId): array
{
    $query=$pdo->prepare('SELECT id,page_id,title,description,position FROM page_objectives WHERE page_id=? ORDER BY position,id');
    $query->execute([$pageId]);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function pathway_alphabetical_key(string $value): string
{
    $normalized=class_exists('Normalizer')?Normalizer::normalize($value,Normalizer::FORM_D):$value;
    if(!is_string($normalized))$normalized=$value;
    $withoutMarks=preg_replace('/\p{Mn}+/u','',$normalized);
    return mb_strtolower(is_string($withoutMarks)?$withoutMarks:$normalized,'UTF-8');
}

function pathway_natural_compare(string $left, string $right): int
{
    $comparison=strnatcmp(pathway_alphabetical_key($left),pathway_alphabetical_key($right));
    return $comparison!==0?$comparison:strnatcasecmp($left,$right);
}

function student_detail_neighbors(PDO $pdo, int $courseId, int $enrollmentId): array
{
    $query=$pdo->prepare("SELECT e.id FROM enrollments e JOIN users u ON u.id=e.student_id WHERE e.course_id=? AND e.status='active' AND u.account_status='active' ORDER BY u.name,e.id");
    $query->execute([$courseId]);$ids=array_map('intval',$query->fetchAll(PDO::FETCH_COLUMN));$index=array_search($enrollmentId,$ids,true);
    if($index===false)return ['previous'=>null,'next'=>null];
    return ['previous'=>$index>0?$ids[$index-1]:null,'next'=>$index<count($ids)-1?$ids[$index+1]:null];
}

function normalize_reward_points(mixed $value, int $fallback = 1): int
{
    $fallback=max(-100,min(100,$fallback));if($fallback===0)$fallback=1;
    if(!is_numeric($value))return $fallback;
    $points=max(-100,min(100,(int)$value));
    return $points===0?$fallback:$points;
}

function format_signed_points(int $points): string
{
    return $points>0?'+'.$points:($points<0?'−'.abs($points):'0');
}

function complete_consultation_step(PDO $pdo, int $studentId, int $itemId): bool
{
    $query=$pdo->prepare("SELECT e.id FROM pathway_items pi
        JOIN courses c ON c.id=pi.course_id
        JOIN enrollments e ON e.course_id=pi.course_id AND e.student_id=? AND e.status='active'
        JOIN users u ON u.id=e.student_id AND u.account_status='active'
        WHERE pi.id=? AND c.archived=0 AND pi.self_evaluation_enabled=0 AND pi.is_evaluation=0
          AND (pi.access_mode='all' OR (pi.access_mode='restricted' AND EXISTS(
              SELECT 1 FROM pathway_item_students a WHERE a.pathway_item_id=pi.id AND a.student_id=?)))");
    $query->execute([$studentId,$itemId,$studentId]);$enrollmentId=(int)($query->fetchColumn()?:0);
    if($enrollmentId<1)return false;
    $complete=$pdo->prepare("INSERT INTO progress(enrollment_id,pathway_item_id,completed_at,updated_at)
        VALUES(?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)
        ON CONFLICT(enrollment_id,pathway_item_id) DO UPDATE SET completed_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP
        WHERE progress.completed_at IS NULL");
    $complete->execute([$enrollmentId,$itemId]);
    return true;
}

function pathway_objectives(PDO $pdo, int $courseId): array
{
    $query=$pdo->prepare("SELECT MIN(po.id) AS id,po.title,MAX(po.description) AS description,MIN(pi.position) AS position,COUNT(DISTINCT pi.id) AS item_count,GROUP_CONCAT(DISTINCT pi.position) AS item_positions
        FROM pathway_items pi JOIN page_objectives po ON po.page_id=pi.page_id
        WHERE pi.course_id=? AND pi.framework_tracking_enabled=1 GROUP BY lower(po.title)");
    $query->execute([$courseId]);
    $objectives=$query->fetchAll(PDO::FETCH_ASSOC);
    foreach($objectives as &$objective){$positions=array_values(array_unique(array_map('intval',explode(',',(string)$objective['item_positions']))));sort($positions,SORT_NUMERIC);$objective['item_positions']=$positions;}unset($objective);
    usort($objectives,fn(array $left,array $right): int=>pathway_natural_compare((string)$left['title'],(string)$right['title']));
    return $objectives;
}

function pathway_sidebar_skills(PDO $pdo, int $courseId): array
{
    $query=$pdo->prepare("SELECT s.*,
        COUNT(i.pathway_item_id) AS linked_count,
        SUM(CASE WHEN pi.framework_tracking_enabled=1 THEN 1 ELSE 0 END) AS tracked_count,
        SUM(CASE WHEN pi.framework_tracking_enabled=1 AND pi.access_mode='all' THEN 1 ELSE 0 END) AS open_count,
        SUM(CASE WHEN pi.framework_tracking_enabled=1 AND pi.access_mode IN ('restricted','none') THEN 1 ELSE 0 END) AS restricted_count
        FROM course_skills s
        LEFT JOIN item_skills i ON i.skill_id=s.id
        LEFT JOIN pathway_items pi ON pi.id=i.pathway_item_id
        WHERE s.course_id=? GROUP BY s.id");
    $query->execute([$courseId]);
    $skills=[];
    foreach($query->fetchAll(PDO::FETCH_ASSOC) as $skill){
        $linked=(int)$skill['linked_count'];
        $tracked=(int)$skill['tracked_count'];
        $open=(int)$skill['open_count'];
        $restricted=(int)$skill['restricted_count'];
        if($linked>0&&$tracked===0)continue;
        $skill['access_visibility']=$open===0&&$restricted>0?'restricted':'open';
        $skills[]=$skill;
    }
    usort($skills,fn(array $left,array $right): int=>pathway_natural_compare((string)$left['code'],(string)$right['code']));
    return $skills;
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
        $insertItem = $pdo->prepare('INSERT INTO pathway_items(course_id,page_id,position,deadline,is_evaluation,self_evaluation_enabled,evaluation_weight,instructions,access_mode,framework_tracking_enabled) VALUES(?,?,?,?,?,?,?,?,?,?)');
        $oldItemIds = [];
        $itemMap = [];
        foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $insertItem->execute([$newCourseId,$item['page_id'],$item['position'],$resetDeadlines?null:$item['deadline'],$item['is_evaluation'],$item['self_evaluation_enabled']??1,$item['evaluation_weight']??1,$item['instructions'],$item['access_mode'],$item['framework_tracking_enabled']??1]);
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

function normalize_evaluation_weight(mixed $value): ?float
{
    if(is_string($value))$value=str_replace(',','.',trim($value));
    if(!is_numeric($value))return null;
    $weight=(float)$value;
    foreach([0.5,1.0,2.0,3.0,4.0] as $allowed){
        if(abs($weight-$allowed)<0.00001)return $allowed;
    }
    return null;
}

function save_student_private_note(PDO $pdo, int $studentId, int $itemId, string $body): bool
{
    $body=trim($body);
    if(mb_strlen($body)>10000)return false;
    $access=$pdo->prepare("SELECT 1 FROM pathway_items pi
        JOIN enrollments e ON e.course_id=pi.course_id AND e.student_id=? AND e.status='active'
        WHERE pi.id=? AND (pi.access_mode='all' OR (pi.access_mode='restricted' AND EXISTS(
            SELECT 1 FROM pathway_item_students a WHERE a.pathway_item_id=pi.id AND a.student_id=?)))");
    $access->execute([$studentId,$itemId,$studentId]);
    if(!$access->fetchColumn())return false;
    if($body===''){
        $pdo->prepare('DELETE FROM student_private_notes WHERE student_id=? AND pathway_item_id=?')->execute([$studentId,$itemId]);
        return true;
    }
    $pdo->prepare("INSERT INTO student_private_notes(student_id,pathway_item_id,body,updated_at)
        VALUES(?,?,?,CURRENT_TIMESTAMP)
        ON CONFLICT(student_id,pathway_item_id) DO UPDATE SET body=excluded.body,updated_at=CURRENT_TIMESTAMP")
        ->execute([$studentId,$itemId,$body]);
    return true;
}

function evaluation_summary(PDO $pdo, int $courseId, int $enrollmentId): array
{
    $context=$pdo->prepare("SELECT e.student_id FROM enrollments e WHERE e.id=? AND e.course_id=? AND e.status='active'");
    $context->execute([$enrollmentId,$courseId]);
    $studentId=(int)($context->fetchColumn()?:0);
    if($studentId<1)return ['rows'=>[],'average'=>null,'graded'=>0,'total'=>0,'weight_total'=>0.0];
    $query=$pdo->prepare("SELECT pi.id,pi.position,pi.deadline,pi.evaluation_weight,p.title,
        pr.evaluation_score,pr.teacher_note,pr.teacher_validated_at
        FROM pathway_items pi JOIN pages p ON p.id=pi.page_id
        LEFT JOIN progress pr ON pr.pathway_item_id=pi.id AND pr.enrollment_id=?
        WHERE pi.course_id=? AND pi.is_evaluation=1 AND (
            pi.access_mode='all' OR (pi.access_mode='restricted' AND EXISTS(
                SELECT 1 FROM pathway_item_students a WHERE a.pathway_item_id=pi.id AND a.student_id=?)))
        ORDER BY pi.position,pi.id");
    $query->execute([$enrollmentId,$courseId,$studentId]);
    $rows=$query->fetchAll(PDO::FETCH_ASSOC);
    $weighted=0.0;$weightTotal=0.0;$graded=0;
    foreach($rows as &$row){
        $row['evaluation_weight']=(float)$row['evaluation_weight'];
        if($row['evaluation_score']!==null){
            $row['evaluation_score']=(float)$row['evaluation_score'];
            $weighted+=$row['evaluation_score']*$row['evaluation_weight'];
            $weightTotal+=$row['evaluation_weight'];
            $graded++;
        }
    }
    unset($row);
    return ['rows'=>$rows,'average'=>$weightTotal>0?$weighted/$weightTotal:null,'graded'=>$graded,'total'=>count($rows),'weight_total'=>$weightTotal];
}

function create_course_announcement(PDO $pdo, int $courseId, int $teacherId, string $title, string $body): ?int
{
    $title=trim($title);$body=trim($body);
    if(!teacher_can_access_course($pdo,$courseId,$teacherId)||$title===''||$body===''||mb_strlen($title)>160||mb_strlen($body)>5000)return null;
    $insert=$pdo->prepare('INSERT INTO course_announcements(course_id,created_by,title,body) VALUES(?,?,?,?)');
    $insert->execute([$courseId,$teacherId,$title,$body]);
    $announcementId=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT OR IGNORE INTO announcement_reads(announcement_id,student_id,read_at) VALUES(?,?,CURRENT_TIMESTAMP)')->execute([$announcementId,$teacherId]);
    return $announcementId;
}

function archive_course_announcement(PDO $pdo, int $announcementId, int $teacherId): ?int
{
    $query=$pdo->prepare('SELECT course_id FROM course_announcements WHERE id=? AND archived=0');
    $query->execute([$announcementId]);$courseId=(int)($query->fetchColumn()?:0);
    if($courseId<1||!teacher_can_access_course($pdo,$courseId,$teacherId))return null;
    $pdo->prepare('UPDATE course_announcements SET archived=1 WHERE id=?')->execute([$announcementId]);
    return $courseId;
}

function delete_course_announcement(PDO $pdo, int $announcementId, int $teacherId): ?int
{
    $query=$pdo->prepare('SELECT course_id FROM course_announcements WHERE id=?');
    $query->execute([$announcementId]);$courseId=(int)($query->fetchColumn()?:0);
    if($courseId<1||!teacher_can_access_course($pdo,$courseId,$teacherId))return null;
    $pdo->prepare('DELETE FROM course_announcements WHERE id=?')->execute([$announcementId]);
    return $courseId;
}

function course_announcements_for_student(PDO $pdo, int $courseId, int $studentId): array
{
    $access=$pdo->prepare("SELECT 1 FROM enrollments WHERE course_id=? AND student_id=? AND status='active'");
    $access->execute([$courseId,$studentId]);
    if(!$access->fetchColumn())return [];
    $query=$pdo->prepare("SELECT a.*,u.name AS author_name,r.read_at
        FROM course_announcements a LEFT JOIN users u ON u.id=a.created_by
        LEFT JOIN announcement_reads r ON r.announcement_id=a.id AND r.student_id=?
        WHERE a.course_id=? AND a.archived=0 ORDER BY a.created_at DESC,a.id DESC");
    $query->execute([$studentId,$courseId]);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function unread_announcement_count(PDO $pdo, int $courseId, int $studentId): int
{
    return course_announcement_status($pdo,$courseId,$studentId)['unread'];
}

function course_announcement_status(PDO $pdo, int $courseId, int $studentId): array
{
    $query=$pdo->prepare("SELECT COUNT(a.id) AS total,
        COUNT(CASE WHEN a.id IS NOT NULL AND r.announcement_id IS NULL THEN 1 END) AS unread
        FROM enrollments e
        LEFT JOIN course_announcements a ON a.course_id=e.course_id AND a.archived=0
        LEFT JOIN announcement_reads r ON r.announcement_id=a.id AND r.student_id=e.student_id
        WHERE e.course_id=? AND e.student_id=? AND e.status='active'");
    $query->execute([$courseId,$studentId]);$status=$query->fetch(PDO::FETCH_ASSOC)?:[];
    return ['total'=>(int)($status['total']??0),'unread'=>(int)($status['unread']??0)];
}

function mark_course_announcements_read(PDO $pdo, int $courseId, int $studentId): bool
{
    $access=$pdo->prepare("SELECT 1 FROM enrollments WHERE course_id=? AND student_id=? AND status='active'");
    $access->execute([$courseId,$studentId]);
    if(!$access->fetchColumn())return false;
    $pdo->prepare("INSERT OR IGNORE INTO announcement_reads(announcement_id,student_id,read_at)
        SELECT id,?,CURRENT_TIMESTAMP FROM course_announcements WHERE course_id=? AND archived=0")
        ->execute([$studentId,$courseId]);
    return true;
}

function unread_announcements_for_user(PDO $pdo, int $userId): array
{
    $role=$pdo->prepare("SELECT role FROM users WHERE id=? AND account_status='active'");
    $role->execute([$userId]);$role=(string)($role->fetchColumn()?:'');
    if($role==='student'){
        $query=$pdo->prepare("SELECT a.id,a.course_id,a.title,a.body,a.created_at,c.title AS course_title,u.name AS author_name
            FROM course_announcements a JOIN courses c ON c.id=a.course_id AND c.archived=0
            JOIN enrollments e ON e.course_id=c.id AND e.student_id=? AND e.status='active'
            LEFT JOIN users u ON u.id=a.created_by
            LEFT JOIN announcement_reads r ON r.announcement_id=a.id AND r.student_id=?
            WHERE a.archived=0 AND r.announcement_id IS NULL ORDER BY a.created_at DESC,a.id DESC");
        $query->execute([$userId,$userId]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }
    if($role==='teacher'){
        $query=$pdo->prepare("SELECT a.id,a.course_id,a.title,a.body,a.created_at,c.title AS course_title,u.name AS author_name
            FROM course_announcements a JOIN courses c ON c.id=a.course_id AND c.archived=0
            LEFT JOIN users u ON u.id=a.created_by
            LEFT JOIN announcement_reads r ON r.announcement_id=a.id AND r.student_id=?
            WHERE a.archived=0 AND r.announcement_id IS NULL AND (a.created_by IS NULL OR a.created_by<>?)
              AND (c.teacher_id=? OR EXISTS(SELECT 1 FROM course_teachers ct WHERE ct.course_id=c.id AND ct.teacher_id=?))
            ORDER BY a.created_at DESC,a.id DESC");
        $query->execute([$userId,$userId,$userId,$userId]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }
    return [];
}

function mark_announcement_read_for_user(PDO $pdo, int $announcementId, int $userId): ?array
{
    $user=$pdo->prepare("SELECT role FROM users WHERE id=? AND account_status='active'");
    $user->execute([$userId]);$role=(string)($user->fetchColumn()?:'');
    if($role==='student'){
        $query=$pdo->prepare("SELECT a.id,a.course_id FROM course_announcements a JOIN courses c ON c.id=a.course_id AND c.archived=0
            JOIN enrollments e ON e.course_id=c.id AND e.student_id=? AND e.status='active' WHERE a.id=? AND a.archived=0");
        $query->execute([$userId,$announcementId]);
    }elseif($role==='teacher'){
        $query=$pdo->prepare("SELECT a.id,a.course_id FROM course_announcements a JOIN courses c ON c.id=a.course_id AND c.archived=0
            WHERE a.id=? AND a.archived=0 AND (c.teacher_id=? OR EXISTS(
                SELECT 1 FROM course_teachers ct WHERE ct.course_id=c.id AND ct.teacher_id=?))");
        $query->execute([$announcementId,$userId,$userId]);
    }else return null;
    $announcement=$query->fetch(PDO::FETCH_ASSOC);
    if(!$announcement)return null;
    $pdo->prepare('INSERT OR IGNORE INTO announcement_reads(announcement_id,student_id,read_at) VALUES(?,?,CURRENT_TIMESTAMP)')->execute([$announcementId,$userId]);
    return $announcement;
}
