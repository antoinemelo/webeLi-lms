<?php
declare(strict_types=1);

function render_sharing_dot(string $kind,string $label): void
{
    ?><span class="sharing-dot sharing-dot-<?=e($kind)?>" role="img" aria-label="<?=e($label)?>" title="<?=e($label)?>"></span><?php
}

function render_pathway_sharing(array $sharing): void
{
    if($sharing['incoming'])render_sharing_dot('incoming',t('Contenu partagé de :name',['name'=>$sharing['owner_name']]));
    elseif($sharing['shared_allowed'])render_sharing_dot('outgoing',t('Contenu partagé'));
    if($sharing['sharing_stopped']){ ?><span class="pathway-share-stopped"><?=e(t('Partage arrêté par le propriétaire'))?></span><?php }
}

function render_library_sharing_marks(array $sharing): void
{
    if(!$sharing['own'])return;
    ?><span class="library-sharing-marks"><?php
    if($sharing['shared_allowed'])render_sharing_dot('outgoing',t('Contenu partagé'));
    if($sharing['copy_allowed'])render_sharing_dot('copy',t('Copie indépendante autorisée'));
    if($sharing['external_allowed'])render_sharing_dot('external',t('Utilisation externe autorisée'));
    ?></span><?php
}

function render_library_origin_filters(array $pages,array $sharing,array $teacher): void
{
    $owners=[];
    foreach($pages as $page){$info=$sharing[(int)$page['id']];$owners[$info['owner_id']]=$info['owner_name'];}
    unset($owners[(int)$teacher['id']]);natcasesort($owners);$owners=[(int)$teacher['id']=>$teacher['name']]+$owners;
    ?><div class="library-multi-filters">
      <div class="dropdown library-multi-filter"><button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"><span><?=e(t('Origine'))?></span><strong data-origin-summary><?=e($teacher['name'])?></strong></button>
        <div class="dropdown-menu library-filter-menu"><button type="button" class="dropdown-item" data-origin-all><?=e(t('Voir tous les contenus'))?></button><div class="dropdown-divider"></div><fieldset><legend class="visually-hidden"><?=e(t('Filtrer par enseignants'))?></legend>
        <?php foreach($owners as $id=>$name): ?><label class="library-filter-choice"><input type="checkbox" data-page-owner value="<?=$id?>" data-label="<?=e($name)?>" <?=$id===(int)$teacher['id']?'checked':''?>> <span><?=e($name)?><?php if($id===(int)$teacher['id']): ?> <small><?=e(t('(moi)'))?></small><?php endif; ?></span></label><?php endforeach; ?></fieldset></div></div>
      <div class="dropdown library-multi-filter"><button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"><span><?=e(t('Partage'))?></span><strong data-sharing-summary><?=e(t('Tous les modes'))?></strong></button>
        <div class="dropdown-menu library-filter-menu"><button type="button" class="dropdown-item" data-sharing-all><?=e(t('Tous les modes'))?></button><div class="dropdown-divider"></div><fieldset><legend class="visually-hidden"><?=e(t('Filtrer par partage'))?></legend>
        <?php foreach(['shared'=>'Mes contenus partagés','copy'=>'Copies indépendantes effectuées','external'=>'Utilisations externes autorisées'] as $mode=>$label): ?><label class="library-filter-choice"><input type="checkbox" data-page-sharing value="<?=$mode?>" data-label="<?=e(t($label))?>"> <span><?=e(t($label))?></span></label><?php endforeach; ?></fieldset></div></div>
    </div><?php
}

function render_library_share_button(array $page,string $class='button secondary',string $label='Partager ce contenu'): void
{
    if((int)$page['owner_id']!==(int)actor()['id'])return;
    ?><button class="<?=e($class)?> share-content-button" type="button" data-bs-toggle="modal" data-bs-target="#page-share-<?=(int)$page['id']?>" aria-label="<?=e(t($label))?>" title="<?=e(t($label))?>"><i class="bi bi-share" aria-hidden="true"></i><?php if($class!=='pathway-row-action'): ?><span><?=e(t($label))?></span><?php endif; ?></button><?php
}

function render_library_share_panel(array $page,int $courseId=0,string $returnView='page-edit'): void
{
    if((int)$page['owner_id']!==(int)actor()['id'])return;
    $settings=library_share_settings(db(),(int)$page['id']);
    ?><section class="library-sharing" id="library-sharing-<?=(int)$page['id']?>"><p><?=e(t('Un seul lien. Vous choisissez les modes autorisés ; votre collègue choisit comment utiliser le contenu.'))?></p>
    <div data-library-share-link <?=$settings['enabled']&&$settings['token']?'':'hidden'?>><label class="field"><span><?=e(t('Lien à partager'))?></span><input class="form-control" type="url" readonly value="<?=e($settings['token']?library_share_url($settings['token']):'')?>" onclick="this.select()"></label><p><a href="<?=e(route('shared-content',['share'=>$settings['token']]))?>" target="_blank" rel="noopener noreferrer"><?=e(t('Ouvrir le lien'))?> ↗</a></p></div>
    <form method="post" data-library-share-form data-saving-message="<?=e(t('Enregistrement en cours…'))?>" data-error-message="<?=e(t('Enregistrement impossible. Rechargez la page pour vérifier le partage.'))?>"><?=csrf_field()?><input type="hidden" name="action" value="save_library_share"><input type="hidden" name="page_id" value="<?=(int)$page['id']?>"><input type="hidden" name="share_revision" value="<?=(int)$settings['revision']?>"><input type="hidden" name="return_course" value="<?=$courseId?>"><input type="hidden" name="return_item" value="<?=(int)($_GET['item']??0)?>"><input type="hidden" name="return_view" value="<?=e($returnView)?>">
      <fieldset><legend class="fs-5"><?=e(t('Modes autorisés'))?></legend><?php foreach(library_share_modes() as $mode=>$label): ?><label class="border rounded p-3 d-block mb-2"><span class="check plain"><input type="checkbox" name="modes[]" value="<?=e($mode)?>" <?=$settings['enabled']&&$settings['allow_'.$mode]?'checked':''?>> <b><?=e(t($label))?></b></span><span class="d-block mt-2"><?=e(t(library_share_description($mode)))?></span></label><?php endforeach; ?></fieldset>
      <p data-library-share-hint hidden><?=e(t('Cochez un mode pour partager. Décochez les trois modes pour arrêter. Les changements sont enregistrés automatiquement.'))?></p>
      <?php if($page['status']!=='ready'): ?><p><?=e(t('Enregistrez la page avec le statut Prêt avant de la partager.'))?></p><?php endif; ?>
      <p class="muted-copy"><?=e(t('Le lien reste identique après modification des modes ou réactivation. Les nouvelles utilisations suivent vos réglages ; les copies et les contenus déjà récupérés sont conservés. Désactiver l’utilisation externe ferme aussi les séances anonymes en cours.'))?></p>
      <p class="text-secondary" role="status" data-library-share-status><?=e(t($settings['enabled']?'Partage activé.':'Partage désactivé.'))?></p>
      <div class="d-flex flex-wrap gap-2"><button class="button primary btn btn-dark" type="submit" data-library-share-save><?=e(t('Enregistrer le partage'))?></button><button class="btn btn-outline-danger" type="submit" name="stop_sharing" value="1" <?=$settings['enabled']?'':'hidden'?>><?=e(t('Arrêter le partage'))?></button><button class="btn btn-outline-secondary" type="button" data-library-share-reload hidden><?=e(t('Recharger'))?></button></div>
    </form></section><?php
}

function render_library_share_modal(array $page,int $courseId=0,string $returnView='page-edit'): void
{
    if((int)$page['owner_id']!==(int)actor()['id'])return;
    ?><div class="modal fade" id="page-share-<?=(int)$page['id']?>" tabindex="-1" aria-labelledby="page-share-title-<?=(int)$page['id']?>" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-4 share-content-button" id="page-share-title-<?=(int)$page['id']?>"><i class="bi bi-share" aria-hidden="true"></i><span><?=e(t('Partager ce contenu'))?></span></h2><button class="btn-close" data-bs-dismiss="modal" aria-label="<?=e(t('Fermer'))?>"></button></div><div class="modal-body"><h3 class="fs-5"><?=e($page['title'])?></h3><?php render_library_share_panel($page,$courseId,$returnView); ?></div></div></div></div><?php
}

function library_share_page_start(string $title): void
{
    ?><!doctype html><html lang="<?=e(current_language())?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title><?=e($title)?> · liike</title><link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css"><link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css"><link rel="stylesheet" href="assets/app.css?v=184"><link rel="stylesheet" href="assets/qcm-modes.css?v=4"><link rel="stylesheet" href="assets/qcm-adaptive.css?v=7"></head><body data-csrf="<?=e(csrf_token())?>"><main class="container py-4" style="max-width:960px"><p class="eyebrow">liike · <?=e(t(actor()&&actor()['role']==='teacher'?'Contenu partagé':'Utilisation anonyme'))?></p><?php
}

function library_share_http(): never
{
    header('Cache-Control: private, no-store');header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow');header('X-Content-Type-Options: nosniff');
    $token=is_string($_GET['share']??null)?$_GET['share']:'';
    $post=($_SERVER['REQUEST_METHOD']??'GET')==='POST';
    $mode=is_string($_GET['mode']??null)?$_GET['mode']:'';
    try{
        $share=library_share_resolve(db(),$token);
        if($mode!=='')library_share_require_mode($share,$mode);
    }catch(InvalidArgumentException $exception){http_response_code(404);library_share_page_start(t('Contenu indisponible'));echo '<h1>'.e(t($exception->getMessage())).'</h1></main></body></html>';exit;}
    if($post)verify_csrf();
    $user=actor();$error=null;$isTeacher=$user&&$user['role']==='teacher'&&$user['account_status']==='active';
    if(!$user){$_SESSION['pending_library_share']=$token;$_SESSION['pending_library_mode']=$mode;}
    if(!$isTeacher&&in_array($mode,['shared','copy'],true)){
        http_response_code($user?403:401);library_share_page_start(t('Connexion enseignant nécessaire'));
        echo '<h1>'.e(t('Connexion enseignant nécessaire')).'</h1><p>'.e(t('Connectez-vous avec un compte enseignant pour accéder à cette utilisation.')).'</p>';
        if(!$user)echo '<a class="btn btn-dark" href="'.e(route('login')).'">'.e(t('Se connecter')).'</a>';
        echo '</main></body></html>';exit;
    }
    $visibleModes=library_share_visible_modes($share,$user);
    if(!$post&&$mode==='copy'&&($_GET['download']??'')==='1')send_json_download(export_page_document(db(),(int)$share['page_id'],(int)$share['owner_id'],APR_PUBLIC_ROOT),'liike-page-'.$share['page_id'].'.json');
    if($post&&($mode!=='external'||($_POST['action']??'')==='create_guest_class')){
        try{
            if($mode==='external'&&($_POST['action']??'')==='create_guest_class'){
                if($user&&!$isTeacher)throw new InvalidArgumentException('Partage non autorisé.');
                $room=QcmGuest::create(db(),$token,$isTeacher?(int)$user['id']:null,(int)($_POST['block']??0),(string)($_POST['key']??''),$isTeacher?'':qcm_guest_host_secret(true));
                redirect('guest-class',['room'=>$room]);
            }
            if(!$isTeacher)throw new InvalidArgumentException('Un compte enseignant est nécessaire.');
            if(($_POST['action']??'')!=='accept_library_share')throw new InvalidArgumentException('Partage non autorisé.');
            $courseId=filter_var($_POST['course_id']??0,FILTER_VALIDATE_INT);
            if($courseId===false||$courseId<0)throw new InvalidArgumentException('Parcours introuvable.');
            $pageId=library_share_accept(db(),$token,(int)$user['id'],$courseId,$mode);
            unset($_SESSION['pending_library_share'],$_SESSION['pending_library_mode']);flash('Contenu ajouté à votre bibliothèque.');
            if($courseId)redirect('pathway',['course'=>$courseId]);
            redirect('page-edit',['id'=>$pageId]);
        }catch(InvalidArgumentException|TransferException|DomainException $exception){$error=$exception->getMessage();http_response_code(400);}
    }
    library_share_page_start($share['title']);
    ?><h1><?=e($share['title'])?></h1><p><?=e($share['summary'])?></p><p class="text-secondary"><?=e(t('Partagé par :name',['name'=>$share['owner_name']]))?> · <?=(int)$share['estimated_minutes']?> min</p>
    <?php if($error): ?><p class="alert alert-danger"><?=e(t($error))?></p><?php endif; ?>
    <?php if($mode===''): ?><h2><?=e(t($isTeacher?'Comment souhaitez-vous utiliser ce contenu ?':'Utilisation anonyme'))?></h2><div class="d-grid gap-3"><?php foreach($visibleModes as $choice): ?><article class="border rounded p-3"><h3><?=e(t(library_share_modes()[$choice]))?></h3><p><?=e(t(library_share_description($choice)))?></p><a class="button primary btn btn-dark" href="<?=e(route('shared-content',['share'=>$token,'mode'=>$choice]))?>"><?=e(t($isTeacher?'Choisir ce mode':'Compris'))?></a></article><?php endforeach; ?></div><?php
        if(!$visibleModes): echo '<p>'.e(t('Ce contenu n’est pas disponible en utilisation anonyme.')).'</p>'; endif;
        if(!$user&&array_intersect(['shared','copy'],$share['modes'])): echo '<p class="mt-3"><a href="'.e(route('login')).'">'.e(t('Se connecter pour récupérer ce contenu')).'</a></p>'; endif;
    else: if($isTeacher): ?><p><a href="<?=e(route('shared-content',['share'=>$token]))?>">← <?=e(t('Choisir un autre mode'))?></a></p><?php endif; ?><div class="alert alert-info"><?=e(t($mode==='external'?'Les réponses sont temporaires, restent anonymes et ne deviennent pas des notes.':library_share_description($mode)))?></div><?php
        if(!$user){$_SESSION['pending_library_share']=$token;$_SESSION['pending_library_mode']=$mode;}
        if($mode==='external'){
            $blocks=all('SELECT * FROM page_blocks WHERE page_id=? ORDER BY position,id',[$share['page_id']]);
            $adaptiveRendered=false;
            foreach($blocks as $block){
                echo '<section class="content-block type-'.e($block['type']).'">';
                if($block['type']==='markdown'){
                    foreach(Qcm::sections($block['body']) as $section){
                        if($section['type']==='markdown'){echo Markdown::render($section['source']);continue;}
                        if(in_array($section['type'],['adaptive','reference'],true)){
                            if(!$adaptiveRendered){
                                render_adaptive_qcm(['id'=>$share['page_id'],'estimated_minutes'=>$share['estimated_minutes'],'is_evaluation'=>0],$blocks,false,$token);
                                $adaptiveRendered=true;
                            }
                            continue;
                        }
                        if(!empty($section['valid'])&&$section['mode']==='classe'){
                            echo '<section class="qcm-card p-3"><h2>'.e(t('QCM en classe')).'</h2>';
                            if(!$user||$isTeacher){
                                $key=Qcm::key((int)$block['id'],(int)$section['index'],$section['source'],'classe');
                                echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="create_guest_class"><input type="hidden" name="block" value="'.(int)$block['id'].'"><input type="hidden" name="key" value="'.e($key).'"><button class="button primary btn btn-dark">'.e(t('Animer une séance anonyme')).'</button></form>';
                            }else echo '<p>'.e(t('Votre enseignant vous donnera le lien ou le QR code de la séance.')).'</p>';
                            echo '</section>';
                        }else{
                            // Keep the original section index so multiple quizzes in one block remain independent.
                            echo Qcm::renderExternal($block['body'],(int)$block['id'],$post?$_POST:[],(int)$section['index']);
                        }
                    }
                }elseif($block['type']==='image')echo '<figure><img src="'.e($block['body']).'" alt="'.e($block['image_alt']??$block['caption']).'"><figcaption>'.e($block['caption']).'</figcaption></figure>';
                elseif($block['type']==='file')echo '<a class="file-card" href="'.e($block['body']).'" download>'.e($block['caption']?:t('Télécharger le fichier')).'</a>';
                elseif($block['type']==='submission')echo '<h2>'.e($block['caption']?:t('Travail à rendre')).'</h2>'.Markdown::render($block['body']).'<p class="text-secondary">'.e(t('La remise de travaux nécessite une inscription dans un parcours.')).'</p>';
                else echo ContentBlock::kind($block)==='media'?ContentBlock::renderMedia($block):Embed::render($block['body'],$block['caption'],$block['embed_height']??null);
                echo '</section>';
            }
            if($adaptiveRendered)echo '<script src="assets/qcm-adaptive.js?v=10"></script>';
        }else{
            if($user&&$user['role']==='teacher'){
                $courses=all('SELECT c.id,c.title FROM courses c WHERE c.archived=0 AND (c.teacher_id=? OR EXISTS(SELECT 1 FROM course_teachers ct WHERE ct.course_id=c.id AND ct.teacher_id=?)) ORDER BY c.title',[$user['id'],$user['id']]);
                ?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="accept_library_share"><label class="field"><span><?=e(t('Destination'))?></span><select class="form-select" name="course_id"><option value="0"><?=e(t('Ma bibliothèque uniquement'))?></option><?php foreach($courses as $course): ?><option value="<?=(int)$course['id']?>"><?=e($course['title'])?></option><?php endforeach; ?></select></label><p><?=e(t('Une page ajoutée au parcours commence en consultation simple. Vous pouvez ensuite régler son type et ses échéances.'))?></p><button class="button primary btn btn-dark"><?=e(t('Récupérer ce contenu'))?></button></form><?php
            }elseif(!$user){echo '<p><a class="button primary btn btn-dark" href="'.e(route('login')).'">'.e(t('Se connecter pour récupérer ce contenu')).'</a></p>';}
            else echo '<p>'.e(t('Un compte enseignant est nécessaire.')).'</p>';
            if($mode==='copy')echo '<p class="mt-3"><a class="button secondary btn btn-outline-secondary" href="'.e(route('shared-content',['share'=>$token,'mode'=>'copy','download'=>1])).'">'.e(t('Télécharger la page JSON')).'</a></p>';
        }
    endif; ?></main></body></html><?php exit;
}
