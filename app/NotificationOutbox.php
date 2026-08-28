<?php

declare(strict_types=1);

const MAIL_CRON_HEARTBEAT_MAX_AGE = 120;
const MAIL_OUTBOX_BATCH_SIZE = 5;
const MAIL_OUTBOX_BATCH_INTERVAL = 5;
const MAIL_OUTBOX_WORKER_SECONDS = 55;
const ANNOUNCEMENT_MAIL_DELAY_SECONDS = 90;

function mail_cron_heartbeat_path(string $applicationRoot): string
{
    return rtrim($applicationRoot,'/').'/storage/mail-cron.heartbeat';
}

function mail_cron_is_active(string $applicationRoot, ?int $now=null): bool
{
    $modifiedAt=@filemtime(mail_cron_heartbeat_path($applicationRoot));
    $now??=time();
    return $modifiedAt!==false&&$modifiedAt<=$now&&$modifiedAt>=$now-MAIL_CRON_HEARTBEAT_MAX_AGE;
}

function record_mail_cron_heartbeat(string $applicationRoot, ?int $now=null): bool
{
    $storage=rtrim($applicationRoot,'/').'/storage';
    if(!is_dir($storage)&&!mkdir($storage,0775,true)&&!is_dir($storage))return false;
    return touch(mail_cron_heartbeat_path($applicationRoot),$now??time());
}

function outbox_send_pending_batch(PDO $pdo, int $limit=MAIL_OUTBOX_BATCH_SIZE, bool $announcementsOnly=false, ?callable $delivery=null): array
{
    $limit=max(1,min(500,$limit));
    $scope=$announcementsOnly?" AND o.event='course.announcement'":'';
    $messages=$pdo->query("SELECT o.* FROM notification_outbox o
        WHERE o.status='pending'
          AND COALESCE(o.available_at,o.created_at)<=CURRENT_TIMESTAMP
          $scope
          AND (o.event<>'course.announcement' OR o.announcement_id IS NULL OR EXISTS(
              SELECT 1 FROM course_announcements a WHERE a.id=o.announcement_id AND a.archived=0
          ))
        ORDER BY COALESCE(o.available_at,o.created_at),o.id
        LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC);
    $delivery??=static fn(string $recipient,string $subject,string $body):bool=>deliver_app_mail($recipient,$subject,$body);
    $results=[];
    foreach($messages as $message){
        if($message['event']==='course.announcement'&&$message['announcement_id']!==null){
            $announcement=$pdo->prepare('SELECT 1 FROM course_announcements WHERE id=? AND archived=0');
            $announcement->execute([(int)$message['announcement_id']]);
            if(!$announcement->fetchColumn()){
                $pdo->prepare("DELETE FROM notification_outbox WHERE id=? AND status='pending'")->execute([(int)$message['id']]);
                continue;
            }
        }
        $ok=(bool)$delivery((string)$message['recipient'],(string)$message['subject'],(string)$message['body']);
        $statement=$pdo->prepare($ok
            ? "UPDATE notification_outbox SET status='sent',attempts=attempts+1,last_error=NULL,sent_at=CURRENT_TIMESTAMP WHERE id=? AND status='pending'"
            : "UPDATE notification_outbox SET attempts=attempts+1,last_error='mail() a retourné false',available_at=datetime('now','+5 minutes') WHERE id=? AND status='pending'");
        $statement->execute([(int)$message['id']]);
        $results[]=['id'=>(int)$message['id'],'recipient'=>(string)$message['recipient'],'sent'=>$ok];
    }
    return $results;
}

