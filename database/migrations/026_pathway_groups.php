<?php
declare(strict_types=1);

return ['version'=>26,'name'=>'Regroupements facultatifs des étapes','up'=>static function(PDO $pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS pathway_groups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
        title TEXT NOT NULL CHECK(length(trim(title)) BETWEEN 1 AND 120)
    )");
    $columns=array_column($pdo->query('PRAGMA table_info(pathway_items)')->fetchAll(PDO::FETCH_ASSOC),'name');
    if(!in_array('group_id',$columns,true))$pdo->exec('ALTER TABLE pathway_items ADD COLUMN group_id INTEGER REFERENCES pathway_groups(id) ON DELETE SET NULL');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pathway_groups_course ON pathway_groups(course_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pathway_items_group ON pathway_items(group_id)');
    foreach(['INSERT','UPDATE OF group_id,course_id'] as $event){
        $suffix=$event==='INSERT'?'insert':'update';
        $pdo->exec("CREATE TRIGGER IF NOT EXISTS pathway_group_course_$suffix BEFORE $event ON pathway_items
            WHEN NEW.group_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM pathway_groups WHERE id=NEW.group_id AND course_id=NEW.course_id)
            BEGIN SELECT RAISE(ABORT,'pathway group belongs to another course'); END");
    }
}];
