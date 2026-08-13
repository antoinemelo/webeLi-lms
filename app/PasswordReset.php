<?php

declare(strict_types=1);

const PASSWORD_RESET_SECONDS = 900;
const PASSWORD_RESET_IP_LIMIT = 5;
const PASSWORD_RESET_EMAIL_LIMIT = 3;

function password_reset_user(PDO $pdo, string $tokenHash, ?int $now = null): ?array
{
    $query=$pdo->prepare("SELECT * FROM users WHERE password_reset_token_hash=? AND role='teacher' AND account_status='active'");
    $query->execute([$tokenHash]);
    $user=$query->fetch(PDO::FETCH_ASSOC);
    if(!$user)return null;
    $expires=verification_expiry_epoch($user['password_reset_expires_at']??null);
    return $expires!==null&&$expires>($now??time())?$user:null;
}

function password_reset_request_allowed(PDO $pdo, string $ipHash, string $emailHash): bool
{
    $delete=$pdo->prepare("DELETE FROM password_reset_attempts WHERE requested_at<datetime('now','-2 days')");
    $delete->execute();
    $query=$pdo->prepare("SELECT COUNT(*) FROM password_reset_attempts WHERE ip_hash=? AND requested_at>=datetime('now','-15 minutes')");
    $query->execute([$ipHash]);
    if((int)$query->fetchColumn()>=PASSWORD_RESET_IP_LIMIT)return false;
    $query=$pdo->prepare("SELECT COUNT(*) FROM password_reset_attempts WHERE email_hash=? AND requested_at>=datetime('now','-15 minutes')");
    $query->execute([$emailHash]);
    return (int)$query->fetchColumn()<PASSWORD_RESET_EMAIL_LIMIT;
}

function record_password_reset_request(PDO $pdo, string $ipHash, string $emailHash): void
{
    $query=$pdo->prepare('INSERT INTO password_reset_attempts(ip_hash,email_hash) VALUES(?,?)');
    $query->execute([$ipHash,$emailHash]);
}
