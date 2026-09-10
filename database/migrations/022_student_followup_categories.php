<?php

declare(strict_types=1);

return [
    'version'=>22,
    'name'=>'Réunions, correspondances et paiements',
    'up'=>static function(PDO $pdo): void {
        $pdo->exec(<<<'SQL'
CREATE TEMP TABLE followups_v22_sequence AS SELECT seq FROM sqlite_sequence WHERE name='student_followups';
CREATE TEMP TABLE followups_v22_backup AS SELECT * FROM student_followups;
CREATE TEMP TABLE participants_v22_backup AS SELECT * FROM student_followup_participants;
DROP TRIGGER IF EXISTS remove_empty_student_followup;
DROP TABLE student_followup_participants;
DROP TABLE student_followups;
CREATE TABLE student_followups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
            course_id INTEGER REFERENCES courses(id) ON DELETE SET NULL,
            course_title TEXT NOT NULL DEFAULT '',
            kind TEXT NOT NULL CHECK(kind IN ('meeting','correspondence','payment')),
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
        CREATE TABLE student_followup_participants (
            followup_id INTEGER NOT NULL REFERENCES student_followups(id) ON DELETE CASCADE,
            student_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            PRIMARY KEY(followup_id,student_id)
        );
        CREATE INDEX idx_followups_date ON student_followups(occurred_at DESC,id DESC);
        CREATE INDEX idx_followup_participants_student ON student_followup_participants(student_id,followup_id);
        INSERT INTO student_followups(id,created_by,course_id,course_title,kind,occurred_at,title,body,revision,request_key,request_hash,created_at,updated_at) SELECT id,created_by,course_id,course_title,CASE WHEN kind='discussion' THEN 'meeting' ELSE kind END,occurred_at,title,body,revision,request_key,request_hash,created_at,updated_at FROM followups_v22_backup;
INSERT INTO student_followup_participants SELECT * FROM participants_v22_backup;
CREATE TRIGGER remove_empty_student_followup AFTER DELETE ON student_followup_participants BEGIN DELETE FROM student_followups WHERE id=OLD.followup_id AND NOT EXISTS(SELECT 1 FROM student_followup_participants WHERE followup_id=OLD.followup_id); END;
UPDATE sqlite_sequence SET seq=MAX(seq,COALESCE((SELECT seq FROM followups_v22_sequence),0)) WHERE name='student_followups';
INSERT INTO sqlite_sequence(name,seq) SELECT 'student_followups',seq FROM followups_v22_sequence WHERE NOT EXISTS(SELECT 1 FROM sqlite_sequence WHERE name='student_followups');
DROP TABLE followups_v22_sequence;
DROP TABLE followups_v22_backup;DROP TABLE participants_v22_backup;
SQL);
    },
];
