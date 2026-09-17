<?php
declare(strict_types=1);

return ['version'=>27,'name'=>'Position des regroupements dans le parcours','up'=>static function(PDO $pdo):void{
    $columns=array_column($pdo->query('PRAGMA table_info(pathway_groups)')->fetchAll(PDO::FETCH_ASSOC),'name');
    if(in_array('position',$columns,true))return;
    $pdo->exec('ALTER TABLE pathway_groups ADD COLUMN position INTEGER NOT NULL DEFAULT 0');
    $update=$pdo->prepare('UPDATE pathway_groups SET position=? WHERE id=?');
    foreach($pdo->query('SELECT DISTINCT course_id FROM pathway_groups')->fetchAll(PDO::FETCH_COLUMN) as $courseId){
        $items=$pdo->prepare('SELECT group_id FROM pathway_items WHERE course_id=? ORDER BY position,id');$items->execute([$courseId]);
        $seen=[];$position=0;
        foreach($items->fetchAll(PDO::FETCH_COLUMN) as $groupId){if(!$groupId){$position++;continue;}if(!isset($seen[$groupId])){$update->execute([++$position,$groupId]);$seen[$groupId]=true;}}
        $groups=$pdo->prepare('SELECT id FROM pathway_groups WHERE course_id=? ORDER BY id');$groups->execute([$courseId]);
        foreach($groups->fetchAll(PDO::FETCH_COLUMN) as $groupId)if(!isset($seen[$groupId]))$update->execute([++$position,$groupId]);
    }
}];
