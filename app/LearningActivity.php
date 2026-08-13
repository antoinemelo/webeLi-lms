<?php

declare(strict_types=1);

const LEARNING_ACTIVITY_MAX_VISIT_SECONDS = 14400;

function record_learning_activity(PDO $pdo, int $studentId, int $itemId, string $visitToken, int $activeSeconds): bool
{
    if (!preg_match('/^[A-Za-z0-9_-]{16,80}$/', $visitToken)) return false;
    $allowed=$pdo->prepare("SELECT pi.id FROM pathway_items pi
        JOIN courses c ON c.id=pi.course_id
        JOIN enrollments e ON e.course_id=c.id
        JOIN users u ON u.id=e.student_id
        WHERE pi.id=? AND e.student_id=? AND e.status='active'
          AND c.archived=0 AND u.account_status='active'
          AND (NOT EXISTS(SELECT 1 FROM pathway_item_students a WHERE a.pathway_item_id=pi.id)
            OR EXISTS(SELECT 1 FROM pathway_item_students a WHERE a.pathway_item_id=pi.id AND a.student_id=?))");
    $allowed->execute([$itemId,$studentId,$studentId]);
    if (!$allowed->fetchColumn()) return false;

    $activeSeconds=max(0,min(LEARNING_ACTIVITY_MAX_VISIT_SECONDS,$activeSeconds));
    $find=$pdo->prepare('SELECT id,started_at,duration_seconds FROM learning_visits WHERE student_id=? AND pathway_item_id=? AND visit_token=?');
    $find->execute([$studentId,$itemId,$visitToken]);
    $visit=$find->fetch(PDO::FETCH_ASSOC);
    if (!$visit) {
        $insert=$pdo->prepare('INSERT INTO learning_visits(student_id,pathway_item_id,visit_token,duration_seconds) VALUES(?,?,?,?)');
        $insert->execute([$studentId,$itemId,$visitToken,min(30,$activeSeconds)]);
        return true;
    }

    $startedAt=strtotime((string)$visit['started_at'].' UTC') ?: time();
    $credibleSeconds=max(30,time()-$startedAt+30);
    $duration=min($activeSeconds,$credibleSeconds,LEARNING_ACTIVITY_MAX_VISIT_SECONDS);
    $update=$pdo->prepare('UPDATE learning_visits SET duration_seconds=MAX(duration_seconds,?),last_seen_at=CURRENT_TIMESTAMP WHERE id=?');
    $update->execute([$duration,$visit['id']]);
    return true;
}

function purge_learning_activity(PDO $pdo): int
{
    $delete=$pdo->prepare("DELETE FROM learning_visits WHERE last_seen_at < datetime('now','-1 month')");
    $delete->execute();
    return $delete->rowCount();
}

function learning_activity_report(PDO $pdo, int $enrollmentId, int $teacherId): array
{
    $context=$pdo->prepare('SELECT e.student_id,e.course_id FROM enrollments e WHERE e.id=?');
    $context->execute([$enrollmentId]);
    $enrollment=$context->fetch(PDO::FETCH_ASSOC);
    if (!$enrollment||!teacher_can_access_course($pdo,(int)$enrollment['course_id'],$teacherId)) return [];

    $query=$pdo->prepare("SELECT lv.started_at,lv.last_seen_at,lv.duration_seconds,pi.id AS item_id,pi.position,p.title
        FROM learning_visits lv
        JOIN pathway_items pi ON pi.id=lv.pathway_item_id
        JOIN pages p ON p.id=pi.page_id
        WHERE lv.student_id=? AND pi.course_id=? AND lv.last_seen_at>=datetime('now','-1 month')
        ORDER BY lv.started_at DESC");
    $query->execute([$enrollment['student_id'],$enrollment['course_id']]);
    $groups=[];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $visit) {
        $start=learning_activity_local_datetime((string)$visit['started_at']);
        $last=learning_activity_local_datetime((string)$visit['last_seen_at']);
        $day=$start->format('Y-m-d');
        $key=$day.'-'.$visit['item_id'];
        if (!isset($groups[$key])) {
            $groups[$key]=[
                'day'=>$day,
                'day_label'=>learning_activity_day_label($start),
                'item_id'=>(int)$visit['item_id'],
                'position'=>(int)$visit['position'],
                'title'=>(string)$visit['title'],
                'first_at'=>$start,
                'last_at'=>$last,
                'duration_seconds'=>0,
                'sessions'=>0,
            ];
        }
        if ($start<$groups[$key]['first_at']) $groups[$key]['first_at']=$start;
        if ($last>$groups[$key]['last_at']) $groups[$key]['last_at']=$last;
        $groups[$key]['duration_seconds']+=(int)$visit['duration_seconds'];
        $groups[$key]['sessions']++;
    }
    usort($groups,static fn(array $a,array $b): int=>$b['last_at']<=>$a['last_at']);
    return array_values($groups);
}

function learning_activity_local_datetime(string $utc): DateTimeImmutable
{
    $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$utc,new DateTimeZone('UTC'));
    if (!$date) $date=new DateTimeImmutable($utc,new DateTimeZone('UTC'));
    return $date->setTimezone(new DateTimeZone('Europe/Zurich'));
}

function learning_activity_day_label(DateTimeInterface $date): string
{
    return $date->format('d/m/Y');
}

function learning_activity_datetime_label(?string $utc): string
{
    if (!$utc) return t('Jamais consultée');
    $date=learning_activity_local_datetime($utc);
    return learning_activity_day_label($date).' · '.$date->format('H:i');
}

function learning_activity_time_label(DateTimeInterface $date): string
{
    return $date->format('H:i');
}

function learning_activity_duration_label(int $seconds): string
{
    $seconds=max(0,$seconds);
    if ($seconds<60) return $seconds.' s';
    $minutes=intdiv($seconds,60);
    if ($minutes<60) return $minutes.' min';
    $hours=intdiv($minutes,60);
    $remaining=$minutes%60;
    return $hours.' h'.($remaining ? ' '.str_pad((string)$remaining,2,'0',STR_PAD_LEFT) : '');
}
