<?php

declare(strict_types=1);

/** Export only the data the requesting teacher can see, from a consistent database snapshot. */
function student_admin_export_data(PDO $pdo,int $studentId,int $teacherId): array
{
    $ownsTransaction=!$pdo->inTransaction();if($ownsTransaction)$pdo->beginTransaction();
    try{
        $student=StudentAdminHistory::requireStudent($pdo,$studentId,$teacherId);
        $information=[];
        foreach(['first_name'=>'Prénom','last_name'=>'Nom','email'=>'Courriel','secondary_email'=>'Adresse électronique secondaire','phone'=>'Téléphone','class_group'=>'Groupe classe','login_code'=>'Code élève'] as $field=>$label)$information[]=[$label,(string)($student[$field]??'')];
        $information[]=['Statut',t(['active'=>'Actif','pending'=>'Courriel à valider','archived'=>'Compte archivé'][$student['account_status']])];
        // Match the enrolments shown in the management panel; account controls themselves are not document content.
        $query=$pdo->prepare('SELECT c.title,e.status,e.joined_at,e.archived_at FROM enrollments e JOIN courses c ON c.id=e.course_id WHERE e.student_id=? AND c.teacher_id=? ORDER BY c.title');$query->execute([$studentId,$teacherId]);$enrollments=$query->fetchAll(PDO::FETCH_ASSOC);
        $history=[];$page=1;
        do{
            $batch=StudentAdminHistory::history($pdo,$studentId,$teacherId,$page++);
            foreach($batch['items'] as $entry)$history[]=$entry;
            if($batch['has_more']&&$page>100000)throw new RuntimeException('Export indisponible');
        }while($batch['has_more']);
        if($ownsTransaction)$pdo->commit();
        return ['student_id'=>$studentId,'name'=>trim($student['first_name'].' '.$student['last_name']),'information'=>$information,'enrollments'=>$enrollments,'history'=>$history];
    }catch(Throwable $exception){if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();throw $exception;}
}

function student_admin_export_markdown(array $data,bool $includeHistory=true): string
{
    $plain=static fn(string $text):string=>document_markdown_text($text);
    $lines=['# '.$plain(mb_strtoupper(t('Suivi administratif / pédagogique'),'UTF-8')),'','## '.mb_strtoupper(t('Informations'),'UTF-8'),''];
    foreach($data['information'] as [$label,$value])$lines[]='**'.$plain(t($label)).'** : '.$plain($value!==''?$value:'—').'  ';
    $lines[]='';$lines[]='## '.mb_strtoupper(t('Inscriptions'),'UTF-8');$lines[]='';
    foreach($data['enrollments'] as $enrollment){
        $lines[]='- **'.$plain($enrollment['title']).'** · '.t($enrollment['status']==='active'?'Participation active':'Participation archivée');
        $lines[]='  - '.t('Inscription, le :date',['date'=>student_history_date($enrollment['joined_at'])]);
        if($enrollment['archived_at'])$lines[]='  - '.t('Archivé le :date',['date'=>student_history_date($enrollment['archived_at'])]);
    }
    if(!$data['enrollments'])$lines[]=t('Aucune participation à vos cours.');
    $lines[]='';$lines[]='## '.mb_strtoupper(t('Historique'),'UTF-8');$lines[]='';
    if(!$includeHistory)return rtrim(implode("\n",$lines))."\n";
    foreach($data['history'] as $index=>$entry){
        if($index){$lines[]='---';$lines[]='';}
        $lines[]=student_admin_export_entry_markdown($entry,(int)$data['student_id']);
    }
    if(!$data['history'])$lines[]=t('Aucun suivi enregistré pour cet élève.');
    return rtrim(implode("\n",$lines))."\n";
}

function student_admin_export_entry_markdown(array $entry,int $studentId): string
{
    $plain=static fn(string $text):string=>document_markdown_text($text);
    $lines=['**'.t(StudentAdminHistory::KINDS[$entry['kind']]??'Message').'** '.$plain(t('créé par :name, le :date',['name'=>$entry['author_name']?:t('Enseignant'),'date'=>student_history_date($entry['created_at'])])).'  '];
    $others=StudentAdminHistory::otherParticipants($entry,$studentId);
    if($others)$lines[]='**'.t('Autres participants').'** : '.$plain(implode(', ',array_column($others,'name'))).'  ';
    $subject=($entry['course_title']!==''?$entry['course_title'].' / ':'').$entry['title'];
    $lines[]='**'.$plain(t('Concerne')).'** : '.$plain($subject).'  ';
    $lines[]='**'.t('Contenu').'**  ';
    // Keep the complete content and its internal Markdown formatting, even when collapsed on screen.
    $lines[]=trim($entry['body']);
    if($entry['source']==='announcement'){
        $lines[]='';
        $lines[]='**'.t('À').'** : '.$plain($entry['email']).($entry['cc']?' · **CC** : '.$plain($entry['cc']):'').($entry['bcc']?' · **CCI** : '.$plain($entry['bcc']):'').'  ';
        $status=t($entry['read_at']?'Lu dans l’application':'Non lu dans l’application');
        if($entry['mail_status'])$status.=' · '.t(['sent'=>'Envoyé','pending'=>'En attente','failed'=>'En échec'][$entry['mail_status']]);
        if($entry['sent_at'])$status.=' '.student_history_date($entry['sent_at']);
        $lines[]=$status;
    }elseif($entry['revision']>0){$lines[]='';$lines[]='*'.$plain(t('Modifié le :date',['date'=>student_history_date($entry['updated_at'])])).'*';}
    return rtrim(implode("\n",$lines))."\n";
}

function student_admin_export_pdf_html(array $data): string
{
    $html=Markdown::render(student_admin_export_markdown($data,false));
    foreach($data['history'] as $index=>$entry){
        $html.='<div style="page-break-inside:avoid">'.($index?'<hr>':'').Markdown::render(student_admin_export_entry_markdown($entry,(int)$data['student_id'])).'</div>';
    }
    if(!$data['history'])$html.='<p>'.e(t('Aucun suivi enregistré pour cet élève.')).'</p>';
    // Markdown produces escaped HTML. Keep image descriptions and links as text, without fetching resources.
    $html=preg_replace_callback('/<img\b[^>]*>/i',static function(array $match):string{
        $values=[];
        foreach(['alt','src'] as $attribute){
            preg_match('/\b'.$attribute.'=(["\x27])(.*?)\1/i',$match[0],$value);
            $values[$attribute]=html_entity_decode($value[2]??'',ENT_QUOTES|ENT_HTML5,'UTF-8');
        }
        return e(trim($values['alt']).($values['src']!==''?' ('.$values['src'].')':''));
    },$html)??$html;
    $html=preg_replace_callback('/<(h[1-6]|p|li|td|th|small|code|pre)(\s[^>]*|)>/i',static function(array $match):string{
        $attributes=$match[2];
        if(preg_match('/\bstyle=(["\x27])(.*?)\1/i',$attributes)){
            $attributes=preg_replace_callback('/\bstyle=(["\x27])(.*?)\1/i',static fn(array $style):string=>'style='.$style[1].$style[2].';font-size:10pt;'.$style[1],$attributes);
        }else{$attributes.=' style="font-size:10pt"';}
        return '<'.$match[1].$attributes.'>';
    },$html)??$html;
    return pdf_document(t('Gestion de l’élève').' · '.$data['name'],$html);
}
