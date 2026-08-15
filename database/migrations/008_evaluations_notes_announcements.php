<?php

declare(strict_types=1);

return [
    'version'=>8,
    'name'=>'Évaluations pondérées, notes privées et annonces de parcours',
    'up'=>static function(PDO $pdo): void {
        $itemColumns=array_column($pdo->query('PRAGMA table_info(pathway_items)')->fetchAll(PDO::FETCH_ASSOC),'name');
        if(!in_array('evaluation_weight',$itemColumns,true)){
            $pdo->exec('ALTER TABLE pathway_items ADD COLUMN evaluation_weight REAL NOT NULL DEFAULT 1 CHECK(evaluation_weight IN (0.5,1,2,3,4))');
        }
        $progressColumns=array_column($pdo->query('PRAGMA table_info(progress)')->fetchAll(PDO::FETCH_ASSOC),'name');
        if(!in_array('evaluation_score',$progressColumns,true)){
            $pdo->exec('ALTER TABLE progress ADD COLUMN evaluation_score REAL CHECK(evaluation_score BETWEEN 0 AND 10)');
        }
        $pdo->exec("UPDATE progress SET teacher_level=NULL,teacher_validated_at=NULL,updated_at=CURRENT_TIMESTAMP
            WHERE evaluation_score IS NULL AND teacher_validated_at IS NOT NULL
              AND pathway_item_id IN (SELECT id FROM pathway_items WHERE is_evaluation=1)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS student_private_notes (
            student_id INTEGER NOT NULL,
            pathway_item_id INTEGER NOT NULL,
            body TEXT NOT NULL DEFAULT '',
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(student_id, pathway_item_id),
            FOREIGN KEY(student_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(pathway_item_id) REFERENCES pathway_items(id) ON DELETE CASCADE
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS course_announcements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_id INTEGER NOT NULL,
            created_by INTEGER,
            title TEXT NOT NULL,
            body TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            archived INTEGER NOT NULL DEFAULT 0 CHECK(archived IN (0,1)),
            FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE,
            FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS announcement_reads (
            announcement_id INTEGER NOT NULL,
            student_id INTEGER NOT NULL,
            read_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(announcement_id, student_id),
            FOREIGN KEY(announcement_id) REFERENCES course_announcements(id) ON DELETE CASCADE,
            FOREIGN KEY(student_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_student_private_notes_item ON student_private_notes(pathway_item_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_course_announcements_course ON course_announcements(course_id, archived, created_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_announcement_reads_student ON announcement_reads(student_id, announcement_id)');
    },
];
