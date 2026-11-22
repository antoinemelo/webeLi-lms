<?php
declare(strict_types=1);

function qcm_guest_participant(string $room): string
{
    return (string)($_SESSION['qcm_guest_participants'][$room]??'');
}

/** This secret stays in the server session; it is never part of a room URL or form. */
function qcm_guest_host_secret(bool $create=false): string
{
    if($create&&empty($_SESSION['qcm_guest_host_secret'])){
        session_regenerate_id(true);
        $_SESSION['qcm_guest_host_secret']=bin2hex(random_bytes(32));
    }
    return (string)($_SESSION['qcm_guest_host_secret']??'');
}

function qcm_guest_http(bool $write=false): never
{
    header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: private, no-store');header('Referrer-Policy: no-referrer');
    $input=$write?$_POST:$_GET;$room=is_string($input['room']??null)?$input['room']:'';$user=actor();$participant=qcm_guest_participant($room);$hostSecret=qcm_guest_host_secret();
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    try{
        $result=$write?QcmGuest::command(db(),$room,$user,$participant,$input,$hostSecret):QcmGuest::state(db(),$room,$user,$participant,$hostSecret);
        echo json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
    }catch(DomainException $exception){http_response_code(in_array($exception->getCode(),[403,404,409,422],true)?$exception->getCode():409);echo json_encode(['status'=>'error','message'=>t($exception->getMessage())],JSON_THROW_ON_ERROR);}
    catch(Throwable $exception){error_log('QCM anonyme: '.$exception->getMessage());http_response_code(503);echo json_encode(['status'=>'error','message'=>t('Connexion interrompue. Réessayez dans un instant.')]);}
    exit;
}

function render_qcm_guest_page(): never
{
    header('Cache-Control: private, no-store');header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow');
    $room=is_string($_GET['room']??null)?$_GET['room']:'';$user=actor();$hostSecret=qcm_guest_host_secret();
    try{
        $session=QcmGuest::context(db(),$room);$teacher=QcmGuest::isHost($session,$user,$hostSecret);$participant=qcm_guest_participant($room);
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            verify_csrf();
            if(($_POST['action']??'')!=='guest_join'||$teacher)throw new DomainException('Cette séance n’est pas accessible.',403);
            $participant=$participant?:bin2hex(random_bytes(32));QcmGuest::join(db(),$room,$participant);
            $_SESSION['qcm_guest_participants'][$room]=$participant;
            $_SESSION['qcm_guest_participants']=array_slice($_SESSION['qcm_guest_participants'],-20,null,true);
            redirect('guest-class',['room'=>$room]);
        }
        if(!$teacher&&!$participant){
            library_share_page_start($session['title']);
            ?><h1><?=e($session['title'])?></h1><p><?=e(t('Aucun nom, courriel ou compte n’est demandé. Un identifiant aléatoire sert uniquement à vos réponses dans cette séance.'))?></p><?php if($session['phase']==='finished'): ?><p><?=e(t('Séance terminée'))?></p><?php else: ?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="guest_join"><button class="button primary btn btn-dark"><?=e(t('Rejoindre anonymement'))?></button></form><?php endif; ?></main></body></html><?php exit;
        }
        QcmGuest::state(db(),$room,$user,$participant,$hostSecret);
    }catch(DomainException $exception){http_response_code($exception->getCode()?:404);library_share_page_start(t('Contenu indisponible'));echo '<h1>'.e(t($exception->getMessage())).'</h1></main></body></html>';exit;}
    $projection=$teacher&&($_GET['projection']??'')==='1';$strings=qcm_class_labels();
    $strings['join']=t('Scannez le QR code ou ouvrez le lien pour rejoindre anonymement.');
    $strings['count']=t('Réponses : :count / :total participants');$strings['login']=t('Rejoignez la séance pour continuer.');
    $joinUrl=app_public_base_url().'/'.route('guest-class',['room'=>$room]);
    ?><!doctype html><html lang="<?=e(current_language())?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title><?=e(t('QCM en classe'))?> · <?=e($session['title'])?></title><link rel="stylesheet" href="assets/app.css?v=171"><link rel="stylesheet" href="assets/qcm-modes.css?v=2"></head>
    <body class="qcm-class-page<?=$projection?' qcm-projection':''?>" data-csrf="<?=e(csrf_token())?>"><main class="qcm-live" data-qcm-live data-guest="1" data-teacher="<?=$teacher?'1':'0'?>" data-projection="<?=$projection?'1':'0'?>" data-state-view="guest-class-state" data-command-action="guest_class_command" data-context="<?=e(json_encode(['room'=>$room],JSON_THROW_ON_ERROR))?>" data-labels="<?=e(json_encode($strings,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))?>">
    <header class="qcm-live-header"><?php if($teacher): ?><a class="back" href="<?=e(route('shared-content',['share'=>$session['share_token'],'mode'=>'external']))?>">← <?=e(t('Retour au contenu'))?></a><?php else: ?><span><?=e(t('Participation anonyme'))?></span><?php endif; ?><div class="qcm-live-tools"><?php if($teacher&&!$projection): ?><a class="button secondary" href="<?=e(route('guest-class',['room'=>$room,'projection'=>1]))?>" target="_blank" rel="noopener"><?=e(t('Ouvrir la projection'))?></a><?php endif; ?><button type="button" class="button secondary" data-qcm-fullscreen><?=e(t('Plein écran'))?></button></div></header>
    <?php if($teacher): ?>
    <p class="eyebrow"><?=e(t('QCM en classe'))?></p>
    <details class="guest-invitation" open>
      <summary class="guest-activity-title"><span><?=e($session['title'])?></span></summary>
      <section class="guest-invite"><div class="guest-qr" data-guest-qr data-url="<?=e($joinUrl)?>" role="img" aria-label="<?=e(t('QR code pour rejoindre la séance'))?>"></div><div class="guest-invite-copy"><h2><?=e(t('Inviter les apprenants'))?></h2><p><?=e(t('Scannez le QR code ou ouvrez le lien pour rejoindre anonymement.'))?></p><input aria-label="<?=e(t('Lien de la séance'))?>" readonly value="<?=e($joinUrl)?>" onclick="this.select()"><p><a href="<?=e($joinUrl)?>" target="_blank" rel="noopener"><?=e(t('Ouvrir le lien'))?></a></p><small><?=e(t('Lien valable 24 heures. Les réponses anonymes sont ensuite supprimées automatiquement.'))?></small></div></section>
    </details>
    <?php else: ?><p class="eyebrow"><?=e(t('QCM en classe'))?> · <?=e($session['title'])?></p><?php endif; ?>
    <p data-qcm-screen-status role="status"></p><p data-qcm-live-error role="alert" hidden></p><div data-qcm-live-content><p><?=e(t('Chargement de la séance…'))?></p></div><p data-qcm-vote-status role="status"></p><div class="qcm-live-controls" data-qcm-live-controls></div><noscript><?=e(t('Activez JavaScript pour participer à la séance.'))?></noscript>
    </main><?php if($teacher): ?><script src="assets/vendor/qrcode/qrcode.min.js"></script><script src="assets/guest-qr.js?v=1"></script><?php endif; ?><script src="assets/qcm-modes.js?v=2"></script></body></html><?php exit;
}
