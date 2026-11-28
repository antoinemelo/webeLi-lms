<?php
declare(strict_types=1);
require_once __DIR__.'/SecurityActions.php';

function render_student_code(): void
{
    header('Cache-Control: private, no-store');
    $flow=$_SESSION['student_email_flow']??null;
    if(!is_array($flow))redirect(actor()?'profile':'login');
    if($flow['purpose']!=='login'){
        $user=require_actor();if($user['role']!=='student'||(int)$user['id']!==(int)$flow['user_id'])redirect('profile');
    }
    $flash=$_SESSION['flash']??null;unset($_SESSION['flash']);
    $address=(string)($flow['recipient']??'');
    if($address!==''){$parts=explode('@',$address,2);$address=mb_substr($parts[0],0,1).'•••@'.($parts[1]??'');}
    $title=match($flow['purpose']){'enable'=>'Activer la protection','disable'=>'Désactiver la protection','email_old'=>'Confirmer l’adresse actuelle','email_new'=>'Confirmer la nouvelle adresse',default=>'Vérifier la connexion'};
    ?><!doctype html><html lang="<?=e(current_language())?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#29273b"><meta name="apple-mobile-web-app-capable" content="yes"><title><?=e(t($title))?> · liike</title><link rel="manifest" href="manifest.webmanifest"><link rel="icon" href="assets/icon.svg"><link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css"><link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css"><link rel="stylesheet" href="assets/app.css?v=185"></head>
    <body class="login-page"><main class="container min-vh-100 d-flex align-items-center justify-content-center py-4"><section class="settings-card p-4 p-md-5 security-code-card">
    <a class="brand mb-4" href="."><span class="brand-mark">ii</span><span>liike</span></a><p class="eyebrow"><?=e(t('Code par courriel'))?></p><h1 class="h3"><?=e(t($title))?></h1>
    <?php if($flash): ?><div class="alert <?=$flash['kind']==='error'?'alert-danger':'alert-success'?>" role="status"><?=e($flash['message'])?></div><?php endif; ?>
    <?php if(!empty($flow['id'])): ?><p><?=e(t('Saisissez le code à 4 chiffres envoyé à :email.',['email'=>$address]))?></p><p class="text-secondary small"><?=e(t('Il est valable 10 minutes. L’envoi peut prendre quelques instants ; pensez aussi aux courriers indésirables.'))?></p>
    <form method="post"><?=csrf_field()?><input type="hidden" name="action" value="student_email_verify"><label for="security-code" class="form-label fw-semibold"><?=e(t('Code de vérification'))?></label><input id="security-code" name="code" class="form-control form-control-lg security-code-input" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="one-time-code" required autofocus><button class="btn btn-dark btn-lg w-100 mt-3"><?=e(t('Confirmer'))?></button></form>
    <?php else: ?><p><?=e(t('Demandez un code pour poursuivre la vérification.'))?></p><?php endif; ?>
    <form method="post" class="mt-3"><?=csrf_field()?><input type="hidden" name="action" value="student_email_resend"><button class="btn btn-outline-secondary w-100"><?=e(t(empty($flow['id'])?'Envoyer un code':'Renvoyer un code'))?></button><p class="small text-secondary mt-2 mb-0"><?=e(t('Patientez au moins une minute entre deux envois.'))?></p></form>
    <form method="post" class="mt-3 text-center"><?=csrf_field()?><input type="hidden" name="action" value="student_email_cancel"><button class="btn btn-link text-secondary"><?=e(t('Annuler'))?></button></form>
    </section></main></body></html><?php
}

function render_student_security_profile(array $user): void
{
    if($user['role']!=='student')return;
    ?><section class="settings-card p-4 mt-4"><h2><i class="bi bi-shield-lock me-2" aria-hidden="true"></i><?=e(t('Protection de la connexion'))?></h2><p class="text-secondary"><?=e(t('Une fois activée, un code à 4 chiffres est envoyé par courriel à chaque nouvelle connexion.'))?></p>
    <form method="post"><?=csrf_field()?><input type="hidden" name="action" value="student_email_security_begin"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="email-login-enabled" name="email_login_enabled" value="1" <?=!empty($user['email_login_enabled'])?'checked':''?>><label class="form-check-label fw-semibold" for="email-login-enabled"><?=e(t('Protéger mes connexions'))?></label></div><p class="small text-secondary mt-3"><?=e(t("L’activation et la désactivation sont confirmées par l'envoi d'un code."))?></p><button class="btn btn-outline-primary"><i class="bi bi-check2 me-2" aria-hidden="true"></i><?=e(t('Enregistrer'))?></button></form></section><?php
}

function render_student_login_block(array $student,string $returnView='students'): void
{
    $teacher=require_actor();if(!StudentEmailLogin::canBlock($teacher,$student))return;
    $blocked=!empty($student['login_blocked_at']);
    ?><form method="post" class="m-0" onsubmit="return confirm('<?=e(t($blocked?'Autoriser à nouveau cet apprenant à se connecter ?':'Bloquer la connexion à ce compte et fermer ses sessions ? Ses inscriptions et résultats seront conservés.'))?>')"><?=csrf_field()?><input type="hidden" name="action" value="set_student_login_block"><input type="hidden" name="student_id" value="<?=(int)$student['id']?>"><input type="hidden" name="blocked" value="<?=$blocked?'0':'1'?>"><input type="hidden" name="return_view" value="<?=e($returnView)?>"><button class="btn btn-sm <?=$blocked?'btn-outline-primary':'btn-outline-danger'?>"><i class="bi <?=$blocked?'bi-unlock':'bi-lock'?> me-2" aria-hidden="true"></i><?=e(t($blocked?'Débloquer l’accès':'Bloquer l’accès'))?></button></form><?php
}

function render_student_attempts_reset(array $student): void
{
    $admin=require_actor();
    if($admin['role']!=='teacher'||(int)($admin['is_superadmin']??0)!==1||$student['role']!=='student'||$student['account_status']!=='active')return;
    $blocked=!empty($student['login_blocked_at']);
    ?><form method="post" class="m-0" onsubmit="return confirm('<?=e(t('Réinitialiser les demandes de code et les essais de connexion de cet apprenant ? Ses anciens codes seront invalidés et il devra en demander un nouveau.'))?>')"><?=csrf_field()?><input type="hidden" name="action" value="reset_student_login_attempts"><input type="hidden" name="student_id" value="<?=(int)$student['id']?>"><button class="btn btn-sm btn-outline-primary" <?=$blocked?'disabled':''?> title="<?=e(t($blocked?'Débloquez d’abord l’accès au compte.':'Lever les limites temporaires de demandes de code et d’essais incorrects pour ce compte.'))?>"><i class="bi bi-arrow-counterclockwise me-2" aria-hidden="true"></i><?=e(t('Réinitialiser les tentatives'))?></button></form><?php
}

function render_smtp_admin(): void
{
    try{$config=SmtpConfig::read(dirname(__DIR__));}catch(Throwable){echo '<p class="alert alert-danger">'.e(t('Configuration SMTP illisible. Vérifiez le fichier de configuration sur le serveur.')).'</p>';return;}
    ?><section class="settings-card p-4 my-4"><h2><i class="bi bi-envelope me-2" aria-hidden="true"></i><?=e(t('Serveur de messagerie'))?></h2><p class="text-secondary"><?=e(t('SMTP est facultatif. S’il est désactivé, l’envoi utilise la messagerie habituelle du serveur.'))?></p>
    <form method="post" autocomplete="off"><?=csrf_field()?><input type="hidden" name="action" value="save_smtp"><input type="hidden" name="smtp_revision" value="<?=e($config['revision'])?>">
    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="smtp-enabled" name="smtp_enabled" value="1" <?=$config['enabled']?'checked':''?>><label class="form-check-label fw-semibold" for="smtp-enabled"><?=e(t('Utiliser un serveur SMTP'))?></label></div>
    <div class="row g-3"><div class="col-md-6"><label class="field"><span><?=e(t('Serveur SMTP'))?></span><input name="smtp_host" maxlength="253" value="<?=e($config['host'])?>" placeholder="smtp.exemple.ch" autocomplete="off"></label></div><div class="col-5 col-md-2"><label class="field"><span><?=e(t('Port'))?></span><input type="number" min="1" max="65535" name="smtp_port" value="<?=(int)$config['port']?>" required></label></div><div class="col-7 col-md-4"><label class="field"><span><?=e(t('Sécurité'))?></span><select name="smtp_encryption"><option value="starttls" <?=$config['encryption']==='starttls'?'selected':''?>>STARTTLS (587)</option><option value="smtps" <?=$config['encryption']==='smtps'?'selected':''?>>TLS (465)</option></select></label></div>
    <div class="col-md-6"><label class="field"><span><?=e(t('Identifiant SMTP'))?></span><input name="smtp_username" maxlength="254" value="<?=e($config['username'])?>" autocomplete="off"></label></div><div class="col-md-6"><label class="field"><span><?=e(t('Mot de passe SMTP'))?></span><input type="password" name="smtp_password" maxlength="1024" autocomplete="new-password" placeholder="<?=e(t($config['password']!==''?'Laisser vide pour le conserver':'Mot de passe'))?>"></label></div>
    <div class="col-md-6"><label class="field"><span><?=e(t('Adresse d’expéditeur'))?></span><input type="email" name="smtp_from_address" maxlength="254" value="<?=e($config['from_address'])?>"></label></div><div class="col-md-6"><label class="field"><span><?=e(t('Nom d’expéditeur'))?></span><input name="smtp_from_name" maxlength="120" value="<?=e($config['from_name'])?>"></label></div></div>
    <button class="btn btn-primary"><i class="bi bi-check2 me-2" aria-hidden="true"></i><?=e(t('Enregistrer'))?></button></form>
    <?php if($config['enabled']): ?><form method="post" class="mt-3"><?=csrf_field()?><input type="hidden" name="action" value="test_smtp"><button class="btn btn-outline-secondary"><i class="bi bi-send me-2" aria-hidden="true"></i><?=e(t('Envoyer un test à mon adresse'))?></button><p class="small text-secondary mt-2 mb-0"><?=e(t('Le test utilise les réglages enregistrés.'))?></p></form><?php endif; ?></section><?php
}
