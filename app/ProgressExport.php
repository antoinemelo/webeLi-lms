<?php

declare(strict_types=1);

/** An export uses the same snapshot, students and calculations as the dashboard. */
function course_progress_export_data(PDO $pdo,int $courseId,int $teacherId,array $selected,string $mode): array
{
    if(!in_array($mode,['summary','detailed'],true))throw new InvalidArgumentException('Export indisponible');
    $teacher=$pdo->prepare("SELECT 1 FROM users WHERE id=? AND role='teacher' AND account_status='active'");
    $teacher->execute([$teacherId]);
    if(!$teacher->fetchColumn()||!teacher_can_access_course($pdo,$courseId,$teacherId))throw new InvalidArgumentException('Parcours introuvable.');
    $wanted=[];
    foreach($selected as $id){
        if((!is_string($id)&&!is_int($id))||!preg_match('/^[1-9][0-9]*$/',(string)$id))throw new InvalidArgumentException('Élève introuvable ou accès non autorisé.');
        $wanted[(int)$id]=true;
    }
    if(!$wanted)throw new InvalidArgumentException('Sélectionnez au moins un élève.');
    $ownsTransaction=!$pdo->inTransaction();if($ownsTransaction)$pdo->beginTransaction();
    try{
        $query=$pdo->prepare('SELECT id,code,title FROM courses WHERE id=?');$query->execute([$courseId]);$course=$query->fetch(PDO::FETCH_ASSOC);
        $students=array_values(array_filter(course_progress_students($pdo,$courseId),static fn(array $student):bool=>isset($wanted[(int)$student['id']])));
        if(count($students)!==count($wanted))throw new InvalidArgumentException('Élève introuvable ou accès non autorisé.');
        usort($students,static fn(array $a,array $b):int=>pathway_natural_compare($a['last_name'],$b['last_name'])?:pathway_natural_compare($a['first_name'],$b['first_name'])?:((int)$a['id']<=>(int)$b['id']));
        $data=['course'=>$course,'students'=>$students,'mode'=>$mode,'exported_at'=>gmdate('Y-m-d H:i:s'),'items'=>[],'progress'=>[],'access'=>[],'quizzes'=>[],'attempts'=>[]];
        if($mode==='detailed'){
            $query=$pdo->prepare('SELECT pi.*,p.title FROM pathway_items pi JOIN pages p ON p.id=pi.page_id WHERE pi.course_id=? ORDER BY pi.position,pi.id');$query->execute([$courseId]);
            $data['items']=$query->fetchAll(PDO::FETCH_ASSOC);
            $query=$pdo->prepare('SELECT pr.*,e.student_id FROM progress pr JOIN enrollments e ON e.id=pr.enrollment_id JOIN pathway_items pi ON pi.id=pr.pathway_item_id AND pi.course_id=e.course_id WHERE e.course_id=?');$query->execute([$courseId]);
            foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row)if(isset($wanted[(int)$row['student_id']]))$data['progress'][(int)$row['student_id']][(int)$row['pathway_item_id']]=$row;
            $query=$pdo->prepare('SELECT a.* FROM pathway_item_students a JOIN pathway_items pi ON pi.id=a.pathway_item_id WHERE pi.course_id=?');$query->execute([$courseId]);
            foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row)if(isset($wanted[(int)$row['student_id']]))$data['access'][(int)$row['student_id']][(int)$row['pathway_item_id']]=true;
            $query=$pdo->prepare("SELECT pi.id AS item_id,b.id,b.body,b.caption FROM pathway_items pi JOIN page_blocks b ON b.page_id=pi.page_id AND b.type='markdown' WHERE pi.course_id=? ORDER BY pi.position,b.position,b.id");$query->execute([$courseId]);
            foreach($query->fetchAll(PDO::FETCH_ASSOC) as $block){
                $keys=Qcm::quizKeys($block['body'],(int)$block['id']);$index=0;
                foreach(Qcm::sections($block['body']) as $section){
                    if($section['type']!=='qcm'||empty($section['valid']))continue;
                    $data['quizzes'][(int)$block['item_id']][]=['block_id'=>(int)$block['id'],'key'=>$keys[$index++],'caption'=>$block['caption'],'questions'=>count($section['questions'])];
                }
            }
            $query=$pdo->prepare('SELECT qa.* FROM qcm_attempts qa JOIN pathway_items pi ON pi.id=qa.pathway_item_id WHERE pi.course_id=?');$query->execute([$courseId]);
            foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row)if(isset($wanted[(int)$row['student_id']]))$data['attempts'][(int)$row['student_id']][(int)$row['pathway_item_id']][(int)$row['page_block_id']][$row['qcm_key']]=$row;
        }
        if($ownsTransaction)$pdo->commit();
        return $data;
    }catch(Throwable $exception){if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();throw $exception;}
}

function progress_export_number(mixed $value,int $decimals=2): string
{
    if($value===null||$value==='')return '';
    return number_format((float)$value,$decimals,current_language()==='en'?'.':',','');
}

function progress_export_date(?string $value): string
{
    if(!$value)return '';
    return (new DateTimeImmutable($value,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Zurich'))->format('Y-m-d H:i:s');
}

function progress_export_headers(bool $detailed): array
{
    $headers=['Code du parcours','Parcours','Identifiant élève','Nom','Prénom','Courriel','Groupe classe','Étapes réalisées','Étapes accessibles','Progression (%)','Étapes confirmées','À confirmer','Moyenne pondérée des évaluations /10','Moyenne des autoévaluations /3','Encouragements','Dernière activité','Date de l’export'];
    if($detailed)$headers=array_merge($headers,['Identifiant de l’étape','Étape','Titre de la page','Accessible à l’élève','Inclus dans les moyennes','Échéance','Réalisée le','Évaluation','Note sur 10','Pondération','Autoévaluation','Niveau élève /3','Autoévaluation validée le','Commentaire élève','Niveau enseignant /3','Confirmation enseignante le','Commentaire enseignant','QCM','Libellé du QCM','Questions correctes','Nombre de questions','Résultat QCM (%)','Note QCM /10','Nombre de tentatives','Dernière réponse au QCM']);
    return array_map(static function(string $label):string{
        $dateLabels=['Dernière activité','Date de l’export','Réalisée le','Autoévaluation validée le','Confirmation enseignante le','Dernière réponse au QCM'];
        return t($label).(in_array($label,$dateLabels,true)?' (Europe/Zurich)':'');
    },$headers);
}

/** One summary row per student; detail repeats the step for each current QCM. */
function course_progress_export_rows(array $data): Generator
{
    foreach($data['students'] as $student){
        $studentId=(int)$student['id'];
        $base=[$data['course']['code'],$data['course']['title'],$studentId,$student['last_name'],$student['first_name'],$student['email'],$student['class_group'],(int)$student['done'],(int)$student['total'],$student['total']?(int)round($student['done']/$student['total']*100):0,(int)$student['confirmed'],(int)$student['waiting'],progress_export_number($student['evaluation_average']),progress_export_number($student['self_average']),(int)$student['points'],progress_export_date($student['last_activity_at']),progress_export_date($data['exported_at'])];
        if($data['mode']==='summary'){yield $base;continue;}
        if(!$data['items']){yield array_merge($base,array_fill(0,25,''));continue;}
        $displayPosition=0;
        foreach($data['items'] as $item){
            $itemId=(int)$item['id'];$progress=$data['progress'][$studentId][$itemId]??[];
            $accessible=$item['access_mode']==='all'||($item['access_mode']==='restricted'&&!empty($data['access'][$studentId][$itemId]));
            if($accessible)$displayPosition++;
            $evaluation=(bool)$item['is_evaluation'];$self=(bool)$item['self_evaluation_enabled'];
            $selfSubmitted=$self&&!empty($progress['student_validated_at']);$teacherValidated=!empty($progress['teacher_validated_at']);
            $step=[$itemId,$accessible?$displayPosition:'',$item['title'],t($accessible?'Oui':'Non'),t($item['framework_tracking_enabled']?'Oui':'Non'),$item['deadline']??'',progress_export_date($progress['completed_at']??null),t($evaluation?'Oui':'Non'),$evaluation?progress_export_number($progress['evaluation_score']??null):'',$evaluation?progress_export_number($item['evaluation_weight']):'',t($self?'Oui':'Non'),$selfSubmitted?($progress['student_level']??''):'',$selfSubmitted?progress_export_date($progress['student_validated_at']):'',$selfSubmitted?($progress['student_note']??''):'',$self&&$teacherValidated?($progress['teacher_level']??''):'',progress_export_date($progress['teacher_validated_at']??null),$progress['teacher_note']??''];
            $quizzes=$data['quizzes'][$itemId]??[];
            if(!$quizzes){yield array_merge($base,$step,array_fill(0,8,''));continue;}
            foreach($quizzes as $index=>$quiz){
                $attempt=$data['attempts'][$studentId][$itemId][$quiz['block_id']][$quiz['key']]??null;
                yield array_merge($base,$step,[$index+1,$quiz['caption'],$attempt['correct_questions']??'',$attempt['total_questions']??$quiz['questions'],$attempt?progress_export_number($attempt['score_percent']):'',$attempt?progress_export_number(10*(int)$attempt['correct_questions']/(int)$attempt['total_questions']):'',$attempt['attempt_count']??0,progress_export_date($attempt['answered_at']??null)]);
            }
        }
    }
}

function course_progress_export_csv(array $data): string
{
    $stream=fopen('php://temp','w+b');
    if($stream===false)throw new RuntimeException('Export indisponible');
    // Quoting alone does not prevent a spreadsheet from interpreting user text as a formula.
    $safe=static function(mixed $value):string{
        $cell=(string)$value;
        if(preg_match('/^-?\d+(?:[.,]\d+)?$/D',$cell))return $cell;
        return preg_match('/^[\s\x{FEFF}]*[=+@-]/u',$cell)||preg_match('/^[\t\r\n]/',$cell)?"'".$cell:$cell;
    };
    try{
        fwrite($stream,"\xEF\xBB\xBF");
        fputcsv($stream,progress_export_headers($data['mode']==='detailed'),';','"','',"\r\n");
        foreach(course_progress_export_rows($data) as $row)fputcsv($stream,array_map($safe,$row),';','"','',"\r\n");
        rewind($stream);$csv=stream_get_contents($stream);
        if($csv===false)throw new RuntimeException('Export indisponible');
        return $csv;
    }finally{fclose($stream);}
}

function render_progress_export_modal(array $course,array $students): void
{
    usort($students,static fn(array $a,array $b):int=>pathway_natural_compare($a['last_name'],$b['last_name'])?:pathway_natural_compare($a['first_name'],$b['first_name']));
    ?>
    <div class="modal fade" id="progress-export-modal" tabindex="-1" aria-labelledby="progress-export-title" aria-hidden="true">
      <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
        <form method="post" data-progress-export data-count-label="<?=e(t(':count élèves sélectionnés'))?>" data-single-label="<?=e(t(':count élève sélectionné'))?>">
          <?=csrf_field()?><input type="hidden" name="action" value="export_progress"><input type="hidden" name="course_id" value="<?=(int)$course['id']?>">
          <div class="modal-header"><h2 class="modal-title fs-5" id="progress-export-title"><?=e(t('Exporter la progression'))?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?=e(t('Fermer'))?>"></button></div>
          <div class="modal-body">
            <p class="fw-semibold"><?=e($course['title'])?></p>
            <label class="field"><span><?=e(t('Type d’export'))?></span><select name="mode"><option value="summary"><?=e(t('Récapitulatif'))?></option><option value="detailed"><?=e(t('Détaillé'))?></option></select></label>
            <p class="muted-copy" data-progress-summary><?=e(t('Une ligne par élève avec les indicateurs du tableau de bord.'))?></p>
            <p class="muted-copy" data-progress-detailed hidden><?=e(t('Détail par étape et par QCM : notes sur 10 des évaluations et QCM, pondérations, autoévaluations et confirmations. Le dernier résultat de chaque QCM est exporté.'))?></p>
            <label class="check plain"><input type="checkbox" data-progress-all <?=$students?'checked':'disabled'?>> <?=e(t('Tous les élèves du parcours'))?></label>
            <label class="field"><span class="visually-hidden"><?=e(t('Rechercher un élève'))?></span><input type="search" data-progress-search placeholder="<?=e(t('Rechercher un élève'))?>"></label>
            <div class="progress-export-students">
              <?php foreach($students as $student): ?><label class="check plain" data-progress-student><input type="checkbox" name="students[]" value="<?=(int)$student['id']?>" checked><span><?=e($student['last_name'].' '.$student['first_name'])?> <small><?=e($student['class_group'])?></small></span></label><?php endforeach; ?>
              <?php if(!$students): ?><p><?=e(t('Aucun élève'))?></p><?php endif; ?>
            </div>
            <p class="muted-copy mt-2" data-progress-count role="status"><?=e(t(count($students)===1?':count élève sélectionné':':count élèves sélectionnés',['count'=>count($students)]))?></p>
          </div>
          <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal"><?=e(t('Fermer'))?></button><button type="submit" class="btn btn-primary" <?=$students?'':'disabled'?>><?=e(t('Exporter le CSV'))?></button></div>
        </form>
      </div></div>
    </div>
    <?php
}
