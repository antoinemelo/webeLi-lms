<?php
declare(strict_types=1);
return ['version'=>25,'name'=>'Événements du parcours et types exclusifs','up'=>static function(PDO $pdo):void{
    $columns=array_column($pdo->query('PRAGMA table_info(pathway_items)')->fetchAll(PDO::FETCH_ASSOC),'name');
    if(!in_array('event_data',$columns,true))$pdo->exec('ALTER TABLE pathway_items ADD COLUMN event_data TEXT');
    // Preserve all historical scores and comments; evaluation takes precedence for legacy mixed types.
    $pdo->exec('UPDATE pathway_items SET self_evaluation_enabled=0 WHERE is_evaluation=1 AND self_evaluation_enabled=1');
    $pdo->exec("CREATE TRIGGER IF NOT EXISTS touch_pathway_event_after_update AFTER UPDATE OF event_data ON pathway_items WHEN NEW.updated_at IS OLD.updated_at BEGIN UPDATE pathway_items SET updated_at=strftime('%Y-%m-%d %H:%M:%f','now') WHERE id=NEW.id; END;");
}];
