<?php

declare(strict_types=1);

$applicationRoot = is_file(__DIR__ . '/app/bootstrap.php') ? __DIR__ : dirname(__DIR__);

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
