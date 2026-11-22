<?php
declare(strict_types=1);

function library_share_modes(): array
{
    return ['shared'=>'Contenu partagé','copy'=>'Copie indépendante','external'=>'Utilisation externe'];
}

function library_share_description(string $mode): string
{
    return match($mode){
        'shared'=>'Les collègues connectés à cette plateforme utilisent la même page. Les modifications du contenu sont communes à tous les parcours qui l’utilisent.',
        'copy'=>'Chaque collègue récupère sa propre copie. Les modifications ultérieures restent indépendantes. Un fichier JSON permet aussi de la transférer vers une autre installation liike.',
        default=>'Consultez le contenu sans inscription ou animez un QCM où les apprenants rejoignent la séance par lien ou QR code, librement, sans nom ni compte.',
    };
}

function library_share_settings(PDO $pdo,int $pageId): array
{
    $query=$pdo->prepare('SELECT * FROM page_share_links WHERE page_id=?');$query->execute([$pageId]);
    return $query->fetch(PDO::FETCH_ASSOC)?:['page_id'=>$pageId,'token'=>'','enabled'=>0,'allow_shared'=>0,'allow_copy'=>0,'allow_external'=>0,'revision'=>0];
}

/** The single URL survives policy changes and disabling/re-enabling. */
function library_share_save(PDO $pdo,int $pageId,int $teacherId,bool $enabled,array $modes,int $revision): string
{
    if(!teacher_owns_page($pdo,$pageId,$teacherId))throw new InvalidArgumentException('Partage non autorisé.');
    foreach($modes as $mode)if(!is_string($mode)||!isset(library_share_modes()[$mode]))throw new InvalidArgumentException('Mode de partage invalide.');
    $modes=array_values(array_unique($modes));
    if($enabled&&!$modes)throw new InvalidArgumentException('Choisissez au moins un mode de partage.');
    $pdo->beginTransaction();
    try{
        $settings=library_share_settings($pdo,$pageId);
        if((int)$settings['revision']!==$revision)throw new InvalidArgumentException('Le partage a changé. Rechargez la page avant de réessayer.');
        $query=$pdo->prepare("SELECT p.status FROM pages p JOIN users u ON u.id=p.owner_id WHERE p.id=? AND u.role='teacher' AND u.account_status='active'");$query->execute([$pageId]);$status=$query->fetchColumn();
        if(!$status)throw new InvalidArgumentException('Partage non autorisé.');
        if($enabled&&$status!=='ready')throw new InvalidArgumentException('Enregistrez la page avec le statut Prêt avant de la partager.');
        $token=$settings['token']?:bin2hex(random_bytes(32));
        $pdo->prepare('INSERT INTO page_share_links(page_id,token,enabled,allow_shared,allow_copy,allow_external) VALUES(?,?,?,?,?,?) ON CONFLICT(page_id) DO UPDATE SET enabled=excluded.enabled,allow_shared=excluded.allow_shared,allow_copy=excluded.allow_copy,allow_external=excluded.allow_external,revision=page_share_links.revision+1')
            ->execute([$pageId,$token,(int)$enabled,(int)in_array('shared',$modes,true),(int)in_array('copy',$modes,true),(int)in_array('external',$modes,true)]);
        if(!$enabled||!in_array('external',$modes,true))$pdo->prepare("UPDATE qcm_guest_sessions SET phase='finished',revision=revision+1 WHERE page_id=? AND phase<>'finished'")->execute([$pageId]);
        // Legacy mode records now serve as receipt identities; their URLs are never displayed.
        foreach($modes as $mode)$pdo->prepare('INSERT OR IGNORE INTO page_shares(page_id,mode,token,created_by) VALUES(?,?,?,?)')->execute([$pageId,$mode,bin2hex(random_bytes(32)),$teacherId]);
        $pdo->commit();return $token;
    }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
}

function library_share_resolve(PDO $pdo,string $token): array
{
    if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new InvalidArgumentException('Ce lien de partage est indisponible.');
    $query=$pdo->prepare("SELECT l.*,p.id AS page_id,p.title,p.summary,p.owner_id,p.estimated_minutes,u.name AS owner_name FROM page_share_links l JOIN pages p ON p.id=l.page_id JOIN users u ON u.id=p.owner_id WHERE (l.token=? OR EXISTS(SELECT 1 FROM page_shares legacy WHERE legacy.page_id=l.page_id AND legacy.token=? AND legacy.revoked_at IS NULL)) AND l.enabled=1 AND p.status='ready' AND u.account_status='active' AND u.role='teacher'");$query->execute([$token,$token]);
    $share=$query->fetch(PDO::FETCH_ASSOC);if(!$share)throw new InvalidArgumentException('Ce lien de partage est indisponible.');
    $share['modes']=array_values(array_filter(array_keys(library_share_modes()),static fn(string $mode):bool=>(bool)$share['allow_'.$mode]));
    if(!hash_equals($share['token'],$token)){
        // Legacy links retain their original mode, within the owner's current policy.
        $query=$pdo->prepare('SELECT mode FROM page_shares WHERE page_id=? AND token=? AND revoked_at IS NULL');$query->execute([$share['page_id'],$token]);
        $legacyMode=(string)$query->fetchColumn();
        if(!in_array($legacyMode,$share['modes'],true))throw new InvalidArgumentException('Ce lien de partage est indisponible.');
        $share['modes']=[$legacyMode];
    }
    return $share;
}

function library_share_require_mode(array $share,string $mode): void
{
    if(!in_array($mode,$share['modes'],true))throw new InvalidArgumentException('Ce mode de partage n’est plus autorisé.');
}

/** Administrative modes and their source files are reserved to signed-in teachers. */
function library_share_visible_modes(array $share,?array $user): array
{
    if($user&&$user['role']==='teacher'&&($user['account_status']??'')==='active')return $share['modes'];
    return array_values(array_intersect($share['modes'],['external']));
}

/** Metadata for pages already authorized by the library or pathway query. */
function library_page_sharing(PDO $pdo,array $pageIds,int $teacherId): array
{
    $pageIds=array_values(array_unique(array_map('intval',$pageIds)));if(!$pageIds)return [];
    $result=[];
    foreach(array_chunk($pageIds,400) as $ids){
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $query=$pdo->prepare("SELECT p.id,p.owner_id,u.name AS owner_name,p.status,u.account_status,
            COALESCE(l.enabled,0) AS enabled,COALESCE(l.allow_shared,0) AS allow_shared,
            COALESCE(l.allow_copy,0) AS allow_copy,COALESCE(l.allow_external,0) AS allow_external,
            o.page_id AS copied_page_id,COALESCE(source.name,o.source_owner_name,'') AS copy_source_name,
            EXISTS(SELECT 1 FROM page_shares history WHERE history.page_id=p.id AND history.mode='shared') AS was_shared,
            p.id IN (SELECT pi.page_id FROM pathway_items pi JOIN pages source_page ON source_page.id=pi.page_id
                JOIN courses c ON c.id=pi.course_id JOIN users colleague ON colleague.id=c.teacher_id
                WHERE c.teacher_id<>source_page.owner_id AND c.archived=0
                    AND colleague.role='teacher' AND colleague.account_status='active') AS used_in_colleague_pathway
            FROM pages p JOIN users u ON u.id=p.owner_id LEFT JOIN page_share_links l ON l.page_id=p.id
            LEFT JOIN page_copy_origins o ON o.page_id=p.id LEFT JOIN users source ON source.id=o.source_owner_id
            WHERE p.id IN ($marks)");$query->execute($ids);
        foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row){
            $own=(int)$row['owner_id']===$teacherId;
            $active=(bool)$row['enabled']&&$row['status']==='ready'&&$row['account_status']==='active';
            // Dots describe permissions; blue requires actual use in a colleague's active pathway.
            $shared=$own&&$active&&(bool)$row['allow_shared'];$copied=$row['copied_page_id']!==null;
            $used=$shared&&(bool)$row['used_in_colleague_pathway'];
            $result[(int)$row['id']]=[
                'owner_id'=>(int)$row['owner_id'],'owner_name'=>$row['owner_name'],'own'=>$own,
                'incoming'=>!$own,'shared_allowed'=>$shared,'shared_used'=>$used,'copied'=>$copied,'copy_source_name'=>$row['copy_source_name'],
                'sharing_stopped'=>!$own&&(bool)$row['was_shared']&&(!$active||!(bool)$row['allow_shared']),
                'copy_allowed'=>$active&&(bool)$row['allow_copy'],'external_allowed'=>$active&&(bool)$row['allow_external'],
                'card_class'=>!$own?'page-card-shared-in':($used?'page-card-shared-out':''),
                'filters'=>array_keys(array_filter(['shared'=>$shared,'copy'=>$copied,'external'=>$active&&(bool)$row['allow_external']]))
            ];
        }
    }
    return $result;
}

/** A receipt makes refresh/double submit idempotent. No enrollment or progress is copied. */
function library_share_accept(PDO $pdo,string $token,int $teacherId,int $courseId=0,string $mode=''): int
{
    $query=$pdo->prepare("SELECT 1 FROM users WHERE id=? AND role='teacher' AND account_status='active'");$query->execute([$teacherId]);
    if(!$query->fetchColumn())throw new InvalidArgumentException('Un compte enseignant est nécessaire.');
    if($courseId){
        $query=$pdo->prepare('SELECT 1 FROM courses WHERE id=? AND archived=0');$query->execute([$courseId]);
        if(!$query->fetchColumn()||!teacher_can_access_course($pdo,$courseId,$teacherId))throw new InvalidArgumentException('Parcours introuvable.');
        if(!acquire_edit_lock($pdo,'course_structure',$courseId,$teacherId)['ok'])throw new InvalidArgumentException('La structure du parcours est momentanément verrouillée par un autre enseignant.');
    }
    try{
        $pdo->beginTransaction();$share=library_share_resolve($pdo,$token);
        library_share_require_mode($share,$mode);
        if(!in_array($mode,['copy','shared'],true))throw new InvalidArgumentException('Ce lien ne permet pas de récupérer une page.');
        $pageId=(int)$share['page_id'];
        $query=$pdo->prepare('SELECT id FROM page_shares WHERE page_id=? AND mode=?');$query->execute([$pageId,$mode]);$share['share_id']=(int)$query->fetchColumn();
        if($mode==='shared'){
            if((int)$share['owner_id']!==$teacherId)$pdo->prepare('INSERT OR IGNORE INTO page_collaborators(page_id,teacher_id,added_by) VALUES(?,?,?)')->execute([$pageId,$teacherId,$share['owner_id']]);
        }else{
            $query=$pdo->prepare('SELECT page_id FROM page_share_receipts WHERE share_id=? AND teacher_id=?');$query->execute([$share['share_id'],$teacherId]);$received=(int)$query->fetchColumn();
            if($received&&teacher_owns_page($pdo,$received,$teacherId))$pageId=$received;
            else{
                $document=export_page_document($pdo,$pageId,(int)$share['owner_id']);
                $pageId=import_page_document($pdo,$document,$teacherId,'copy');
            }
            $pdo->prepare('INSERT OR IGNORE INTO page_copy_origins(page_id,source_page_id,source_owner_id,source_owner_name) VALUES(?,?,?,?)')
                ->execute([$pageId,$share['page_id'],$share['owner_id'],$share['owner_name']]);
        }
        $pdo->prepare('INSERT INTO page_share_receipts(share_id,teacher_id,page_id) VALUES(?,?,?) ON CONFLICT(share_id,teacher_id) DO UPDATE SET page_id=excluded.page_id')->execute([$share['share_id'],$teacherId,$pageId]);
        if($courseId){
            $query=$pdo->prepare('SELECT 1 FROM pathway_items WHERE course_id=? AND page_id=?');$query->execute([$courseId,$pageId]);
            if(!$query->fetchColumn())$pdo->prepare('INSERT INTO pathway_items(course_id,page_id,position,self_evaluation_enabled,is_evaluation) SELECT ?,?,COALESCE(MAX(position),0)+1,0,0 FROM pathway_items WHERE course_id=?')->execute([$courseId,$pageId,$courseId]);
        }
        $pdo->commit();return $pageId;
    }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
    finally{if($courseId)release_edit_locks($pdo,$teacherId,'course_structure',$courseId);}
}

function library_share_url(string $token): string
{
    return app_public_base_url().'/'.route('shared-content',['share'=>$token]);
}
