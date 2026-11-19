<?php

declare(strict_types=1);

require_once __DIR__.'/RegistrationPolicy.php';

/** Separate, persistent counters shared by HTTP requests and the mail worker. */
function app_mail_guard_database(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $pdo = new PDO('sqlite:'.dirname(__DIR__).'/storage/mail-guard.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec('PRAGMA busy_timeout=30000');
    $pdo->exec('CREATE TABLE IF NOT EXISTS mail_guard_events (scope TEXT NOT NULL, recorded_at INTEGER NOT NULL, cost INTEGER NOT NULL)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS mail_guard_scope_time ON mail_guard_events(scope,recorded_at)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS mail_guard_time ON mail_guard_events(recorded_at)');
    return $pdo;
}

/** Check and reserve all budgets together, including across PHP processes. */
function app_mail_reserve(array $rules, ?PDO $pdo = null, ?int $now = null, ?array &$limits = null): bool
{
    $limits=[];
    $locked = false;
    try {
        $pdo ??= app_mail_guard_database();
        $now ??= time();
        $pdo->exec('BEGIN IMMEDIATE');
        $locked = true;
        $pdo->prepare('DELETE FROM mail_guard_events WHERE recorded_at<=?')->execute([$now-86400]);
        $count = $pdo->prepare('SELECT COALESCE(SUM(cost),0) FROM mail_guard_events WHERE scope=? AND recorded_at>?');
        $usage=[];$allowed=true;
        foreach ($rules as [$scope, $window, $limit, $cost]) {
            $count->execute([$scope, $now-$window]);
            $used=(int)$count->fetchColumn();
            $usage[]=compact('scope','window','limit','cost','used');
            if ($used+$cost > $limit) $allowed=false;
        }
        foreach($usage as $rule){
            if($rule['used']+($allowed?$rule['cost']:0)>=$rule['limit'])$limits[]=['scope'=>$rule['scope'],'window'=>$rule['window'],'limit'=>$rule['limit']];
        }
        if(!$allowed){
            $pdo->exec('ROLLBACK');
            return false;
        }
        $insert = $pdo->prepare('INSERT INTO mail_guard_events(scope,recorded_at,cost) VALUES(?,?,?)');
        // A scope may have several windows; count each reservation only once.
        $reserved = [];
        foreach ($rules as [$scope, $window, $limit, $cost]) {
            if (isset($reserved[$scope])) continue;
            $insert->execute([$scope, $now, $cost]);
            $reserved[$scope] = true;
        }
        $pdo->exec('COMMIT');
        return true;
    } catch (Throwable $exception) {
        $limits=[];
        if ($locked) $pdo->exec('ROLLBACK');
        // Fail closed: unavailable counters must never disable the protection.
        error_log('liike mail: protection indisponible ('.get_class($exception).')');
        return false;
    }
}

function app_public_mail_ip_hash(): string
{
    // Forwarded headers are deliberately ignored: only the actual peer is trusted.
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $packed = @inet_pton($ip);
    // Group IPv6 privacy addresses within the same /64.
    if ($packed !== false && strlen($packed) === 16) $ip = bin2hex(substr($packed, 0, 8));
    return hash('sha256',$ip);
}

function app_public_mail_allowed(string $email, ?PDO $pdo = null, ?int $now = null, string $purpose = 'recovery', ?array &$limits = null): bool
{
    $limits=[];
    if (!app_mail_address_valid($email)||!in_array($purpose,['registration','recovery'],true)) return false;
    $registration=$purpose==='registration';
    // Keep recovery budgets separate: a class registration must not exhaust them.
    $scope=$registration?'registration':'public';
    return app_mail_reserve([
        [$scope.':ip:'.app_public_mail_ip_hash(), 900, $registration?REGISTRATION_IP_ATTEMPTS_15_MINUTES:5, 1],
        ['public:email:'.hash('sha256', strtolower($email)), 900, 3, 1],
        [$scope.':global', 3600, $registration?REGISTRATION_ACCEPTED_PER_HOUR:10, 1],
        [$scope.':global', 86400, $registration?REGISTRATION_ACCEPTED_PER_DAY:30, 1],
    ], $pdo, $now, $limits);
}

function app_mail_form_field(string $action): string
{
    $now = microtime(true);
    $forms = $_SESSION['mail_forms'] ?? [];
    foreach ($forms as $key => $form) {
        if ($now-$form['started_at'] > 1800) unset($forms[$key]);
    }
    $forms = array_slice($forms, -7, null, true);
    $token = bin2hex(random_bytes(24));
    $forms[$token] = ['action'=>$action, 'started_at'=>$now];
    $_SESSION['mail_forms'] = $forms;
    return '<input type="hidden" name="mail_form_token" value="'.$token.'">'
        .'<label class="registration-trap" aria-hidden="true">Site web<input name="website" tabindex="-1" autocomplete="off"></label>';
}

function app_mail_form_valid(string $action, array $post, ?float $now = null): bool
{
    $token = $post['mail_form_token'] ?? null;
    if (!is_string($token) || !preg_match('/^[a-f0-9]{48}$/D', $token)) return false;
    $form = $_SESSION['mail_forms'][$token] ?? null;
    unset($_SESSION['mail_forms'][$token]); // Consumed even after a rejected attempt.
    if (!is_array($form) || $form['action'] !== $action) return false;
    $elapsed = ($now ?? microtime(true))-$form['started_at'];
    return isset($post['website']) && $post['website'] === '' && $elapsed >= 2 && $elapsed <= 1800;
}
