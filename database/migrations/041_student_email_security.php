<?php
declare(strict_types=1);
return ['version'=>41,'name'=>'Code de connexion facultatif et suspension des comptes apprenants','up'=>static function(PDO $pdo):void{
    $columns=$pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN,1);
    foreach(['email_login_enabled'=>'INTEGER NOT NULL DEFAULT 0 CHECK(email_login_enabled IN (0,1))','login_blocked_at'=>'TEXT','login_blocked_by'=>'INTEGER REFERENCES users(id) ON DELETE SET NULL','security_revision'=>'INTEGER NOT NULL DEFAULT 0'] as $name=>$type){
        if(!in_array($name,$columns,true))$pdo->exec('ALTER TABLE users ADD COLUMN '.$name.' '.$type);
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_email_challenges (
        id TEXT PRIMARY KEY,user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        purpose TEXT NOT NULL CHECK(purpose IN ('login','enable','disable','email_old','email_new')),
        binding TEXT NOT NULL,recipient TEXT NOT NULL,code_hash TEXT NOT NULL,payload TEXT NOT NULL DEFAULT '{}',
        security_revision INTEGER NOT NULL,expires_at INTEGER NOT NULL,attempts INTEGER NOT NULL DEFAULT 0,created_at INTEGER NOT NULL
    );
    CREATE INDEX IF NOT EXISTS student_email_challenge_user ON student_email_challenges(user_id,purpose,binding);
    CREATE INDEX IF NOT EXISTS student_email_challenge_expiry ON student_email_challenges(expires_at);
    CREATE TABLE IF NOT EXISTS student_auth_events(scope TEXT NOT NULL,recorded_at INTEGER NOT NULL);
    CREATE INDEX IF NOT EXISTS student_auth_scope ON student_auth_events(scope,recorded_at);
    CREATE INDEX IF NOT EXISTS student_auth_expiry ON student_auth_events(recorded_at);");
    $columns=$pdo->query('PRAGMA table_info(notification_outbox)')->fetchAll(PDO::FETCH_COLUMN,1);
    if(!in_array('auth_challenge_id',$columns,true))$pdo->exec('ALTER TABLE notification_outbox ADD COLUMN auth_challenge_id TEXT REFERENCES student_email_challenges(id) ON DELETE CASCADE');
    $pdo->exec("CREATE INDEX IF NOT EXISTS outbox_auth_challenge ON notification_outbox(auth_challenge_id);
    CREATE TRIGGER IF NOT EXISTS revoke_student_security AFTER UPDATE OF email,email_login_enabled,login_blocked_at,account_status ON users
    WHEN NEW.role='student' AND (OLD.email IS NOT NEW.email OR OLD.email_login_enabled IS NOT NEW.email_login_enabled OR OLD.login_blocked_at IS NOT NEW.login_blocked_at OR OLD.account_status IS NOT NEW.account_status)
    BEGIN
        UPDATE users SET security_revision=OLD.security_revision+1,student_session_token_hash=NULL,student_session_seen_at=NULL WHERE id=NEW.id;
        DELETE FROM pwa_logins WHERE user_id=NEW.id;
        DELETE FROM student_email_challenges WHERE user_id=NEW.id;
    END;");
}];
