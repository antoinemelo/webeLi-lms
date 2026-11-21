<?php
declare(strict_types=1);

const LIBRARY_CARD_BATCH_SIZE = 24;

/** The same authorization predicate is used for the index and every requested batch. */
function library_catalog(PDO $pdo, int $teacherId, ?array $pageIds=null): array
{
    $where='(p.owner_id=? OR p.id IN (SELECT page_id FROM page_collaborators WHERE teacher_id=? UNION SELECT pi.page_id FROM pathway_items pi JOIN courses c ON c.id=pi.course_id WHERE c.teacher_id=? OR c.id IN (SELECT course_id FROM course_teachers WHERE teacher_id=?)))';
    $params=[$teacherId,$teacherId,$teacherId,$teacherId];
    if($pageIds!==null){
        $pageIds=array_values(array_unique(array_filter(array_map('intval',$pageIds),static fn(int $id):bool=>$id>0)));
        if(count($pageIds)>LIBRARY_CARD_BATCH_SIZE)throw new InvalidArgumentException('Requête invalide.');
        $where.=$pageIds?' AND p.id IN ('.implode(',',array_fill(0,count($pageIds),'?')).')':' AND 0';
        array_push($params,...$pageIds);
    }
    $query=$pdo->prepare('SELECT p.id,p.title,p.summary,p.status,p.revision,p.estimated_minutes,p.owner_id,p.updated_at,u.name AS owner_name,
        COALESCE(usage_totals.usages,0) AS usages,COALESCE(block_totals.blocks,0) AS blocks
        FROM pages p JOIN users u ON u.id=p.owner_id
        LEFT JOIN (SELECT page_id,COUNT(*) AS usages FROM pathway_items GROUP BY page_id) usage_totals ON usage_totals.page_id=p.id
        LEFT JOIN (SELECT page_id,COUNT(*) AS blocks FROM page_blocks GROUP BY page_id) block_totals ON block_totals.page_id=p.id
        WHERE '.$where.' ORDER BY p.updated_at DESC,p.title,p.id');
    $query->execute($params);$pages=$query->fetchAll(PDO::FETCH_ASSOC);
    $ids=array_column($pages,'id');$tagsByPage=[];$objectivesByPage=[];$tags=[];$objectives=[];
    foreach(array_chunk($ids,400) as $chunk){
        $marks=implode(',',array_fill(0,count($chunk),'?'));
        $query=$pdo->prepare("SELECT t.*,pt.page_id FROM tags t JOIN page_tags pt ON pt.tag_id=t.id WHERE pt.page_id IN ($marks) ORDER BY t.name");
        $query->execute($chunk);
        foreach($query->fetchAll(PDO::FETCH_ASSOC) as $tag){$tagsByPage[(int)$tag['page_id']][]=$tag;$tags[(int)$tag['id']]=$tag;}
        $query=$pdo->prepare("SELECT * FROM page_objectives WHERE page_id IN ($marks) ORDER BY position,id");
        $query->execute($chunk);
        foreach($query->fetchAll(PDO::FETCH_ASSOC) as $objective){
            $objectivesByPage[(int)$objective['page_id']][]=$objective;
            $objectives[mb_strtolower($objective['title'],'UTF-8')]=$objective;
        }
    }
    uasort($tags,static fn(array $a,array $b):int=>strcasecmp($a['name'],$b['name']));
    uasort($objectives,static fn(array $a,array $b):int=>strcasecmp($a['title'],$b['title']));
    $sharing=library_page_sharing($pdo,$ids,$teacherId);
    return compact('pages','sharing','tagsByPage','objectivesByPage','tags','objectives');
}

/** Compact searchable metadata; cards and their editing controls are loaded in batches. */
function library_search_index(array $catalog): array
{
    $entries=[];
    foreach($catalog['pages'] as $page){
        $id=(int)$page['id'];$info=$catalog['sharing'][$id];
        $tags=$catalog['tagsByPage'][$id]??[];$objectives=$catalog['objectivesByPage'][$id]??[];
        $extra=$info['owner_name'].' '.$info['copy_source_name'].' '.implode(' ',array_column($tags,'name')).' '.implode(' ',array_column($objectives,'title'));
        $marks=[];
        if($info['own'])foreach(['outgoing'=>['shared_allowed','Contenu partagé'],'copy'=>['copy_allowed','Copie indépendante autorisée'],'external'=>['external_allowed','Utilisation externe autorisée']] as $kind=>[$flag,$label]){
            if($info[$flag])$marks[]=['kind'=>$kind,'label'=>t($label)];
        }
        $entries[]=[
            'id'=>(string)$id,'ownerId'=>(string)$info['owner_id'],'status'=>$page['status'],
            'search'=>mb_strtolower($page['title'].' '.$page['summary'].' '.$extra,'UTF-8'),
            'tags'=>array_map('strval',array_column($tags,'id')),
            'objectives'=>array_map(static fn(array $o):string=>sha1(mb_strtolower($o['title'],'UTF-8')),$objectives),
            'sharing'=>$info['filters'],'cardClass'=>$info['card_class'],'marks'=>$marks,
        ];
    }
    return $entries;
}
