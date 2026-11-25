<?php
declare(strict_types=1);

/** Two metadata queries for the entire selection; reuse the Acquis calculation. */
function progress_export_mastery(PDO $pdo,array $data): array
{
    $queries=[
        'objective'=>"SELECT pi.id AS item_id,po.id AS framework_id,po.title,'' AS code,po.description,pi.position AS framework_position FROM pathway_items pi JOIN page_objectives po ON po.page_id=pi.page_id WHERE pi.course_id=? ORDER BY pi.position,po.position,po.id",
        'skill'=>"SELECT pi.id AS item_id,f.id AS framework_id,f.title,f.code,f.description,f.position AS framework_position FROM course_skills f JOIN item_skills l ON l.skill_id=f.id JOIN pathway_items pi ON pi.id=l.pathway_item_id AND pi.course_id=f.course_id WHERE f.course_id=? ORDER BY f.position,f.id,pi.position,pi.id",
    ];
    $items=array_column($data['items'],null,'id');$result=[];
    foreach($queries as $kind=>$sql){
        $query=$pdo->prepare($sql);$query->execute([$data['course']['id']]);$references=$query->fetchAll(PDO::FETCH_ASSOC);
        foreach($data['students'] as $student){
            $studentId=(int)$student['id'];$rows=[];
            foreach($references as $reference){
                $item=$items[(int)$reference['item_id']];$progress=$data['progress'][$studentId][(int)$item['id']]??[];
                $rows[]=$reference+[
                    'is_evaluation'=>$item['is_evaluation'],'self_evaluation_enabled'=>$item['self_evaluation_enabled'],
                    'evaluation_weight'=>$item['evaluation_weight'],'framework_tracking_enabled'=>$item['framework_tracking_enabled'],
                    'accessible'=>$item['access_mode']==='all'||($item['access_mode']==='restricted'&&!empty($data['access'][$studentId][$item['id']])),
                    'student_level'=>$progress['student_level']??null,'student_validated_at'=>$progress['student_validated_at']??null,
                    'teacher_level'=>$progress['teacher_level']??null,'teacher_validated_at'=>$progress['teacher_validated_at']??null,
                    'evaluation_score'=>$progress['evaluation_score']??null,'evaluation_included'=>$progress['evaluation_included']??1,
                ];
            }
            $levels=framework_progress_from_rows($rows,$kind);
            usort($levels,static function(array $a,array $b):int{
                $left=$a['teacher_level']??$a['student_level'];$right=$b['teacher_level']??$b['student_level'];
                if($left===null&&$right!==null)return 1;
                if($right===null&&$left!==null)return -1;
                return (($right??-1)<=>($left??-1))?:pathway_natural_compare($a['title'],$b['title'])?:($a['id']<=>$b['id']);
            });
            $result[$studentId][$kind]=$levels;
        }
    }
    return $result;
}

function progress_report_name(array $student): string
{
    return trim($student['first_name'].' '.$student['last_name']);
}

function progress_report_contact(array $student): string
{
    return implode(' · ',array_filter([$student['class_group'],$student['email']],static fn(string $part):bool=>$part!==''));
}

/** Presentation data shared by PDF, the live HTML page and the workbook. */
function progress_report_student(array $data,array $student): array
{
    $studentId=(int)$student['id'];$position=0;$sections=[];$comments=[];
    foreach($data['sections'] as $section){
        $rows=[];$group=$section['group'];
        foreach($section['items'] as $item){
            $itemId=(int)$item['id'];$progress=$data['progress'][$studentId][$itemId]??[];
            $accessible=($item['group_visible']??true)&&($item['access_mode']==='all'||($item['access_mode']==='restricted'&&!empty($data['access'][$studentId][$itemId])));
            $title=($accessible?++$position.'. ':'').$item['title'];$quizzes=[];
            foreach($data['quizzes'][$itemId]??[] as $index=>$quiz){
                $attempt=$data['attempts'][$studentId][$itemId][$quiz['block_id']][$quiz['key']]??null;
                $quizzes[]=['title'=>t('QCM').' '.($index+1).($quiz['caption']!==''?' · '.$quiz['caption']:''),
                    'score'=>$attempt&&(int)$attempt['total_questions']>0?(!empty($attempt['adaptive'])?(float)$attempt['score_percent']/10:10*(int)$attempt['correct_questions']/(int)$attempt['total_questions']):null,
                    'answered_at'=>$attempt['answered_at']??null];
            }
            $rows[]=['title'=>$title,'deadline'=>$item['deadline'],'completed_at'=>$progress['completed_at']??null,
                'evaluation'=>$item['is_evaluation']?($progress['evaluation_score']??null):null,
                'evaluation_included'=>(bool)$item['framework_tracking_enabled']&&(bool)($progress['evaluation_included']??1),
                'weight'=>$item['is_evaluation']?$item['evaluation_weight']:null,
                'student_level'=>$item['self_evaluation_enabled']&&!empty($progress['student_validated_at'])?($progress['student_level']??null):null,
                'teacher_level'=>$item['self_evaluation_enabled']&&!empty($progress['teacher_validated_at'])?($progress['teacher_level']??null):null,
                'quizzes'=>$quizzes];
            $studentNote=$item['self_evaluation_enabled']&&!empty($progress['student_validated_at'])?trim((string)($progress['student_note']??'')):'';
            $teacherNote=trim((string)($progress['teacher_note']??''));
            if($studentNote!==''||$teacherNote!=='')$comments[]=['title'=>($group?$group['title'].' · ':'').$title,'student'=>$studentNote,'teacher'=>$teacherNote];
        }
        $sections[]=['title'=>$group['title']??null,'rows'=>$rows];
    }
    return ['student'=>$student,'sections'=>$sections,'comments'=>$comments,'mastery'=>$data['mastery'][$studentId]??[]];
}

function progress_report_url(string $token): string
{
    return app_public_base_url().'/?'.http_build_query(['view'=>'progress-report','key'=>$token,'lang'=>current_language()]);
}

function progress_report_internal_url(int $enrollmentId): string
{
    return app_public_base_url().'/?'.http_build_query(['view'=>'progress-report','enrollment'=>$enrollmentId]);
}

/** Authentication and enrollment ACL are independent of the optional bearer link. */
function progress_report_internal_data(PDO $pdo,int $enrollmentId,int $userId): array
{
    $q=$pdo->prepare("SELECT e.student_id,e.course_id,c.teacher_id,u.role FROM enrollments e JOIN courses c ON c.id=e.course_id JOIN users student ON student.id=e.student_id JOIN users u ON u.id=? AND u.account_status='active' JOIN users owner ON owner.id=c.teacher_id AND owner.account_status='active' AND owner.role='teacher' WHERE e.id=? AND e.status='active' AND student.account_status='active' AND c.archived=0");
    $q->execute([$userId,$enrollmentId]);$row=$q->fetch(PDO::FETCH_ASSOC);
    if(!$row||!(($row['role']==='student'&&(int)$row['student_id']===$userId)||($row['role']==='teacher'&&teacher_can_access_course($pdo,(int)$row['course_id'],$userId))))throw new InvalidArgumentException('Bilan indisponible.');
    return course_progress_export_data($pdo,(int)$row['course_id'],(int)$row['teacher_id'],[(int)$row['student_id']],'detailed');
}

/** Stable per-enrollment links: exporting again does not invalidate printed QR codes. */
function progress_report_attach_links(PDO $pdo,array $data,int $teacherId): array
{
    $query=$pdo->prepare("SELECT 1 FROM users WHERE id=? AND role='teacher' AND account_status='active'");$query->execute([$teacherId]);
    if(!$query->fetchColumn()||!teacher_can_access_course($pdo,(int)$data['course']['id'],$teacherId))throw new InvalidArgumentException('Accès interdit.');
    app_public_base_url(); // Validate deployment URL before writing anything.
    $own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();
    try{
        $insert=$pdo->prepare("INSERT INTO progress_report_links(enrollment_id,token) SELECT e.id,? FROM enrollments e JOIN users u ON u.id=e.student_id JOIN courses c ON c.id=e.course_id WHERE e.id=? AND e.course_id=? AND e.student_id=? AND e.status='active' AND u.account_status='active' AND c.archived=0 ON CONFLICT(enrollment_id) DO NOTHING");
        $find=$pdo->prepare('SELECT token,enabled FROM progress_report_links WHERE enrollment_id=?');
        foreach($data['students'] as $student){
            $insert->execute([bin2hex(random_bytes(32)),$student['enrollment_id'],$data['course']['id'],$student['id']]);
            $find->execute([$student['enrollment_id']]);$link=$find->fetch(PDO::FETCH_ASSOC);
            if(!$link)throw new InvalidArgumentException('Apprenant introuvable.');
            $data['report_urls'][(int)$student['id']]=$link['enabled']?progress_report_url($link['token']):progress_report_internal_url((int)$student['enrollment_id']);
        }
        if($own)$pdo->commit();return $data;
    }catch(Throwable $error){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $error;}
}

function progress_report_revoke(PDO $pdo,int $enrollmentId,int $teacherId): void
{
    progress_report_set_enabled($pdo,$enrollmentId,$teacherId,false);
}

function progress_report_set_enabled(PDO $pdo,int $enrollmentId,int $teacherId,bool $enabled): void
{
    $query=$pdo->prepare("SELECT e.course_id FROM enrollments e JOIN users u ON u.id=? AND u.role='teacher' AND u.account_status='active' WHERE e.id=?");$query->execute([$teacherId,$enrollmentId]);$courseId=$query->fetchColumn();
    if(!$courseId||!teacher_can_access_course($pdo,(int)$courseId,$teacherId))throw new InvalidArgumentException('Accès interdit.');
    $token=bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO progress_report_links(enrollment_id,token,enabled) VALUES(?,?,?) ON CONFLICT(enrollment_id) DO UPDATE SET enabled=excluded.enabled,token=CASE WHEN excluded.enabled=0 THEN excluded.token ELSE progress_report_links.token END')->execute([$enrollmentId,$token,(int)$enabled]);
}

function progress_report_data(PDO $pdo,string $token): array
{
    if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new InvalidArgumentException('Bilan indisponible.');
    $query=$pdo->prepare("SELECT e.student_id,e.course_id,c.teacher_id FROM progress_report_links l JOIN enrollments e ON e.id=l.enrollment_id AND e.status='active' JOIN users u ON u.id=e.student_id AND u.account_status='active' JOIN courses c ON c.id=e.course_id AND c.archived=0 JOIN users owner ON owner.id=c.teacher_id AND owner.account_status='active' AND owner.role='teacher' WHERE l.token=? AND l.enabled=1");$query->execute([$token]);$row=$query->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new InvalidArgumentException('Bilan indisponible.');
    return course_progress_export_data($pdo,(int)$row['course_id'],(int)$row['teacher_id'],[(int)$row['student_id']],'detailed');
}

function progress_report_http(): never
{
    header('Content-Type: text/html; charset=UTF-8');header('Cache-Control: private, no-store');
    header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow, noarchive');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data:; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");
    header('X-Content-Type-Options: nosniff');
    // Language comes from the printed link, not an unrelated signed-in account.
    if(isset($_GET['lang']))use_language(normalize_language((string)$_GET['lang'])??'fr');
    $user=isset($_GET['enrollment'])?actor():null;
    if(isset($_GET['enrollment'])&&!$user){$_SESSION['pending_progress_report_enrollment']=max(0,(int)$_GET['enrollment']);redirect('login');}
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    try{
        $data=isset($_GET['enrollment'])?progress_report_internal_data(db(),(int)$_GET['enrollment'],(int)$user['id']):progress_report_data(db(),is_string($_GET['key']??null)?$_GET['key']:'');
        if(isset($_GET['enrollment']))$data['return_url']=$user['role']==='student'?route('competencies',['course'=>$data['course']['id']]):route('student-detail',['enrollment'=>(int)$_GET['enrollment']]);
        echo course_progress_export_pdf($data,true);
    }
    catch(InvalidArgumentException){http_response_code(404);echo '<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e(t('Bilan indisponible.')).'</title><p>'.e(t('Bilan indisponible.')).'</p></html>';}
    exit;
}
