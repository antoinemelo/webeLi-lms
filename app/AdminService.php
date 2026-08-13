<?php

declare(strict_types=1);

function superadmin_delete_page(PDO $pdo, int $pageId): bool
{
    $exists=$pdo->prepare('SELECT id FROM pages WHERE id=?');$exists->execute([$pageId]);if(!$exists->fetchColumn())return false;
    $pdo->beginTransaction();
    try{$pdo->prepare("DELETE FROM collaboration_comments WHERE subject_type='page' AND subject_id=?")->execute([$pageId]);$pdo->prepare("DELETE FROM edit_locks WHERE entity_type='page_metadata' AND entity_id=?")->execute([$pageId]);$pdo->prepare("DELETE FROM edit_locks WHERE entity_type='page_block' AND entity_id IN (SELECT id FROM page_blocks WHERE page_id=?)")->execute([$pageId]);$pdo->prepare("DELETE FROM edit_locks WHERE entity_type='pathway_item' AND entity_id IN (SELECT id FROM pathway_items WHERE page_id=?)")->execute([$pageId]);$pdo->prepare('DELETE FROM pathway_items WHERE page_id=?')->execute([$pageId]);$pdo->prepare('DELETE FROM pages WHERE id=?')->execute([$pageId]);$pdo->commit();return true;}
    catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
}

function superadmin_delete_course(PDO $pdo, int $courseId): bool
{
    $pdo->beginTransaction();
    try{$pdo->prepare("DELETE FROM collaboration_comments WHERE subject_type='course' AND subject_id=?")->execute([$courseId]);$pdo->prepare("DELETE FROM edit_locks WHERE entity_type='course_structure' AND entity_id=?")->execute([$courseId]);$pdo->prepare("DELETE FROM edit_locks WHERE entity_type='pathway_item' AND entity_id IN (SELECT id FROM pathway_items WHERE course_id=?)")->execute([$courseId]);$delete=$pdo->prepare('DELETE FROM courses WHERE id=?');$delete->execute([$courseId]);$pdo->commit();return $delete->rowCount()===1;}
    catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
}

function superadmin_delete_user(PDO $pdo, int $userId): bool
{
    $query=$pdo->prepare('SELECT * FROM users WHERE id=?');$query->execute([$userId]);$user=$query->fetch(PDO::FETCH_ASSOC);if(!$user)return false;
    $pdo->beginTransaction();
    try{
        if($user['role']==='teacher'){
            $pdo->prepare('DELETE FROM course_teachers WHERE teacher_id=? OR added_by=?')->execute([$userId,$userId]);
            $pdo->prepare("DELETE FROM collaboration_comments WHERE subject_type='course' AND subject_id IN (SELECT id FROM courses WHERE teacher_id=?)")->execute([$userId]);
            $pdo->prepare("DELETE FROM collaboration_comments WHERE subject_type='page' AND subject_id IN (SELECT id FROM pages WHERE owner_id=?)")->execute([$userId]);
            $pdo->prepare("DELETE FROM edit_locks WHERE entity_type='course_structure' AND entity_id IN (SELECT id FROM courses WHERE teacher_id=?)")->execute([$userId]);
            $pdo->prepare("DELETE FROM edit_locks WHERE entity_type='pathway_item' AND entity_id IN (SELECT pi.id FROM pathway_items pi JOIN courses c ON c.id=pi.course_id WHERE c.teacher_id=? UNION SELECT pi.id FROM pathway_items pi JOIN pages p ON p.id=pi.page_id WHERE p.owner_id=?)")->execute([$userId,$userId]);
            $pdo->prepare("DELETE FROM edit_locks WHERE entity_type='page_metadata' AND entity_id IN (SELECT id FROM pages WHERE owner_id=?)")->execute([$userId]);
            $pdo->prepare("DELETE FROM edit_locks WHERE entity_type='page_block' AND entity_id IN (SELECT b.id FROM page_blocks b JOIN pages p ON p.id=b.page_id WHERE p.owner_id=?)")->execute([$userId]);
            $pdo->prepare('DELETE FROM pathway_items WHERE page_id IN (SELECT id FROM pages WHERE owner_id=?)')->execute([$userId]);
            $pdo->prepare('DELETE FROM courses WHERE teacher_id=?')->execute([$userId]);
            $pdo->prepare('DELETE FROM reward_awards WHERE awarded_by=?')->execute([$userId]);
            $pdo->prepare('DELETE FROM pages WHERE owner_id=?')->execute([$userId]);
        }
        $pdo->prepare('DELETE FROM notification_outbox WHERE recipient=?')->execute([$user['email']]);
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$userId]);
        $pdo->commit();return true;
    }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
}
