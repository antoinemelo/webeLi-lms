<?php

declare(strict_types=1);
require_once __DIR__.'/QuizAttempts.php';

require_once __DIR__.'/AdaptiveQcm.php';

final class Qcm
{
    /**
     * @return list<array{type:'markdown'|'qcm',source:string,mode?:string,index?:int,valid?:bool,questions?:array}>
     */
    public static function sections(string $source): array
    {
        if(preg_match('/^\s*:::qcm_tiers\b/u',$source))return [['type'=>'reference','source'=>$source,'valid'=>AdaptiveQcm::reference($source)!==null]];
        $lines=explode("\n",str_replace(["\r\n","\r"],"\n",$source));
        $sections=[];$markdown=[];$quiz=[];$inQuiz=false;$quizIndex=0;$mode='classic';$domain='';$theme='';$validHeader=true;
        $flushMarkdown=static function()use(&$sections,&$markdown):void{
            $text=trim(implode("\n",$markdown));$markdown=[];
            if($text!=='')$sections[]=['type'=>'markdown','source'=>$text];
        };
        foreach($lines as $line){
            if(!$inQuiz&&preg_match('/^\s*:::\s*qcm(_strict|_classe|_adap|_adaptatif)?(?:\[([^\]\r\n]{1,100})\]\[([^\]\r\n]{1,100})\])?\s*$/iu',$line,$opening)){
                $flushMarkdown();$inQuiz=true;$quiz=[];$validHeader=true;$mode=match(mb_strtolower($opening[1]??'')){ '_strict'=>'strict','_classe'=>'classe','_adap','_adaptatif'=>'adaptive',default=>'classic' };$domain=trim($opening[2]??'');$theme=trim($opening[3]??'');continue;
            }
            if(!$inQuiz&&preg_match('/^\s*:::\s*qcm_(?:adap|adaptatif)\b/iu',$line)){$flushMarkdown();$inQuiz=true;$quiz=[];$mode='adaptive';$validHeader=false;$domain='';$theme='';continue;}
            if($inQuiz&&preg_match('/^\s*:::\s*$/u',$line)){
                $raw=trim(implode("\n",$quiz));$parsed=$mode==='adaptive'?AdaptiveQcm::parse($raw):self::parseQuiz($raw);
                $sections[]=['type'=>$mode==='adaptive'?'adaptive':'qcm','mode'=>$mode,'domain'=>$domain,'theme'=>$theme,'source'=>$raw,'index'=>$quizIndex++,'valid'=>$validHeader&&$parsed['valid'],'questions'=>$parsed['questions']];
                $quiz=[];$inQuiz=false;continue;
            }
            if($inQuiz)$quiz[]=$line;else $markdown[]=$line;
        }
        if($inQuiz){
            $raw=trim(implode("\n",$quiz));$parsed=$mode==='adaptive'?AdaptiveQcm::parse($raw):self::parseQuiz($raw);
            $sections[]=['type'=>$mode==='adaptive'?'adaptive':'qcm','mode'=>$mode,'source'=>$raw,'index'=>$quizIndex,'valid'=>false,'questions'=>$parsed['questions']];
        }
        $flushMarkdown();
        return $sections;
    }

    public static function hasValidQuiz(string $source,bool $individualOnly=false): bool
    {
        foreach(self::sections($source) as $section)if(in_array($section['type'],['adaptive','reference'],true)&&!empty($section['valid']))return true;
        foreach(self::sections($source) as $section)if($section['type']==='qcm'&&!empty($section['valid'])&&(!$individualOnly||$section['mode']!=='classe'))return true;
        return false;
    }

    /** @return list<string> */
    public static function quizKeys(string $source,int $blockId,bool $individualOnly=false): array
    {
        $keys=[];
        foreach(self::sections($source) as $section)if($section['type']==='qcm'&&!empty($section['valid'])&&(!$individualOnly||$section['mode']!=='classe'))$keys[]=self::key($blockId,(int)$section['index'],$section['source'],$section['mode']);
        return $keys;
    }

    public static function renderForStudent(PDO $pdo,string $source,int $blockId,int $itemId,int $studentId): string
    {
        $html=[];
        foreach(self::sections($source) as $section){
            if(in_array($section['type'],['adaptive','reference'],true))continue;
            if($section['type']==='markdown'){$html[]=Markdown::render($section['source']);continue;}
            if(empty($section['valid'])){$html[]='<section class="qcm-card qcm-invalid"><b>'.e(t('QCM incomplet')).'</b><p>'.e(t('Ce questionnaire doit être corrigé par l’équipe enseignante.')).'</p></section>';continue;}
            $key=self::key($blockId,(int)$section['index'],$section['source'],$section['mode']);
            if($section['mode']==='classe'){
                $html[]='<section class="qcm-card qcm-class-entry"><h3>'.e(t('QCM en classe')).'</h3><p>'.e(t('Répondez au rythme de votre enseignant.')).'</p><a class="button primary" href="'.e(route('qcm-class',['item'=>$itemId,'block'=>$blockId,'key'=>$key])).'">'.e(t('Rejoindre la séance')).'</a></section>';
            }else{
                $html[]=self::renderQuizForm($pdo,$section['questions'],$key,$blockId,$itemId,$studentId,$section['mode']==='strict');
            }
        }
        return implode("\n",$html);
    }

    public static function renderStatic(string $source): string
    {
        $html=[];
        foreach(self::sections($source) as $section){
            if(in_array($section['type'],['adaptive','reference'],true)){$html[]='<p>'.e(t('QCM adaptatif : activité à réaliser dans le parcours.')).'</p>';continue;}
            if($section['type']==='markdown'){$html[]=Markdown::render($section['source']);continue;}
            if(empty($section['valid'])){$html[]='<section class="qcm-static"><b>'.e(t('QCM incomplet')).'</b></section>';continue;}
            $quiz='<section class="qcm-static"><span>'.e(t('QCM')).'</span>';
            foreach($section['questions'] as $question){
                $quiz.='<div><h3>'.e($question['title']).'</h3><div class="qcm-static-answers">';
                foreach($question['answers'] as $answer)$quiz.='<div class="qcm-static-answer">□ '.e($answer['text']).'</div>';
                $quiz.='</div></div>';
            }
            $html[]=$quiz.'</section>';
        }
        return implode("\n",$html);
    }

    /** Anonymous practice: server-side correction, no draft, attempt or grade writes. */
    public static function renderExternal(string $source,int $blockId,array $post=[],?int $onlyIndex=null): string
    {
        $html='';
        foreach(self::sections($source) as $section){
            if(in_array($section['type'],['adaptive','reference'],true)){$html.='<p>'.e(t('QCM adaptatif : activité à réaliser dans le parcours.')).'</p>';continue;}
            if($section['type']==='markdown'){if($onlyIndex===null)$html.=Markdown::render($section['source']);continue;}
            if($onlyIndex!==null&&(int)$section['index']!==$onlyIndex)continue;
            if(empty($section['valid'])){$html.='<p>'.e(t('QCM incomplet')).'</p>';continue;}
            $key=self::key($blockId,(int)$section['index'],$section['source'],$section['mode']);
            $submitted=($post['action']??'')==='external_qcm'&&is_string($post['quiz']??null)&&$post['quiz']===$key;
            $answers=$submitted&&is_array($post['answers']??null)?$post['answers']:[];
            // Only flat, scalar selections may reach the common scoring function.
            foreach($answers as $name=>$values){$answers[$name]=is_array($values)?array_values(array_filter($values,'is_scalar')):[];}
            $html.='<section class="qcm-card" id="qcm-'.e($key).'"><header><span>'.e(t('QCM')).'</span></header>';
            if($submitted){$result=self::score($section['questions'],$answers);$html.='<p class="alert alert-success mx-3 mt-3" role="status">'.e(t(':correct question(s) juste(s) sur :total',$result)).' · '.e(t('Résultat : :score %',['score'=>self::formatScore($result['score'])])).'</p>';}
            $html.='<form method="post" action="#qcm-'.e($key).'">'.csrf_field().'<input type="hidden" name="action" value="external_qcm"><input type="hidden" name="quiz" value="'.e($key).'">';
            foreach($section['questions'] as $index=>$question){
                $html.='<fieldset class="qcm-question"><legend><span>'.($index+1).'</span>'.e($question['title']).'</legend>';
                $html.='<div class="qcm-options">';
                foreach(self::shuffledAnswers($question['answers']) as $answer)$html.='<label><input type="'.($question['multiple']?'checkbox':'radio').'" name="answers[q'.$index.'][]" value="'.$answer['index'].'"'.(in_array($answer['index'],array_map('intval',$answers['q'.$index]??[]),true)?' checked':'').'><span>'.e($answer['text']).'</span></label>';
                $html.='</div></fieldset>';
            }
            $html.='<button class="button primary btn btn-dark">'.e(t('Vérifier mes réponses')).'</button></form></section>';
        }
        return $html;
    }

    public static function renderPreview(string $source,bool $isEvaluation=false): string
    {
        $html=[];
        foreach(self::sections($source) as $section){
            if(in_array($section['type'],['adaptive','reference'],true)){$html[]='<p>'.e(t(empty($section['valid'])?'Réservoir QCM incomplet.':'Réservoir du QCM adaptatif de la page.')).'</p>';continue;}
            if($section['type']==='markdown'){$html[]=Markdown::render($section['source']);continue;}
            if(empty($section['valid'])){$html[]='<section class="qcm-card qcm-invalid"><b>'.e(t('QCM incomplet')).'</b><p>'.e(t('Ce questionnaire doit être corrigé par l’équipe enseignante.')).'</p></section>';continue;}
            $quiz='<section class="qcm-card qcm-preview"><header><span><i class="bi bi-ui-checks-grid"></i> '.e(t(match($section['mode']){'strict'=>'QCM strict','classe'=>'QCM en classe',default=>'QCM'})).'</span><small>'.e(t('Interaction désactivée dans l’aperçu')).'</small></header><form>';
            if($isEvaluation)$quiz.='<p class="qcm-evaluation-notice"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> '.e(t('Cette évaluation ne peut être envoyée qu’une seule fois.')).'</p>';
            foreach($section['questions'] as $questionIndex=>$question){
                $quiz.='<fieldset class="qcm-question"><legend><span>'.($questionIndex+1).'</span>'.e($question['title']).'</legend><div class="qcm-options">';
                $type=$question['multiple']?'checkbox':'radio';
                foreach(self::shuffledAnswers($question['answers']) as $answer)$quiz.='<label><input type="'.$type.'" name="preview-q'.$questionIndex.'[]" value="'.$answer['index'].'"><span>'.e($answer['text']).'</span></label>';
                $quiz.='</div></fieldset>';
            }
            $html[]=$quiz.'<button class="button primary" type="button" disabled>'.e(t($isEvaluation?'Terminer le QCM':'Vérifier mes réponses')).'</button></form></section>';
        }
        return implode("\n",$html);
    }

    /**
     * @param array<string,mixed> $submitted
     * @return array{status:string,score?:float,correct?:int,total?:int,course_id?:int,is_evaluation?:bool}
     */
    public static function submit(PDO $pdo,int $studentId,int $itemId,int $blockId,string $key,array $submitted,string $epoch='0:0'): array
    {
        return QuizAttempts::transaction($pdo,static fn()=>self::submitCurrent($pdo,$studentId,$itemId,$blockId,$key,$submitted,$epoch));
    }

    private static function submitCurrent(PDO $pdo,int $studentId,int $itemId,int $blockId,string $key,array $submitted,string $epoch='0:0'): array
    {
        $context=self::quizContext($pdo,$studentId,$itemId,$blockId,$key);
        if($context['status']!=='available')return $context;
        if(!hash_equals(QuizAttempts::epoch($context['item']),$epoch))return ['status'=>'conflict'];
        $item=$context['item'];$section=$context['section'];
        $result=self::score($section['questions'],$submitted);
        $isEvaluation=(bool)$item['is_evaluation'];
        if($item['is_controlled_exercise']){
            $pdo->prepare('INSERT INTO qcm_exercise_completions(student_id,pathway_item_id,page_block_id,qcm_key) VALUES(?,?,?,?) ON CONFLICT(student_id,pathway_item_id,page_block_id,qcm_key) DO UPDATE SET attempt_count=attempt_count+1,answered_at=CURRENT_TIMESTAMP')->execute([$studentId,$itemId,$blockId,$key]);
        }else{
        $conflict=$isEvaluation?'DO NOTHING':"DO UPDATE SET
              score_percent=excluded.score_percent,correct_questions=excluded.correct_questions,total_questions=excluded.total_questions,
              attempt_count=qcm_attempts.attempt_count+1,answered_at=CURRENT_TIMESTAMP";
        $save=$pdo->prepare("INSERT INTO qcm_attempts(student_id,pathway_item_id,page_block_id,qcm_key,score_percent,correct_questions,total_questions,attempt_count,answered_at)
            VALUES(?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)
            ON CONFLICT(student_id,pathway_item_id,page_block_id,qcm_key) $conflict");
        $save->execute([$studentId,$itemId,$blockId,$key,$result['score'],$result['correct'],$result['total'],1]);
        if($isEvaluation&&$save->rowCount()!==1)return ['status'=>'already_submitted','course_id'=>(int)$item['course_id']];
        }
        $pdo->prepare('DELETE FROM qcm_drafts WHERE student_id=? AND pathway_item_id=? AND page_block_id=? AND qcm_key=?')->execute([$studentId,$itemId,$blockId,$key]);
        if($item['is_controlled_exercise'])self::completeControlled($pdo,$studentId,$itemId);
        return ['status'=>'saved','score'=>$result['score'],'correct'=>$result['correct'],'total'=>$result['total'],'course_id'=>(int)$item['course_id'],'is_evaluation'=>$isEvaluation,'controlled'=>(bool)$item['is_controlled_exercise']];
    }

    private static function quizContext(PDO $pdo,int $studentId,int $itemId,int $blockId,string $key): array
    {
        $access=$pdo->prepare("SELECT pi.*,COALESCE(pr.quiz_generation,0) AS quiz_generation FROM pathway_items pi
            JOIN enrollments e ON e.course_id=pi.course_id AND e.student_id=? AND e.status='active'
            LEFT JOIN progress pr ON pr.enrollment_id=e.id AND pr.pathway_item_id=pi.id
            WHERE pi.id=? AND NOT EXISTS(SELECT 1 FROM pathway_groups visibility_group WHERE visibility_group.id=pi.group_id AND visibility_group.is_visible=0) AND (pi.access_mode='all' OR (pi.access_mode='restricted' AND EXISTS(
                SELECT 1 FROM pathway_item_students a WHERE a.pathway_item_id=pi.id AND a.student_id=?)))");
        $access->execute([$studentId,$itemId,$studentId]);$item=$access->fetch(PDO::FETCH_ASSOC);
        if(!$item)return ['status'=>'forbidden'];
        $block=$pdo->prepare("SELECT id,body FROM page_blocks WHERE id=? AND page_id=? AND type='markdown'");
        $block->execute([$blockId,$item['page_id']]);$block=$block->fetch(PDO::FETCH_ASSOC);
        if(!$block)return ['status'=>'invalid'];
        foreach(self::sections((string)$block['body']) as $section){
            if($section['type']!=='qcm'||empty($section['valid'])||$section['mode']==='classe')continue;
            $candidate=self::key($blockId,(int)$section['index'],$section['source'],$section['mode']);
            if(!hash_equals($candidate,$key))continue;
            return ['status'=>'available','item'=>$item,'section'=>$section];
        }
        return ['status'=>'changed'];
    }

    /** Completion is retained for a controlled exercise, but never an assessment mark. */
    public static function completeControlled(PDO $pdo,int $studentId,int $itemId,?array $adaptiveRun=null): void
    {
        $q=$pdo->prepare('SELECT pi.*,e.id AS enrollment_id,COALESCE(pr.quiz_generation,0) AS quiz_generation FROM pathway_items pi JOIN enrollments e ON e.course_id=pi.course_id AND e.student_id=? LEFT JOIN progress pr ON pr.enrollment_id=e.id AND pr.pathway_item_id=pi.id WHERE pi.id=? AND pi.is_controlled_exercise=1');
        $q->execute([$studentId,$itemId]);$item=$q->fetch(PDO::FETCH_ASSOC);if(!$item)return;
        $q=$pdo->prepare("SELECT id,body FROM page_blocks WHERE page_id=? AND type='markdown'");$q->execute([$item['page_id']]);$expected=[];$adaptive=false;
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $block){
            $adaptive=$adaptive||AdaptiveQcm::has($block['body']);
            foreach(self::quizKeys($block['body'],(int)$block['id'],true) as $key)$expected[$block['id'].':'.$key]=true;
        }
        if(!$expected&&!$adaptive)return;
        $q=$pdo->prepare('SELECT page_block_id,qcm_key FROM qcm_exercise_completions WHERE student_id=? AND pathway_item_id=?');$q->execute([$studentId,$itemId]);
        foreach($q as $answer)unset($expected[$answer['page_block_id'].':'.$answer['qcm_key']]);
        if($expected)return;
        if($adaptive){
            $adaptiveRun??=AdaptiveQcm::store($pdo,false)?->latest($studentId,$itemId,QuizAttempts::epoch($item));
            if(!$adaptiveRun||$adaptiveRun['finished_at']===null||$adaptiveRun['epoch']!==QuizAttempts::epoch($item))return;
        }
        $pdo->prepare('INSERT INTO progress(enrollment_id,pathway_item_id,completed_at) VALUES(?,?,CURRENT_TIMESTAMP) ON CONFLICT(enrollment_id,pathway_item_id) DO UPDATE SET completed_at=COALESCE(progress.completed_at,CURRENT_TIMESTAMP),updated_at=CURRENT_TIMESTAMP')->execute([$item['enrollment_id'],$itemId]);
    }

    public static function saveDraft(PDO $pdo,int $studentId,int $itemId,int $blockId,string $key,array $submitted,int $revision=0,int $attemptCount=0,string $epoch='0:0'): array
    {
        return QuizAttempts::transaction($pdo,static fn()=>self::saveDraftCurrent($pdo,$studentId,$itemId,$blockId,$key,$submitted,$revision,$attemptCount,$epoch));
    }

    private static function saveDraftCurrent(PDO $pdo,int $studentId,int $itemId,int $blockId,string $key,array $submitted,int $revision=0,int $attemptCount=0,string $epoch='0:0'): array
    {
        $context=self::quizContext($pdo,$studentId,$itemId,$blockId,$key);
        if($context['status']!=='available')return ['status'=>$context['status']];
        if(!hash_equals(QuizAttempts::epoch($context['item']),$epoch))return ['status'=>'conflict'];
        $answers=[];
        foreach($context['section']['questions'] as $index=>$question){
            $selected=$submitted['q'.$index]??[];
            if(!is_array($selected))return ['status'=>'invalid'];
            $values=[];
            foreach($selected as $value){
                if(!is_scalar($value)||!preg_match('/^\d+$/D',(string)$value)||!isset($question['answers'][(int)$value]))return ['status'=>'invalid'];
                $values[]=(int)$value;
            }
            $values=array_values(array_unique($values));
            if(!$question['multiple']&&count($values)>1)return ['status'=>'invalid'];
            $answers['q'.$index]=$values;
        }
        $attemptTable=$context['item']['is_controlled_exercise']?'qcm_exercise_completions':'qcm_attempts';
        // One atomic statement rejects stale tabs and saves arriving after final submission.
        $save=$pdo->prepare("INSERT INTO qcm_drafts(student_id,pathway_item_id,page_block_id,qcm_key,answers,revision)
            SELECT ?,?,?,?,?,? WHERE
              COALESCE((SELECT attempt_count FROM $attemptTable WHERE student_id=? AND pathway_item_id=? AND page_block_id=? AND qcm_key=?),0)=CAST(? AS INTEGER)
              AND (CAST(? AS INTEGER)=0 OR CAST(? AS INTEGER)=0)
              AND COALESCE((SELECT revision FROM qcm_drafts WHERE student_id=? AND pathway_item_id=? AND page_block_id=? AND qcm_key=?),0)=CAST(? AS INTEGER)
            ON CONFLICT(student_id,pathway_item_id,page_block_id,qcm_key)
            DO UPDATE SET answers=excluded.answers,revision=excluded.revision,updated_at=CURRENT_TIMESTAMP");
        $ids=[$studentId,$itemId,$blockId,$key];
        $save->execute(array_merge($ids,[json_encode($answers,JSON_THROW_ON_ERROR),$revision+1],$ids,[$attemptCount,(int)$context['item']['is_evaluation'],$attemptCount],$ids,[$revision]));
        return $save->rowCount()===1?['status'=>'saved','revision'=>$revision+1]:['status'=>'conflict'];
    }

    public static function summary(PDO $pdo,int $studentId,int $itemId): ?array
    {
        if(function_exists('adaptive_qcm_results')){
            $adaptive=adaptive_qcm_results($pdo,[$itemId])[$itemId][$studentId]??null;
            if($adaptive)return $adaptive+['correct'=>$adaptive['correct_questions'],'total'=>$adaptive['total_questions'],'attempts'=>$adaptive['attempt_count']];
        }
        $query=$pdo->prepare("SELECT SUM(correct_questions) AS correct,SUM(total_questions) AS total,SUM(attempt_count) AS attempts,MAX(answered_at) AS answered_at
            FROM qcm_attempts WHERE student_id=? AND pathway_item_id=?");
        $query->execute([$studentId,$itemId]);$row=$query->fetch(PDO::FETCH_ASSOC);
        if(!$row||(int)$row['total']<1)return null;
        $row['correct']=(int)$row['correct'];$row['total']=(int)$row['total'];$row['attempts']=(int)$row['attempts'];
        $row['score_percent']=round($row['correct']/$row['total']*100,1);
        return $row;
    }

    /**
     * @return array{expected:array<int,int>,completed:array<int,array<int,bool>>}
     */
    public static function courseEvaluationCompletion(PDO $pdo,int $courseId): array
    {
        $blocks=$pdo->prepare("SELECT pi.id AS item_id,b.id AS block_id,b.body
            FROM pathway_items pi JOIN page_blocks b ON b.page_id=pi.page_id AND b.type='markdown'
            WHERE pi.course_id=? AND pi.is_evaluation=1 ORDER BY pi.id,b.position,b.id");
        $blocks->execute([$courseId]);$expectedKeys=[];
        foreach($blocks->fetchAll(PDO::FETCH_ASSOC) as $block){
            $itemId=(int)$block['item_id'];$blockId=(int)$block['block_id'];
            if(AdaptiveQcm::enabled()&&AdaptiveQcm::has($block['body']))$expectedKeys[$itemId]['adaptive']=true;
            foreach(self::quizKeys((string)$block['body'],$blockId,true) as $key)$expectedKeys[$itemId][$blockId."\0".$key]=true;
        }
        if(!$expectedKeys)return ['expected'=>[],'completed'=>[]];
        $attempts=$pdo->prepare("SELECT qa.student_id,qa.pathway_item_id,qa.page_block_id,qa.qcm_key
            FROM qcm_attempts qa JOIN pathway_items pi ON pi.id=qa.pathway_item_id
            JOIN enrollments e ON e.course_id=pi.course_id AND e.student_id=qa.student_id AND e.status='active'
            JOIN users u ON u.id=qa.student_id AND u.account_status='active'
            WHERE pi.course_id=? AND pi.is_evaluation=1");
        $attempts->execute([$courseId]);$answered=[];
        foreach($attempts->fetchAll(PDO::FETCH_ASSOC) as $attempt){
            $itemId=(int)$attempt['pathway_item_id'];$key=(int)$attempt['page_block_id']."\0".(string)$attempt['qcm_key'];
            if(isset($expectedKeys[$itemId][$key]))$answered[(int)$attempt['student_id']][$itemId][$key]=true;
        }
        $expected=[];$completed=[];
        if(function_exists('adaptive_qcm_results'))foreach(adaptive_qcm_results($pdo,array_keys($expectedKeys),true) as $itemId=>$results)foreach($results as $studentId=>$result)if(isset($expectedKeys[$itemId]['adaptive']))$answered[$studentId][$itemId]['adaptive']=true;
        foreach($expectedKeys as $itemId=>$keys)$expected[(int)$itemId]=count($keys);
        foreach($answered as $studentId=>$items)foreach($items as $itemId=>$keys)if(count($keys)===$expected[$itemId])$completed[(int)$studentId][(int)$itemId]=true;
        return ['expected'=>$expected,'completed'=>$completed];
    }

    /** @return list<array{item_id:int,position:int|string,title:string,score_percent:?float}> */
    public static function courseStepAverages(PDO $pdo,int $courseId): array
    {
        $query=$pdo->prepare("SELECT pi.id AS item_id,pi.position,p.title,b.body
            FROM pathway_items pi JOIN pages p ON p.id=pi.page_id
            JOIN page_blocks b ON b.page_id=p.id AND b.type='markdown'
            WHERE pi.course_id=? ORDER BY pi.position,b.position,b.id");
        $query->execute([$courseId]);$steps=[];
        foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row){
            $itemId=(int)$row['item_id'];
            if(isset($steps[$itemId])||!self::hasValidQuiz((string)$row['body'],true))continue;
            $steps[$itemId]=['item_id'=>$itemId,'position'=>(int)$row['position'],'title'=>(string)$row['title'],'score_percent'=>null];
        }
        if(!$steps)return [];
        $placeholders=implode(',',array_fill(0,count($steps),'?'));
        $scores=$pdo->prepare("SELECT pathway_item_id,AVG(student_score) AS score_percent FROM (
              SELECT qa.student_id,qa.pathway_item_id,100.0*SUM(qa.correct_questions)/SUM(qa.total_questions) AS student_score
              FROM qcm_attempts qa JOIN pathway_items pi ON pi.id=qa.pathway_item_id
              JOIN enrollments e ON e.course_id=pi.course_id AND e.student_id=qa.student_id AND e.status='active'
              JOIN users u ON u.id=qa.student_id AND u.account_status='active'
              LEFT JOIN progress pr ON pr.enrollment_id=e.id AND pr.pathway_item_id=pi.id
              WHERE qa.pathway_item_id IN ($placeholders) AND (pi.is_evaluation=0 OR COALESCE(pr.evaluation_included,1)=1)
              GROUP BY qa.student_id,qa.pathway_item_id
            ) GROUP BY pathway_item_id");
        $scores->execute(array_keys($steps));
        foreach($scores->fetchAll(PDO::FETCH_ASSOC) as $score){
            $itemId=(int)$score['pathway_item_id'];
            if(isset($steps[$itemId]))$steps[$itemId]['score_percent']=round((float)$score['score_percent'],1);
        }
        $displayPositions=pathway_display_position_map($pdo,$courseId);
        if(function_exists('adaptive_qcm_results')){
            $adaptive=adaptive_qcm_results($pdo,array_keys($steps));$grouped=[];
            if($adaptive){
                $eligible=$pdo->prepare("SELECT pi.id,e.student_id FROM pathway_items pi JOIN enrollments e ON e.course_id=pi.course_id AND e.status='active'
                    JOIN users u ON u.id=e.student_id AND u.account_status='active' LEFT JOIN progress pr ON pr.enrollment_id=e.id AND pr.pathway_item_id=pi.id
                    WHERE pi.course_id=? AND (pi.is_evaluation=0 OR COALESCE(pr.evaluation_included,1)=1)");$eligible->execute([$courseId]);
                foreach($eligible->fetchAll(PDO::FETCH_ASSOC) as $row)if(isset($adaptive[$row['id']][$row['student_id']]))$grouped[$row['id']][]=$adaptive[$row['id']][$row['student_id']]['score_percent'];
                foreach($grouped as $itemId=>$values)$steps[$itemId]['score_percent']=round(array_sum($values)/count($values),1);
            }
        }
        foreach($steps as &$step)$step['position']=$displayPositions[(int)$step['item_id']]??'–';
        unset($step);
        return array_values($steps);
    }

    public static function syncPageTag(PDO $pdo,int $pageId): void
    {
        $tag=$pdo->query("SELECT id FROM tags WHERE lower(name)='qcm' LIMIT 1")->fetchColumn();
        if($tag===false){$pdo->prepare('INSERT INTO tags(name,color) VALUES(?,?)')->execute(['QCM','#dff5ef']);$tag=$pdo->lastInsertId();}
        $blocks=$pdo->prepare("SELECT body FROM page_blocks WHERE page_id=? AND type='markdown'");$blocks->execute([$pageId]);$hasQuiz=false;
        foreach($blocks->fetchAll(PDO::FETCH_COLUMN) as $body)if(self::hasValidQuiz((string)$body)){$hasQuiz=true;break;}
        if($hasQuiz)$pdo->prepare('INSERT OR IGNORE INTO page_tags(page_id,tag_id) VALUES(?,?)')->execute([$pageId,(int)$tag]);
        else $pdo->prepare('DELETE FROM page_tags WHERE page_id=? AND tag_id=?')->execute([$pageId,(int)$tag]);
    }

    public static function syncAttemptsForBlock(PDO $pdo,int $blockId,string $source): void
    {
        $keys=self::quizKeys($source,$blockId);
        foreach(['qcm_attempts','qcm_drafts'] as $table){
            if(!$keys){$pdo->prepare("DELETE FROM $table WHERE page_block_id=?")->execute([$blockId]);continue;}
            $placeholders=implode(',',array_fill(0,count($keys),'?'));
            $pdo->prepare("DELETE FROM $table WHERE page_block_id=? AND qcm_key NOT IN ($placeholders)")->execute(array_merge([$blockId],$keys));
        }
    }

    public static function key(int $blockId,int $index,string $source,string $mode='classic'): string
    {
        return substr(hash('sha256',$blockId."\0".$index."\0".trim($source).($mode==='classic'?'':"\0".$mode)),0,32);
    }

    private static function parseQuiz(string $source): array
    {
        if(preg_match('/^\s*\[[0-3]\]\[[0-9]+\]\[/mu',$source))return AdaptiveQcm::parse($source,false);
        $questions=[];$current=null;$valid=true;
        foreach(explode("\n",$source) as $line){
            $trim=trim($line);if($trim==='')continue;
            if(preg_match('/^#{1,4}\s+(.+)$/u',$trim,$match)){
                if($current!==null)$questions[]=$current;
                $current=['title'=>trim($match[1]),'answers'=>[]];continue;
            }
            if(preg_match('/^(?:[-*+]\s+)?\[(v|-|x)\]\s+(.+)$/iu',$trim,$match)&&$current!==null){
                $current['answers'][]=['kind'=>mb_strtolower($match[1],'UTF-8'),'text'=>trim($match[2])];continue;
            }
            $valid=false;
        }
        if($current!==null)$questions[]=$current;
        if(!$questions)$valid=false;
        foreach($questions as &$question){
            $correct=0;foreach($question['answers'] as $index=>&$answer){$answer['index']=$index;if($answer['kind']==='v')$correct++;}unset($answer);
            $question['multiple']=$correct>1;
            if($question['title']===''||count($question['answers'])<2||$correct<1)$valid=false;
        }unset($question);
        return ['valid'=>$valid,'questions'=>$questions];
    }

    private static function score(array $questions,array $submitted): array
    {
        $correctQuestions=0;
        foreach($questions as $questionIndex=>$question){
            $answerIndexes=array_column($question['answers'],'index');
            $selected=array_values(array_intersect($answerIndexes,array_unique(array_map('intval',(array)($submitted['q'.$questionIndex]??[])))));
            $correct=[];$false=[];
            foreach($question['answers'] as $answer){if($answer['kind']==='v')$correct[]=$answer['index'];elseif($answer['kind']==='x')$false[]=$answer['index'];}
            if(!array_diff($correct,$selected)&&!array_intersect($false,$selected))$correctQuestions++;
        }
        $total=count($questions);
        return ['correct'=>$correctQuestions,'total'=>$total,'score'=>$total?round($correctQuestions/$total*100,2):0.0];
    }

    private static function renderQuizForm(PDO $pdo,array $questions,string $key,int $blockId,int $itemId,int $studentId,bool $strict=false): string
    {
        $evaluationQuery=$pdo->prepare('SELECT pi.*,COALESCE(pr.quiz_generation,0) AS quiz_generation FROM pathway_items pi LEFT JOIN enrollments e ON e.course_id=pi.course_id AND e.student_id=? LEFT JOIN progress pr ON pr.enrollment_id=e.id AND pr.pathway_item_id=pi.id WHERE pi.id=?');
        $evaluationQuery->execute([$studentId,$itemId]);$item=$evaluationQuery->fetch(PDO::FETCH_ASSOC);$isEvaluation=(bool)$item['is_evaluation'];$controlled=(bool)$item['is_controlled_exercise'];$epoch=QuizAttempts::epoch($item);
        $query=$pdo->prepare('SELECT * FROM '.($controlled?'qcm_exercise_completions':'qcm_attempts').' WHERE student_id=? AND pathway_item_id=? AND page_block_id=? AND qcm_key=?');
        $query->execute([$studentId,$itemId,$blockId,$key]);$attempt=$query->fetch(PDO::FETCH_ASSOC);
        $draftId=$studentId.':'.$itemId.':'.$blockId.':'.$key.':'.$epoch;
        $html='<section class="qcm-card" data-qcm-storage="'.e($draftId).'" id="qcm-'.e($key).'"><header><span><i class="bi bi-ui-checks-grid"></i> '.e(t('QCM')).'</span>';
        if($attempt&&$controlled)$html.='<p>'.e(t('Exercice terminé, aucune note conservée.')).'</p>';
        if($attempt&&!$controlled)$html.='<div class="qcm-result"><strong>'.e(t('Résultat : :score %',['score'=>self::formatScore((float)$attempt['score_percent'])])).'</strong><small>'.e(t(':correct question(s) juste(s) sur :total',['correct'=>$attempt['correct_questions'],'total'=>$attempt['total_questions']])).'</small></div>';
        $html.='</header>';
        if($isEvaluation&&$attempt)return $html.'<div class="qcm-evaluation-complete"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><p><b>'.e(t('QCM terminé')).'</b><span>'.e(t('Vos réponses ont été enregistrées définitivement.')).'</span></p></div></section>';
        $query=$pdo->prepare('SELECT answers,revision FROM qcm_drafts WHERE student_id=? AND pathway_item_id=? AND page_block_id=? AND qcm_key=?');
        $query->execute([$studentId,$itemId,$blockId,$key]);$draft=$query->fetch(PDO::FETCH_ASSOC);
        $selected=$draft?json_decode($draft['answers'],true):[];
        $confirm=$isEvaluation?' data-qcm-confirm="'.e(t('Terminer définitivement ce QCM ? Vous ne pourrez plus modifier vos réponses.')).'"':'';
        $html.='<form method="post" data-qcm-form data-revision="'.(int)($draft['revision']??0).'" data-attempt-count="'.(int)($attempt['attempt_count']??0).'"'.$confirm.'>'.csrf_field().'<input type="hidden" name="quiz_epoch" value="'.e($epoch).'"><input type="hidden" name="action" value="submit_qcm"><input type="hidden" name="item_id" value="'.$itemId.'"><input type="hidden" name="block_id" value="'.$blockId.'"><input type="hidden" name="qcm_key" value="'.e($key).'">';
        if($isEvaluation)$html.='<p class="qcm-evaluation-notice"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> '.e(t('Cette évaluation ne peut être envoyée qu’une seule fois.')).'</p>';
        foreach($questions as $questionIndex=>$question){
            $html.='<fieldset class="qcm-question"><legend><span>'.($questionIndex+1).'</span>'.e($question['title']).'</legend><div class="qcm-options">';
            $type=$question['multiple']?'checkbox':'radio';
            foreach(self::shuffledAnswers($question['answers']) as $answer)$html.='<label><input type="'.$type.'" name="answers[q'.$questionIndex.'][]" value="'.$answer['index'].'"'.(in_array($answer['index'],$selected['q'.$questionIndex]??[],true)?' checked':'').'><span>'.e($answer['text']).'</span></label>';
            $html.='</div></fieldset>';
        }
        $button=$isEvaluation?t('Terminer le QCM'):($attempt?t('Réessayer'):t('Vérifier mes réponses'));
        $html.='<p class="muted" role="status" aria-live="polite" data-qcm-status data-saved="'.e(t('Brouillon enregistré. Vous pouvez fermer et reprendre ce QCM.')).'" data-saving="'.e(t('Enregistrement du brouillon…')).'" data-error="'.e(t('Brouillon non enregistré sur le serveur. Gardez cette page ouverte et vérifiez votre connexion.')).'" data-conflict="'.e(t('Ce QCM a été modifié ou terminé ailleurs. Rechargez la page avant de continuer.')).'">'.e(t($draft?'Brouillon enregistré. Vous pouvez fermer et reprendre ce QCM.':'Vos réponses seront sauvegardées automatiquement pendant la saisie.')).'</p>';
        $form=$html.'<button class="button primary" type="submit">'.e($button).'</button></form></section>';
        return $strict?self::strictForm($form,$controlled?null:($attempt?:null)):$form;
    }

    public static function uiLabels(): array
    {
        return [
            'fullscreenUnavailable'=>t('Le plein écran n’est pas disponible. Le questionnaire reste utilisable dans cette fenêtre.'),
            'fullscreenExited'=>t('Plein écran quitté. Vos réponses sont conservées ; vous pouvez continuer ou réactiver le plein écran.'),
            'copied'=>t('Exemple copié.'),
            'copyFallback'=>t('Exemple sélectionné : utilisez la commande Copier de votre appareil.'),
            'choose'=>t('Sélectionnez une réponse.'),
        ];
    }

    private static function strictForm(string $form,?array $attempt): string
    {
        $result=$attempt?'<p class="qcm-strict-result"><strong>'.e(t('Résultat : :score %',['score'=>self::formatScore((float)$attempt['score_percent'])])).'</strong></p>':'';
        return '<div class="qcm-strict" data-qcm-strict data-qcm-labels="'.e(json_encode(self::uiLabels(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)).'"><div data-qcm-strict-intro><h3>'.e(t('QCM strict')).'</h3>'.$result.'<p>'.e(t('Le questionnaire masque la navigation du cours jusqu’à la remise. Le plein écran dépend de votre navigateur et ne verrouille pas votre appareil.')).'</p><button type="button" class="button primary" data-qcm-start>'.e(t($attempt?'Réessayer':'Commencer le QCM')).'</button><noscript><p>'.e(t('Activez JavaScript pour ouvrir ce questionnaire.')).'</p></noscript></div><dialog class="qcm-strict-dialog" aria-label="'.e(t('QCM strict')).'"><div class="qcm-strict-toolbar"><strong>'.e(t('QCM strict')).'</strong><button type="button" class="button secondary" data-qcm-fullscreen>'.e(t('Plein écran')).'</button></div><p data-qcm-screen-status role="status"></p>'.$form.'</dialog></div>';
    }

    private static function shuffledAnswers(array $answers): array
    {
        shuffle($answers);
        return $answers;
    }

    public static function formatScore(float $score): string
    {
        return abs($score-round($score))<0.001?(string)(int)round($score):number_format($score,1,',','');
    }
}
