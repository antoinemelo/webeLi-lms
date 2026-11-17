<?php
declare(strict_types=1);

return ['version'=>32,'name'=>'Lien de partage unique et séances QCM anonymes','up'=>static function(PDO $pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS page_share_links (
        page_id INTEGER PRIMARY KEY REFERENCES pages(id) ON DELETE CASCADE,
        token TEXT NOT NULL UNIQUE,
        enabled INTEGER NOT NULL DEFAULT 0 CHECK(enabled IN (0,1)),
        allow_shared INTEGER NOT NULL DEFAULT 0 CHECK(allow_shared IN (0,1)),
        allow_copy INTEGER NOT NULL DEFAULT 0 CHECK(allow_copy IN (0,1)),
        allow_external INTEGER NOT NULL DEFAULT 0 CHECK(allow_external IN (0,1)),
        revision INTEGER NOT NULL DEFAULT 1
    );
    INSERT OR IGNORE INTO page_share_links(page_id,token,enabled,allow_shared,allow_copy,allow_external)
        SELECT page_id,COALESCE(MIN(CASE WHEN revoked_at IS NULL THEN token END),lower(hex(randomblob(32)))),
        MAX(revoked_at IS NULL),MAX(mode='shared' AND revoked_at IS NULL),MAX(mode='copy' AND revoked_at IS NULL),MAX(mode='external' AND revoked_at IS NULL)
        FROM page_shares GROUP BY page_id;
    CREATE TABLE IF NOT EXISTS qcm_guest_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        page_id INTEGER NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
        page_block_id INTEGER NOT NULL REFERENCES page_blocks(id) ON DELETE CASCADE,
        qcm_key TEXT NOT NULL,
        created_by INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        room_token TEXT NOT NULL UNIQUE,
        questions TEXT NOT NULL,
        phase TEXT NOT NULL DEFAULT 'waiting' CHECK(phase IN ('waiting','open','results','finished')),
        question_index INTEGER NOT NULL DEFAULT 0,
        revealed INTEGER NOT NULL DEFAULT 0 CHECK(revealed IN (0,1)),
        revision INTEGER NOT NULL DEFAULT 1,
        expires_at INTEGER NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    CREATE INDEX IF NOT EXISTS idx_qcm_guest_expiry ON qcm_guest_sessions(expires_at);
    CREATE TABLE IF NOT EXISTS qcm_guest_participants (
        session_id INTEGER NOT NULL REFERENCES qcm_guest_sessions(id) ON DELETE CASCADE,
        participant_hash TEXT NOT NULL,
        PRIMARY KEY(session_id,participant_hash)
    );
    CREATE TABLE IF NOT EXISTS qcm_guest_votes (
        session_id INTEGER NOT NULL,
        participant_hash TEXT NOT NULL,
        question_index INTEGER NOT NULL,
        answers TEXT NOT NULL,
        PRIMARY KEY(session_id,participant_hash,question_index),
        FOREIGN KEY(session_id,participant_hash) REFERENCES qcm_guest_participants(session_id,participant_hash) ON DELETE CASCADE
    );");
}];
