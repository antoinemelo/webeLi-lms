<?php
declare(strict_types=1);

function pathway_groups(PDO $pdo,int $courseId): array
{
    $query=$pdo->prepare('SELECT id,course_id,title,position FROM pathway_groups WHERE course_id=? ORDER BY position,id');
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
    if($includeEmpty)foreach($groups as $group)if(!isset($seen[(int)$group['id']])){
        $index=(int)($group['position']??0)>0?min((int)$group['position']-1,count($sections)):count($sections);
        array_splice($sections,$index,0,[['group'=>$group,'items'=>[]]]);
    }
    return $sections;
}

function pathway_structure_sections(PDO $pdo,int $courseId): array
{
    $query=$pdo->prepare('SELECT id,position,group_id FROM pathway_items WHERE course_id=? ORDER BY position,id');$query->execute([$courseId]);
    return pathway_group_sections($query->fetchAll(PDO::FETCH_ASSOC),pathway_groups($pdo,$courseId),true);
}

function pathway_section_key(array $section): string
{
    return $section['group']?'g'.(int)$section['group']['id']:'i'.(int)$section['items'][0]['id'];
}

/** Called within the structure transaction; groups never consume a step number. */
function store_pathway_sections(PDO $pdo,int $courseId,array $sections): void
{
    $ordered=[];$groupUpdate=$pdo->prepare('UPDATE pathway_groups SET position=? WHERE id=? AND course_id=?');
    $membership=$pdo->prepare('UPDATE pathway_items SET group_id=? WHERE id=? AND course_id=? AND group_id IS NOT ?');
    foreach($sections as $index=>$section){
        $groupId=$section['group']?(int)$section['group']['id']:null;
        if($groupId)$groupUpdate->execute([$index+1,$groupId,$courseId]);
        foreach($section['items'] as $item){$ordered[]=(int)$item['id'];$membership->execute([$groupId,$item['id'],$courseId,$groupId]);}
    }
    $query=$pdo->prepare('SELECT id,position FROM pathway_items WHERE course_id=? ORDER BY position,id');$query->execute([$courseId]);$current=$query->fetchAll(PDO::FETCH_ASSOC);
    if(array_column($current,'id')===$ordered&&array_column($current,'position')===array_map(static fn(int $index):int=>$index+1,array_keys($ordered)))return;
    $pdo->prepare('UPDATE pathway_items SET position=-position WHERE course_id=?')->execute([$courseId]);
    $update=$pdo->prepare("UPDATE pathway_items SET position=?,updated_at=strftime('%Y-%m-%d %H:%M:%f','now') WHERE id=? AND course_id=?");
    foreach($ordered as $index=>$id)$update->execute([$index+1,$id,$courseId]);
}

function reorder_pathway_group(PDO $pdo,int $courseId,int $groupId,int $teacherId,string $target,bool $after,string $expected): void
{
    if(!teacher_can_access_course($pdo,$courseId,$teacherId))throw new InvalidArgumentException('Parcours introuvable.');
    if(!acquire_edit_lock($pdo,'course_structure',$courseId,$teacherId)['ok'])throw new InvalidArgumentException('La structure du parcours est momentanément verrouillée par un autre enseignant.');
    try{
        $pdo->beginTransaction();pathway_structure_check($pdo,$courseId,$expected);
        $sections=pathway_structure_sections($pdo,$courseId);$keys=array_map('pathway_section_key',$sections);$source=array_search('g'.$groupId,$keys,true);
        if($source===false)throw new InvalidArgumentException('Regroupement introuvable.');
        if(in_array($target,['previous','next'],true)){
            $neighbor=$target==='previous'?$source-1:$source+1;
            if(!isset($keys[$neighbor])){$pdo->commit();return;}
            $after=$target==='next';$target=$keys[$neighbor];
        }
        if($target==='g'.$groupId){$pdo->commit();return;}
        $moving=array_splice($sections,$source,1)[0];
        $destination=$target==='end'?count($sections):array_search($target,array_map('pathway_section_key',$sections),true);
        if($destination===false)throw new InvalidArgumentException('Position d’étape invalide.');
        array_splice($sections,$destination+($after&&$target!=='end'?1:0),0,[$moving]);
        store_pathway_sections($pdo,$courseId,$sections);$pdo->commit();
    }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
    finally{release_edit_locks($pdo,$teacherId,'course_structure',$courseId);}
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
        $sections=pathway_structure_sections($pdo,$courseId);
        if($operation==='create'){
            $pdo->prepare('INSERT INTO pathway_groups(course_id,title,position) VALUES(?,?,?)')->execute([$courseId,$title,count($sections)+1]);
            $groupId=(int)$pdo->lastInsertId();
        }else{
            $query=$pdo->prepare('SELECT id FROM pathway_groups WHERE id=? AND course_id=?');$query->execute([$groupId,$courseId]);
            if(!$query->fetchColumn())throw new InvalidArgumentException('Regroupement introuvable.');
            if($operation==='delete'){
                $pdo->prepare('DELETE FROM pathway_groups WHERE id=? AND course_id=?')->execute([$groupId,$courseId]);
                $remaining=[];foreach($sections as $section){if((int)($section['group']['id']??0)===$groupId){foreach($section['items'] as $item)$remaining[]=['group'=>null,'items'=>[$item]];}else $remaining[]=$section;}
                store_pathway_sections($pdo,$courseId,$remaining);
            }
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
        $sections=pathway_structure_sections($pdo,$courseId);
        $query=$pdo->prepare('SELECT id,group_id,position FROM pathway_items WHERE course_id=? ORDER BY position,id');$query->execute([$courseId]);$items=$query->fetchAll(PDO::FETCH_ASSOC);
        $oldIndex=array_search($itemId,array_column($items,'id'),true);
        if($oldIndex===false){$pdo->rollBack();return ['status'=>'missing','course_id'=>$courseId];}
        $moving=$items[$oldIndex];$oldGroup=(int)($moving['group_id']??0);
        if($groupId>0&&!in_array($groupId,array_map('intval',array_column(pathway_groups($pdo,$courseId),'id')),true)){$pdo->rollBack();return $result;}
        array_splice($items,$oldIndex,1);
        if($targetPosition===0){
            $indices=[];$searchGroup=$groupId?:$oldGroup;
            if($searchGroup)foreach($items as $index=>$candidate)if((int)($candidate['group_id']??0)===$searchGroup)$indices[]=$index;
            $targetIndex=$indices?max($indices)+1:min($oldIndex,count($items));
            if($groupId&&!$indices){$targetIndex=0;foreach($sections as $section){if((int)($section['group']['id']??0)===$groupId)break;foreach($section['items'] as $candidate)if((int)$candidate['id']!==$itemId)$targetIndex++;}}
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
        foreach($sections as &$section)$section['items']=array_values(array_filter($section['items'],static fn(array $candidate):bool=>(int)$candidate['id']!==$itemId));
        unset($section);$sections=array_values(array_filter($sections,static fn(array $section):bool=>$section['group']!==null||$section['items']!==[]));
        if($groupId){
            $offset=0;foreach(array_slice($items,0,$targetIndex) as $candidate)if((int)($candidate['group_id']??0)===$groupId)$offset++;
            $relocate=null;
            foreach($sections as $index=>&$section)if((int)($section['group']['id']??0)===$groupId){if(!$section['items']&&$targetPosition>0)$relocate=$index;array_splice($section['items'],$offset,0,[$moving]);break;}
            unset($section);
            if($relocate!==null){
                $movedGroup=array_splice($sections,$relocate,1)[0];$nextId=$items[$targetIndex+1]['id']??null;$destination=count($sections);
                foreach($sections as $index=>$section)if(in_array($nextId,array_column($section['items'],'id'),true)){$destination=$index;break;}
                array_splice($sections,$destination,0,[$movedGroup]);
            }
        }else{
            $nextId=$items[$targetIndex+1]['id']??null;$destination=count($sections);
            foreach($sections as $index=>$section)if(in_array($nextId,array_column($section['items'],'id'),true)){$destination=$index;break;}
            array_splice($sections,$destination,0,[['group'=>null,'items'=>[$moving]]]);
        }
        store_pathway_sections($pdo,$courseId,$sections);
        $pdo->commit();return ['status'=>'updated','course_id'=>$courseId];
    }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
    finally{release_edit_locks($pdo,$teacherId,'course_structure',$courseId);}
}
