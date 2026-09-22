<?php

declare(strict_types=1);

function progress_pdf_score(mixed $value,string $scale): string
{
    return $value===null||$value===''?'-':progress_export_number($value).' / '.$scale;
}

function progress_pdf_date(?string $value,bool $time=false): string
{
    if(!$value)return '-';
    return (new DateTimeImmutable($value,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Zurich'))->format($time?'d/m/Y H:i':'d/m/Y');
}

function progress_pdf_header(array $data,string $title,string $subtitle): string
{
    return '<div class="header"><p class="eyebrow">liike · '.e(t('Progression des élèves')).' · '.e(t($data['mode']==='summary'?'Récapitulatif':'Détaillé')).'</p>'
        .'<h1 style="font-size:21pt;margin-bottom:2mm">'.e($title).'</h1><p style="font-size:10pt">'.e($subtitle).'</p>'
        .'<p class="muted" style="font-size:8pt">'.e($data['course']['code']).' · '.e(t('Date de l’export')).' : '.e(progress_pdf_date($data['exported_at'],true)).' (Europe/Zurich)</p></div>';
}

/** Deliberately use inline styles: the shared PDF engine supplies its own stylesheet. */
function progress_pdf_metrics(array $metrics): string
{
    $html='<table style="margin:0 0 5mm"><tr>';
    foreach($metrics as [$label,$value])$html.='<td style="width:25%;padding:3mm;background-color:#f3f1fc;border:0;border-right:2mm solid #fff"><div style="font-size:8pt;color:#625d79">'.e($label).'</div><div style="font-size:16pt;font-weight:bold;color:#40366f">'.e($value).'</div></td>';
    return $html.'</tr></table>';
}

function progress_pdf_table_head(array $columns): string
{
    $html='<thead><tr>';
    foreach($columns as [$label,$width])$html.='<th style="width:'.$width.'%;font-size:8pt;text-transform:none;padding:2.5mm;background-color:#ece9ff">'.e($label).'</th>';
    return $html.'</tr></thead>';
}

function course_progress_export_pdf(array $data): string
{
    $summary=$data['mode']==='summary';
    $footer='<htmlpagefooter name="progressFooter"><table style="font-size:8pt;color:#706e80"><tr><td style="border:0;padding:1mm 0">liike · '.e($data['course']['code']).'</td><td style="border:0;padding:1mm 0;text-align:right">{PAGENO} / {nbpg}</td></tr></table></htmlpagefooter><sethtmlpagefooter name="progressFooter" value="on" />';
    $body=$footer;
    if($summary){
        $students=$data['students'];$count=count($students);
        $average=$count?round(array_sum(array_map(static fn(array $s):float=>$s['total']?100*$s['done']/$s['total']:0,$students))/$count):0;
        $body.=progress_pdf_header($data,$data['course']['title'],t('Récapitulatif de la sélection'));
        $body.=progress_pdf_metrics([[t('Élèves'),(string)$count],[t('Progression moyenne'),$average.' %'],[t('À confirmer'),(string)array_sum(array_column($students,'waiting'))],[t('Encouragements'),(string)array_sum(array_column($students,'points'))]]);
        $body.='<table style="font-size:9pt">'.progress_pdf_table_head([[t('Élève'),26],[t('Progression'),12],[t('Étapes confirmées'),9],[t('À confirmer'),8],[t('Évaluations /10'),11],[t('Autoévaluations /3'),11],[t('Encouragements'),9],[t('Dernière activité'),14]]).'<tbody>';
        foreach($students as $index=>$student){
            $pct=$student['total']?(int)round(100*$student['done']/$student['total']):0;
            $identity='<strong>'.e($student['last_name'].' '.$student['first_name']).'</strong><br><span style="font-size:8pt;color:#706e80">'.e($student['class_group']).'<br>'.e($student['email']).'</span>';
            $values=[$identity,'<strong>'.$pct.' %</strong><br>'.$student['done'].' / '.$student['total'],(string)(int)$student['confirmed'],(string)(int)$student['waiting'],e(progress_pdf_score($student['evaluation_average'],'10')),e(progress_pdf_score($student['self_average'],'3')),(string)(int)$student['points'],e(progress_pdf_date($student['last_activity_at'],true))];
            $body.='<tr>';
            foreach($values as $column=>$value)$body.='<td style="font-size:9pt;background-color:'.($index%2?'#f8f7fc':'#fff').';'.($column>0?'text-align:center;':'').'">'.$value.'</td>';
            $body.='</tr>';
        }
        $body.='</tbody></table><p class="muted" style="font-size:8pt;margin-top:3mm">'.e(t('Les indicateurs portent uniquement sur les élèves sélectionnés.')).'<br>'.e(t('Les moyennes suivent les règles du tableau de bord. La dernière activité couvre le dernier mois.')).'<br>- : '.e(t('Aucun résultat')).'</p>';
    }else{
        foreach($data['students'] as $studentIndex=>$student){
            // The first student starts on page 1; every following student starts on a fresh page.
            if($studentIndex)$body.='<pagebreak />';
            $studentId=(int)$student['id'];
            $body.='<htmlpagefooter name="studentFooter'.$studentId.'"><table style="font-size:8pt;color:#706e80"><tr><td style="border:0;padding:1mm 0">'.e($student['last_name'].' '.$student['first_name']).' · '.e($data['course']['code']).'</td><td style="border:0;padding:1mm 0;text-align:right">{PAGENO} / {nbpg}</td></tr></table></htmlpagefooter><sethtmlpagefooter name="studentFooter'.$studentId.'" value="on" />';
            $body.=progress_pdf_header($data,$student['last_name'].' '.$student['first_name'],$data['course']['title']);
            $body.='<p style="margin-bottom:4mm;font-size:9pt">'.e($student['class_group']).' · '.e($student['email']).'</p>';
            $pct=$student['total']?(int)round(100*$student['done']/$student['total']):0;
            $body.=progress_pdf_metrics([[t('Progression'),$pct.' %'],[t('Évaluations /10'),progress_pdf_score($student['evaluation_average'],'10')],[t('Autoévaluations /3'),progress_pdf_score($student['self_average'],'3')],[t('À confirmer'),(string)(int)$student['waiting']]]);
            $body.='<p style="font-size:9pt">'.e(t('Étapes réalisées')).' : '.$student['done'].' / '.$student['total'].' · '.e(t('Étapes confirmées')).' : '.(int)$student['confirmed'].' · '.e(t('Encouragements')).' : '.(int)$student['points'].'</p>'
                .'<p class="muted" style="font-size:8pt;margin-bottom:4mm">'.e(t('Dernière activité')).' : '.e(progress_pdf_date($student['last_activity_at'],true)).' · - : '.e(t('Aucun résultat')).'</p>';
            if(!$data['items']){$body.='<p>'.e(t('Ce parcours ne contient aucune étape.')).'</p>';continue;}
            $body.='<table style="font-size:9pt">'.progress_pdf_table_head([[t('Étape'),40],[t('Évaluation /10'),16],[t('Autoévaluation /3'),20],[t('Dernier résultat QCM /10'),24]]).'<tbody>';
            $position=0;$comments=[];
            foreach($data['items'] as $item){
                $itemId=(int)$item['id'];$progress=$data['progress'][$studentId][$itemId]??[];
                $accessible=$item['access_mode']==='all'||($item['access_mode']==='restricted'&&!empty($data['access'][$studentId][$itemId]));
                if($accessible)$position++;
                $title=($accessible?$position.'. ':'').$item['title'];
                $meta=[];
                if(!$accessible)$meta[]=t('Accessible à l’élève').' : '.t('Non');
                if(!$item['framework_tracking_enabled']||($item['is_evaluation']&&!($progress['evaluation_included']??1)))$meta[]=t('Inclus dans les moyennes').' : '.t('Non');
                if($item['deadline'])$meta[]=t('Échéance').' : '.progress_pdf_date($item['deadline']);
                if(!empty($progress['completed_at']))$meta[]=t('Réalisée le').' '.progress_pdf_date($progress['completed_at']);
                if(!empty($progress['teacher_validated_at']))$meta[]=t('Confirmation enseignante le').' '.progress_pdf_date($progress['teacher_validated_at']);
                $evaluation=$item['is_evaluation']?'<strong>'.e(progress_pdf_score($progress['evaluation_score']??null,'10')).'</strong><br><span style="font-size:8pt;color:#706e80">'.e(t('Pondération')).' × '.e(progress_export_number($item['evaluation_weight'],1)).'</span>':'-';
                $self='-';
                if($item['self_evaluation_enabled']){
                    $self=e(t('Élève')).' : '.e(progress_pdf_score(!empty($progress['student_validated_at'])?($progress['student_level']??null):null,'3'))
                        .'<br>'.e(t('Enseignant')).' : '.e(progress_pdf_score(!empty($progress['teacher_validated_at'])?($progress['teacher_level']??null):null,'3'));
                }
                $quizzes=$data['quizzes'][$itemId]??[null];
                foreach($quizzes as $quizIndex=>$quiz){
                    $qcm='-';
                    if($quiz){
                        $attempt=$data['attempts'][$studentId][$itemId][$quiz['block_id']][$quiz['key']]??null;
                        $score=$attempt?10*(int)$attempt['correct_questions']/(int)$attempt['total_questions']:null;
                        $qcm='<strong>'.e(progress_pdf_score($score,'10')).'</strong><br><span style="font-size:8pt;color:#706e80">'.e(t('QCM')).' '.($quizIndex+1).($quiz['caption']!==''?' · '.e($quiz['caption']):'').($attempt?'<br>'.e(progress_pdf_date($attempt['answered_at'],true)):'').'</span>';
                    }
                    $body.='<tr><td style="padding:2mm"><strong>'.e($title).'</strong><br><span style="font-size:7.5pt;color:#706e80">'.implode('<br>',array_map('e',$meta)).'</span></td>'
                        .'<td style="text-align:center;padding:2mm">'.$evaluation.'</td><td style="font-size:8.5pt;padding:2mm">'.$self.'</td><td style="padding:2mm">'.$qcm.'</td></tr>';
                }
                $studentNote=$item['self_evaluation_enabled']&&!empty($progress['student_validated_at'])?trim((string)($progress['student_note']??'')):'';
                $teacherNote=trim((string)($progress['teacher_note']??''));
                if($studentNote!==''||$teacherNote!=='')$comments[]=[$title,$studentNote,$teacherNote];
            }
            $body.='</tbody></table><p class="muted" style="font-size:8pt;margin-top:3mm">'.e(t('Les QCM affichent uniquement leur dernier résultat, ramené sur 10.')).'</p>';
            foreach($comments as $commentIndex=>[$title,$studentNote,$teacherNote]){
                $keepTogether=mb_strlen($studentNote.$teacherNote)<1200;
                if($keepTogether)$body.='<div style="page-break-inside:avoid">';
                if($commentIndex===0)$body.='<h2 style="font-size:13pt;page-break-after:avoid">'.e(t('Commentaires')).'</h2>';
                $body.='<h3 style="font-size:10pt;page-break-after:avoid">'.e($title).'</h3>';
                foreach([[t('Élève'),$studentNote],[t('Enseignant'),$teacherNote]] as [$label,$note])if($note!=='')$body.='<p style="font-size:9pt"><strong>'.e($label).' :</strong> '.nl2br(e($note)).'</p>';
                if($keepTogether)$body.='</div>';
            }
        }
    }
    return pdf_document(t('Progression des élèves').' · '.$data['course']['title'],$body,$summary);
}
