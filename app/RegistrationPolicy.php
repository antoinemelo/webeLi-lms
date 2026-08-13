<?php

declare(strict_types=1);

const REGISTRATION_IP_ATTEMPTS_15_MINUTES = 5;
const REGISTRATION_ACCEPTED_PER_HOUR = 10;
const REGISTRATION_ACCEPTED_PER_DAY = 30;
const REGISTRATION_PENDING_LIMIT = 10;
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
