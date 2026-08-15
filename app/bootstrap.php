<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/I18n.php';
require_once __DIR__ . '/Markdown.php';
require_once __DIR__ . '/MailDelivery.php';
require_once __DIR__ . '/RegistrationPolicy.php';
require_once __DIR__ . '/PasswordReset.php';
require_once __DIR__ . '/SessionPolicy.php';
require_once __DIR__ . '/LearningActivity.php';
require_once __DIR__ . '/Collaboration.php';
require_once __DIR__ . '/PathwayService.php';
require_once __DIR__ . '/CourseEnrollment.php';
require_once __DIR__ . '/TransferService.php';
require_once __DIR__ . '/AdminService.php';
require_once __DIR__ . '/UpdateService.php';
require_once __DIR__ . '/PdfExport.php';

session_start();

const APP_NAME = 'liike';
const LEVELS = [
    0 => ['label' => 'Je découvre', 'short' => 'Découverte'],
    1 => ['label' => 'Je commence', 'short' => 'En cours'],
    2 => ['label' => 'Je maîtrise', 'short' => 'Maîtrisé'],
    3 => ['label' => 'Je peux expliquer', 'short' => 'Expert'],
];

$root = dirname(__DIR__);
if (!defined('APR_PUBLIC_ROOT')) {
    define('APR_PUBLIC_ROOT', is_dir($root . '/public') ? $root . '/public' : $root);
}
$db = Database::connect($root);
initialize_i18n($db);

function db(): PDO
{
    global $db;
    return $db;
}

function one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function run(string $sql, array $params = []): void
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function route(string $view, array $params = []): string
{
    return '?' . http_build_query(['view' => $view] + $params);
}

function redirect(string $view, array $params = []): never
{
    header('Location: ' . route($view, $params));
    exit;
}

function flash(string $message, string $kind = 'success'): void
{
    $_SESSION['flash'] = ['message' => t($message), 'kind' => $kind];
}

function actor(): ?array
{
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if($id<1)return null;
    $user=one("SELECT * FROM users WHERE id=? AND account_status='active'",[$id]);
    if(!$user)return null;
    if($user['role']==='student'&&!student_session_is_valid(db(),$id,(string)($_SESSION['student_session_token']??''))){
        unset($_SESSION['user_id'],$_SESSION['student_session_token']);
        $_SESSION['flash']=['message'=>t('Toutes les sessions de ce compte ont été déconnectées, car une connexion simultanée a été détectée.'),'kind'=>'error'];
        return null;
    }
    return $user;
}

function require_actor(): array
{
    $user = actor();
    if (!$user) redirect('login');
    return $user;
}

function student_code(string $firstName, string $lastName): string
{
    $compactFirstName = preg_replace('/\s+/u', '', trim($firstName)) ?? '';
    $compactLastName = preg_replace('/\s+/u', '', trim($lastName)) ?? '';
    return mb_strtoupper(mb_substr($compactFirstName, 0, 2) . mb_substr($compactLastName, 0, 3), 'UTF-8');
}

function unique_login_code(string $base, bool $uppercase = false): string
{
    $base = trim($base);
    $base = $uppercase ? mb_strtoupper($base, 'UTF-8') : mb_strtolower($base, 'UTF-8');
    $candidate = $base;
    $suffix = 0;
    while (one('SELECT id FROM users WHERE lower(login_code)=lower(?)', [$candidate])) {
        $suffix++;
        $suffixText = (string)$suffix;
        $stem = $uppercase ? $base : mb_substr($base, 0, 40 - mb_strlen($suffixText));
        $candidate = $stem . $suffixText;
    }
    return $candidate;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['token'] ?? ''))) {
        http_response_code(403);
        exit(t('Formulaire expiré. Rechargez la page.'));
    }
}

function level_label(?int $level): string
{
    return $level === null ? t('À confirmer') : t(LEVELS[$level]['short']);
}

function level_percent(?float $level): int
{
    return $level === null ? 0 : (int) round(($level / 3) * 100);
}

function date_fr(?string $date, bool $withYear = false): string
{
    if (!$date) return t('Sans échéance');
    $time = strtotime($date);
    return $time===false?t('Sans échéance'):date('d/m/Y',$time);
}

function date_input_value(?string $date): string
{
    if(!$date)return '';$time=strtotime($date);return $time===false?'':date('d/m/Y',$time);
}

function database_date_from_input(mixed $value): ?string
{
    $value=trim((string)$value);if($value==='')return null;
    foreach(['!d/m/Y','!Y-m-d'] as $format){$date=DateTimeImmutable::createFromFormat($format,$value);$errors=DateTimeImmutable::getLastErrors();if($date&&($errors===false||($errors['warning_count']===0&&$errors['error_count']===0))&&$date->format($format==='!d/m/Y'?'d/m/Y':'Y-m-d')===$value)return $date->format('Y-m-d');}
    return null;
}

function due_meta(?string $deadline, bool $done): array
{
    if (!$deadline) return [t('Sans échéance'), 'muted'];
    $today = strtotime(date('Y-m-d'));
    $due = strtotime($deadline);
    $days = (int) floor(($due - $today) / 86400);
    if ($done) return [t('Réalisé'), 'success'];
    if ($days < 0) return [t('En retard') . ' · ' . date_fr($deadline), 'danger'];
    if ($days === 0) return [t('Aujourd’hui'), 'warning'];
    if ($days === 1) return [t('Demain'), 'warning'];
    return [date_fr($deadline), $days <= 5 ? 'warning' : 'muted'];
}

function enqueue(string $event, string $recipient, string $subject, string $body): int
{
    run('INSERT INTO notification_outbox(event,recipient,subject,body) VALUES (?,?,?,?)', [$event,$recipient,$subject,$body]);
    return (int)db()->lastInsertId();
}

function try_send_outbox(int $messageId): bool
{
    $message = one("SELECT * FROM notification_outbox WHERE id=? AND status='pending'", [$messageId]);
    if (!$message) return false;
    $sent = deliver_app_mail($message['recipient'], $message['subject'], $message['body']);
    if ($sent) {
        run("UPDATE notification_outbox SET status='sent',attempts=attempts+1,last_error=NULL,sent_at=CURRENT_TIMESTAMP WHERE id=?", [$messageId]);
    } else {
        run("UPDATE notification_outbox SET attempts=attempts+1,last_error='mail() a retourné false' WHERE id=?", [$messageId]);
    }
    return $sent;
}

function verification_url(string $token): string
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080');
    if (!preg_match('/^[A-Za-z0-9.:-]+$/', $host)) $host = '127.0.0.1:8080';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/lms/index.php');
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return $scheme . '://' . $host . $base . '/?v=' . rawurlencode($token);
}

function password_reset_url(string $token): string
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080');
    if (!preg_match('/^[A-Za-z0-9.:-]+$/', $host)) $host = '127.0.0.1:8080';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/lms/index.php');
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return $scheme . '://' . $host . $base . '/?r=' . rawurlencode($token);
}

function course_invitation_url(string $code): string
{
    $host=(string)($_SERVER['HTTP_HOST']??'127.0.0.1:8080');
    if(!preg_match('/^[A-Za-z0-9.:-]+$/',$host))$host='127.0.0.1:8080';
    $scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
    $script=(string)($_SERVER['SCRIPT_NAME']??'/lms/index.php');
    $base=rtrim(str_replace('\\','/',dirname($script)),'/');
    return $scheme.'://'.$host.$base.'/'.route('join',['code'=>$code]);
}

function purge_expired_registrations(): int
{
    return purge_expired_registrations_in(db());
}

function tags_for_page(int $pageId): array
{
    return all('SELECT t.* FROM tags t JOIN page_tags pt ON pt.tag_id=t.id WHERE pt.page_id=? ORDER BY t.name', [$pageId]);
}

function framework_progress(int $courseId, int $enrollmentId, string $kind): array
{
    $isSkill = $kind === 'skill';
    $studentId=(int)(one('SELECT student_id FROM enrollments WHERE id=?',[$enrollmentId])['student_id']??0);
    if (!$isSkill) {
        return all("SELECT MIN(po.id) AS id,po.title,'' AS code,MAX(po.description) AS description,
            COUNT(DISTINCT visible.id) AS item_count,
            AVG(CASE WHEN p.student_validated_at IS NOT NULL THEN p.student_level END) AS student_level,
            AVG(CASE WHEN p.teacher_validated_at IS NOT NULL THEN p.teacher_level END) AS teacher_level,
            SUM(CASE WHEN p.student_validated_at IS NOT NULL THEN 1 ELSE 0 END) AS student_done,
            SUM(CASE WHEN p.teacher_validated_at IS NOT NULL THEN 1 ELSE 0 END) AS teacher_done
            FROM pathway_items pi JOIN page_objectives po ON po.page_id=pi.page_id
            LEFT JOIN pathway_items visible ON visible.id=pi.id AND visible.is_evaluation=0 AND (
                visible.access_mode='all'
                OR (visible.access_mode='restricted' AND EXISTS(SELECT 1 FROM pathway_item_students a WHERE a.pathway_item_id=visible.id AND a.student_id=?)))
            LEFT JOIN progress p ON p.pathway_item_id=visible.id AND p.enrollment_id=?
            WHERE pi.course_id=? GROUP BY lower(po.title) HAVING COUNT(DISTINCT visible.id)>0 ORDER BY MIN(pi.position),po.title",[$studentId,$enrollmentId,$courseId]);
    }
    $table = 'course_skills';
    $link = 'item_skills';
    $column = 'skill_id';
    $code = 'f.code';
    return all("SELECT f.id, f.title, $code AS code, f.description,
        COUNT(DISTINCT visible.id) AS item_count,
        AVG(CASE WHEN p.student_validated_at IS NOT NULL THEN p.student_level END) AS student_level,
        AVG(CASE WHEN p.teacher_validated_at IS NOT NULL THEN p.teacher_level END) AS teacher_level,
        SUM(CASE WHEN p.student_validated_at IS NOT NULL THEN 1 ELSE 0 END) AS student_done,
        SUM(CASE WHEN p.teacher_validated_at IS NOT NULL THEN 1 ELSE 0 END) AS teacher_done
        FROM $table f
        LEFT JOIN $link l ON l.$column=f.id
        LEFT JOIN pathway_items visible ON visible.id=l.pathway_item_id AND visible.is_evaluation=0 AND (
            visible.access_mode='all'
            OR (visible.access_mode='restricted' AND EXISTS(SELECT 1 FROM pathway_item_students a WHERE a.pathway_item_id=visible.id AND a.student_id=?)))
        LEFT JOIN progress p ON p.pathway_item_id=visible.id AND p.enrollment_id=?
        WHERE f.course_id=? GROUP BY f.id HAVING COUNT(DISTINCT visible.id)>0 ORDER BY f.position, f.id", [$studentId,$enrollmentId,$courseId]);
}

function reward_summary(int $enrollmentId): array
{
    $total = one('SELECT COALESCE(SUM(points),0) AS total, COUNT(*) AS count FROM reward_awards WHERE enrollment_id=?', [$enrollmentId]);
    $types = all('SELECT rt.name,rt.icon,rt.color,SUM(ra.points) AS points,COUNT(*) AS count
        FROM reward_awards ra JOIN reward_types rt ON rt.id=ra.reward_type_id
        WHERE ra.enrollment_id=? GROUP BY rt.id ORDER BY points DESC,rt.name', [$enrollmentId]);
    $recent = all('SELECT ra.*,rt.name,rt.icon,rt.color,p.title AS page_title
        FROM reward_awards ra JOIN reward_types rt ON rt.id=ra.reward_type_id
        JOIN pathway_items pi ON pi.id=ra.pathway_item_id JOIN pages p ON p.id=pi.page_id
        WHERE ra.enrollment_id=? ORDER BY ra.awarded_at DESC LIMIT 4', [$enrollmentId]);
    return ['total'=>(int)($total['total'] ?? 0),'count'=>(int)($total['count'] ?? 0),'types'=>$types,'recent'=>$recent];
}

function csrf_field(): string
{
    return '<input type="hidden" name="token" value="' . e(csrf_token()) . '">';
}

function render_level_picker(string $name, ?int $selected = null): string
{
    $html = '<div class="level-picker">';
    foreach (LEVELS as $number => $level) {
        $checked = $selected === $number ? ' checked' : '';
        $html .= '<label><input type="radio" name="' . e($name) . '" value="' . $number . '"' . $checked . ' required><span><b>' . $number . '</b><small>' . e(t($level['label'])) . '</small></span></label>';
    }
    return $html . '</div>';
}

purge_expired_registrations();
purge_learning_activity(db());
