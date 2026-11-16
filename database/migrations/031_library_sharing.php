<?php
declare(strict_types=1);

return ['version'=>31,'name'=>'Partage des contenus de bibliothèque','up'=>static function(PDO $pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS page_shares (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        page_id INTEGER NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
        mode TEXT NOT NULL CHECK(mode IN ('shared','copy','external')),
        token TEXT NOT NULL UNIQUE,
        created_by INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        revoked_at TEXT,
        UNIQUE(page_id,mode)
    );
    CREATE TABLE IF NOT EXISTS page_collaborators (
        page_id INTEGER NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
        teacher_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        added_by INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        PRIMARY KEY(page_id,teacher_id)
    );
    CREATE TABLE IF NOT EXISTS page_share_receipts (
        share_id INTEGER NOT NULL REFERENCES page_shares(id) ON DELETE CASCADE,
        teacher_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        page_id INTEGER REFERENCES pages(id) ON DELETE SET NULL,
        PRIMARY KEY(share_id,teacher_id)
    );");
}];
