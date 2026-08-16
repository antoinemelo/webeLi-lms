<?php

declare(strict_types=1);

final class Qcm
{
    /**
     * @return list<array{type:'markdown'|'qcm',source:string,index?:int,valid?:bool,questions?:array}>
     */
    public static function sections(string $source): array
    {
        $lines=explode("\n",str_replace(["\r\n","\r"],"\n",$source));
        $sections=[];$markdown=[];$quiz=[];$inQuiz=false;$quizIndex=0;
        $flushMarkdown=static function()use(&$sections,&$markdown):void{
            $text=trim(implode("\n",$markdown));$markdown=[];
            if($text!=='')$sections[]=['type'=>'markdown','source'=>$text];
        };
        foreach($lines as $line){
            if(!$inQuiz&&preg_match('/^\s*:::\s*qcm\s*$/iu',$line)){
                $flushMarkdown();$inQuiz=true;$quiz=[];continue;
            }
            if($inQuiz&&preg_match('/^\s*:::\s*$/u',$line)){
                $raw=trim(implode("\n",$quiz));$parsed=self::parseQuiz($raw);
                $sections[]=['type'=>'qcm','source'=>$raw,'index'=>$quizIndex++,'valid'=>$parsed['valid'],'questions'=>$parsed['questions']];
                $quiz=[];$inQuiz=false;continue;
            }
            if($inQuiz)$quiz[]=$line;else $markdown[]=$line;
        }
        if($inQuiz){
            $raw=trim(implode("\n",$quiz));$parsed=self::parseQuiz($raw);
            $sections[]=['type'=>'qcm','source'=>$raw,'index'=>$quizIndex,'valid'=>false,'questions'=>$parsed['questions']];
        }
        $flushMarkdown();
        return $sections;
    }

    public static function hasValidQuiz(string $source): bool
    {
        foreach(self::sections($source) as $section)if($section['type']==='qcm'&&!empty($section['valid']))return true;
        return false;
    }

    /** @return list<string> */
    public static function quizKeys(string $source,int $blockId): array
    {
        $keys=[];
        foreach(self::sections($source) as $section)if($section['type']==='qcm'&&!empty($section['valid']))$keys[]=self::key($blockId,(int)$section['index'],$section['source']);
        return $keys;
    }

    public static function renderForStudent(PDO $pdo,string $source,int $blockId,int $itemId,int $studentId): string
    {
        $html=[];
        foreach(self::sections($source) as $section){
            if($section['type']==='markdown'){$html[]=Markdown::render($section['source']);continue;}
            if(empty($section['valid'])){$html[]='<section class="qcm-card qcm-invalid"><b>'.e(t('QCM incomplet')).'</b><p>'.e(t('Ce questionnaire doit être corrigé par l’équipe enseignante.')).'</p></section>';continue;}
            $key=self::key($blockId,(int)$section['index'],$section['source']);
            $html[]=self::renderQuizForm($pdo,$section['questions'],$key,$blockId,$itemId,$studentId);
        }
        return implode("\n",$html);
    }

    public static function renderStatic(string $source): string
    {
        $html=[];
        foreach(self::sections($source) as $section){
            if($section['type']==='markdown'){$html[]=Markdown::render($section['source']);continue;}
            if(empty($section['valid'])){$html[]='<section class="qcm-static"><b>'.e(t('QCM incomplet')).'</b></section>';continue;}
            $quiz='<section class="qcm-static"><span>'.e(t('QCM')).'</span>';
            foreach($section['questions'] as $question){
                $quiz.='<div><h3>'.e($question['title']).'</h3><ul>';
                foreach($question['answers'] as $answer)$quiz.='<li>□ '.e($answer['text']).'</li>';
                $quiz.='</ul></div>';
            }
            $html[]=$quiz.'</section>';
        }
        return implode("\n",$html);
    }

    /**
     * @param array<string,mixed> $submitted
     * @return array{status:string,score?:float,correct?:int,total?:int,course_id?:int}
     */
    public static function submit(PDO $pdo,int $studentId,int $itemId,int $blockId,string $key,array $submitted): array
    {
        $access=$pdo->prepare("SELECT pi.id,pi.page_id,pi.course_id FROM pathway_items pi
            JOIN enrollments e ON e.course_id=pi.course_id AND e.student_id=? AND e.status='active'
            WHERE pi.id=? AND (pi.access_mode='all' OR (pi.access_mode='restricted' AND EXISTS(
                SELECT 1 FROM pathway_item_students a WHERE a.pathway_item_id=pi.id AND a.student_id=?)))");
        $access->execute([$studentId,$itemId,$studentId]);$item=$access->fetch(PDO::FETCH_ASSOC);
        if(!$item)return ['status'=>'forbidden'];
        $block=$pdo->prepare("SELECT id,body FROM page_blocks WHERE id=? AND page_id=? AND type='markdown'");
        $block->execute([$blockId,$item['page_id']]);$block=$block->fetch(PDO::FETCH_ASSOC);
        if(!$block)return ['status'=>'invalid'];
        foreach(self::sections((string)$block['body']) as $section){
            if($section['type']!=='qcm'||empty($section['valid']))continue;
            $candidate=self::key($blockId,(int)$section['index'],$section['source']);
            if(!hash_equals($candidate,$key))continue;
            $result=self::score($section['questions'],$submitted);
            $save=$pdo->prepare("INSERT INTO qcm_attempts(student_id,pathway_item_id,page_block_id,qcm_key,score_percent,correct_questions,total_questions,attempt_count,answered_at)
                VALUES(?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)
                ON CONFLICT(student_id,pathway_item_id,page_block_id,qcm_key) DO UPDATE SET
                  score_percent=excluded.score_percent,correct_questions=excluded.correct_questions,total_questions=excluded.total_questions,
                  attempt_count=qcm_attempts.attempt_count+1,answered_at=CURRENT_TIMESTAMP");
            $save->execute([$studentId,$itemId,$blockId,$key,$result['score'],$result['correct'],$result['total'],1]);
            return ['status'=>'saved','score'=>$result['score'],'correct'=>$result['correct'],'total'=>$result['total'],'course_id'=>(int)$item['course_id']];
        }
        return ['status'=>'changed'];
    }

    public static function summary(PDO $pdo,int $studentId,int $itemId): ?array
    {
        $query=$pdo->prepare("SELECT SUM(correct_questions) AS correct,SUM(total_questions) AS total,SUM(attempt_count) AS attempts,MAX(answered_at) AS answered_at
            FROM qcm_attempts WHERE student_id=? AND pathway_item_id=?");
        $query->execute([$studentId,$itemId]);$row=$query->fetch(PDO::FETCH_ASSOC);
        if(!$row||(int)$row['total']<1)return null;
        $row['correct']=(int)$row['correct'];$row['total']=(int)$row['total'];$row['attempts']=(int)$row['attempts'];
        $row['score_percent']=round($row['correct']/$row['total']*100,1);
        return $row;
    }

    /** @return list<array{item_id:int,position:int,title:string,score_percent:?float}> */
    public static function courseStepAverages(PDO $pdo,int $courseId): array
    {
        $query=$pdo->prepare("SELECT pi.id AS item_id,pi.position,p.title,b.body
            FROM pathway_items pi JOIN pages p ON p.id=pi.page_id
            JOIN page_blocks b ON b.page_id=p.id AND b.type='markdown'
            WHERE pi.course_id=? ORDER BY pi.position,b.position,b.id");
        $query->execute([$courseId]);$steps=[];
        foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row){
            $itemId=(int)$row['item_id'];
            if(isset($steps[$itemId])||!self::hasValidQuiz((string)$row['body']))continue;
            $steps[$itemId]=['item_id'=>$itemId,'position'=>(int)$row['position'],'title'=>(string)$row['title'],'score_percent'=>null];
        }
        if(!$steps)return [];
        $placeholders=implode(',',array_fill(0,count($steps),'?'));
        $scores=$pdo->prepare("SELECT pathway_item_id,AVG(student_score) AS score_percent FROM (
              SELECT qa.student_id,qa.pathway_item_id,100.0*SUM(qa.correct_questions)/SUM(qa.total_questions) AS student_score
              FROM qcm_attempts qa JOIN pathway_items pi ON pi.id=qa.pathway_item_id
              JOIN enrollments e ON e.course_id=pi.course_id AND e.student_id=qa.student_id AND e.status='active'
              JOIN users u ON u.id=qa.student_id AND u.account_status='active'
              WHERE qa.pathway_item_id IN ($placeholders)
                AND (pi.access_mode='all' OR (pi.access_mode='restricted' AND EXISTS(
                  SELECT 1 FROM pathway_item_students a WHERE a.pathway_item_id=pi.id AND a.student_id=qa.student_id)))
              GROUP BY qa.student_id,qa.pathway_item_id
            ) GROUP BY pathway_item_id");
        $scores->execute(array_keys($steps));
        foreach($scores->fetchAll(PDO::FETCH_ASSOC) as $score){
            $itemId=(int)$score['pathway_item_id'];
            if(isset($steps[$itemId]))$steps[$itemId]['score_percent']=round((float)$score['score_percent'],1);
        }
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
        if(!$keys){$pdo->prepare('DELETE FROM qcm_attempts WHERE page_block_id=?')->execute([$blockId]);return;}
        $placeholders=implode(',',array_fill(0,count($keys),'?'));
        $delete=$pdo->prepare('DELETE FROM qcm_attempts WHERE page_block_id=? AND qcm_key NOT IN ('.$placeholders.')');
        $delete->execute(array_merge([$blockId],$keys));
    }

    private static function key(int $blockId,int $index,string $source): string
    {
        return substr(hash('sha256',$blockId."\0".$index."\0".trim($source)),0,32);
    }

    private static function parseQuiz(string $source): array
    {
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

    private static function renderQuizForm(PDO $pdo,array $questions,string $key,int $blockId,int $itemId,int $studentId): string
    {
        $query=$pdo->prepare('SELECT score_percent,correct_questions,total_questions,attempt_count,answered_at FROM qcm_attempts WHERE student_id=? AND pathway_item_id=? AND page_block_id=? AND qcm_key=?');
        $query->execute([$studentId,$itemId,$blockId,$key]);$attempt=$query->fetch(PDO::FETCH_ASSOC);
        $html='<section class="qcm-card" id="qcm-'.e($key).'"><header><span><i class="bi bi-ui-checks-grid"></i> '.e(t('QCM')).'</span>';
        if($attempt)$html.='<div class="qcm-result"><strong>'.e(t('Résultat : :score %',['score'=>self::formatScore((float)$attempt['score_percent'])])).'</strong><small>'.e(t(':correct question(s) juste(s) sur :total',['correct'=>$attempt['correct_questions'],'total'=>$attempt['total_questions']])).'</small></div>';
        $html.='</header><form method="post">'.csrf_field().'<input type="hidden" name="action" value="submit_qcm"><input type="hidden" name="item_id" value="'.$itemId.'"><input type="hidden" name="block_id" value="'.$blockId.'"><input type="hidden" name="qcm_key" value="'.e($key).'">';
        foreach($questions as $questionIndex=>$question){
            $html.='<fieldset class="qcm-question"><legend><span>'.($questionIndex+1).'</span>'.e($question['title']).'</legend><div class="qcm-options">';
            $type=$question['multiple']?'checkbox':'radio';
            foreach($question['answers'] as $answer)$html.='<label><input type="'.$type.'" name="answers[q'.$questionIndex.'][]" value="'.$answer['index'].'"><span>'.e($answer['text']).'</span></label>';
            $html.='</div></fieldset>';
        }
        $button=$attempt?t('Réessayer'):t('Vérifier mes réponses');
        return $html.'<button class="button primary" type="submit">'.e($button).'</button></form></section>';
    }

    public static function formatScore(float $score): string
    {
        return abs($score-round($score))<0.001?(string)(int)round($score):number_format($score,1,',','');
    }
}
