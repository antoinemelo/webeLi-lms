<?php
declare(strict_types=1);

return ['version'=>28,'name'=>'Prise en compte individuelle des évaluations dans les moyennes','up'=>static function(PDO $pdo):void{
    $columns=array_column($pdo->query('PRAGMA table_info(progress)')->fetchAll(PDO::FETCH_ASSOC),'name');
    if(in_array('evaluation_included',$columns,true))return;
    $pdo->exec('ALTER TABLE progress ADD COLUMN evaluation_included INTEGER NOT NULL DEFAULT 1 CHECK(evaluation_included IN (0,1))');
}];
