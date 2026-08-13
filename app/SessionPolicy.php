<?php

declare(strict_types=1);

const STUDENT_SESSION_ACTIVE_SECONDS = 1800;

function start_student_session(PDO $pdo, int $studentId): ?string
{
    $now=time();
    $pdo->exec('BEGIN IMMEDIATE');
    try{
        $query=$pdo->prepare("SELECT student_session_token_hash,student_session_seen_at FROM users WHERE id=? AND role='student' AND account_status='active'");
        $query->execute([$studentId]);$student=$query->fetch(PDO::FETCH_ASSOC);
        if(!$student){$pdo->exec('ROLLBACK');return null;}
        $active=trim((string)($student['student_session_token_hash']??''))!==''&&(int)($student['student_session_seen_at']??0)>=$now-STUDENT_SESSION_ACTIVE_SECONDS;
        if($active){
            $pdo->prepare('UPDATE users SET student_session_token_hash=NULL,student_session_seen_at=NULL WHERE id=?')->execute([$studentId]);
            $pdo->exec('COMMIT');return null;
        }
        $token=bin2hex(random_bytes(32));
        $pdo->prepare('UPDATE users SET student_session_token_hash=?,student_session_seen_at=? WHERE id=?')->execute([hash('sha256',$token),$now,$studentId]);
        $pdo->exec('COMMIT');return $token;
    }catch(Throwable $exception){try{$pdo->exec('ROLLBACK');}catch(Throwable){}throw $exception;}
}

function student_session_is_valid(PDO $pdo, int $studentId, string $token): bool
{
    if(!preg_match('/^[a-f0-9]{64}$/',$token))return false;
    $query=$pdo->prepare("SELECT 1 FROM users WHERE id=? AND role='student' AND account_status='active' AND student_session_token_hash=?");
    $query->execute([$studentId,hash('sha256',$token)]);
    if(!$query->fetchColumn())return false;
    $pdo->prepare('UPDATE users SET student_session_seen_at=? WHERE id=? AND student_session_token_hash=?')->execute([time(),$studentId,hash('sha256',$token)]);
    return true;
}

function close_student_session(PDO $pdo, int $studentId, string $token): void
{
    if($studentId<1||!preg_match('/^[a-f0-9]{64}$/',$token))return;
    $pdo->prepare('UPDATE users SET student_session_token_hash=NULL,student_session_seen_at=NULL WHERE id=? AND student_session_token_hash=?')->execute([$studentId,hash('sha256',$token)]);
}
