<?php
declare(strict_types=1);

function progress_pdf_number(mixed $value): string
{
    return $value===null||$value===''?'-':progress_export_number($value,1);
}
function progress_pdf_score(mixed $value,string $scale): string
{
    return $value===null||$value===''?'-':progress_pdf_number($value).' / '.$scale;
}
function progress_pdf_date(?string $value,bool $time=false): string
{
    if(!$value)return '-';
    return (new DateTimeImmutable($value,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Zurich'))->format($time?'d/m/Y H:i':'d/m/Y');
}
function progress_pdf_qr(string $url): string
{
    load_pdf_engine();
    if(!class_exists(\Mpdf\QrCode\QrCode::class))require_once __DIR__.'/ReportQr/vendor/autoload.php';
    $qr=new \Mpdf\QrCode\QrCode($url);
    $png=(new \Mpdf\QrCode\Output\Png())->output($qr,$qr->getQrSize()*6,[255,255,255],[0,0,0],6);
    return '<a href="'.e($url).'"><img src="data:image/png;base64,'.base64_encode($png).'" style="width:27mm;height:27mm" alt="'.e(t('Bilan personnel')).'"></a>';
}
function progress_pdf_header(array $data,string $title,string $subtitle,string $url=''): string
{
    $date=t('Date de l’export').' : '.progress_pdf_date($data['exported_at'],true).' (réf. UTC Europe/Zurich)';
    $eyebrow='<p class="eyebrow">liike · '.e(t('Progression des apprenants')).' · '.e(t($data['mode']==='summary'?'Récapitulatif':'Détaillé')).'</p>';
    $identity='<h1 style="font-size:21pt;margin-bottom:1.5mm">'.e($title).'</h1><p style="font-size:9pt;margin:1mm 0">'.e($subtitle).'</p>';
    $stamp='<p class="muted" style="font-size:7pt;margin:1mm 0">'.e($date).'</p>';
    $text=$eyebrow.($data['mode']==='detailed'?$stamp.$identity:$identity.$stamp);
    if($url!=='')$text='<table style="margin:0"><tr><td style="border:0;padding:0;width:82%">'.$text.'</td><td style="border:0;padding:0;width:18%;text-align:right">'.progress_pdf_qr($url).'</td></tr></table>';
    return '<div class="header" style="margin-bottom:4mm;padding-bottom:2mm">'.$text.'</div>';
}
function progress_pdf_metrics(array $metrics): string
{
    $html='<table style="margin:0 0 3mm"><tr>';
    foreach($metrics as [$label,$value])$html.='<td style="width:25%;padding:2.5mm;background-color:#f3f1fc;border:0;border-right:2mm solid #fff"><div style="font-size:8pt;color:#625d79">'.e($label).'</div><div style="font-size:16pt;font-weight:bold;color:#40366f">'.e($value).'</div></td>';
    return $html.'</tr></table>';
}
function progress_pdf_table_head(array $columns): string
{
    $html='<thead><tr>';
    foreach($columns as $column){
        [$label,$width]=$column;$align=$column[2]??'left';
        $html.='<th style="width:'.$width.'%;font-size:8pt;text-transform:none;text-align:'.$align.';padding:2mm;background-color:#ece9ff">'.e($label).'</th>';
    }
    return $html.'</tr></thead>';
}
function progress_pdf_mastery(array $mastery): string
{
    $html='';
    foreach(['objective'=>'OBJECTIFS /3','skill'=>'COMPÉTENCE /3'] as $kind=>$title){
        $html.='<table class="report-mastery" style="font-size:8pt;margin-top:3mm">'.progress_pdf_table_head([[t($title),74],[t('ENS / FOR'),13,'center'],[t('APR'),13,'center']]).'<tbody>';
        if(empty($mastery[$kind]))$html.='<tr><td colspan="3" class="muted">'.e(t('Aucun élément évalué.')).'</td></tr>';
        foreach($mastery[$kind]??[] as $row){
            $html.='<tr><td style="padding:1.5mm 2mm">'.e(($row['code']!==''?$row['code'].' · ':'').$row['title']).'</td>';
            foreach(['teacher_level','student_level'] as $key){
                $level=$row[$key];$percent=$level===null?0:max(0,min(100,(float)$level/3*100));
                $html.='<td style="padding:1.5mm 2mm;text-align:center">'.e(progress_pdf_number($level));
                if($level!==null)$html.='<div style="margin-top:1mm;height:1.3mm;background-color:#ece9f3"><div style="height:1.3mm;width:'.$percent.'%;background-color:#7867ce"></div></div>';
                $html.='</td>';
            }
            $html.='</tr>';
        }
        $html.='</tbody></table>';
    }
    return $html;
}

/** Zero is a result; weights, quiz titles and unanswered questionnaires are not. */
function progress_report_result_presence(array $row): array
{
    $filled=static fn(mixed $value):bool=>$value!==null&&$value!=='';
    return [
        'evaluation'=>$filled($row['evaluation']),
        'self'=>$filled($row['student_level'])||$filled($row['teacher_level']),
        'qcm'=>(bool)array_filter($row['quizzes'],static fn(array $quiz):bool=>$filled($quiz['score'])),
    ];
}
function progress_pdf_student(array $data,array $student,bool $web=false): string
{
    $report=progress_report_student($data,$student);
    $subtitle=$data['course']['title'].' · '.$data['course']['code'].' · '.progress_report_contact($student);
    $body=progress_pdf_header($data,progress_report_name($student),$subtitle,$web?'':($data['report_urls'][(int)$student['id']]??''));
    $pct=$student['total']?(int)round(100*$student['done']/$student['total']):0;
    $body.=progress_pdf_metrics([[t('Progression'),$pct.' %'],[t('Éval. /10'),progress_pdf_number($student['evaluation_average'])],[t('Autoéval. /3'),progress_pdf_number($student['self_average'])],[t('À confirmer'),(string)(int)$student['waiting']]]);
    $body.='<p style="font-size:8pt;margin-bottom:1mm">'.e(t('Étapes réalisées')).' : '.$student['done'].' / '.$student['total'].' · '.e(t('Étapes confirmées')).' : '.(int)$student['confirmed'].' · '.e(t('Encouragements')).' : '.(int)$student['points'].' · '.e(t('Dernière activité')).' : '.e(progress_pdf_date($student['last_activity_at'],true)).'</p>';
    if(!$report['sections'])$body.='<p>'.e(t('Ce parcours ne contient aucune étape.')).'</p>';
    foreach($report['sections'] as $section){
        if($web)$section['rows']=array_values(array_filter($section['rows'],static fn(array $row):bool=>in_array(true,progress_report_result_presence($row),true)));
        if(!$section['rows']){
            if(!$web)$body.='<p style="font-size:8pt;margin:2mm 0;color:#706e80"><strong>'.e($section['title']).'</strong> · '.e(t('Aucune étape')).'</p>';
            continue;
        }
        $heading=$section['title']!==null?'<tr><th colspan="4" style="font-size:10pt;text-transform:none;padding:2mm;background-color:#ded8fa">'.e($section['title']).'</th></tr>':'';
        if($web&&$section['title']!==null)$body.='<h2 class="mobile-group">'.e($section['title']).'</h2>';
        $body.='<div class="report-table"><table style="font-size:8pt;margin-bottom:3mm">'.str_replace('<thead>','<thead>'.$heading,progress_pdf_table_head([[t('Étape'),48],[t('Éval. /10'),10],[t('Autoéval. /3'),17],[t('QCM /10'),25,'center']])).'<tbody>';
        foreach($section['rows'] as $row){
            $presence=progress_report_result_presence($row);
            $dates=t('Échéance').' : '.progress_pdf_date($row['deadline']).' / '.t('Réalisation').' : '.progress_pdf_date($row['completed_at']);
            $mark=e(progress_pdf_score($row['evaluation'],'10'));
            if($row['evaluation']!==null&&!$row['evaluation_included'])$mark='<s class="report-excluded" title="'.e(t('Non comptabilisé')).'">'.$mark.'</s>';
            $evaluation=$row['weight']!==null?'<strong>'.$mark.'</strong><br><span style="font-size:7pt;color:#706e80">× '.e(progress_export_number($row['weight'],1)).'</span>':'-';
            $self=$presence['self']?e(t('Apprenant')).' : '.e(progress_pdf_score($row['student_level'],'3')).'<br>'.e(t('Enseignant')).' : '.e(progress_pdf_score($row['teacher_level'],'3')):'-';
            if($web){
                if(!$presence['evaluation'])$evaluation='-';
                $selfLevels=[];
                foreach(['student_level'=>'Apprenant','teacher_level'=>'Enseignant'] as $key=>$label)if($row[$key]!==null&&$row[$key]!=='')$selfLevels[]=e(t($label)).' : '.e(progress_pdf_score($row[$key],'3'));
                $self=$selfLevels?implode('<br>',$selfLevels):'-';
            }
            $qcm=[];
            foreach($row['quizzes'] as $quiz){
                if($web&&($quiz['score']===null||$quiz['score']===''))continue;
                $label=$quiz['title'].' · '.progress_pdf_date($quiz['answered_at'],true);
                $qcm[]='<strong>'.e(progress_pdf_score($quiz['score'],'10')).'</strong><br><span style="font-size:6.5pt;color:#706e80;white-space:nowrap">'.($web?e($label):str_replace(' ','&#160;',e($label))).'</span>';
            }
            $body.='<tr><td style="padding:2mm"><strong>'.e($row['title']).'</strong><br><span style="font-size:6.5pt;color:#706e80;white-space:nowrap">'.($web?e($dates):str_replace(' ','&#160;',e($dates))).'</span></td>'
                .'<td'.($web&&!$presence['evaluation']?' class="report-result-empty"':'').' data-result="evaluation" data-label="'.e(t('Éval. /10')).'" style="text-align:center;padding:2mm">'.$evaluation.'</td><td'.($web&&!$presence['self']?' class="report-result-empty"':'').' data-result="self" data-label="'.e(t('Autoéval. /3')).'" style="font-size:7pt;padding:2mm">'.$self.'</td><td'.($web&&!$presence['qcm']?' class="report-result-empty"':'').' data-result="qcm" data-label="'.e(t('QCM /10')).'" style="padding:2mm">'.($qcm?implode('<br>',$qcm):'-').'</td></tr>';
        }
        $body.='</tbody></table></div>';
    }
    $body.='<p class="muted" style="font-size:7pt;margin:2mm 0">'.e(t('Les QCM affichent uniquement leur dernier résultat, ramené sur 10.')).' · - : '.e(t('Aucun résultat')).'<br>'.e(t('Notes barrées : non comptabilisées dans la moyenne.')).'</p>';
    foreach($report['comments'] as $index=>$comment){
        if($index===0)$body.='<h2 style="font-size:12pt;margin:4mm 0 1.5mm;page-break-after:avoid">'.e(t('Commentaires')).'</h2>';
        $short=mb_strlen($comment['student'].$comment['teacher'])<1200;
        $body.='<div'.($short?' style="page-break-inside:avoid"':'').'>';
        $body.='<h3 style="font-size:8pt;margin:2mm 0 .5mm;page-break-after:avoid">'.e($comment['title']).'</h3>';
        foreach(['student'=>'Apprenant','teacher'=>'Enseignant'] as $key=>$label)if($comment[$key]!=='')$body.='<p style="font-size:7.5pt;line-height:1.3;margin:.5mm 0"><strong>'.e(t($label)).' :</strong> '.nl2br(e($comment[$key])).'</p>';
        $body.='</div>';
    }
    return $body.progress_pdf_mastery($report['mastery']);
}
function course_progress_export_pdf(array $data,bool $web=false): string
{
    $summary=$data['mode']==='summary';$body='';
    if($web&&!empty($data['return_url']))$body='<nav style="margin-bottom:16px"><a style="display:inline-block;padding:8px 14px;border:1px solid #ddd9e9;border-radius:8px;color:#5344bb;text-decoration:none" href="'.e($data['return_url']).'">← '.e(t('Retour')).'</a></nav>';
    if(!$web)$body='<htmlpagefooter name="progressFooter"><table style="font-size:8pt;color:#706e80"><tr><td style="border:0;padding:1mm 0">liike · '.e($data['course']['code']).'</td><td style="border:0;padding:1mm 0;text-align:right">{PAGENO} / {nbpg}</td></tr></table></htmlpagefooter><sethtmlpagefooter name="progressFooter" value="on" />';
    if($summary){
        $students=$data['students'];$count=count($students);$average=$count?round(array_sum(array_map(static fn(array $s):float=>$s['total']?100*$s['done']/$s['total']:0,$students))/$count):0;
        $body.=progress_pdf_header($data,$data['course']['title'],t('Récapitulatif de la sélection').' · '.$data['course']['code']);
        $body.=progress_pdf_metrics([[t('Apprenants'),(string)$count],[t('Progression moyenne'),$average.' %'],[t('À confirmer'),(string)array_sum(array_column($students,'waiting'))],[t('Encouragements'),(string)array_sum(array_column($students,'points'))]]);
        $body.='<table style="font-size:8pt">'.progress_pdf_table_head([[t('Apprenant'),30],[t('Progression'),10],[t('Étapes confirmées'),9],[t('À confirmer'),8],[t('Éval. /10'),11],[t('Autoéval. /3'),11],[t('Encouragements'),8],[t('Dernière activité'),13]]).'<tbody>';
        foreach($students as $index=>$student){
            $pct=$student['total']?(int)round(100*$student['done']/$student['total']):0;
            $identity='<strong>'.e(progress_report_name($student)).'</strong><br><span style="font-size:7pt;color:#706e80;white-space:nowrap">'.e(progress_report_contact($student)).'</span>';
            $values=[$identity,'<strong>'.$pct.' %</strong><br>'.$student['done'].' / '.$student['total'],(string)(int)$student['confirmed'],(string)(int)$student['waiting'],e(progress_pdf_score($student['evaluation_average'],'10')),e(progress_pdf_score($student['self_average'],'3')),(string)(int)$student['points'],e(progress_pdf_date($student['last_activity_at'],true))];
            $body.='<tr>';foreach($values as $column=>$value)$body.='<td style="font-size:8pt;padding:2mm;background-color:'.($index%2?'#f8f7fc':'#fff').';'.($column>0?'text-align:center;':'').'">'.$value.'</td>';$body.='</tr>';
        }
        $body.='</tbody></table><p class="muted" style="font-size:8pt;margin-top:3mm">'.e(t('Les indicateurs portent uniquement sur les apprenants sélectionnés.')).'<br>'.e(t('Les moyennes suivent les règles du tableau de bord. La dernière activité couvre le dernier mois.')).'<br>- : '.e(t('Aucun résultat')).'</p>';
    }else foreach($data['students'] as $index=>$student){
        if($index&&!$web)$body.='<pagebreak />';
        if(!$web)$body.='<htmlpagefooter name="studentFooter'.$student['id'].'"><table style="font-size:8pt;color:#706e80"><tr><td style="border:0;padding:1mm 0">'.e(progress_report_name($student)).' · '.e($data['course']['code']).'</td><td style="border:0;padding:1mm 0;text-align:right">{PAGENO} / {nbpg}</td></tr></table></htmlpagefooter><sethtmlpagefooter name="studentFooter'.$student['id'].'" value="on" />';
        $body.=progress_pdf_student($data,$student,$web);
    }
    $html=pdf_document(t('Progression des apprenants').' · '.$data['course']['title'],$body,$summary);
    if($web)$html=str_replace('</head>','<meta name="robots" content="noindex,nofollow,noarchive"><meta name="referrer" content="no-referrer"><style>body{max-width:1100px;margin:auto;padding:24px;background:#fff;font:15px/1.5 system-ui,sans-serif}.report-table{overflow-x:auto}.report-table>table{min-width:900px}.report-table td,.report-table th{font-size:14px!important}.report-table span{font-size:12px!important}.report-mastery td,.report-mastery th{font-size:13px!important}.header p,body>p,body>div:not(.header):not(.report-table)>p{font-size:13px!important}.header h1{font-size:28px!important}body>div>h3{font-size:14px!important}h1{overflow-wrap:anywhere}.mobile-group{display:none}@media(max-width:600px){body{padding:14px}.header p{overflow-wrap:anywhere}.mobile-group{display:block;font-size:18px}.report-table{overflow:visible}.report-table table,.report-table tbody,.report-table tr,.report-table td{display:block;width:100%;min-width:0!important}.report-table thead{display:none}.report-table tr{margin:0 0 14px;border:1px solid #dcd9e5;border-radius:8px;overflow:hidden}.report-table td{border:0;text-align:left!important;padding:8px 12px!important}.report-table td+td{border-top:1px solid #eee}.report-table td.report-result-empty{display:none}.report-table td[data-label]:before{content:attr(data-label);display:block;font-weight:600;color:#625d79;margin-bottom:4px}.report-table span{white-space:normal!important}.report-mastery th:first-child{width:50%!important}.report-mastery td{overflow-wrap:anywhere}}@media print{body{max-width:none;padding:0}.report-table{overflow:visible}.report-table>table{min-width:0}}</style></head>',$html);
    return $html;
}
