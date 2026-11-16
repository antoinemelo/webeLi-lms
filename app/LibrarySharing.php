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
        default=>'Toute personne disposant du lien peut consulter le contenu sans compte et faire les QCM en entraînement. Aucun résultat, note ou travail rendu n’est enregistré. Les modes strict et classe deviennent des entraînements individuels.',
    };
}

function library_share_create(PDO $pdo,int $pageId,int $teacherId,string $mode): string
{
    if(!isset(library_share_modes()[$mode])||!teacher_owns_page($pdo,$pageId,$teacherId))throw new InvalidArgumentException('Partage non autorisé.');
    $query=$pdo->prepare("SELECT 1 FROM pages p JOIN users u ON u.id=p.owner_id WHERE p.id=? AND p.status='ready' AND u.role='teacher' AND u.account_status='active'");$query->execute([$pageId]);
    if(!$query->fetchColumn())throw new InvalidArgumentException('Enregistrez la page avec le statut Prêt avant de la partager.');
    $token=bin2hex(random_bytes(32));
    $pdo->prepare("INSERT INTO page_shares(page_id,mode,token,created_by) VALUES(?,?,?,?) ON CONFLICT(page_id,mode) DO UPDATE SET token=CASE WHEN page_shares.revoked_at IS NULL THEN page_shares.token ELSE excluded.token END,revoked_at=NULL,created_at=CURRENT_TIMESTAMP")->execute([$pageId,$mode,$token,$teacherId]);
    $query=$pdo->prepare('SELECT token FROM page_shares WHERE page_id=? AND mode=?');$query->execute([$pageId,$mode]);return (string)$query->fetchColumn();
}

function library_share_revoke(PDO $pdo,int $pageId,int $teacherId,string $mode): void
{
    if(!teacher_owns_page($pdo,$pageId,$teacherId))throw new InvalidArgumentException('Partage non autorisé.');
    $pdo->prepare('UPDATE page_shares SET revoked_at=CURRENT_TIMESTAMP WHERE page_id=? AND mode=?')->execute([$pageId,$mode]);
}

function library_share_resolve(PDO $pdo,string $token): array
{
    if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new InvalidArgumentException('Ce lien de partage est indisponible.');
    $query=$pdo->prepare("SELECT s.id AS share_id,s.mode,p.id AS page_id,p.title,p.summary,p.owner_id,p.estimated_minutes,u.name AS owner_name FROM page_shares s JOIN pages p ON p.id=s.page_id JOIN users u ON u.id=p.owner_id WHERE s.token=? AND s.revoked_at IS NULL AND p.status='ready' AND u.account_status='active' AND u.role='teacher'");$query->execute([$token]);
    $share=$query->fetch(PDO::FETCH_ASSOC);if(!$share)throw new InvalidArgumentException('Ce lien de partage est indisponible.');return $share;
}

/** A receipt makes refresh/double submit idempotent. No enrollment or progress is copied. */
function library_share_accept(PDO $pdo,string $token,int $teacherId,int $courseId=0): int
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
        if(!in_array($share['mode'],['copy','shared'],true))throw new InvalidArgumentException('Ce lien ne permet pas de récupérer une page.');
        $pageId=(int)$share['page_id'];
        if($share['mode']==='shared'){
            if((int)$share['owner_id']!==$teacherId)$pdo->prepare('INSERT OR IGNORE INTO page_collaborators(page_id,teacher_id,added_by) VALUES(?,?,?)')->execute([$pageId,$teacherId,$share['owner_id']]);
        }else{
            $query=$pdo->prepare('SELECT page_id FROM page_share_receipts WHERE share_id=? AND teacher_id=?');$query->execute([$share['share_id'],$teacherId]);$received=(int)$query->fetchColumn();
            if($received&&teacher_owns_page($pdo,$received,$teacherId))$pageId=$received;
            else{
                $document=export_page_document($pdo,$pageId,(int)$share['owner_id']);
                $pageId=import_page_document($pdo,$document,$teacherId,'copy');
            }
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
    // Same deployment-aware base as course invitations, including /dev/public/.
    $invitation=course_invitation_url('');
    return substr($invitation,0,(int)strpos($invitation,'?')).route('shared-content',['share'=>$token]);
}
