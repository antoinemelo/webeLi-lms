<?php
declare(strict_types=1);
return ['version'=>40,'name'=>'Exercices contrôlés et autorisations individuelles de nouvel essai','up'=>static function(PDO $pdo):void{
    foreach(['pathway_items'=>['is_controlled_exercise'=>'INTEGER NOT NULL DEFAULT 0 CHECK(is_controlled_exercise IN (0,1))','quiz_revision'=>'INTEGER NOT NULL DEFAULT 0'],
        'progress'=>['quiz_generation'=>'INTEGER NOT NULL DEFAULT 0']] as $table=>$fields){
        $columns=$pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_COLUMN,1);
        foreach($fields as $field=>$definition)if(!in_array($field,$columns,true))$pdo->exec('ALTER TABLE '.$table.' ADD COLUMN '.$field.' '.$definition);
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS qcm_exercise_completions (
    student_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    pathway_item_id INTEGER NOT NULL REFERENCES pathway_items(id) ON DELETE CASCADE,
    page_block_id INTEGER NOT NULL REFERENCES page_blocks(id) ON DELETE CASCADE,
    qcm_key TEXT NOT NULL,
    attempt_count INTEGER NOT NULL DEFAULT 1,
    answered_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(student_id,pathway_item_id,page_block_id,qcm_key)
);");
}];
