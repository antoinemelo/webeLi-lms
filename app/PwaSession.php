<?php

declare(strict_types=1);

const PWA_LOGIN_SECONDS = 90 * 86400;

/** One remembered credential per person, independent of the classic student session. */
function issue_pwa_login(PDO $pdo,int $userId): array
{
    $token=bin2hex(random_bytes(32));$expires=time()+PWA_LOGIN_SECONDS;
    $query=$pdo->prepare("INSERT INTO pwa_logins(user_id,token_hash,expires_at) SELECT id,?,? FROM users WHERE id=? AND account_status='active' ON CONFLICT(user_id) DO UPDATE SET token_hash=excluded.token_hash,expires_at=excluded.expires_at");
    $query->execute([hash('sha256',$token),$expires,$userId]);
    if($query->rowCount()!==1)throw new RuntimeException('Compte inactif.');
    return ['token'=>$token,'expires_at'=>$expires];
}

function pwa_login_user(PDO $pdo,string $token): ?array
{
    if(!preg_match('/^[a-f0-9]{64}$/D',$token))return null;
    $query=$pdo->prepare("SELECT u.* FROM users u JOIN pwa_logins p ON p.user_id=u.id WHERE p.token_hash=? AND p.expires_at>? AND u.account_status='active'");
    $query->execute([hash('sha256',$token),time()]);
    return $query->fetch(PDO::FETCH_ASSOC)?:null;
}

function pwa_session_is_valid(PDO $pdo,int $userId,string $hash): bool
{
    if(!preg_match('/^[a-f0-9]{64}$/D',$hash))return false;
    $query=$pdo->prepare("SELECT 1 FROM pwa_logins p JOIN users u ON u.id=p.user_id WHERE p.user_id=? AND p.token_hash=? AND p.expires_at>? AND u.account_status='active'");
    $query->execute([$userId,$hash,time()]);
    return (bool)$query->fetchColumn();
}

function revoke_pwa_login(PDO $pdo,int $userId,?string $hash=null): void
{
    $query=$pdo->prepare('DELETE FROM pwa_logins WHERE user_id=?'.($hash!==null?' AND token_hash=?':''));
    $query->execute($hash!==null?[$userId,$hash]:[$userId]);
}

function pwa_cookie_path(): string
{
    $path=str_replace('\\','/',dirname((string)($_SERVER['SCRIPT_NAME']??'/index.php')));
    return rtrim($path,'/.').'/';
}

function pwa_cookie_name(): string
{
    return 'liike_pwa_'.substr(hash('sha256',pwa_cookie_path()),0,12);
}

function pwa_cookie_token(): string
{
    $token=$_COOKIE[pwa_cookie_name()]??'';
    return is_string($token)?$token:'';
}

function set_pwa_cookie(string $token,int $expires): void
{
    // HTTPS in production (also behind a proxy); plain HTTP is only allowed on loopback for development.
    $loopback=in_array((string)($_SERVER['SERVER_NAME']??''),['localhost','127.0.0.1','::1'],true);
    $secure=!$loopback||(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off');
    setcookie(pwa_cookie_name(),$token,['expires'=>$expires,'path'=>pwa_cookie_path(),'secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
}

function forget_pwa_cookie(PDO $pdo): void
{
    $token=pwa_cookie_token();
    if(preg_match('/^[a-f0-9]{64}$/D',$token)){
        $query=$pdo->prepare('DELETE FROM pwa_logins WHERE token_hash=?');$query->execute([hash('sha256',$token)]);
    }
    set_pwa_cookie('',time()-3600);
}
