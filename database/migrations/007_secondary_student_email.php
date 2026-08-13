<?php

declare(strict_types=1);

return [
    'version'=>7,
    'name'=>'Adresse électronique secondaire privée des élèves',
    'up'=>static function(PDO $pdo): void {
        $columns=array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC),'name');
        if(!in_array('secondary_email',$columns,true))$pdo->exec('ALTER TABLE users ADD COLUMN secondary_email TEXT');
    },
];
