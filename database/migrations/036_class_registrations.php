<?php

declare(strict_types=1);

return [
    'version'=>36,
    'name'=>'Inscriptions de classes et comptes en attente',
    'up'=>static function(PDO $pdo): void {
        $pdo->exec('DROP TRIGGER IF EXISTS limit_pending_registrations');
        $pdo->exec("CREATE TRIGGER limit_pending_registrations
            BEFORE INSERT ON users
            WHEN NEW.account_status='pending' AND NEW.managed_by IS NULL
              AND (SELECT COUNT(*) FROM users WHERE account_status='pending' AND managed_by IS NULL)>=50
            BEGIN
                SELECT RAISE(ABORT, 'pending registration limit reached');
            END");
    },
];
