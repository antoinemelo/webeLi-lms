<?php
declare(strict_types=1);
require_once __DIR__.'/AdaptiveQcm.php';
require_once __DIR__.'/AdaptiveQcmMaintenance.php';

const HOUSEKEEPING_INTERVAL_SECONDS = 300;

/** Cron can force a run; HTTP fallback never waits for another worker or SQLite writer. */
function run_housekeeping(PDO $pdo, string $root, bool $force=false, ?int $now=null, ?array &$report=null): bool
{
    $report=null;
    $now??=time();
    $path=rtrim($root,'/').'/storage/housekeeping.heartbeat';
    if(is_file(rtrim($root,'/').'/storage/maintenance.flag'))return false;
    $recent=static function()use($path,$now):bool{
        clearstatcache(true,$path);
        $last=is_file($path)?filemtime($path):false;
        return $last!==false && $last<=$now && $last>$now-HOUSEKEEPING_INTERVAL_SECONDS;
    };
    if(!$force && $recent())return false;
    $lock=fopen($path,'c+');
    if(!$lock)return false;
    // A newly created file has a recent mtime: only recheck after locking an existing marker.
    $hasRun=filesize($path)>0;
    if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);return false;}
    $timeout=(int)$pdo->query('PRAGMA busy_timeout')->fetchColumn();
    $ownsTransaction=false;
    try{
        if(!$force && $hasRun && $recent())return false;
        if(!$force)$pdo->exec('PRAGMA busy_timeout=0');
        if($pdo->inTransaction())throw new RuntimeException('Une opération empêche le démarrage de la purge.');
        $pdo->beginTransaction();$ownsTransaction=true;
        purge_expired_registrations_in($pdo,$counts);
        $counts['learning_visits']=purge_learning_activity($pdo);
        $counts['guest_sessions']=QcmGuest::purge($pdo);
        $pdo->commit();$ownsTransaction=false;
        $counts+=adaptive_qcm_housekeeping($pdo,$force);
        $report=['completed_at'=>$now,'counts'=>$counts];
        ftruncate($lock,0);rewind($lock);fwrite($lock,(string)$now);fflush($lock);touch($path,$now);
        return true;
    }catch(Throwable $exception){
        if($ownsTransaction && $pdo->inTransaction())$pdo->rollBack();
        $report=null;
        // Retry shortly after contention instead of making every page attempt the same work.
        ftruncate($lock,0);rewind($lock);fwrite($lock,'retry');fflush($lock);touch($path,$now-HOUSEKEEPING_INTERVAL_SECONDS+30);
        if($force)throw $exception;
        error_log('liike housekeeping deferred: '.get_class($exception));
        return false;
    }finally{
        if(!$force)$pdo->exec('PRAGMA busy_timeout='.$timeout);
        flock($lock,LOCK_UN);fclose($lock);
    }
}

/** Manual access bypasses the schedule, never the retention rules or the worker lock. */
function superadmin_housekeeping(PDO $pdo, string $root, array $user): ?array
{
    if(($user['role']??'')!=='teacher' || (int)($user['is_superadmin']??0)!==1 || ($user['account_status']??'')!=='active'){
        throw new InvalidArgumentException('Accès superadmin refusé.');
    }
    $timeout=(int)$pdo->query('PRAGMA busy_timeout')->fetchColumn();
    try{
        $pdo->exec('PRAGMA busy_timeout=1000');
        if(!run_housekeeping($pdo,$root,true,null,$report))return null;
        return $report;
    }finally{$pdo->exec('PRAGMA busy_timeout='.$timeout);}
}

function housekeeping_report_labels(): array
{
    return [
        'registrations'=>'Inscriptions non validées expirées',
        'learning_visits'=>'Traces de consultation de plus d’un mois',
        'guest_sessions'=>'Séances QCM anonymes expirées',
        'registration_attempts'=>'Tentatives d’inscription de plus de deux jours',
        'verification_tokens'=>'Jetons de validation expirés',
        'adaptive_runs'=>'Anciennes tentatives adaptatives devenues inutiles',
        'adaptive_references'=>'Réservoirs adaptatifs devenus inutilisés',
    ];
}
