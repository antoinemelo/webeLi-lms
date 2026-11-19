<?php

declare(strict_types=1);

// Allow a whole class behind one school gateway.
const REGISTRATION_IP_ATTEMPTS_15_MINUTES = 30;
const REGISTRATION_ACCEPTED_PER_HOUR = 50;
const REGISTRATION_ACCEPTED_PER_DAY = 1000;
const REGISTRATION_PENDING_LIMIT = 50;
const REGISTRATION_VERIFICATION_SECONDS = 900;

function verification_expiry_epoch(?string $stored): ?int
{
    $stored=trim((string)$stored);
    if ($stored==='') return null;
    if (ctype_digit($stored)) return (int)$stored;
    $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$stored,new DateTimeZone('UTC'));
    return $date ? $date->getTimestamp() : null;
}

function verification_user(PDO $pdo, string $tokenHash, ?int $now = null): ?array
{
    $query=$pdo->prepare("SELECT * FROM users WHERE verification_token_hash=? AND account_status IN ('pending','active')");
    $query->execute([$tokenHash]);
    $user=$query->fetch(PDO::FETCH_ASSOC);
    if (!$user) return null;
    $expires=verification_expiry_epoch($user['verification_expires_at']??null);
    return $expires!==null && $expires>($now??time()) ? $user : null;
}

function pending_verification_user(PDO $pdo, string $tokenHash, ?int $now = null): ?array
{
    $user=verification_user($pdo,$tokenHash,$now);
    return $user && $user['account_status']==='pending' ? $user : null;
}

function registration_is_allowed(
    string $honeypot,
    float $elapsedSeconds,
    int $ipAttempts,
    int $acceptedLastHour,
    int $acceptedLastDay,
    int $pendingAccounts,
): bool {
    return $honeypot === ''
        && $elapsedSeconds >= 2
        && $elapsedSeconds <= 1800
        && $ipAttempts < REGISTRATION_IP_ATTEMPTS_15_MINUTES
        && $acceptedLastHour < REGISTRATION_ACCEPTED_PER_HOUR
        && $acceptedLastDay < REGISTRATION_ACCEPTED_PER_DAY
        && $pendingAccounts < REGISTRATION_PENDING_LIMIT;
}

function purge_expired_registrations_in(PDO $pdo): int
{
    $pending=$pdo->query("SELECT id,email,verification_expires_at FROM users WHERE account_status='pending' AND verification_expires_at IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
    $active=$pdo->query("SELECT id,verification_expires_at FROM users WHERE account_status='active' AND verification_token_hash IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
    $now=time();
    $expired=array_values(array_filter($pending,static function(array $user) use($now): bool {
        $expires=verification_expiry_epoch($user['verification_expires_at']??null);
        return $expires===null || $expires<=$now;
    }));
    $expiredActive=array_values(array_filter($active,static function(array $user) use($now): bool {
        $expires=verification_expiry_epoch($user['verification_expires_at']??null);
        return $expires===null || $expires<=$now;
    }));
    if (!$expired && !$expiredActive) {
        $pdo->exec("DELETE FROM registration_attempts WHERE attempted_at<datetime('now','-2 days')");
        return 0;
    }

    $pdo->beginTransaction();
    try {
        $deleteMessages = $pdo->prepare("DELETE FROM notification_outbox WHERE event='account.verification' AND recipient=?");
        $deleteUser = $pdo->prepare("DELETE FROM users WHERE id=? AND account_status='pending'");
        foreach ($expired as $user) {
            $deleteMessages->execute([$user['email']]);
            $deleteUser->execute([$user['id']]);
        }
        $clearActiveToken = $pdo->prepare("UPDATE users SET verification_token_hash=NULL,verification_expires_at=NULL WHERE id=? AND account_status='active'");
        foreach ($expiredActive as $user) $clearActiveToken->execute([$user['id']]);
        $pdo->exec("DELETE FROM registration_attempts WHERE attempted_at<datetime('now','-2 days')");
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
    return count($expired);
}

function is_pending_registration_limit(Throwable $exception): bool
{
    return str_contains($exception->getMessage(), 'pending registration limit reached');
}

/** Queue a single alert per reached limit/window, with no applicant data in the email. */
function registration_notify_limits(PDO $pdo,array $limits,?int $now=null): void
{
    if(!$limits)return;
    $now??=time();$locked=false;
    try{
        $pdo->exec('BEGIN IMMEDIATE');$locked=true;
        $pdo->prepare('DELETE FROM registration_limit_alerts WHERE alerted_at<=?')->execute([$now-86400]);
        $admins=$pdo->query("SELECT email,language FROM users WHERE is_superadmin=1 AND role='teacher' AND account_status='active'")->fetchAll(PDO::FETCH_ASSOC);
        foreach($limits as $limit){
            $scope=$limit['scope'];$window=(int)$limit['window'];
            $label=match(true){
                str_starts_with($scope,'registration:ip:')=>':count demandes depuis un même réseau en 15 minutes',
                $scope==='registration:global'&&$window===3600=>':count demandes acceptées en une heure',
                $scope==='registration:global'&&$window===86400=>':count demandes acceptées en une journée',
                $scope==='registration:pending'=>':count comptes publics en attente de validation',
                default=>null,
            };
            if($label===null)continue;
            $key=$scope.':'.$window;
            $query=$pdo->prepare('SELECT alerted_at FROM registration_limit_alerts WHERE scope=?');$query->execute([$key]);$previous=$query->fetchColumn();
            if($previous!==false&&(int)$previous+$window>$now)continue;
            $queued=false;
            foreach($admins as $admin){
                if(!filter_var($admin['email'],FILTER_VALIDATE_EMAIL))continue;
                $language=normalize_language((string)($admin['language']??''))??'fr';
                $subject=t('Alerte : limite d’inscription atteinte',[],$language);
                $body=t("Un plafond d’inscription a été atteint sur :site.\n\nLimite : :limit.\n\nLes nouvelles demandes concernées peuvent être temporairement refusées. Cette alerte n’est envoyée qu’une fois par plafond et par période.\n\nAdministration : :url",[
                    'site'=>app_public_base_url(),'limit'=>t($label,['count'=>(int)$limit['limit']],$language),'url'=>app_public_base_url().'/?view=admin',
                ],$language);
                // Account validation messages take priority over administrative warnings.
                $pdo->prepare("INSERT INTO notification_outbox(event,recipient,subject,body,available_at) VALUES('registration.limit',?,?,?,datetime('now','+1 minute'))")
                    ->execute([$admin['email'],$subject,$body]);
                $queued=true;
            }
            if($queued)$pdo->prepare('INSERT INTO registration_limit_alerts(scope,alerted_at) VALUES(?,?) ON CONFLICT(scope) DO UPDATE SET alerted_at=excluded.alerted_at')->execute([$key,$now]);
        }
        $pdo->exec('COMMIT');
    }catch(Throwable $exception){
        if($locked)$pdo->exec('ROLLBACK');
        // A notification failure must not prevent a legitimate registration.
        error_log('liike registration alert: '.get_class($exception));
    }
}
