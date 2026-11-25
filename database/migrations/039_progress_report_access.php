<?php
declare(strict_types=1);
return ['version'=>39,'name'=>'Activation indépendante des liens externes de bilan','up'=>static function(PDO $pdo):void{
    $columns=$pdo->query('PRAGMA table_info(progress_report_links)')->fetchAll(PDO::FETCH_COLUMN,1);
    if(!in_array('enabled',$columns,true))$pdo->exec('ALTER TABLE progress_report_links ADD COLUMN enabled INTEGER NOT NULL DEFAULT 1 CHECK(enabled IN (0,1))');
}];
