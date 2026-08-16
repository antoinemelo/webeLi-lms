<?php

declare(strict_types=1);

return [
    'version'=>11,
    'name'=>'Encouragements positifs et négatifs',
    'up'=>static function(PDO $pdo): void {
        $pdo->exec("CREATE TABLE reward_types_v11 (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            icon TEXT NOT NULL DEFAULT '✨',
            color TEXT NOT NULL DEFAULT '#6d5dfc',
            default_points INTEGER NOT NULL DEFAULT 1 CHECK(default_points BETWEEN -100 AND 100 AND default_points <> 0),
            active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1)),
            UNIQUE(course_id,name),
            FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE
        )");
        $pdo->exec("CREATE TABLE reward_awards_v11 (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            enrollment_id INTEGER NOT NULL,
            pathway_item_id INTEGER NOT NULL,
            reward_type_id INTEGER NOT NULL,
            points INTEGER NOT NULL CHECK(points BETWEEN -100 AND 100 AND points <> 0),
            message TEXT NOT NULL DEFAULT '',
            awarded_by INTEGER NOT NULL,
            awarded_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
            FOREIGN KEY(pathway_item_id) REFERENCES pathway_items(id) ON DELETE CASCADE,
            FOREIGN KEY(reward_type_id) REFERENCES reward_types_v11(id) ON DELETE RESTRICT,
            FOREIGN KEY(awarded_by) REFERENCES users(id) ON DELETE RESTRICT
        )");
        $pdo->exec('INSERT INTO reward_types_v11(id,course_id,name,icon,color,default_points,active) SELECT id,course_id,name,icon,color,default_points,active FROM reward_types');
        $pdo->exec('INSERT INTO reward_awards_v11(id,enrollment_id,pathway_item_id,reward_type_id,points,message,awarded_by,awarded_at) SELECT id,enrollment_id,pathway_item_id,reward_type_id,points,message,awarded_by,awarded_at FROM reward_awards');
        $pdo->exec('DROP TABLE reward_awards');
        $pdo->exec('DROP TABLE reward_types');
        $pdo->exec('ALTER TABLE reward_types_v11 RENAME TO reward_types');
        $pdo->exec('ALTER TABLE reward_awards_v11 RENAME TO reward_awards');
        $pdo->exec('CREATE INDEX idx_rewards_enrollment ON reward_awards(enrollment_id,awarded_at DESC)');
    },
];
