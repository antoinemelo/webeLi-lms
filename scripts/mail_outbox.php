<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Database.php';
require_once dirname(__DIR__) . '/app/MailDelivery.php';
require_once dirname(__DIR__) . '/app/NotificationOutbox.php';

$send = in_array('--send', $argv, true);
$worker = in_array('--worker', $argv, true);
$applicationRoot=dirname(__DIR__);
if($worker&&!$send){
    fwrite(STDERR,"--worker nécessite --send.\n");
    exit(2);
}
if($worker&&!record_mail_cron_heartbeat($applicationRoot)){
    fwrite(STDERR,"Le battement de vie du cron ne peut pas être enregistré.\n");
    exit(1);
}
$db = Database::connect($applicationRoot);

$lock=fopen($applicationRoot.'/storage/mail-outbox.lock','c+');
if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){
    echo "Un autre traitement de la boîte d’envoi est déjà actif.\n";
    exit(0);
}

try{
    if(!$send){
        $messages=$db->query("SELECT * FROM notification_outbox WHERE status='pending' ORDER BY COALESCE(available_at,created_at),id LIMIT 25")->fetchAll(PDO::FETCH_ASSOC);
        if(!$messages){echo "Aucun email en attente.\n";exit(0);}
        foreach($messages as $message){
            $availability=(string)($message['available_at']??$message['created_at']);
            echo sprintf("[aperçu #%d · %s] %s — %s\n",$message['id'],$availability,$message['recipient'],$message['subject']);
        }
        echo "\nAucun envoi effectué. Ajoutez --send pour un lot, ou --send --worker pour le cron.\n";
        exit(0);
    }

    $deadline=microtime(true)+($worker?MAIL_OUTBOX_WORKER_SECONDS:0);
    do{
        if($worker)record_mail_cron_heartbeat($applicationRoot);
        $results=outbox_send_pending_batch($db,MAIL_OUTBOX_BATCH_SIZE);
        if(!$results)echo "Aucun email arrivé à échéance.\n";
        foreach($results as $result){
            echo sprintf("[%s #%d] %s\n",$result['sent']?'envoyé':'échec',$result['id'],$result['recipient']);
        }
        if(!$worker||microtime(true)+MAIL_OUTBOX_BATCH_INTERVAL>$deadline)break;
        sleep(MAIL_OUTBOX_BATCH_INTERVAL);
    }while(true);
}finally{
    flock($lock,LOCK_UN);
    fclose($lock);
}
