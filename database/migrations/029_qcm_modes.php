<?php
declare(strict_types=1);

return [
    'version'=>29,
    'name'=>'Séances QCM collectives',
    'up'=>static function(PDO $pdo): void {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS qcm_class_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pathway_item_id INTEGER NOT NULL REFERENCES pathway_items(id) ON DELETE CASCADE,
    page_block_id INTEGER NOT NULL REFERENCES page_blocks(id) ON DELETE CASCADE,
    qcm_key TEXT NOT NULL,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    questions TEXT NOT NULL,
    phase TEXT NOT NULL DEFAULT 'waiting' CHECK(phase IN ('waiting','open','results','finished')),
    question_index INTEGER NOT NULL DEFAULT 0 CHECK(question_index >= 0),
    revealed INTEGER NOT NULL DEFAULT 0 CHECK(revealed IN (0,1)),
    revision INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS qcm_class_active ON qcm_class_sessions(pathway_item_id,page_block_id,qcm_key) WHERE phase <> 'finished';
CREATE INDEX IF NOT EXISTS qcm_class_latest ON qcm_class_sessions(pathway_item_id,page_block_id,qcm_key,id);
CREATE TABLE IF NOT EXISTS qcm_class_votes (
    session_id INTEGER NOT NULL REFERENCES qcm_class_sessions(id) ON DELETE CASCADE,
    student_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    question_index INTEGER NOT NULL CHECK(question_index >= 0),
    answers TEXT NOT NULL,
    answered_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(session_id,student_id,question_index)
);
SQL
        );
    },
];
