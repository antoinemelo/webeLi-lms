<?php
declare(strict_types=1);

/** A snapshot of one assessed step; query count does not depend on class size. */
function activity_results_data(PDO $pdo,int $itemId,int $teacherId): array
{
    $own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();
    try{
        $q=$pdo->prepare("SELECT pi.*,p.title,c.title AS course_title,c.code AS course_code,g.title AS group_title,
            COALESCE(g.is_visible,1) AS group_visible
            FROM pathway_items pi JOIN pages p ON p.id=pi.page_id JOIN courses c ON c.id=pi.course_id
            LEFT JOIN pathway_groups g ON g.id=pi.group_id AND g.course_id=pi.course_id
            JOIN users teacher ON teacher.id=? AND teacher.role='teacher' AND teacher.account_status='active'
            WHERE pi.id=? AND pi.is_evaluation=1");
        $q->execute([$teacherId,$itemId]);$item=$q->fetch(PDO::FETCH_ASSOC);
        if(!$item||!teacher_can_access_course($pdo,(int)$item['course_id'],$teacherId))throw new InvalidArgumentException('Accès interdit.');
        $q=$pdo->prepare("SELECT u.id,u.first_name,u.last_name,u.email,u.class_group,e.id AS enrollment_id,
            pr.completed_at,pr.evaluation_score,COALESCE(pr.evaluation_included,1) AS evaluation_included,
            pr.teacher_validated_at,pr.teacher_note,pr.student_note,pr.student_validated_at,
            CASE WHEN pi.access_mode='all' OR (pi.access_mode='restricted' AND a.student_id IS NOT NULL) THEN 1 ELSE 0 END AS accessible
            FROM enrollments e JOIN users u ON u.id=e.student_id AND u.role='student' AND u.account_status='active'
            JOIN pathway_items pi ON pi.course_id=e.course_id AND pi.id=?
            LEFT JOIN progress pr ON pr.enrollment_id=e.id AND pr.pathway_item_id=pi.id
            LEFT JOIN pathway_item_students a ON a.pathway_item_id=pi.id AND a.student_id=u.id
            WHERE e.course_id=? AND e.status='active'");
        $q->execute([$itemId,$item['course_id']]);$students=$q->fetchAll(PDO::FETCH_ASSOC);
        usort($students,static fn(array $a,array $b):int=>pathway_natural_compare($a['last_name'],$b['last_name'])?:pathway_natural_compare($a['first_name'],$b['first_name'])?:($a['id']<=>$b['id']));
        $q=$pdo->prepare("SELECT id,type,body,caption FROM page_blocks WHERE page_id=? AND type IN ('markdown','submission') ORDER BY position,id");
        $q->execute([$item['page_id']]);$quizzes=[];$workBlocks=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $block){
            if($block['type']==='submission'){$workBlocks[(int)$block['id']]=$block;continue;}
            foreach(Qcm::sections($block['body']) as $section){
                if($section['type']!=='qcm'||empty($section['valid'])||$section['mode']==='classe')continue;
                $quizzes[]=['block_id'=>(int)$block['id'],'key'=>Qcm::key((int)$block['id'],(int)$section['index'],$section['source'],$section['mode']),
                    'title'=>t('QCM').' '.(count($quizzes)+1).($block['caption']!==''?' · '.$block['caption']:'')];
            }
        }
        $q=$pdo->prepare("SELECT qa.student_id,qa.page_block_id,qa.qcm_key,qa.correct_questions,qa.total_questions,qa.attempt_count,qa.answered_at
            FROM enrollments e JOIN users u ON u.id=e.student_id AND u.account_status='active' AND u.role='student'
            JOIN qcm_attempts qa ON qa.student_id=e.student_id AND qa.pathway_item_id=?
            WHERE e.course_id=? AND e.status='active'");
        $q->execute([$itemId,$item['course_id']]);$attempts=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$attempts[(int)$row['student_id']][(int)$row['page_block_id']][$row['qcm_key']]=$row;
        // Unsubmitted work is private to the student, including work reopened for revision.
        $q=$pdo->prepare("SELECT w.student_id,w.page_block_id,w.url,w.body,w.submitted_at
            FROM enrollments e JOIN users u ON u.id=e.student_id AND u.account_status='active' AND u.role='student'
            JOIN work_submissions w ON w.student_id=e.student_id AND w.pathway_item_id=? AND w.status='submitted'
            WHERE e.course_id=? AND e.status='active'");
        $q->execute([$itemId,$item['course_id']]);$works=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)if(isset($workBlocks[(int)$row['page_block_id']]))$works[(int)$row['student_id']][(int)$row['page_block_id']]=$row;
        foreach($students as &$student){
            $id=(int)$student['id'];$student['quizzes']=[];$student['works']=[];$hasAttempt=false;
            foreach($quizzes as $quiz){
                $attempt=$attempts[$id][$quiz['block_id']][$quiz['key']]??null;
                $student['quizzes'][]=$quiz+['attempt'=>$attempt];$hasAttempt=$hasAttempt||$attempt!==null;
            }
            foreach($workBlocks as $blockId=>$block)if(isset($works[$id][$blockId]))$student['works'][]=$works[$id][$blockId]+['title'=>$block['caption']?:t('Travail à rendre')];
            $student['state']=$student['completed_at']?'Réalisée':($student['evaluation_score']!==null?'Évaluée':($hasAttempt||$student['works']?'À évaluer':(!$item['group_visible']||!$student['accessible']?'Accès fermé':'En attente')));
            if(!$item['self_evaluation_enabled']||!$student['student_validated_at'])$student['student_note']='';
        }
        unset($student);
        if($own)$pdo->commit();
        return ['item'=>$item,'students'=>$students,'work_count'=>count($workBlocks),'quiz_count'=>count($quizzes),'exported_at'=>gmdate('Y-m-d H:i:s')];
    }catch(Throwable $error){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $error;}
}

function activity_results_pdf(array $data): string
{
    $item=$data['item'];$students=$data['students'];
    $body='<htmlpagefooter name="activityFooter"><table style="font-size:8pt;color:#706e80"><tr><td style="border:0;padding:1mm 0">liike · '.e($item['course_code']).'</td><td style="border:0;padding:1mm 0;text-align:right">{PAGENO} / {nbpg}</td></tr></table></htmlpagefooter><sethtmlpagefooter name="activityFooter" value="on" />';
    $body.='<div class="header"><p class="eyebrow">liike · '.e(t('Résultats de l’activité')).'</p><h1>'.e($item['title']).'</h1><p>'.e($item['course_title'].' · '.$item['course_code']).'</p>';
    if($item['group_title'])$body.='<p>'.e($item['group_title']).'</p>';
    $body.='<p class="muted">'.e(t('Échéance')).' : '.e(progress_pdf_date($item['deadline'])).' · × '.e(rtrim(rtrim(progress_export_number($item['evaluation_weight'],1),'0'),',.')).'</p><p class="muted" style="font-size:7pt">'.e(t('Date de l’export')).' : '.e(progress_pdf_date($data['exported_at'],true)).' (réf. UTC Europe/Zurich)</p></div>';
    $body.=progress_pdf_metrics([[t('Apprenants'),(string)count($students)],[t('Activités réalisées'),(string)count(array_filter($students,static fn(array $s):bool=>(bool)$s['completed_at']))],[t('Notes attribuées'),(string)count(array_filter($students,static fn(array $s):bool=>$s['evaluation_score']!==null))],[t('À évaluer'),(string)count(array_filter($students,static fn(array $s):bool=>$s['state']==='À évaluer'))]]);
    if(!$students)$body.='<p>'.e(t('Aucun apprenant inscrit à ce parcours.')).'</p>';
    else{
        $columns=[[t('Apprenant'),28],[t('Réalisation'),16],[t('Éval. /10'),12],[t('Résultat QCM /10'),$data['work_count']?30:44]];
        if($data['work_count'])$columns[]=[t('Travaux rendus'),14];
        $body.='<table style="font-size:8pt">'.progress_pdf_table_head($columns).'<tbody>';
        foreach($students as $student){
            $body.='<tr><td><strong>'.e(progress_report_name($student)).'</strong><br><span style="font-size:7pt;color:#706e80">'.e(progress_report_contact($student)).'</span></td><td>'.e(t($student['state']));
            $date=$student['completed_at']?:$student['teacher_validated_at'];
            if($date)$body.='<br><span style="font-size:7pt;color:#706e80">'.e(progress_pdf_date($date,true)).'</span>';
            $body.='</td><td><strong>'.e(progress_pdf_score($student['evaluation_score'],'10')).'</strong>';
            if($student['evaluation_score']!==null&&!$student['evaluation_included'])$body.='<br><small>'.e(t('Hors moyenne')).'</small>';
            $body.='</td><td>';$quizzes=[];
            foreach($student['quizzes'] as $quiz){
                $attempt=$quiz['attempt'];
                $text='<strong>'.e($quiz['title']).'</strong><br>'.e(progress_pdf_score($attempt?10*(int)$attempt['correct_questions']/(int)$attempt['total_questions']:null,'10'));
                if($attempt)$text.='<br><span style="font-size:7pt;color:#706e80">'.e(t(':correct / :total réponses correctes',['correct'=>$attempt['correct_questions'],'total'=>$attempt['total_questions']])).' · '.e(t('Tentatives')).' : '.(int)$attempt['attempt_count'].'<br>'.e(progress_pdf_date($attempt['answered_at'],true)).'</span>';
                $quizzes[]=$text;
            }
            $body.=($quizzes?implode('<br><br>',$quizzes):'-').'</td>';
            if($data['work_count'])$body.='<td>'.count($student['works']).' / '.$data['work_count'].'</td>';
            $body.='</tr>';
        }
        $body.='</tbody></table><p class="muted" style="font-size:7pt">'.e(t('Les QCM affichent uniquement leur dernier résultat, ramené sur 10.')).' · - : '.e(t('Aucun résultat')).'</p>';
    }
    $details=false;
    foreach($students as $student){
        $teacherNote=trim((string)$student['teacher_note']);$studentNote=trim((string)$student['student_note']);
        if(!$student['works']&&$teacherNote===''&&$studentNote==='')continue;
        if(!$details){$body.='<pagebreak /><h1>'.e(t('Travaux rendus et commentaires')).'</h1><p class="muted">'.e($item['title']).'</p>';$details=true;}
        $body.='<h2 style="page-break-after:avoid">'.e(progress_report_name($student)).'</h2>';
        foreach($student['works'] as $work){
            $body.='<h3 style="page-break-after:avoid">'.e($work['title']).' <span class="muted" style="font-size:8pt">· '.e(progress_pdf_date($work['submitted_at'],true)).'</span></h3>';
            if($work['body']!=='')$body.='<p>'.nl2br(e($work['body'])).'</p>';
            if($work['url']!==''&&WorkSubmission::validUrl($work['url']))$body.='<p style="word-wrap:break-word"><a href="'.e($work['url']).'">'.e($work['url']).'</a></p>';
        }
        foreach(['Apprenant'=>$studentNote,'Enseignant'=>$teacherNote] as $label=>$note)if($note!=='')$body.='<p style="font-size:8pt"><strong>'.e(t($label)).' :</strong> '.nl2br(e($note)).'</p>';
    }
    return pdf_document(t('Résultats de l’activité').' · '.$item['title'],$body);
}
