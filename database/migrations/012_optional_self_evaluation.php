<?php

declare(strict_types=1);

return [
    'version'=>12,
    'name'=>'Étapes avec autoévaluation facultative',
    'up'=>static function(PDO $pdo): void {
        $itemColumns=array_column($pdo->query('PRAGMA table_info(pathway_items)')->fetchAll(PDO::FETCH_ASSOC),'name');
        if(!in_array('self_evaluation_enabled',$itemColumns,true)){
            $pdo->exec('ALTER TABLE pathway_items ADD COLUMN self_evaluation_enabled INTEGER NOT NULL DEFAULT 1 CHECK(self_evaluation_enabled IN (0,1))');
        }
        $progressColumns=array_column($pdo->query('PRAGMA table_info(progress)')->fetchAll(PDO::FETCH_ASSOC),'name');
        if(!in_array('completed_at',$progressColumns,true))$pdo->exec('ALTER TABLE progress ADD COLUMN completed_at TEXT');
        $pdo->exec('UPDATE progress SET completed_at=COALESCE(completed_at,student_validated_at,teacher_validated_at) WHERE completed_at IS NULL');
    },
];
