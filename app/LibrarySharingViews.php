<?php
declare(strict_types=1);

function render_library_share_panel(array $page): void
{
    if((int)$page['owner_id']!==(int)actor()['id'])return;
    $shares=all('SELECT * FROM page_shares WHERE page_id=?',[$page['id']]);$byMode=array_column($shares,null,'mode');
    ?><section class="settings-card library-sharing" id="library-sharing"><h2><?=e(t('Partager ce contenu'))?></h2>
    <p><?=e(t('Choisissez le lien à transmettre à vos collègues. Seul le propriétaire peut créer ou désactiver ces liens.'))?></p>
    <?php foreach(library_share_modes() as $mode=>$label): $share=$byMode[$mode]??null;$active=$share&&$share['revoked_at']===null; ?>
      <article class="border rounded p-3 mb-3"><h3><?=e(t($label))?></h3><p><?=e(t(library_share_description($mode)))?></p>
      <?php if($active): ?><label class="field"><span><?=e(t('Lien à partager'))?></span><input class="form-control" type="url" readonly value="<?=e(library_share_url($share['token']))?>" onclick="this.select()"></label><p><a href="<?=e(route('shared-content',['share'=>$share['token']]))?>" target="_blank" rel="noopener noreferrer"><?=e(t('Ouvrir le lien'))?> ↗</a></p><?php endif; ?>
      <form method="post"><?=csrf_field()?><input type="hidden" name="action" value="<?=$active?'revoke_library_share':'create_library_share'?>"><input type="hidden" name="page_id" value="<?=(int)$page['id']?>"><input type="hidden" name="mode" value="<?=e($mode)?>"><button class="button secondary btn btn-outline-secondary" <?=$page['status']!=='ready'&&!$active?'disabled':''?>><?=e(t($active?'Désactiver ce lien':'Créer le lien'))?></button></form></article>
    <?php endforeach; ?>
    <?php if($page['status']!=='ready'): ?><p><?=e(t('Enregistrez la page avec le statut Prêt avant de la partager.'))?></p><?php endif; ?>
    <p class="muted-copy"><?=e(t('Désactiver un lien bloque ses prochaines utilisations. Les copies et les contenus déjà récupérés par des collègues restent dans leurs bibliothèques et parcours. Un nouveau lien ne réactive pas l’ancienne URL.'))?></p>
    </section><?php
}

function library_share_page_start(string $title): void
{
    ?><!doctype html><html lang="<?=e(current_language())?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title><?=e($title)?> · liike</title><link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css"><link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css"><link rel="stylesheet" href="assets/app.css?v=153"></head><body><main class="container py-4" style="max-width:960px"><p class="eyebrow">liike · <?=e(t('Contenu partagé'))?></p><?php
}

/** Public bearer link; never renders the application navigation or student data. */
function library_share_http(): never
{
    header('Cache-Control: private, no-store');header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow');header('X-Content-Type-Options: nosniff');
    $token=is_string($_GET['share']??null)?$_GET['share']:'';
    try{$share=library_share_resolve(db(),$token);}catch(InvalidArgumentException $exception){http_response_code(404);library_share_page_start(t('Contenu indisponible'));echo '<h1>'.e(t($exception->getMessage())).'</h1></main></body></html>';exit;}
    $post=($_SERVER['REQUEST_METHOD']??'GET')==='POST';
    if($post)verify_csrf();
    $user=actor();$error=null;
    // A copy link is an explicit content-transfer capability, including attachments.
    if(!$post&&$share['mode']==='copy'&&($_GET['download']??'')==='1')send_json_download(export_page_document(db(),(int)$share['page_id'],(int)$share['owner_id'],APR_PUBLIC_ROOT),'liike-page-'.$share['page_id'].'.json');
    if($post&&$share['mode']!=='external'){
        try{
            if(!$user||$user['role']!=='teacher')throw new InvalidArgumentException('Un compte enseignant est nécessaire.');
            if(($_POST['action']??'')!=='accept_library_share')throw new InvalidArgumentException('Partage non autorisé.');
            $courseId=filter_var($_POST['course_id']??0,FILTER_VALIDATE_INT);
            if($courseId===false||$courseId<0)throw new InvalidArgumentException('Parcours introuvable.');
            $pageId=library_share_accept(db(),$token,(int)$user['id'],$courseId);
            unset($_SESSION['pending_library_share']);flash('Contenu ajouté à votre bibliothèque.');
            if($courseId)redirect('pathway',['course'=>$courseId]);
            redirect('page-edit',['id'=>$pageId]);
        }catch(InvalidArgumentException|TransferException $exception){$error=$exception->getMessage();http_response_code(400);}
    }
    library_share_page_start($share['title']);
    ?><h1><?=e($share['title'])?></h1><p><?=e($share['summary'])?></p><p class="text-secondary"><?=e(t('Partagé par :name',['name'=>$share['owner_name']]))?> · <?=(int)$share['estimated_minutes']?> min</p>
    <div class="alert alert-info"><?=e(t(library_share_description($share['mode'])))?></div>
    <?php if($error): ?><p class="alert alert-danger"><?=e(t($error))?></p><?php endif; ?>
    <?php if($share['mode']==='external'):
        $blocks=all('SELECT * FROM page_blocks WHERE page_id=? ORDER BY position,id',[$share['page_id']]);
        foreach($blocks as $block): ?><section class="content-block type-<?=e($block['type'])?>"><?php
            if($block['type']==='markdown')echo Qcm::renderExternal($block['body'],(int)$block['id'],$post?$_POST:[]);
            elseif($block['type']==='image')echo '<figure><img src="'.e($block['body']).'" alt="'.e($block['image_alt']??$block['caption']).'"><figcaption>'.e($block['caption']).'</figcaption></figure>';
            elseif($block['type']==='file')echo '<a class="file-card" href="'.e($block['body']).'" download>'.e($block['caption']?:t('Télécharger le fichier')).'</a>';
            elseif($block['type']==='submission')echo '<h2>'.e($block['caption']?:t('Travail à rendre')).'</h2>'.Markdown::render($block['body']).'<p class="text-secondary">'.e(t('La remise de travaux nécessite une inscription dans un parcours.')).'</p>';
            else echo ContentBlock::kind($block)==='media'?ContentBlock::renderMedia($block):Embed::render($block['body'],$block['caption'],$block['embed_height']??null);
        ?></section><?php endforeach;
    else:
        if($user&&$user['role']==='teacher'):
            $courses=all('SELECT c.id,c.title FROM courses c WHERE c.archived=0 AND (c.teacher_id=? OR EXISTS(SELECT 1 FROM course_teachers ct WHERE ct.course_id=c.id AND ct.teacher_id=?)) ORDER BY c.title',[$user['id'],$user['id']]);
            ?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="accept_library_share"><label class="field"><span><?=e(t('Destination'))?></span><select class="form-select" name="course_id"><option value="0"><?=e(t('Ma bibliothèque uniquement'))?></option><?php foreach($courses as $course): ?><option value="<?=(int)$course['id']?>"><?=e($course['title'])?></option><?php endforeach; ?></select></label><p><?=e(t('Une page ajoutée au parcours commence en consultation simple. Vous pouvez ensuite régler son type et ses échéances.'))?></p><button class="button primary btn btn-dark"><?=e(t('Récupérer ce contenu'))?></button></form><?php
        elseif(!$user):
            $_SESSION['pending_library_share']=$token;
            ?><p><a class="button primary btn btn-dark" href="<?=e(route('login'))?>"><?=e(t('Se connecter pour récupérer ce contenu'))?></a></p><?php
        else: ?><p><?=e(t('Un compte enseignant est nécessaire.'))?></p><?php endif;
        if($share['mode']==='copy'): ?><p class="mt-3"><a class="button secondary btn btn-outline-secondary" href="<?=e(route('shared-content',['share'=>$token,'download'=>1]))?>"><?=e(t('Télécharger la page JSON'))?></a></p><?php endif;
    endif; ?></main></body></html><?php exit;
}
