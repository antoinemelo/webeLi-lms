<?php

declare(strict_types=1);
require_once __DIR__.'/AppSecrets.php';

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
    $pdo->exec("DELETE FROM notification_outbox WHERE event='account.security_code' AND (status<>'pending' OR NOT EXISTS(SELECT 1 FROM student_email_challenges c JOIN users u ON u.id=c.user_id WHERE c.id=auth_challenge_id AND c.expires_at>strftime('%s','now') AND c.attempts<5 AND c.security_revision=u.security_revision AND u.account_status='active' AND u.login_blocked_at IS NULL))");
    // Never send expired account links or reminders accumulated during an outage.
    $pdo->exec("DELETE FROM notification_outbox WHERE status='pending'
        AND event IN ('account.verification','password.reset','credentials.reminder')
        AND created_at<=datetime('now','-15 minutes')");
    $limit=max(1,min(500,$limit));
    $scope=$announcementsOnly?" AND o.event='course.announcement'":'';
    $messages=$pdo->query("SELECT o.* FROM notification_outbox o
        WHERE o.status='pending'
          AND COALESCE(o.available_at,o.created_at)<=CURRENT_TIMESTAMP
          $scope
          AND (o.event<>'course.announcement' OR o.announcement_id IS NULL OR EXISTS(
              SELECT 1 FROM course_announcements a WHERE a.id=o.announcement_id AND a.archived=0
          ))
        ORDER BY CASE WHEN o.event='account.security_code' THEN 0 ELSE 1 END,COALESCE(o.available_at,o.created_at),o.id
        LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC);
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
        $ok=outbox_send_message($pdo,(int)$message['id'],$delivery);
        if($ok===null)continue;
        $results[]=['id'=>(int)$message['id'],'recipient'=>(string)$message['recipient'],'sent'=>$ok];
    }
    return $results;
}

/** The conditional lease is shared by immediate sends, HTTP fallback and cron. */
function outbox_send_message(PDO $pdo, int $messageId, ?callable $delivery=null): ?bool
{
    $claim=$pdo->prepare("UPDATE notification_outbox SET available_at=datetime('now','+5 minutes')
        WHERE id=? AND status='pending' AND COALESCE(available_at,created_at)<=CURRENT_TIMESTAMP
        AND (event NOT IN ('account.verification','password.reset','credentials.reminder') OR created_at>datetime('now','-15 minutes'))
        AND (event<>'account.security_code' OR EXISTS(SELECT 1 FROM student_email_challenges c JOIN users u ON u.id=c.user_id WHERE c.id=auth_challenge_id AND c.expires_at>strftime('%s','now') AND c.attempts<5 AND c.security_revision=u.security_revision AND u.account_status='active' AND u.login_blocked_at IS NULL))
        AND (event<>'course.announcement' OR announcement_id IS NULL OR EXISTS(
            SELECT 1 FROM course_announcements a WHERE a.id=announcement_id AND a.archived=0))");
    $claim->execute([$messageId]);
    if($claim->rowCount()!==1)return null;
    $query=$pdo->prepare("SELECT * FROM notification_outbox WHERE id=? AND status='pending'");
    $query->execute([$messageId]);
    $message=$query->fetch(PDO::FETCH_ASSOC);
    // Release the read lock before mail transport and competing workers update the queue.
    $query->closeCursor();
    if(!$message)return null;
    $authentication=$message['event']==='account.security_code';
    $delivery??=static fn(string $recipient,string $subject,string $body,string $cc='',string $bcc=''):bool=>deliver_app_mail($recipient,$subject,$body,$cc,$bcc,$authentication);
    try{
        $body=$authentication?AppSecrets::decrypt((string)$message['body'],dirname(__DIR__),'otp-mail:'.$message['auth_challenge_id']):(string)$message['body'];
        $ok=(bool)$delivery((string)$message['recipient'],(string)$message['subject'],$body,(string)($message['cc']??''),(string)($message['bcc']??''));
    }catch(Throwable $exception){
        error_log('liike mail: transport indisponible ('.get_class($exception).')');
        $ok=false;
    }
    if($authentication){
        $statement=$pdo->prepare($ok?"DELETE FROM notification_outbox WHERE id=?":"UPDATE notification_outbox SET attempts=attempts+1,last_error='Envoi refusé, limité ou transport indisponible',available_at=datetime('now','+30 seconds') WHERE id=? AND status='pending'");
        $statement->execute([$messageId]);return $ok;
    }
    $statement=$pdo->prepare($ok
        ? "UPDATE notification_outbox SET status='sent',attempts=attempts+1,last_error=NULL,sent_at=CURRENT_TIMESTAMP WHERE id=? AND status='pending'"
        : "UPDATE notification_outbox SET attempts=attempts+1,last_error='Envoi refusé, limité ou transport indisponible',available_at=datetime('now','+5 minutes') WHERE id=? AND status='pending'");
    $statement->execute([$messageId]);
    return $ok;
}

function teacher_can_clear_notification_history(PDO $pdo, int $teacherId): bool
{
    $statement=$pdo->prepare('SELECT 1 FROM courses WHERE teacher_id=? LIMIT 1');
    $statement->execute([$teacherId]);
    return (bool)$statement->fetchColumn();
}

function clear_sent_notification_history(PDO $pdo, int $teacherId): int
{
    if(!teacher_can_clear_notification_history($pdo,$teacherId))return 0;
    $statement=$pdo->prepare("UPDATE notification_outbox SET body='' WHERE status='sent' AND body<>''");
    $statement->execute();
    return $statement->rowCount();
}
