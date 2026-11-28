<?php
declare(strict_types=1);
require_once __DIR__.'/AppSecrets.php';

/** Four-digit, single-use codes. All budgets and validations are atomic and server-side. */
final class StudentEmailLogin
{
    public const LIFETIME=600;
    public const MAX_ATTEMPTS=5;
    public function __construct(private PDO $db,private string $root){}

    private function one(string $sql,array $params): ?array
    {
        $q=$this->db->prepare($sql);$q->execute($params);return $q->fetch(PDO::FETCH_ASSOC)?:null;
    }
    private function allowed(array $rules,int $now): bool
    {
        foreach($rules as [$scope,$window,$maximum]){
            $q=$this->db->prepare('SELECT COUNT(*) FROM student_auth_events WHERE scope=? AND recorded_at>?');$q->execute([$scope,$now-$window]);
            if((int)$q->fetchColumn()>=$maximum)return false;
        }
        return true;
    }
    private function record(array $scopes,int $now): void
    {
        $q=$this->db->prepare('INSERT INTO student_auth_events(scope,recorded_at) VALUES(?,?)');foreach(array_unique($scopes) as $scope)$q->execute([$scope,$now]);
    }
    private function account(int $id): array
    {
        $user=$this->one("SELECT * FROM users WHERE id=? AND role='student' AND account_status='active' AND login_blocked_at IS NULL",[$id]);
        if(!$user)throw new InvalidArgumentException('Connexion indisponible. Contactez votre enseignant.');return $user;
    }
    public function challenge(string $id,string $binding,?int $now=null): ?array
    {
        $now??=time();
        return $this->one("SELECT c.* FROM student_email_challenges c JOIN users u ON u.id=c.user_id WHERE c.id=? AND c.binding=? AND c.expires_at>? AND c.attempts<5 AND c.security_revision=u.security_revision AND u.role='student' AND u.account_status='active' AND u.login_blocked_at IS NULL",[$id,hash('sha256',$binding),$now]);
    }
    public function issue(int $userId,string $purpose,string $binding,array $payload=[],?int $now=null): array
    {
        if(!in_array($purpose,['login','enable','disable','email_old','email_new'],true)||strlen($binding)<32)throw new InvalidArgumentException('Demande invalide.');
        $now??=time();$this->db->exec('BEGIN IMMEDIATE');
        try{
            self::purge($this->db,$now);$user=$this->account($userId);
            if(($purpose==='enable')===(bool)$user['email_login_enabled'])throw new InvalidArgumentException('Le réglage de protection a changé. Rechargez la page.');
            if(str_starts_with($purpose,'email_')){
                $email=mb_strtolower(trim((string)($payload['new_email']??'')),'UTF-8');
                if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254||preg_match('/[\s\x00-\x1f\x7f,;]/',$email)||$email===$user['email'])throw new InvalidArgumentException('Adresse électronique invalide.');
                if($this->one('SELECT id FROM users WHERE lower(email)=lower(?) AND id<>?',[$email,$userId]))throw new InvalidArgumentException('Cette adresse électronique est déjà utilisée.');
                $payload['new_email']=$email;
                if($purpose==='email_new'&&((int)($payload['old_verified_at']??0)<$now-self::LIFETIME||(int)($payload['old_revision']??-1)!==(int)$user['security_revision']))throw new InvalidArgumentException('Vérifiez d’abord votre adresse actuelle.');
            }
            $ip=function_exists('app_public_mail_ip_hash')?app_public_mail_ip_hash():hash('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'));
            $rules=[['send:'.$userId.':'.$purpose,60,1],['send:user:'.$userId,3600,6],['send:user:'.$userId,86400,20],['send:ip:'.$ip,900,120],['send:global',3600,600],['fail:user:'.$userId,600,5]];
            if(!$this->allowed($rules,$now))throw new DomainException('Trop de demandes ou d’essais. Patientez avant de réessayer.');
            $this->record(array_column(array_slice($rules,0,5),0),$now);
            $id=bin2hex(random_bytes(24));$code=str_pad((string)random_int(0,9999),4,'0',STR_PAD_LEFT);$recipient=$purpose==='email_new'?$payload['new_email']:$user['email'];
            $hash=hash_hmac('sha256',$id.'|'.$code,AppSecrets::key($this->root));
            $this->db->prepare('DELETE FROM student_email_challenges WHERE user_id=? AND purpose=? AND binding=?')->execute([$userId,$purpose,hash('sha256',$binding)]);
            $this->db->prepare('INSERT INTO student_email_challenges(id,user_id,purpose,binding,recipient,code_hash,payload,security_revision,expires_at,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$id,$userId,$purpose,hash('sha256',$binding),$recipient,$hash,json_encode($payload,JSON_THROW_ON_ERROR),(int)$user['security_revision'],$now+self::LIFETIME,$now]);
            $reason=match($purpose){'enable'=>'activer la protection de votre compte','disable'=>'désactiver la protection de votre compte','email_old'=>'confirmer votre adresse actuelle','email_new'=>'confirmer votre nouvelle adresse',default=>'vous connecter à liike'};
            $language=$user['language']??'fr';$translate=static fn(string $text,array $params=[]):string=>function_exists('t')?t($text,$params,$language):strtr($text,array_combine(array_map(static fn($key)=>':'.$key,array_keys($params)),array_values($params)));
            $subject=$translate('Votre code de vérification liike');
            $body=$translate("Votre code pour :operation : :code\n\nIl est valable 10 minutes et ne peut être utilisé qu’une fois. Ne le communiquez à personne.\nSi vous n’êtes pas à l’origine de cette demande, ignorez ce message.",['operation'=>$translate($reason),'code'=>$code]);
            // The queue never stores a reusable plaintext code. Its row vanishes after delivery/expiry.
            $this->db->prepare("INSERT INTO notification_outbox(event,recipient,subject,body,auth_challenge_id) VALUES('account.security_code',?,?,?,?)")->execute([$recipient,$subject,AppSecrets::encrypt($body,$this->root,'otp-mail:'.$id),$id]);
            $outboxId=(int)$this->db->lastInsertId();$this->db->exec('COMMIT');
            return ['id'=>$id,'user_id'=>$userId,'purpose'=>$purpose,'recipient'=>$recipient,'expires_at'=>$now+self::LIFETIME,'created_at'=>$now,'outbox_id'=>$outboxId];
        }catch(Throwable $error){$this->db->exec('ROLLBACK');throw $error;}
    }
    public function verify(string $id,string $binding,string $code,?int $now=null): array
    {
        $now??=time();$this->db->exec('BEGIN IMMEDIATE');
        try{
            $challenge=$this->challenge($id,$binding,$now);
            if(!$challenge)throw new InvalidArgumentException('Code expiré ou demande indisponible. Demandez un nouveau code.');
            $userId=(int)$challenge['user_id'];$user=$this->account($userId);
            $ip=function_exists('app_public_mail_ip_hash')?app_public_mail_ip_hash():hash('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'));
            if(!$this->allowed([['fail:user:'.$userId,600,5],['fail:ip:'.$ip,600,300]],$now))throw new DomainException('Trop de demandes ou d’essais. Patientez avant de réessayer.');
            $valid=preg_match('/^[0-9]{4}$/D',$code)&&hash_equals($challenge['code_hash'],hash_hmac('sha256',$id.'|'.$code,AppSecrets::key($this->root,false)));
            if(!$valid){
                $this->db->prepare('UPDATE student_email_challenges SET attempts=attempts+1 WHERE id=?')->execute([$id]);$this->record(['fail:user:'.$userId,'fail:ip:'.$ip],$now);
                $failed=$this->one('SELECT COUNT(*) AS total FROM student_auth_events WHERE scope=? AND recorded_at>?',['fail:user:'.$userId,$now-600]);
                $remaining=max(0,min(4-(int)$challenge['attempts'],self::MAX_ATTEMPTS-(int)$failed['total']));
                $this->db->exec('COMMIT');return ['valid'=>false,'remaining'=>$remaining];
            }
            $purpose=$challenge['purpose'];$payload=json_decode($challenge['payload'],true,512,JSON_THROW_ON_ERROR);
            if($purpose==='enable'||$purpose==='disable')$this->db->prepare('UPDATE users SET email_login_enabled=?,email_verified_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(int)($purpose==='enable'),$userId]);
            elseif($purpose==='email_new'){
                if($this->one('SELECT id FROM users WHERE lower(email)=lower(?) AND id<>?',[$payload['new_email'],$userId]))throw new InvalidArgumentException('Cette adresse électronique est déjà utilisée.');
                $this->db->prepare('UPDATE users SET email=?,email_verified_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$payload['new_email'],$userId]);
            }elseif($purpose==='email_old'){$payload['old_verified_at']=$now;$payload['old_revision']=(int)$user['security_revision'];}
            $this->db->prepare('DELETE FROM student_email_challenges WHERE id=?')->execute([$id]);
            if($purpose==='login')$this->db->prepare("DELETE FROM student_email_challenges WHERE user_id=? AND purpose='login'")->execute([$userId]);
            $user=$this->account($userId);$this->db->exec('COMMIT');return ['valid'=>true,'user'=>$user,'purpose'=>$purpose,'payload'=>$payload];
        }catch(Throwable $error){$this->db->exec('ROLLBACK');throw $error;}
    }
    public static function purge(PDO $db,?int $now=null): int
    {
        $now??=time();$q=$db->prepare('DELETE FROM student_email_challenges WHERE expires_at<=?');$q->execute([$now]);$count=$q->rowCount();
        $db->prepare('DELETE FROM student_auth_events WHERE recorded_at<=?')->execute([$now-86400]);return $count;
    }
    public static function canBlock(array $teacher,array $student): bool
    {
        return ($teacher['role']??'')==='teacher'&&($teacher['account_status']??'')==='active'&&($student['role']??'')==='student'
            &&((int)($teacher['is_superadmin']??0)===1||(int)($student['managed_by']??0)===(int)$teacher['id']);
    }
    /** Reset this account's code budgets only; shared network/global limits remain in force. */
    public function resetAttempts(int $studentId,array $admin): void
    {
        if(($admin['role']??'')!=='teacher'||($admin['account_status']??'')!=='active'||(int)($admin['is_superadmin']??0)!==1
            ||!$this->one("SELECT id FROM users WHERE id=? AND role='teacher' AND account_status='active' AND is_superadmin=1",[(int)($admin['id']??0)])){
            throw new InvalidArgumentException('Accès superadmin refusé.');
        }
        $attached=false;$transaction=false;
        try{
            // One transaction includes the optional delivery counter database: no partial reset.
            $guard=rtrim($this->root,'/').'/storage/mail-guard.sqlite';
            if(is_file($guard)){
                $this->db->prepare('ATTACH DATABASE ? AS student_login_mail_guard')->execute([$guard]);$attached=true;
            }
            $this->db->exec('BEGIN IMMEDIATE');$transaction=true;
            if(!$this->one("SELECT id FROM users WHERE id=? AND role='teacher' AND account_status='active' AND is_superadmin=1",[(int)$admin['id']]))throw new InvalidArgumentException('Accès superadmin refusé.');
            $student=$this->one("SELECT * FROM users WHERE id=? AND role='student' AND account_status='active'",[$studentId]);
            if(!$student)throw new InvalidArgumentException('Compte apprenant actif introuvable.');
            if(!empty($student['login_blocked_at']))throw new InvalidArgumentException('Débloquez d’abord l’accès au compte.');
            $scopes=['send:user:'.$studentId,'fail:user:'.$studentId];
            foreach(['login','enable','disable','email_old','email_new'] as $purpose)$scopes[]='send:'.$studentId.':'.$purpose;
            $this->db->prepare('DELETE FROM student_auth_events WHERE scope IN ('.implode(',',array_fill(0,count($scopes),'?')).')')->execute($scopes);
            $this->db->prepare('DELETE FROM student_email_challenges WHERE user_id=?')->execute([$studentId]);
            if($attached&&$this->one("SELECT name FROM student_login_mail_guard.sqlite_master WHERE type='table' AND name='mail_guard_events'",[])){
                $this->db->prepare('DELETE FROM student_login_mail_guard.mail_guard_events WHERE scope=?')->execute(['delivery:auth-email:'.hash('sha256',strtolower((string)$student['email']))]);
            }
            $this->db->exec('COMMIT');$transaction=false;
        }catch(Throwable $error){if($transaction)$this->db->exec('ROLLBACK');throw $error;}
        finally{if($attached)$this->db->exec('DETACH DATABASE student_login_mail_guard');}
    }
    public function block(int $studentId,array $teacher,bool $blocked): void
    {
        $student=$this->one("SELECT * FROM users WHERE id=? AND role='student'",[$studentId]);
        if(!$student||!self::canBlock($teacher,$student))throw new InvalidArgumentException('Seuls le responsable du compte et le superadmin peuvent bloquer ou débloquer cet accès.');
        $this->db->prepare('UPDATE users SET login_blocked_at=?,login_blocked_by=? WHERE id=?')->execute([$blocked?gmdate('Y-m-d H:i:s'):null,$blocked?(int)$teacher['id']:null,$studentId]);
    }
}
