<?php

declare(strict_types=1);

return [
    'version'=>17,
    'name'=>'Brouillons des QCM et reprise après interruption',
    'up'=>static function(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS qcm_drafts (
    student_id INTEGER NOT NULL,
    pathway_item_id INTEGER NOT NULL,
    page_block_id INTEGER NOT NULL,
    qcm_key TEXT NOT NULL,
    answers TEXT NOT NULL,
    revision INTEGER NOT NULL DEFAULT 1 CHECK(revision > 0),
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(student_id,pathway_item_id,page_block_id,qcm_key),
    FOREIGN KEY(student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(pathway_item_id) REFERENCES pathway_items(id) ON DELETE CASCADE,
    FOREIGN KEY(page_block_id) REFERENCES page_blocks(id) ON DELETE CASCADE
)");
    },
];
