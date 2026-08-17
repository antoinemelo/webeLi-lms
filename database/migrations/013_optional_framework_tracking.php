<?php

declare(strict_types=1);

return [
    'version'=>13,
    'name'=>'Suivi facultatif du référentiel pour les étapes fermées',
    'up'=>static function(PDO $pdo): void {
        $columns=array_column($pdo->query('PRAGMA table_info(pathway_items)')->fetchAll(PDO::FETCH_ASSOC),'name');
        if(!in_array('framework_tracking_enabled',$columns,true)){
            $pdo->exec('ALTER TABLE pathway_items ADD COLUMN framework_tracking_enabled INTEGER NOT NULL DEFAULT 1 CHECK(framework_tracking_enabled IN (0,1))');
        }
    },
];
