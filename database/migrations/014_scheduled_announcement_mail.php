<?php

declare(strict_types=1);

return [
    'version'=>14,
    'name'=>'Planification et annulation des courriels d’annonce',
    'up'=>static function(PDO $pdo): void {
        $columns=array_column($pdo->query('PRAGMA table_info(notification_outbox)')->fetchAll(PDO::FETCH_ASSOC),'name');
        if(!in_array('announcement_id',$columns,true))$pdo->exec('ALTER TABLE notification_outbox ADD COLUMN announcement_id INTEGER REFERENCES course_announcements(id) ON DELETE SET NULL');
        if(!in_array('available_at',$columns,true))$pdo->exec('ALTER TABLE notification_outbox ADD COLUMN available_at TEXT');
        $pdo->exec('UPDATE notification_outbox SET available_at=COALESCE(available_at,created_at,CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_outbox_available ON notification_outbox(status,available_at,id)');
        $pdo->exec("CREATE TRIGGER IF NOT EXISTS cancel_pending_announcement_mail BEFORE DELETE ON course_announcements BEGIN
            DELETE FROM notification_outbox WHERE event='course.announcement' AND announcement_id=OLD.id AND status='pending';
        END");
    },
];
