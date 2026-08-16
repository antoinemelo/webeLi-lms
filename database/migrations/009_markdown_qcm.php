<?php

declare(strict_types=1);

return [
    'version'=>9,
    'name'=>'QCM formatifs intégrés au Markdown',
    'up'=>static function(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS qcm_attempts (
            student_id INTEGER NOT NULL,
            pathway_item_id INTEGER NOT NULL,
            page_block_id INTEGER NOT NULL,
            qcm_key TEXT NOT NULL,
            score_percent REAL NOT NULL CHECK(score_percent BETWEEN 0 AND 100),
            correct_questions INTEGER NOT NULL CHECK(correct_questions >= 0),
            total_questions INTEGER NOT NULL CHECK(total_questions > 0 AND correct_questions <= total_questions),
            attempt_count INTEGER NOT NULL DEFAULT 1 CHECK(attempt_count > 0),
            answered_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(student_id,pathway_item_id,page_block_id,qcm_key),
            FOREIGN KEY(student_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(pathway_item_id) REFERENCES pathway_items(id) ON DELETE CASCADE,
            FOREIGN KEY(page_block_id) REFERENCES page_blocks(id) ON DELETE CASCADE
        )");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_qcm_attempts_student_item ON qcm_attempts(student_id,pathway_item_id,answered_at DESC)');
        $pdo->exec("INSERT OR IGNORE INTO tags(name,color) VALUES('QCM','#dff5ef')");
        $pdo->exec("INSERT OR IGNORE INTO page_tags(page_id,tag_id)
            SELECT DISTINCT b.page_id,t.id FROM page_blocks b JOIN tags t ON lower(t.name)='qcm'
            WHERE b.type='markdown' AND lower(b.body) LIKE '%:::qcm%'");
    },
];
