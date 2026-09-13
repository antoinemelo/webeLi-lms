<?php

declare(strict_types=1);

/** Separate, persistent counters shared by HTTP requests and the mail worker. */
function app_mail_guard_database(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $pdo = new PDO('sqlite:'.dirname(__DIR__).'/storage/mail-guard.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec('PRAGMA busy_timeout=5000');
    $pdo->exec('CREATE TABLE IF NOT EXISTS mail_guard_events (scope TEXT NOT NULL, recorded_at INTEGER NOT NULL, cost INTEGER NOT NULL)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS mail_guard_scope_time ON mail_guard_events(scope,recorded_at)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS mail_guard_time ON mail_guard_events(recorded_at)');
    return $pdo;
}

/** Check and reserve all budgets together, including across PHP processes. */
function app_mail_reserve(array $rules, ?PDO $pdo = null, ?int $now = null): bool
{
    $locked = false;
    try {
        $pdo ??= app_mail_guard_database();
        $now ??= time();
        $pdo->exec('BEGIN IMMEDIATE');
        $locked = true;
        $pdo->prepare('DELETE FROM mail_guard_events WHERE recorded_at<=?')->execute([$now-86400]);
        $count = $pdo->prepare('SELECT COALESCE(SUM(cost),0) FROM mail_guard_events WHERE scope=? AND recorded_at>?');
        foreach ($rules as [$scope, $window, $limit, $cost]) {
            $count->execute([$scope, $now-$window]);
            if ((int)$count->fetchColumn()+$cost > $limit) {
                $pdo->exec('ROLLBACK');
                return false;
            }
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
        if ($locked) $pdo->exec('ROLLBACK');
        // Fail closed: unavailable counters must never disable the protection.
        error_log('liike mail: protection indisponible ('.get_class($exception).')');
        return false;
    }
}

function app_public_mail_allowed(string $email, ?PDO $pdo = null, ?int $now = null): bool
{
    if (!app_mail_address_valid($email)) return false;
    // Forwarded headers are deliberately ignored: only the actual peer is trusted.
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $packed = @inet_pton($ip);
    // Group IPv6 privacy addresses within the same /64.
    if ($packed !== false && strlen($packed) === 16) $ip = bin2hex(substr($packed, 0, 8));
    return app_mail_reserve([
        ['public:ip:'.hash('sha256', $ip), 900, 5, 1],
        ['public:email:'.hash('sha256', strtolower($email)), 900, 3, 1],
        ['public:global', 3600, 10, 1],
        ['public:global', 86400, 30, 1],
    ], $pdo, $now);
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
