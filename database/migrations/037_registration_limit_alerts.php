<?php
declare(strict_types=1);

return ['version'=>37,'name'=>'Alertes des plafonds d’inscription','up'=>static function(PDO $pdo):void{
    $pdo->exec('CREATE TABLE IF NOT EXISTS registration_limit_alerts (
        scope TEXT PRIMARY KEY,
        alerted_at INTEGER NOT NULL
    )');
}];
