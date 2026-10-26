<?php
declare(strict_types=1);

return ['version'=>30,'name'=>'Visibilité indépendante des regroupements','up'=>static function(PDO $pdo):void{
    $columns=array_column($pdo->query('PRAGMA table_info(pathway_groups)')->fetchAll(PDO::FETCH_ASSOC),'name');
    if(!in_array('is_visible',$columns,true)){
        $pdo->exec('ALTER TABLE pathway_groups ADD COLUMN is_visible INTEGER NOT NULL DEFAULT 1 CHECK(is_visible IN (0,1))');
    }
}];
