<?php

declare(strict_types=1);

/** Collective sessions are formative and never write to individual qcm_attempts. */
final class QcmClass
{
    public static function context(PDO $pdo,array $user,int $itemId,int $blockId,string $key): array
    {
        $query=$pdo->prepare("SELECT pi.*,p.title FROM pathway_items pi JOIN pages p ON p.id=pi.page_id
            JOIN courses c ON c.id=pi.course_id WHERE pi.id=? AND c.archived=0");
        $query->execute([$itemId]);$item=$query->fetch(PDO::FETCH_ASSOC);
        $teacher=($user['role']??'')==='teacher';
        if(!$item||($user['account_status']??'active')!=='active'
            ||($teacher?!teacher_can_access_course($pdo,(int)$item['course_id'],(int)$user['id'])
                :(($user['role']??'')!=='student'||!item_is_visible_to_student($pdo,$itemId,(int)$user['id'])))){
            throw new DomainException('Cette séance n’est pas accessible.',403);
        }
        $query=$pdo->prepare("SELECT body FROM page_blocks WHERE id=? AND page_id=? AND type='markdown'");
        $query->execute([$blockId,$item['page_id']]);$body=$query->fetchColumn();
        foreach(Qcm::sections((string)$body) as $section){
            if($section['type']!=='qcm'||empty($section['valid'])||$section['mode']!=='classe')continue;
            if(hash_equals(Qcm::key($blockId,(int)$section['index'],$section['source'],'classe'),$key)){
                return ['item'=>$item,'section'=>$section,'teacher'=>$teacher,'block'=>$blockId,'key'=>$key];
            }
        }
        throw new DomainException('Le QCM a changé. Rechargez l’étape.',409);
    }

    private static function latest(PDO $pdo,array $context): ?array
    {
        $query=$pdo->prepare('SELECT * FROM qcm_class_sessions WHERE pathway_item_id=? AND page_block_id=? AND qcm_key=? ORDER BY id DESC LIMIT 1');
        $query->execute([(int)$context['item']['id'],$context['block'],$context['key']]);
        return $query->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public static function state(PDO $pdo,array $user,int $itemId,int $blockId,string $key): array
    {
        // One snapshot keeps phase, votes and totals consistent with concurrent closing.
        $pdo->beginTransaction();
        try{
            $context=self::context($pdo,$user,$itemId,$blockId,$key);
            $session=self::latest($pdo,$context);
            $state=['status'=>'ok','session'=>null];
            if($session)$state['session']=self::publicSession($pdo,$context,$session,(int)$user['id']);
            $pdo->commit();return $state;
        }catch(Throwable $exception){$pdo->rollBack();throw $exception;}
    }

    private static function publicSession(PDO $pdo,array $context,array $session,int $userId): array
    {
        $questions=json_decode($session['questions'],true,512,JSON_THROW_ON_ERROR);
        $index=(int)$session['question_index'];$phase=$session['phase'];
        $state=['id'=>(int)$session['id'],'phase'=>$phase,'revision'=>(int)$session['revision'],
            'index'=>$index,'total'=>count($questions),'revealed'=>(bool)$session['revealed'],'question'=>null,'selected'=>[]];
        if($phase==='waiting')return $state;
        $question=$questions[$index];$showResults=$phase==='results'||$phase==='finished';
        $answers=[];
        foreach($question['answers'] as $answer){
            $public=['index'=>$answer['index'],'text'=>$answer['text']];
            if($showResults&&$session['revealed'])$public['kind']=$answer['kind'];
            if($showResults)$public['count']=0;
            $answers[]=$public;
        }
        // Only currently eligible accounts count; archived/restricted accounts cannot leak votes.
        $eligible=$pdo->prepare("SELECT u.id FROM users u JOIN enrollments e ON e.student_id=u.id AND e.status='active'
            JOIN pathway_items pi ON pi.course_id=e.course_id WHERE pi.id=? AND NOT EXISTS(SELECT 1 FROM pathway_groups visibility_group WHERE visibility_group.id=pi.group_id AND visibility_group.is_visible=0) AND u.account_status='active' AND u.role='student'
            AND (pi.access_mode='all' OR (pi.access_mode='restricted' AND EXISTS(
                SELECT 1 FROM pathway_item_students a WHERE a.pathway_item_id=pi.id AND a.student_id=u.id)))");
        $eligible->execute([(int)$context['item']['id']]);$students=array_fill_keys($eligible->fetchAll(PDO::FETCH_COLUMN),true);
        $query=$pdo->prepare('SELECT student_id,answers FROM qcm_class_votes WHERE session_id=? AND question_index=?');
        $query->execute([$session['id'],$index]);$responded=0;
        foreach($query->fetchAll(PDO::FETCH_ASSOC) as $vote){
            if(!isset($students[$vote['student_id']]))continue;
            $selected=json_decode($vote['answers'],true,512,JSON_THROW_ON_ERROR);$responded++;
            if((int)$vote['student_id']===$userId)$state['selected']=$selected;
            if($showResults)foreach($selected as $choice)$answers[$choice]['count']++;
        }
        $state['responded']=$responded;$state['eligible']=count($students);
        $state['question']=['title'=>$question['title'],'multiple'=>$question['multiple'],'answers'=>$answers];
        return $state;
    }

    public static function command(PDO $pdo,array $user,int $itemId,int $blockId,string $key,array $input): array
    {
        // Serialize a vote with closing/advancing: no late vote can slip in between checks and writes.
        $pdo->exec('BEGIN IMMEDIATE');
        try{
            $context=self::context($pdo,$user,$itemId,$blockId,$key);
            $session=self::latest($pdo,$context);$command=(string)($input['command']??'');
            if($command==='vote'){
                if($context['teacher'])throw new DomainException('Seuls les élèves peuvent répondre.',403);
                if(!$session||$session['phase']!=='open'||(int)($input['session']??0)!==(int)$session['id']
                    ||(int)($input['question']??-1)!==(int)$session['question_index'])throw new DomainException('Cette question n’accepte plus de réponse.',409);
                $questions=json_decode($session['questions'],true,512,JSON_THROW_ON_ERROR);
                $question=$questions[(int)$session['question_index']];$answers=$input['answers']??null;
                if(!is_array($answers)||!$answers)throw new DomainException('Sélectionnez une réponse.',422);
                $selected=[];
                foreach($answers as $value){
                    if(!is_scalar($value)||!preg_match('/^\d+$/D',(string)$value)||!isset($question['answers'][(int)$value]))throw new DomainException('Réponse invalide.',422);
                    $selected[]=(int)$value;
                }
                $selected=array_values(array_unique($selected));sort($selected);
                if(!$question['multiple']&&count($selected)!==1)throw new DomainException('Sélectionnez une seule réponse.',422);
                $save=$pdo->prepare('INSERT INTO qcm_class_votes(session_id,student_id,question_index,answers) VALUES(?,?,?,?)
                    ON CONFLICT(session_id,student_id,question_index) DO UPDATE SET answers=excluded.answers,answered_at=CURRENT_TIMESTAMP');
                $save->execute([$session['id'],$user['id'],$session['question_index'],json_encode($selected,JSON_THROW_ON_ERROR)]);
            }else{
                if(!$context['teacher'])throw new DomainException('Seul l’enseignant peut piloter la séance.',403);
                // Expected session + revision make duplicate clicks and stale teacher tabs harmless.
                if((int)($input['session']??0)!==(int)($session['id']??0)||(int)($input['revision']??0)!==(int)($session['revision']??0)){
                    throw new DomainException('La séance a évolué. Les commandes vont être actualisées.',409);
                }
                if($command==='start'){
                    if($session&&$session['phase']!=='finished')throw new DomainException('Une séance est déjà en cours.',409);
                    $save=$pdo->prepare('INSERT INTO qcm_class_sessions(pathway_item_id,page_block_id,qcm_key,created_by,questions) VALUES(?,?,?,?,?)');
                    $save->execute([$itemId,$blockId,$key,$user['id'],json_encode($context['section']['questions'],JSON_THROW_ON_ERROR)]);
                }else{
                    if(!$session||$session['phase']==='finished')throw new DomainException('Cette séance est terminée.',409);
                    $phase=$session['phase'];$index=(int)$session['question_index'];$revealed=(int)$session['revealed'];
                    $total=count(json_decode($session['questions'],true,512,JSON_THROW_ON_ERROR));
                    if($command==='open'&&$phase==='waiting')$phase='open';
                    elseif($command==='close'&&$phase==='open')$phase='results';
                    elseif($command==='reveal'&&$phase==='results'&&!$revealed)$revealed=1;
                    elseif($command==='next'&&$phase==='results'&&$index+1<$total){$phase='open';$index++;$revealed=0;}
                    elseif($command==='finish'&&$phase==='results'&&$index+1===$total)$phase='finished';
                    else throw new DomainException('Commande indisponible à cette étape de la séance.',409);
                    $save=$pdo->prepare('UPDATE qcm_class_sessions SET phase=?,question_index=?,revealed=?,revision=revision+1,updated_at=CURRENT_TIMESTAMP WHERE id=?');
                    $save->execute([$phase,$index,$revealed,$session['id']]);
                }
            }
            $pdo->exec('COMMIT');return ['status'=>'saved'];
        }catch(Throwable $exception){$pdo->exec('ROLLBACK');throw $exception;}
    }
}
