<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Database.php';
require_once dirname(__DIR__) . '/app/MailDelivery.php';

$send = in_array('--send', $argv, true);
$db = Database::connect(dirname(__DIR__));
$messages = $db->query("SELECT * FROM notification_outbox WHERE status='pending' ORDER BY id LIMIT 25")->fetchAll(PDO::FETCH_ASSOC);

if (!$messages) {
    echo "Aucun email en attente.\n";
    exit(0);
}

foreach ($messages as $message) {
    if (!$send) {
        echo sprintf("[aperçu #%d] %s — %s\n", $message['id'], $message['recipient'], $message['subject']);
        continue;
    }
    $ok = deliver_app_mail($message['recipient'], $message['subject'], $message['body']);
    $statement = $db->prepare($ok
        ? "UPDATE notification_outbox SET status='sent',attempts=attempts+1,last_error=NULL,sent_at=CURRENT_TIMESTAMP WHERE id=?"
        : "UPDATE notification_outbox SET attempts=attempts+1,last_error='mail() a retourné false' WHERE id=?");
    $statement->execute([$message['id']]);
    echo sprintf("[%s #%d] %s\n", $ok ? 'envoyé' : 'échec', $message['id'], $message['recipient']);
}

if (!$send) echo "\nAucun envoi effectué. Ajoutez --send pour appeler mail().\n";
