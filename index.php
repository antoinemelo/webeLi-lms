<?php

declare(strict_types=1);

$applicationRoot = is_file(__DIR__ . '/app/bootstrap.php') ? __DIR__ : dirname(__DIR__);

if (is_file($applicationRoot . '/storage/maintenance.flag')) {
    http_response_code(503);
    header('Retry-After: 30');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>liike · Maintenance</title><body style="font:16px system-ui;max-width:42rem;margin:12vh auto;padding:1.5rem"><h1>Mise à jour en cours</h1><p>liike sera de nouveau disponible dans quelques instants.</p></body></html>';
    exit;
}

require_once $applicationRoot . '/app/bootstrap.php';
require_once $applicationRoot . '/app/actions.php';
require_once $applicationRoot . '/app/views.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['restart_login'])) {
    unset($_SESSION['login_teacher_id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    handle_action((string) ($_POST['action'] ?? ''));
}

$verificationToken=$_GET['v']??((($_GET['view']??'')==='verify')?($_GET['token']??''):null);
if ($verificationToken !== null) {
    verify_registration((string)$verificationToken);
}

if (isset($_GET['r'])) {
    render_password_reset((string)$_GET['r']);
    exit;
}

if (!actor()) {
    $publicView=(string)($_GET['view']??'login');
    if($publicView==='register')render_register();
    elseif($publicView==='recover')render_recover();
    elseif($publicView==='join')render_public_join();
    else render_login();
    exit;
}

$user = require_actor();
$view = (string) ($_GET['view'] ?? ($user['role'] === 'teacher' ? 'teacher' : 'student'));
$allowed = $user['role'] === 'teacher'
    ? ['teacher','student-detail','students','library','page-edit','pathway','outbox','profile']
    : ['student','learn','competencies','rewards','profile','join'];
if ($user['role'] === 'teacher' && (int)($user['is_superadmin'] ?? 0) === 1) $allowed[] = 'admin';
if (!in_array($view, $allowed, true)) {
    $view = $user['role'] === 'teacher' ? 'teacher' : 'student';
}

render_app($view);
