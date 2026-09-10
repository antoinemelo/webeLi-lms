<?php

declare(strict_types=1);

return [
    'version'=>21,
    'name'=>'Historique administratif et suppression des corps techniques envoyés',
    'up'=>static function(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS student_followups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
            course_id INTEGER REFERENCES courses(id) ON DELETE SET NULL,
            course_title TEXT NOT NULL DEFAULT '',
            kind TEXT NOT NULL CHECK(kind IN ('meeting','discussion','correspondence')),
            occurred_at TEXT NOT NULL,
            title TEXT NOT NULL,
            body TEXT NOT NULL,
            revision INTEGER NOT NULL DEFAULT 0,
            request_key TEXT NOT NULL,
            request_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(created_by,request_key)
        );
        CREATE TABLE IF NOT EXISTS student_followup_participants (
            followup_id INTEGER NOT NULL REFERENCES student_followups(id) ON DELETE CASCADE,
            student_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            PRIMARY KEY(followup_id,student_id)
        );
        CREATE INDEX IF NOT EXISTS idx_followups_date ON student_followups(occurred_at DESC,id DESC);
        CREATE INDEX IF NOT EXISTS idx_followup_participants_student ON student_followup_participants(student_id,followup_id);
        CREATE INDEX IF NOT EXISTS idx_outbox_announcement_recipient ON notification_outbox(announcement_id,recipient);
        CREATE TRIGGER IF NOT EXISTS remove_empty_student_followup AFTER DELETE ON student_followup_participants
        BEGIN DELETE FROM student_followups WHERE id=OLD.followup_id AND NOT EXISTS(SELECT 1 FROM student_followup_participants WHERE followup_id=OLD.followup_id); END;
        UPDATE notification_outbox SET body='' WHERE status='sent' AND body<>'';
        CREATE TRIGGER IF NOT EXISTS clear_sent_mail_body_insert AFTER INSERT ON notification_outbox WHEN NEW.status='sent' AND NEW.body<>''
        BEGIN UPDATE notification_outbox SET body='' WHERE id=NEW.id; END;
        CREATE TRIGGER IF NOT EXISTS clear_sent_mail_body_update AFTER UPDATE OF status,body ON notification_outbox WHEN NEW.status='sent' AND NEW.body<>''
        BEGIN UPDATE notification_outbox SET body='' WHERE id=NEW.id; END;");
    },
];
