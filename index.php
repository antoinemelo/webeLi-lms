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

if($_SERVER['REQUEST_METHOD']==='GET'&&($_GET['view']??'')==='session-status'){
    $sessionUser=actor();
    http_response_code($sessionUser?200:401);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, private');
    echo json_encode(['authenticated'=>(bool)$sessionUser,'checked_at'=>time(),'csrf'=>$sessionUser?csrf_token():null],JSON_THROW_ON_ERROR);
    exit;
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
if((int)($_GET['announcement']??0)>0)mark_announcement_read_for_user(db(),(int)$_GET['announcement'],(int)$user['id']);
$view = (string) ($_GET['view'] ?? ($user['role'] === 'teacher' ? 'teacher' : 'student'));
if($view==='pdf-document'&&$user['role']==='teacher'){
    try{
        $type=($_GET['type']??'course')==='item'?'item':'course';$id=(int)($_GET['id']??0);
        $document=$type==='item'?pathway_page_pdf_html(db(),$id,(int)$user['id']):course_pdf_html(db(),$id,(int)$user['id']);
        header('Content-Type: text/html; charset=UTF-8');header('Cache-Control: private, no-store');echo $document;
    }catch(Throwable $exception){http_response_code(404);echo '<!doctype html><meta charset="utf-8"><p>'.e(t('Document introuvable.')).'</p>';}
    exit;
}
if($view==='pdf-download'){
    $courseId=0;
    try{
        $type=($_GET['type']??'course')==='item'?'item':'course';$id=(int)($_GET['id']??0);
        if($user['role']==='student'){
            if($type!=='item'||!item_is_visible_to_student(db(),$id,(int)$user['id']))throw new TransferException('Étape introuvable.');
            $context=one('SELECT p.title,pi.course_id FROM pathway_items pi JOIN pages p ON p.id=pi.page_id WHERE pi.id=?',[$id]);
            $courseId=(int)($context['course_id']??0);
            if(!$context)throw new TransferException('Étape introuvable.');
            send_pdf_download(student_pathway_page_pdf_html(db(),$id,(int)$user['id']),'etape-'.mb_strtolower((string)$context['title'],'UTF-8').'.pdf');
        }
        if($user['role']!=='teacher')throw new TransferException('Document introuvable.');
        if($type==='item'){
            $context=one('SELECT p.title,pi.course_id FROM pathway_items pi JOIN pages p ON p.id=pi.page_id WHERE pi.id=?',[$id]);
            $courseId=(int)($context['course_id']??0);
            if(!$context||!teacher_can_access_course(db(),$courseId,(int)$user['id']))throw new TransferException('Étape introuvable.');
            send_pdf_download(pathway_page_pdf_html(db(),$id,(int)$user['id']),'etape-'.mb_strtolower((string)$context['title'],'UTF-8').'.pdf');
        }
        $context=one('SELECT id,title FROM courses WHERE id=?',[$id]);$courseId=(int)($context['id']??0);
        if(!$context||!teacher_can_access_course(db(),$courseId,(int)$user['id']))throw new TransferException('Parcours introuvable.');
        send_pdf_download(course_pdf_html(db(),$id,(int)$user['id']),'parcours-'.mb_strtolower((string)$context['title'],'UTF-8').'.pdf',true);
    }catch(Throwable $exception){
        http_response_code(503);header('Content-Type: text/html; charset=UTF-8');header('Cache-Control: private, no-store');
        $returnRoute=$user['role']==='student'?route('student',$courseId?['course'=>$courseId]:[]):route('pathway',$courseId?['course'=>$courseId]:[]);
        echo '<!doctype html><html lang="'.e(current_language()).'"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e(t('Export PDF indisponible')).'</title><body style="font:16px system-ui;max-width:42rem;margin:12vh auto;padding:1.5rem"><h1>'.e(t('Export PDF indisponible')).'</h1><p>'.e(import_failure_message($exception)).'</p><p><a href="'.e($returnRoute).'">'.e(t('Retour au parcours')).'</a></p></body></html>';
        exit;
    }
}
if($view==='document-download'){
    $courseId=0;
    try{
        if($user['role']!=='teacher')throw new TransferException('Document introuvable.');
        $requestedFormat=(string)($_GET['format']??'markdown');$format=in_array($requestedFormat,['markdown','docx','latex'],true)?$requestedFormat:'markdown';$id=(int)($_GET['id']??0);
        $document=pathway_page_document_export(db(),$id,(int)$user['id']);
        $context=one('SELECT course_id FROM pathway_items WHERE id=?',[$id]);$courseId=(int)($context['course_id']??0);
        $base='etape-'.mb_strtolower($document['title'],'UTF-8');
        if($format==='docx')send_docx_download($document['markdown'],$document['title'],$base.'.docx');
        if($format==='latex')send_latex_download($document['markdown'],$document['title'],$base.'.tex');
        send_markdown_download($document['markdown'],$base.'.md');
    }catch(Throwable $exception){
        http_response_code(404);header('Content-Type: text/html; charset=UTF-8');header('Cache-Control: private, no-store');
        echo '<!doctype html><html lang="'.e(current_language()).'"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e(t('Export indisponible')).'</title><body style="font:16px system-ui;max-width:42rem;margin:12vh auto;padding:1.5rem"><h1>'.e(t('Export indisponible')).'</h1><p>'.e(import_failure_message($exception)).'</p><p><a href="'.e(route('pathway',$courseId?['course'=>$courseId]:[])).'">'.e(t('Retour au parcours')).'</a></p></body></html>';
        exit;
    }
}
$allowed = $user['role'] === 'teacher'
    ? ['teacher','student-detail','students','library','page-edit','pathway','teacher-preview','teacher-preview-page','pdf-preview','outbox','profile']
    : ['student','learn','competencies','announcements','rewards','profile','join'];
if ($user['role'] === 'teacher' && (int)($user['is_superadmin'] ?? 0) === 1) $allowed[] = 'admin';
if (!in_array($view, $allowed, true)) {
    $view = $user['role'] === 'teacher' ? 'teacher' : 'student';
}
if($view==='announcements'&&$user['role']==='student'&&(int)($_GET['announcement']??0)<1){
    [, $announcementCourse]=student_context();
    if($announcementCourse)mark_course_announcements_read(db(),(int)$announcementCourse['id'],(int)$user['id']);
}

render_app($view);
