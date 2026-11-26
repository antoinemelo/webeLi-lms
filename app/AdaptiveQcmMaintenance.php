<?php
declare(strict_types=1);

/** Remove only records whose LMS owner/step/enrollment was permanently deleted, never archived. */
function adaptive_qcm_prune(PDO $core,AdaptiveQcm $store,bool $force=false): array
{
    $db=$store->db;$counts=['adaptive_runs'=>0,'adaptive_references'=>0];
    $timeout=(int)$db->query('PRAGMA busy_timeout')->fetchColumn();
    $db->exec('PRAGMA busy_timeout='.($force?1000:0));$writing=false;
    try{
        $db->exec('BEGIN IMMEDIATE');$writing=true;
        $cursor=$force?0:(int)($store->one("SELECT value FROM maintenance_state WHERE name='cleanup_cursor'")['value']??0);
        do{
            $q=$db->prepare('SELECT r.rowid AS sequence,r.id,r.student_id,r.item_id,r.epoch,(r.evaluation=0 AND r.score IS NULL AND r.finished_at IS NOT NULL AND EXISTS(SELECT 1 FROM runs newer WHERE newer.student_id=r.student_id AND newer.item_id=r.item_id AND newer.epoch=r.epoch AND newer.finished_at IS NOT NULL AND (newer.started_at>r.started_at OR (newer.started_at=r.started_at AND newer.id>r.id)))) AS superseded FROM runs r WHERE r.rowid>? ORDER BY r.rowid LIMIT 500');$q->execute([$cursor]);$rows=$q->fetchAll();
            if(!$rows)break;
            $cursor=(int)end($rows)['sequence'];
            foreach(array_chunk($rows,100) as $batch){
                $values=[];foreach($batch as $row)array_push($values,$row['id'],$row['student_id'],$row['item_id'],$row['epoch']);
                $valid=$core->prepare('WITH candidates(id,student,item,epoch) AS (VALUES '.implode(',',array_fill(0,count($batch),'(?,?,?,?)')).') SELECT k.id FROM candidates k JOIN users u ON u.id=k.student JOIN pathway_items pi ON pi.id=k.item JOIN enrollments e ON e.student_id=k.student AND e.course_id=pi.course_id LEFT JOIN progress pr ON pr.enrollment_id=e.id AND pr.pathway_item_id=pi.id WHERE k.epoch=CAST(pi.quiz_revision AS TEXT)||char(58)||CAST(COALESCE(pr.quiz_generation,0) AS TEXT)');
                $valid->execute($values);$keep=array_flip($valid->fetchAll(PDO::FETCH_COLUMN));
                $remove=$db->prepare('DELETE FROM runs WHERE id=?');
                foreach($batch as $row)if($row['superseded']||!isset($keep[$row['id']])){$remove->execute([$row['id']]);$counts['adaptive_runs']+=$remove->rowCount();}
            }
        }while($force&&count($rows)===500);
        if(count($rows)<500)$cursor=0;
        $db->prepare("INSERT INTO maintenance_state VALUES('cleanup_cursor',?) ON CONFLICT(name) DO UPDATE SET value=excluded.value")->execute([$cursor]);
        // Calibration is aggregated, with no individual result or learner identifier to retain.
        foreach(['evaluation_pools'=>'item_id','evaluation_revisions'=>'item_id','settings'=>'page_id'] as $table=>$field){
            $ids=$db->query('SELECT DISTINCT '.$field.' FROM '.$table)->fetchAll(PDO::FETCH_COLUMN);
            foreach(array_chunk($ids,400) as $batch){
                $valid=$core->prepare('SELECT id FROM '.($field==='page_id'?'pages WHERE':'pathway_items WHERE is_evaluation=1 AND').' id IN ('.implode(',',array_fill(0,count($batch),'?')).')');$valid->execute($batch);$keep=array_flip($valid->fetchAll(PDO::FETCH_COLUMN));
                $remove=$db->prepare('DELETE FROM '.$table.' WHERE '.$field.'=?');foreach($batch as $id)if(!isset($keep[$id]))$remove->execute([$id]);
            }
        }
        $db->exec('DELETE FROM evaluation_pools WHERE NOT EXISTS(SELECT 1 FROM runs WHERE runs.pool_id=evaluation_pools.pool_id) AND EXISTS(SELECT 1 FROM evaluation_revisions ref WHERE ref.item_id=evaluation_pools.item_id AND ref.fingerprint IS NOT NULL AND ref.fingerprint<>evaluation_pools.fingerprint)');
        $counts['adaptive_references']=$db->exec('DELETE FROM question_sets WHERE NOT EXISTS(SELECT 1 FROM runs WHERE runs.pool_id=question_sets.id) AND NOT EXISTS(SELECT 1 FROM evaluation_pools WHERE evaluation_pools.pool_id=question_sets.id)');
        $db->exec('COMMIT');$writing=false;
        // An abandoned, expired test also loses its temporary answers, even if never reopened.
        $q=$db->prepare('SELECT id,student_id FROM runs WHERE finished_at IS NULL AND ends_at<=? AND EXISTS(SELECT 1 FROM answers WHERE run_id=runs.id) ORDER BY ends_at LIMIT 50');
        $q->execute([microtime(true)]);
        foreach($q->fetchAll() as $expired)$store->advance($expired['id'],(int)$expired['student_id'],microtime(true));
        if($force&&(int)$db->query('PRAGMA auto_vacuum')->fetchColumn()!==2)$db->exec('PRAGMA auto_vacuum=INCREMENTAL; VACUUM');
        // No VACUUM on normal page requests. Reclaim at most 64 free pages per housekeeping pass.
        $db->query('PRAGMA incremental_vacuum(64)')->fetchAll();
        return $counts;
    }catch(Throwable $error){if($writing)$db->exec('ROLLBACK');throw $error;}
    finally{$db->exec('PRAGMA busy_timeout='.$timeout);$store->clearPoolCache();}
}

function adaptive_qcm_housekeeping(PDO $core,bool $force=false): array
{
    $file='';foreach($core->query('PRAGMA database_list') as $row)if($row['name']==='main')$file=$row['file'];
    if($file===''||!is_file(dirname($file).'/adaptive.sqlite'))return [];
    $pdo=new PDO('sqlite:'.dirname($file).'/adaptive.sqlite');
    // An HTTP cleanup must never trigger a potentially expensive schema migration.
    if((int)$pdo->query('PRAGMA user_version')->fetchColumn()!==AdaptiveQcm::SCHEMA_VERSION)return [];
    return adaptive_qcm_prune($core,new AdaptiveQcm($pdo),$force);
}
