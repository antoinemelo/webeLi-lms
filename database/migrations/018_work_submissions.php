<?php

declare(strict_types=1);

return [
    'version'=>18,
    'name'=>'Travaux rendus par lien ou texte court',
    'up'=>static function(PDO $pdo): void {
        if(!in_array('submission_mode',array_column($pdo->query('PRAGMA table_info(page_blocks)')->fetchAll(PDO::FETCH_ASSOC),'name'),true)){
        $sequence=(int)$pdo->query("SELECT COALESCE(seq,0) FROM sqlite_sequence WHERE name='page_blocks'")->fetchColumn();
        foreach(['qcm_attempts','qcm_drafts'] as $table)$pdo->exec('CREATE TEMP TABLE work_migration_'.$table.' AS SELECT * FROM '.$table);
        $pdo->exec(<<<'SQL'
CREATE TABLE page_blocks_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page_id INTEGER NOT NULL,
    type TEXT NOT NULL CHECK(type IN ('markdown','image','file','iframe','submission')),
    body TEXT NOT NULL DEFAULT '',
    caption TEXT NOT NULL DEFAULT '',
    submission_mode TEXT NOT NULL DEFAULT 'link' CHECK(submission_mode IN ('link','text','both')),
    submission_required INTEGER NOT NULL DEFAULT 1 CHECK(submission_required IN (0,1)),
    position INTEGER NOT NULL,
    revision INTEGER NOT NULL DEFAULT 0,
    updated_by INTEGER,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(page_id, position),
    FOREIGN KEY(page_id) REFERENCES pages(id) ON DELETE CASCADE,
    FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
);
SQL);
        $columns='id,page_id,type,body,caption,position,revision,updated_by,updated_at';
        $pdo->exec('INSERT INTO page_blocks_new('.$columns.') SELECT id,page_id,type,body,caption,position,revision,updated_by,COALESCE(updated_at,CURRENT_TIMESTAMP) FROM page_blocks');
        $pdo->exec('DROP TABLE page_blocks');
        $pdo->exec('ALTER TABLE page_blocks_new RENAME TO page_blocks');
        $pdo->prepare("UPDATE sqlite_sequence SET seq=MAX(seq,?) WHERE name='page_blocks'")->execute([$sequence]);
        foreach(['qcm_attempts','qcm_drafts'] as $table){
            $pdo->exec('INSERT INTO '.$table.' SELECT * FROM work_migration_'.$table);
            $pdo->exec('DROP TABLE work_migration_'.$table);
        }
        }
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS work_submissions (
    student_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    pathway_item_id INTEGER NOT NULL REFERENCES pathway_items(id) ON DELETE CASCADE,
    page_block_id INTEGER NOT NULL REFERENCES page_blocks(id) ON DELETE CASCADE,
    url TEXT NOT NULL DEFAULT '',
    body TEXT NOT NULL DEFAULT '' CHECK(length(body)<=512),
    status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','submitted')),
    revision INTEGER NOT NULL DEFAULT 0 CHECK(revision>=0),
    submitted_at TEXT,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(student_id,pathway_item_id,page_block_id)
);

CREATE TABLE IF NOT EXISTS work_submission_versions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    pathway_item_id INTEGER NOT NULL REFERENCES pathway_items(id) ON DELETE CASCADE,
    page_block_id INTEGER NOT NULL REFERENCES page_blocks(id) ON DELETE CASCADE,
    url TEXT NOT NULL DEFAULT '',
    body TEXT NOT NULL DEFAULT '' CHECK(length(body)<=512),
    prompt TEXT NOT NULL DEFAULT '',
    title TEXT NOT NULL DEFAULT '',
    submitted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reopened_at TEXT,
    reopened_by INTEGER REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_work_versions_lookup ON work_submission_versions(student_id,pathway_item_id,page_block_id,id);

SQL);
    },
];
