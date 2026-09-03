<?php

declare(strict_types=1);

return [
    'version'=>15,
    'name'=>'Repère léger des changements de parcours pour les élèves',
    'up'=>static function(PDO $pdo): void {
        $itemColumns=array_column($pdo->query('PRAGMA table_info(pathway_items)')->fetchAll(PDO::FETCH_ASSOC),'name');
        if(!in_array('updated_at',$itemColumns,true))$pdo->exec('ALTER TABLE pathway_items ADD COLUMN updated_at TEXT');
        $pdo->exec("UPDATE pathway_items SET updated_at=COALESCE(updated_at,strftime('%Y-%m-%d %H:%M:%f','now'))");
        $pdo->exec("CREATE TRIGGER IF NOT EXISTS touch_pathway_item_after_insert AFTER INSERT ON pathway_items WHEN NEW.updated_at IS NULL BEGIN UPDATE pathway_items SET updated_at=strftime('%Y-%m-%d %H:%M:%f','now') WHERE id=NEW.id; END");
        $pdo->exec("CREATE TRIGGER IF NOT EXISTS touch_pathway_item_after_update AFTER UPDATE OF course_id,page_id,position,deadline,is_evaluation,self_evaluation_enabled,evaluation_weight,instructions,access_mode,framework_tracking_enabled ON pathway_items WHEN NEW.updated_at IS OLD.updated_at BEGIN UPDATE pathway_items SET updated_at=strftime('%Y-%m-%d %H:%M:%f','now') WHERE id=NEW.id; END");

        $enrollmentColumns=array_column($pdo->query('PRAGMA table_info(enrollments)')->fetchAll(PDO::FETCH_ASSOC),'name');
        if(!in_array('pathway_changes_seen_at',$enrollmentColumns,true))$pdo->exec('ALTER TABLE enrollments ADD COLUMN pathway_changes_seen_at TEXT');
        $pdo->exec("UPDATE enrollments SET pathway_changes_seen_at=COALESCE(pathway_changes_seen_at,strftime('%Y-%m-%d %H:%M:%f','now'))");
    },
];
