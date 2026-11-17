<?php
declare(strict_types=1);

/** Anonymous room participants have no user/account/name/IP relationship. */
final class QcmGuest
{
    public const LIFETIME=86400;

    public static function purge(PDO $pdo): void
    {
        $pdo->prepare('DELETE FROM qcm_guest_sessions WHERE expires_at<=?')->execute([time()]);
    }

    private static function quiz(PDO $pdo,int $pageId,int $blockId,string $key): array
    {
        $query=$pdo->prepare("SELECT body FROM page_blocks WHERE page_id=? AND id=? AND type='markdown'");$query->execute([$pageId,$blockId]);
        foreach(Qcm::sections((string)$query->fetchColumn()) as $section){
            if($section['type']==='qcm'&&!empty($section['valid'])&&$section['mode']==='classe'&&hash_equals(Qcm::key($blockId,(int)$section['index'],$section['source'],'classe'),$key))return $section;
        }
        throw new DomainException('Le QCM a changé. Rechargez l’étape.',409);
    }

    public static function create(PDO $pdo,string $shareToken,int $teacherId,int $blockId,string $key): string
    {
        self::purge($pdo);$pdo->beginTransaction();
        try{
            $share=library_share_resolve($pdo,$shareToken);library_share_require_mode($share,'external');
            $query=$pdo->prepare("SELECT 1 FROM users WHERE id=? AND role='teacher' AND account_status='active'");$query->execute([$teacherId]);
            if(!$query->fetchColumn())throw new DomainException('Un compte enseignant est nécessaire.',403);
            $query=$pdo->prepare("SELECT COUNT(*) FROM qcm_guest_sessions WHERE created_by=? AND phase<>'finished'");$query->execute([$teacherId]);
            if((int)$query->fetchColumn()>=20)throw new DomainException('Terminez une séance avant d’en créer une nouvelle.',422);
            $quiz=self::quiz($pdo,(int)$share['page_id'],$blockId,$key);$token=bin2hex(random_bytes(16));
            $pdo->prepare('INSERT INTO qcm_guest_sessions(page_id,page_block_id,qcm_key,created_by,room_token,questions,expires_at,source_share_token) VALUES(?,?,?,?,?,?,?,?)')->execute([$share['page_id'],$blockId,$key,$teacherId,$token,json_encode($quiz['questions'],JSON_THROW_ON_ERROR),time()+self::LIFETIME,$shareToken]);
            $pdo->commit();return $token;
        }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
    }

    public static function context(PDO $pdo,string $room): array
    {
        if(!preg_match('/^[a-f0-9]{32}$/D',$room))throw new DomainException('Cette séance n’est pas accessible.',404);
        $query=$pdo->prepare("SELECT s.*,p.title,s.source_share_token AS share_token FROM qcm_guest_sessions s JOIN pages p ON p.id=s.page_id JOIN page_share_links l ON l.page_id=p.id JOIN users owner ON owner.id=p.owner_id JOIN users host ON host.id=s.created_by WHERE s.room_token=? AND s.expires_at>? AND l.enabled=1 AND l.allow_external=1 AND p.status='ready' AND owner.account_status='active' AND host.account_status='active' AND owner.role='teacher' AND host.role='teacher'");$query->execute([$room,time()]);
        $session=$query->fetch(PDO::FETCH_ASSOC);if(!$session)throw new DomainException('Cette séance n’est pas accessible.',404);
        self::quiz($pdo,(int)$session['page_id'],(int)$session['page_block_id'],$session['qcm_key']);return $session;
    }

    public static function isHost(array $session,?array $actor): bool
    {
        return $actor&&($actor['role']??'')==='teacher'&&($actor['account_status']??'active')==='active'&&(int)$actor['id']===(int)$session['created_by'];
    }

    public static function join(PDO $pdo,string $room,string $participant): void
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$participant))throw new DomainException('Cette séance n’est pas accessible.',403);
        $pdo->exec('BEGIN IMMEDIATE');
        try{
            $session=self::context($pdo,$room);
            if($session['phase']==='finished')throw new DomainException('Cette séance est terminée.',409);
            $query=$pdo->prepare('SELECT 1 FROM qcm_guest_participants WHERE session_id=? AND participant_hash=?');$query->execute([$session['id'],$participant]);
            if($query->fetchColumn()){$pdo->exec('COMMIT');return;}
            $query=$pdo->prepare('SELECT COUNT(*) FROM qcm_guest_participants WHERE session_id=?');$query->execute([$session['id']]);
            if((int)$query->fetchColumn()>=500)throw new DomainException('Cette séance a atteint sa capacité.',422);
            $pdo->prepare('INSERT OR IGNORE INTO qcm_guest_participants(session_id,participant_hash) VALUES(?,?)')->execute([$session['id'],$participant]);
            $pdo->exec('COMMIT');
        }catch(Throwable $exception){$pdo->exec('ROLLBACK');throw $exception;}
    }

    private static function authorize(PDO $pdo,array $session,?array $actor,string $participant): bool
    {
        $host=self::isHost($session,$actor);if($host)return true;
        $query=$pdo->prepare('SELECT 1 FROM qcm_guest_participants WHERE session_id=? AND participant_hash=?');$query->execute([$session['id'],$participant]);
        if(!$query->fetchColumn())throw new DomainException('Rejoignez la séance pour continuer.',403);return false;
    }

    public static function state(PDO $pdo,string $room,?array $actor,string $participant): array
    {
        $pdo->beginTransaction();
        try{
            $session=self::context($pdo,$room);self::authorize($pdo,$session,$actor,$participant);
            $questions=json_decode($session['questions'],true,512,JSON_THROW_ON_ERROR);$index=(int)$session['question_index'];$phase=$session['phase'];
            $query=$pdo->prepare('SELECT COUNT(*) FROM qcm_guest_participants WHERE session_id=?');$query->execute([$session['id']]);
            $state=['id'=>(int)$session['id'],'revision'=>(int)$session['revision'],'index'=>$index,'total'=>count($questions),'phase'=>$phase,'revealed'=>(bool)$session['revealed'],'eligible'=>(int)$query->fetchColumn(),'responded'=>0,'selected'=>[],'question'=>null];
            if($phase!=='waiting'){
                $question=$questions[$index];$results=in_array($phase,['results','finished'],true);$answers=[];
                foreach($question['answers'] as $answer){$visible=['index'=>$answer['index'],'text'=>$answer['text']];if($results){$visible['count']=0;if($session['revealed'])$visible['kind']=$answer['kind'];}$answers[]=$visible;}
                $query=$pdo->prepare('SELECT participant_hash,answers FROM qcm_guest_votes WHERE session_id=? AND question_index=?');$query->execute([$session['id'],$index]);
                foreach($query->fetchAll(PDO::FETCH_ASSOC) as $vote){$choices=json_decode($vote['answers'],true,512,JSON_THROW_ON_ERROR);$state['responded']++;if(hash_equals($vote['participant_hash'],$participant))$state['selected']=$choices;if($results)foreach($choices as $choice)$answers[$choice]['count']++;}
                $state['question']=['title'=>$question['title'],'multiple'=>$question['multiple'],'answers'=>$answers];
            }
            $pdo->commit();return ['status'=>'ok','session'=>$state];
        }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
    }

    public static function command(PDO $pdo,string $room,?array $actor,string $participant,array $input): array
    {
        $pdo->exec('BEGIN IMMEDIATE');
        try{
            $session=self::context($pdo,$room);$host=self::authorize($pdo,$session,$actor,$participant);$command=is_string($input['command']??null)?$input['command']:'';
            if((int)($input['session']??0)!==(int)$session['id'])throw new DomainException('Cette séance n’est pas accessible.',409);
            if($command==='vote'){
                if($host)throw new DomainException('Seuls les élèves peuvent répondre.',403);
                if($session['phase']!=='open'||(int)($input['question']??-1)!==(int)$session['question_index'])throw new DomainException('Cette question n’accepte plus de réponse.',409);
                $questions=json_decode($session['questions'],true,512,JSON_THROW_ON_ERROR);$question=$questions[(int)$session['question_index']];$values=$input['answers']??null;
                if(!is_array($values)||!$values)throw new DomainException('Sélectionnez une réponse.',422);$selected=[];
                foreach($values as $value){if(!is_scalar($value)||!preg_match('/^\d+$/D',(string)$value)||!isset($question['answers'][(int)$value]))throw new DomainException('Réponse invalide.',422);$selected[]=(int)$value;}
                $selected=array_values(array_unique($selected));sort($selected);
                if(!$question['multiple']&&count($selected)!==1)throw new DomainException('Sélectionnez une seule réponse.',422);
                $pdo->prepare('INSERT INTO qcm_guest_votes(session_id,participant_hash,question_index,answers) VALUES(?,?,?,?) ON CONFLICT(session_id,participant_hash,question_index) DO UPDATE SET answers=excluded.answers')->execute([$session['id'],$participant,$session['question_index'],json_encode($selected,JSON_THROW_ON_ERROR)]);
            }else{
                if(!$host)throw new DomainException('Seul l’enseignant peut piloter la séance.',403);
                if((int)($input['revision']??0)!==(int)$session['revision'])throw new DomainException('La séance a évolué. Les commandes vont être actualisées.',409);
                $phase=$session['phase'];$index=(int)$session['question_index'];$revealed=(int)$session['revealed'];$total=count(json_decode($session['questions'],true,512,JSON_THROW_ON_ERROR));
                if($phase==='finished')throw new DomainException('Cette séance est terminée.',409);
                if($command==='open'&&$phase==='waiting')$phase='open';
                elseif($command==='close'&&$phase==='open')$phase='results';
                elseif($command==='reveal'&&$phase==='results'&&!$revealed)$revealed=1;
                elseif($command==='next'&&$phase==='results'&&$index+1<$total){$index++;$phase='open';$revealed=0;}
                elseif($command==='stop'||($command==='finish'&&$phase==='results'&&$index+1===$total))$phase='finished';
                else throw new DomainException('Commande indisponible à cette étape de la séance.',409);
                $pdo->prepare('UPDATE qcm_guest_sessions SET phase=?,question_index=?,revealed=?,revision=revision+1 WHERE id=?')->execute([$phase,$index,$revealed,$session['id']]);
            }
            $pdo->exec('COMMIT');return ['status'=>'saved'];
        }catch(Throwable $exception){$pdo->exec('ROLLBACK');throw $exception;}
    }
}
