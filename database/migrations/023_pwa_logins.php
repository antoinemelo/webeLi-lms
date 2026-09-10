<?php

declare(strict_types=1);

return [
    'version'=>23,
    'name'=>'Reconnexion persistante de la PWA par personne',
    'up'=>static function(PDO $pdo): void {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS pwa_logins (
    user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL UNIQUE,
    expires_at INTEGER NOT NULL
);
CREATE TRIGGER IF NOT EXISTS revoke_pwa_login_on_account_change
AFTER UPDATE OF password_hash,login_code,account_status,role ON users
WHEN OLD.password_hash IS NOT NEW.password_hash OR OLD.login_code IS NOT NEW.login_code
  OR OLD.account_status IS NOT NEW.account_status OR OLD.role IS NOT NEW.role
BEGIN DELETE FROM pwa_logins WHERE user_id=NEW.id; END;
SQL);
    },
];
