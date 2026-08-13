<?php

declare(strict_types=1);

const EDIT_LOCK_SECONDS = 120;

function teacher_can_access_course(PDO $pdo, int $courseId, int $teacherId): bool
{
    $query=$pdo->prepare("SELECT 1 FROM courses c WHERE c.id=? AND (c.teacher_id=? OR EXISTS(SELECT 1 FROM course_teachers ct WHERE ct.course_id=c.id AND ct.teacher_id=?))");
    $query->execute([$courseId,$teacherId,$teacherId]);
    return (bool)$query->fetchColumn();
}

function teacher_owns_course(PDO $pdo, int $courseId, int $teacherId): bool
{
    $query=$pdo->prepare('SELECT 1 FROM courses WHERE id=? AND teacher_id=?');
    $query->execute([$courseId,$teacherId]);
    return (bool)$query->fetchColumn();
}

function teacher_can_access_page(PDO $pdo, int $pageId, int $teacherId): bool
{
    $query=$pdo->prepare("SELECT 1 FROM pages p WHERE p.id=? AND (p.owner_id=? OR EXISTS(
        SELECT 1 FROM pathway_items pi JOIN courses c ON c.id=pi.course_id
        WHERE pi.page_id=p.id AND (c.teacher_id=? OR EXISTS(SELECT 1 FROM course_teachers ct WHERE ct.course_id=c.id AND ct.teacher_id=?))
    ))");
    $query->execute([$pageId,$teacherId,$teacherId,$teacherId]);
    return (bool)$query->fetchColumn();
}

function teacher_owns_page(PDO $pdo, int $pageId, int $teacherId): bool
{
    $query=$pdo->prepare('SELECT 1 FROM pages WHERE id=? AND owner_id=?');
    $query->execute([$pageId,$teacherId]);
    return (bool)$query->fetchColumn();
}

function course_teachers(PDO $pdo, int $courseId): array
{
    $query=$pdo->prepare("SELECT u.id,u.name,u.email,u.initials,u.color,(u.id=c.teacher_id) AS is_owner
        FROM courses c JOIN users u ON u.id=c.teacher_id WHERE c.id=?
        UNION ALL
        SELECT u.id,u.name,u.email,u.initials,u.color,0 AS is_owner
        FROM course_teachers ct JOIN users u ON u.id=ct.teacher_id WHERE ct.course_id=?
        ORDER BY is_owner DESC,name");
    $query->execute([$courseId,$courseId]);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function lock_target_is_accessible(PDO $pdo, string $type, int $entityId, int $teacherId): bool
{
    return match($type) {
        'page_metadata' => teacher_can_access_page($pdo,$entityId,$teacherId),
        'page_block' => (function() use($pdo,$entityId,$teacherId): bool {
            $query=$pdo->prepare('SELECT page_id FROM page_blocks WHERE id=?');$query->execute([$entityId]);$pageId=(int)$query->fetchColumn();
            return $pageId>0 && teacher_can_access_page($pdo,$pageId,$teacherId);
        })(),
        'pathway_item' => (function() use($pdo,$entityId,$teacherId): bool {
            $query=$pdo->prepare('SELECT course_id FROM pathway_items WHERE id=?');$query->execute([$entityId]);$courseId=(int)$query->fetchColumn();
            return $courseId>0 && teacher_can_access_course($pdo,$courseId,$teacherId);
        })(),
        'course_structure' => teacher_can_access_course($pdo,$entityId,$teacherId),
        default => false,
    };
}

function acquire_edit_lock(PDO $pdo, string $type, int $entityId, int $teacherId): array
{
    if(!in_array($type,['page_metadata','page_block','pathway_item','course_structure'],true)||$entityId<1||!lock_target_is_accessible($pdo,$type,$entityId,$teacherId))return ['ok'=>false,'owner'=>null];
    $now=time();$expires=$now+EDIT_LOCK_SECONDS;
    $pdo->prepare('DELETE FROM edit_locks WHERE expires_at<=?')->execute([$now]);
    $claim=$pdo->prepare('INSERT INTO edit_locks(entity_type,entity_id,teacher_id,acquired_at,expires_at) VALUES(?,?,?,?,?)
        ON CONFLICT(entity_type,entity_id) DO UPDATE SET teacher_id=excluded.teacher_id,acquired_at=excluded.acquired_at,expires_at=excluded.expires_at
        WHERE edit_locks.teacher_id=excluded.teacher_id OR edit_locks.expires_at<=excluded.acquired_at');
    $claim->execute([$type,$entityId,$teacherId,$now,$expires]);
    $query=$pdo->prepare('SELECT l.*,u.name AS owner_name FROM edit_locks l JOIN users u ON u.id=l.teacher_id WHERE l.entity_type=? AND l.entity_id=?');
    $query->execute([$type,$entityId]);$lock=$query->fetch(PDO::FETCH_ASSOC);
    return ['ok'=>$lock&&(int)$lock['teacher_id']===$teacherId,'owner'=>$lock['owner_name']??null,'expires_at'=>(int)($lock['expires_at']??0)];
}

function edit_lock_allows(PDO $pdo, string $type, int $entityId, int $teacherId): bool
{
    $pdo->prepare('DELETE FROM edit_locks WHERE expires_at<=?')->execute([time()]);
    $query=$pdo->prepare('SELECT teacher_id FROM edit_locks WHERE entity_type=? AND entity_id=?');$query->execute([$type,$entityId]);$owner=$query->fetchColumn();
    return $owner===false || (int)$owner===$teacherId;
}

function release_edit_locks(PDO $pdo, int $teacherId, ?string $type=null, ?int $entityId=null): void
{
    if($type!==null&&$entityId!==null)$pdo->prepare('DELETE FROM edit_locks WHERE teacher_id=? AND entity_type=? AND entity_id=?')->execute([$teacherId,$type,$entityId]);
    else $pdo->prepare('DELETE FROM edit_locks WHERE teacher_id=?')->execute([$teacherId]);
}

function collaboration_comments(PDO $pdo, string $type, int $subjectId, string $status): array
{
    $query=$pdo->prepare('SELECT cc.*,a.name AS author_name,a.first_name AS author_first_name,a.last_name AS author_last_name,r.name AS resolver_name,r.first_name AS resolver_first_name,r.last_name AS resolver_last_name FROM collaboration_comments cc JOIN users a ON a.id=cc.author_id LEFT JOIN users r ON r.id=cc.resolved_by WHERE cc.subject_type=? AND cc.subject_id=? AND cc.status=? ORDER BY cc.created_at DESC,cc.id DESC');
    $query->execute([$type,$subjectId,$status]);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function collaboration_person_label(array $comment, string $role='author'): string
{
    $firstName=trim((string)($comment[$role.'_first_name']??''));
    $lastName=trim((string)($comment[$role.'_last_name']??''));
    if($firstName===''&&$lastName==='')return trim((string)($comment[$role.'_name']??''))?:'—';
    $initial=$lastName!==''?mb_strtoupper(mb_substr($lastName,0,1),'UTF-8').'.':'';
    return trim($firstName.' '.$initial);
}

function teacher_can_access_subject(PDO $pdo, string $type, int $subjectId, int $teacherId): bool
{
    return $type==='page' ? teacher_can_access_page($pdo,$subjectId,$teacherId) : ($type==='course' && teacher_can_access_course($pdo,$subjectId,$teacherId));
}

function item_is_visible_to_student(PDO $pdo, int $itemId, int $studentId): bool
{
    $query=$pdo->prepare("SELECT 1 FROM pathway_items pi JOIN enrollments e ON e.course_id=pi.course_id AND e.student_id=? AND e.status='active'
        JOIN courses c ON c.id=pi.course_id WHERE pi.id=? AND c.archived=0 AND (
            pi.access_mode='all'
            OR (pi.access_mode='restricted' AND EXISTS(SELECT 1 FROM pathway_item_students a WHERE a.pathway_item_id=pi.id AND a.student_id=?)))");
    $query->execute([$studentId,$itemId,$studentId]);
    return (bool)$query->fetchColumn();
}
