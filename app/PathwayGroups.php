<?php
declare(strict_types=1);

function pathway_groups(PDO $pdo,int $courseId): array
{
    $query=$pdo->prepare('SELECT id,course_id,title FROM pathway_groups WHERE course_id=? ORDER BY id');
    $query->execute([$courseId]);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

/** Group only the supplied (already access-filtered and numbered) items. */
function pathway_group_sections(array $items,array $groups,bool $includeEmpty=false): array
{
    $byId=array_column($groups,null,'id');$sections=[];$seen=[];
    foreach($items as $item){
        $groupId=(int)($item['group_id']??0);
        if(!$groupId||!isset($byId[$groupId])){$sections[]=['group'=>null,'items'=>[$item]];continue;}
        $last=array_key_last($sections);
        if($last===null||(int)($sections[$last]['group']['id']??0)!==$groupId){
            $sections[]=['group'=>$byId[$groupId],'items'=>[]];$last=array_key_last($sections);
        }
        $sections[$last]['items'][]=$item;$seen[$groupId]=true;
    }
    if($includeEmpty)foreach($groups as $group)if(!isset($seen[(int)$group['id']]))$sections[]=['group'=>$group,'items'=>[]];
    return $sections;
}

function pathway_structure_token(PDO $pdo,int $courseId): string
{
    $query=$pdo->prepare('SELECT id,position,group_id FROM pathway_items WHERE course_id=? ORDER BY position,id');
    $query->execute([$courseId]);
    return hash('sha256',json_encode([pathway_groups($pdo,$courseId),$query->fetchAll(PDO::FETCH_ASSOC)],JSON_THROW_ON_ERROR));
}

function pathway_structure_check(PDO $pdo,int $courseId,string $expected): void
{
    if(!hash_equals(pathway_structure_token($pdo,$courseId),$expected))throw new InvalidArgumentException('Le parcours a été modifié. Rechargez la page avant de réessayer.');
}

/** All group writes share the existing course structure lock and a stale-view check. */
function save_pathway_group(PDO $pdo,int $courseId,int $teacherId,string $operation,int $groupId,string $title,string $expected): int
{
    if(!teacher_can_access_course($pdo,$courseId,$teacherId))throw new InvalidArgumentException('Parcours introuvable.');
    $title=trim($title);
    if(!in_array($operation,['create','rename','delete'],true))throw new InvalidArgumentException('Regroupement introuvable.');
    if($operation!=='delete'&&($title===''||mb_strlen($title)>120))throw new InvalidArgumentException('Le titre du regroupement doit contenir entre 1 et 120 caractères.');
    $lock=acquire_edit_lock($pdo,'course_structure',$courseId,$teacherId);
    if(!$lock['ok'])throw new InvalidArgumentException('La structure du parcours est momentanément verrouillée par un autre enseignant.');
    try{
        $pdo->beginTransaction();pathway_structure_check($pdo,$courseId,$expected);
        if($operation==='create'){
            $pdo->prepare('INSERT INTO pathway_groups(course_id,title) VALUES(?,?)')->execute([$courseId,$title]);
            $groupId=(int)$pdo->lastInsertId();
        }else{
            $query=$pdo->prepare('SELECT id FROM pathway_groups WHERE id=? AND course_id=?');$query->execute([$groupId,$courseId]);
            if(!$query->fetchColumn())throw new InvalidArgumentException('Regroupement introuvable.');
            if($operation==='delete')$pdo->prepare('DELETE FROM pathway_groups WHERE id=? AND course_id=?')->execute([$groupId,$courseId]);
            else $pdo->prepare('UPDATE pathway_groups SET title=? WHERE id=? AND course_id=?')->execute([$title,$groupId,$courseId]);
        }
        $pdo->commit();return $groupId;
    }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
    finally{release_edit_locks($pdo,$teacherId,'course_structure',$courseId);}
}

/**
 * Move one step, keeping groups contiguous and the existing global numbering.
 * null group: infer from the destination step (number input / legacy callers).
 * 0 group: explicitly ungroup. Position 0: append to the chosen group, or
 * place immediately after the source group when taking a step out of it.
 */
function reorder_pathway_item(PDO $pdo,int $itemId,int $targetPosition,int $teacherId,?int $groupId=null,?string $expected=null): array
{
    $query=$pdo->prepare('SELECT id,course_id FROM pathway_items WHERE id=?');$query->execute([$itemId]);$item=$query->fetch(PDO::FETCH_ASSOC);
    if(!$item||!teacher_can_access_course($pdo,(int)$item['course_id'],$teacherId))return ['status'=>'missing','course_id'=>null];
    $courseId=(int)$item['course_id'];$result=['status'=>'invalid','course_id'=>$courseId];
    if($targetPosition<0||($targetPosition===0&&$groupId===null)||$groupId<0)return $result;
    if(!acquire_edit_lock($pdo,'course_structure',$courseId,$teacherId)['ok'])return ['status'=>'locked','course_id'=>$courseId];
    try{
        $pdo->beginTransaction();
        if($expected!==null&&!hash_equals(pathway_structure_token($pdo,$courseId),$expected)){$pdo->rollBack();return ['status'=>'stale','course_id'=>$courseId];}
        $query=$pdo->prepare('SELECT id,group_id,position FROM pathway_items WHERE course_id=? ORDER BY position,id');$query->execute([$courseId]);$items=$query->fetchAll(PDO::FETCH_ASSOC);
        $oldIndex=array_search($itemId,array_column($items,'id'),true);$moving=$items[$oldIndex];$oldGroup=(int)($moving['group_id']??0);
        if($groupId>0&&!in_array($groupId,array_map('intval',array_column(pathway_groups($pdo,$courseId),'id')),true)){$pdo->rollBack();return $result;}
        array_splice($items,$oldIndex,1);
        if($targetPosition===0){
            $indices=[];$searchGroup=$groupId?:$oldGroup;
            if($searchGroup)foreach($items as $index=>$candidate)if((int)($candidate['group_id']??0)===$searchGroup)$indices[]=$index;
            $targetIndex=$indices?max($indices)+1:($groupId?count($items):min($oldIndex,count($items)));
        }else $targetIndex=min($targetPosition-1,count($items));
        if($groupId===null){
            // A no-op number change must not alter membership at a boundary.
            if($targetIndex===$oldIndex){$pdo->commit();return ['status'=>'unchanged','course_id'=>$courseId];}
            $anchor=$items[min($targetIndex,count($items)-1)]??null;
            $groupId=(int)($anchor['group_id']??0);
        }
        $moving['group_id']=$groupId?:null;array_splice($items,$targetIndex,0,[$moving]);
        // Reject forged drops that would split a group into multiple sections.
        $closed=[];$previous=0;
        foreach($items as $candidate){$current=(int)($candidate['group_id']??0);if($current!==$previous){if($current&&isset($closed[$current])){$pdo->rollBack();return $result;}if($previous)$closed[$previous]=true;$previous=$current;}}
        if($targetIndex===$oldIndex&&$oldGroup===$groupId){$pdo->commit();return ['status'=>'unchanged','course_id'=>$courseId];}
        $pdo->prepare('UPDATE pathway_items SET group_id=? WHERE id=? AND course_id=?')->execute([$groupId?:null,$itemId,$courseId]);
        if($targetIndex!==$oldIndex){
            $pdo->prepare('UPDATE pathway_items SET position=-position WHERE course_id=?')->execute([$courseId]);
            $update=$pdo->prepare("UPDATE pathway_items SET position=?,updated_at=strftime('%Y-%m-%d %H:%M:%f','now') WHERE id=? AND course_id=?");
            foreach($items as $index=>$candidate)$update->execute([$index+1,$candidate['id'],$courseId]);
        }
        $pdo->commit();return ['status'=>'updated','course_id'=>$courseId];
    }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
    finally{release_edit_locks($pdo,$teacherId,'course_structure',$courseId);}
}
