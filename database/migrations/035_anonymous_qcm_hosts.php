<?php
declare(strict_types=1);

return ['version'=>35,'name'=>'Animation des QCM externes sans compte','up'=>static function(PDO $pdo):void{
    if(in_array('host_key_hash',array_column($pdo->query('PRAGMA table_info(qcm_guest_sessions)')->fetchAll(PDO::FETCH_ASSOC),'name'),true))return;
    // Rebuild the parent and its children within the migration transaction, preserving every row and the sequence.
    $pdo->exec("CREATE TEMP TABLE guest_sessions_v35_backup AS SELECT * FROM qcm_guest_sessions;
    CREATE TEMP TABLE guest_participants_v35_backup AS SELECT * FROM qcm_guest_participants;
    CREATE TEMP TABLE guest_votes_v35_backup AS SELECT * FROM qcm_guest_votes;
    CREATE TEMP TABLE guest_sequence_v35_backup AS SELECT seq FROM sqlite_sequence WHERE name='qcm_guest_sessions';
    DROP TABLE qcm_guest_votes;
    DROP TABLE qcm_guest_participants;
    DROP TABLE qcm_guest_sessions;
    CREATE TABLE IF NOT EXISTS qcm_guest_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        page_id INTEGER NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
        page_block_id INTEGER NOT NULL REFERENCES page_blocks(id) ON DELETE CASCADE,
        qcm_key TEXT NOT NULL,
        created_by INTEGER REFERENCES users(id) ON DELETE CASCADE,
        host_key_hash TEXT,
        room_token TEXT NOT NULL UNIQUE,
        source_share_token TEXT NOT NULL DEFAULT '',
        questions TEXT NOT NULL,
        phase TEXT NOT NULL DEFAULT 'waiting' CHECK(phase IN ('waiting','open','results','finished')),
        question_index INTEGER NOT NULL DEFAULT 0,
        revealed INTEGER NOT NULL DEFAULT 0 CHECK(revealed IN (0,1)),
        revision INTEGER NOT NULL DEFAULT 1,
        expires_at INTEGER NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CHECK ((created_by IS NOT NULL AND host_key_hash IS NULL) OR (created_by IS NULL AND host_key_hash IS NOT NULL AND length(host_key_hash)=64))
    );
    CREATE INDEX IF NOT EXISTS idx_qcm_guest_expiry ON qcm_guest_sessions(expires_at);
    CREATE INDEX IF NOT EXISTS idx_qcm_guest_host ON qcm_guest_sessions(host_key_hash) WHERE created_by IS NULL;
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
    );

    INSERT INTO qcm_guest_sessions(id,page_id,page_block_id,qcm_key,created_by,room_token,questions,phase,question_index,revealed,revision,expires_at,created_at,source_share_token)
        SELECT id,page_id,page_block_id,qcm_key,created_by,room_token,questions,phase,question_index,revealed,revision,expires_at,created_at,source_share_token FROM guest_sessions_v35_backup;
    INSERT INTO qcm_guest_participants SELECT * FROM guest_participants_v35_backup;
    INSERT INTO qcm_guest_votes SELECT * FROM guest_votes_v35_backup;
    UPDATE sqlite_sequence SET seq=MAX(seq,COALESCE((SELECT seq FROM guest_sequence_v35_backup),0)) WHERE name='qcm_guest_sessions';
    INSERT INTO sqlite_sequence(name,seq) SELECT 'qcm_guest_sessions',seq FROM guest_sequence_v35_backup WHERE NOT EXISTS(SELECT 1 FROM sqlite_sequence WHERE name='qcm_guest_sessions');
    DROP TABLE guest_votes_v35_backup;
    DROP TABLE guest_participants_v35_backup;
    DROP TABLE guest_sessions_v35_backup;
    DROP TABLE guest_sequence_v35_backup;");
}];
