<?php

declare(strict_types=1);

final class TransferException extends RuntimeException {}

function transfer_document(array $document, string $kind): array
{
    $accepted=[$kind];
    if(str_starts_with($kind,'liike.'))$accepted[]='elan.'.substr($kind,6);
    if (!in_array(($document['format'] ?? ''),$accepted,true) || ($document['version'] ?? null) !== 1) {
        throw new TransferException('Format de fichier incompatible ou version non prise en charge.');
    }
    return $document;
}

function export_page_document(PDO $pdo, int $pageId, int $teacherId, ?string $publicRoot = null): array
{
    $query=$pdo->prepare('SELECT * FROM pages WHERE id=?'); $query->execute([$pageId]);
    $page=$query->fetch(PDO::FETCH_ASSOC); if(!$page||!teacher_can_access_page($pdo,$pageId,$teacherId))throw new TransferException('Page introuvable.');
    $blocks=$pdo->prepare('SELECT type,body,caption,position FROM page_blocks WHERE page_id=? ORDER BY position'); $blocks->execute([$pageId]);
    $tags=$pdo->prepare('SELECT t.name,t.color FROM tags t JOIN page_tags pt ON pt.tag_id=t.id WHERE pt.page_id=? ORDER BY t.name'); $tags->execute([$pageId]);
    $objectives=$pdo->prepare('SELECT title,description,position FROM page_objectives WHERE page_id=? ORDER BY position,id'); $objectives->execute([$pageId]);
    $exportedBlocks=$blocks->fetchAll(PDO::FETCH_ASSOC);
    if($publicRoot){foreach($exportedBlocks as &$block){$body=(string)$block['body'];if(in_array($block['type'],['image','file'],true)&&str_starts_with($body,'uploads/')){$path=rtrim($publicRoot,'/').'/'.ltrim($body,'/');if(is_file($path)&&filesize($path)!==false&&filesize($path)<=10*1024*1024){$data=file_get_contents($path);if($data!==false)$block['embedded_file']=['filename'=>basename($path),'mime'=>(string)(mime_content_type($path)?:'application/octet-stream'),'data_base64'=>base64_encode($data)];}}}unset($block);}
    return ['format'=>'liike.page','version'=>1,'exported_at'=>gmdate(DATE_ATOM),'page'=>[
        'reference'=>$page['reference'],'title'=>$page['title'],'summary'=>$page['summary'],'status'=>$page['status'],
        'estimated_minutes'=>(int)$page['estimated_minutes'],'blocks'=>$exportedBlocks,'tags'=>$tags->fetchAll(PDO::FETCH_ASSOC),'objectives'=>$objectives->fetchAll(PDO::FETCH_ASSOC),
    ]];
}

function import_page_document(PDO $pdo, array $document, int $teacherId, string $mode, ?string $publicRoot = null): int
{
    transfer_document($document,'liike.page'); $page=$document['page']??null;
    if(!is_array($page))throw new TransferException('La page est absente du fichier.');
    $reference=trim((string)($page['reference']??'')); $title=trim((string)($page['title']??''));
    if($reference===''||$title==='')throw new TransferException('La référence et le titre de la page sont requis.');
    $blocks=$page['blocks']??[]; $tags=$page['tags']??[]; $objectives=$page['objectives']??[];
    if(!is_array($blocks)||count($blocks)>100||!is_array($tags)||count($tags)>100||!is_array($objectives)||count($objectives)>50)throw new TransferException('Le contenu de la page dépasse les limites acceptées.');
    foreach($blocks as $block){if(!is_array($block)||!in_array($block['type']??'', ['markdown','image','file','iframe'],true))throw new TransferException('Un bloc de page est invalide.');if(isset($block['embedded_file'])){$embedded=$block['embedded_file'];$decoded=is_array($embedded)?base64_decode((string)($embedded['data_base64']??''),true):false;if($decoded===false||strlen($decoded)>10*1024*1024)throw new TransferException('Une pièce jointe intégrée est invalide ou dépasse 10 Mo.');}}
    foreach($objectives as $objective)if(!is_array($objective)||trim((string)($objective['title']??''))==='')throw new TransferException('Un objectif de page est invalide.');

    $existingQuery=$pdo->prepare('SELECT * FROM pages WHERE reference=?'); $existingQuery->execute([$reference]); $existing=$existingQuery->fetch(PDO::FETCH_ASSOC);
    $overwrite=$mode==='overwrite';
    if($existing&&$overwrite&&(int)$existing['owner_id']!==$teacherId)throw new TransferException('Cette référence de page appartient à un autre enseignant.');
    if($overwrite && !$existing)$existing=null;

    $pdo->beginTransaction();
    try{
        if($overwrite && $existing){
            $pageId=(int)$existing['id'];
            $pdo->prepare('UPDATE pages SET title=?,summary=?,status=?,estimated_minutes=?,updated_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([$title,trim((string)($page['summary']??'')),($page['status']??'draft')==='ready'?'ready':'draft',max(1,(int)($page['estimated_minutes']??15)),$teacherId,$pageId]);
            $pdo->prepare('DELETE FROM page_blocks WHERE page_id=?')->execute([$pageId]);
            $pdo->prepare('DELETE FROM page_tags WHERE page_id=?')->execute([$pageId]);
            $pdo->prepare('DELETE FROM page_objectives WHERE page_id=?')->execute([$pageId]);
        }else{
            $newReference=$overwrite?$reference:new_entity_reference('PAGE');
            $importTitle=$overwrite?$title:$title.' · import';
            $pdo->prepare('INSERT INTO pages(reference,title,summary,status,estimated_minutes,owner_id,updated_by) VALUES(?,?,?,?,?,?,?)')
                ->execute([$newReference,$importTitle,trim((string)($page['summary']??'')),($page['status']??'draft')==='ready'?'ready':'draft',max(1,(int)($page['estimated_minutes']??15)),$teacherId,$teacherId]);
            $pageId=(int)$pdo->lastInsertId();
        }
        $insertBlock=$pdo->prepare('INSERT INTO page_blocks(page_id,type,body,caption,position) VALUES(?,?,?,?,?)');
        foreach(array_values($blocks) as $index=>$block){$body=(string)($block['body']??'');if(isset($block['embedded_file'])&&$publicRoot){$embedded=$block['embedded_file'];$decoded=base64_decode((string)$embedded['data_base64'],true);$safe=preg_replace('/[^A-Za-z0-9._-]/','-',basename((string)($embedded['filename']??'fichier')));$safe=strtolower(new_entity_reference('ASSET')).'-'.$safe;$uploadDir=rtrim($publicRoot,'/').'/uploads';if(!is_dir($uploadDir)&&!mkdir($uploadDir,0775,true)&&!is_dir($uploadDir))throw new TransferException('Le dossier des fichiers importés ne peut pas être créé.');if(file_put_contents($uploadDir.'/'.$safe,$decoded)===false)throw new TransferException('Une pièce jointe ne peut pas être importée.');$body='uploads/'.$safe;}$insertBlock->execute([$pageId,$block['type'],$body,trim((string)($block['caption']??'')),$index+1]);}
        $findTag=$pdo->prepare('SELECT id FROM tags WHERE name=?'); $insertTag=$pdo->prepare('INSERT INTO tags(name,color) VALUES(?,?)'); $linkTag=$pdo->prepare('INSERT OR IGNORE INTO page_tags(page_id,tag_id) VALUES(?,?)');
        foreach($tags as $tag){
            if(!is_array($tag)||trim((string)($tag['name']??''))==='')continue;
            $name=trim((string)$tag['name']); $findTag->execute([$name]); $tagId=$findTag->fetchColumn();
            if($tagId===false){$insertTag->execute([$name,trim((string)($tag['color']??''))?:'#e8e5ff']);$tagId=$pdo->lastInsertId();}
            $linkTag->execute([$pageId,(int)$tagId]);
        }
        $insertObjective=$pdo->prepare('INSERT INTO page_objectives(page_id,title,description,position) VALUES(?,?,?,?)');$seenObjectives=[];
        foreach(array_values($objectives) as $index=>$objective){$name=trim((string)$objective['title']);$key=mb_strtolower($name,'UTF-8');if(isset($seenObjectives[$key]))continue;$seenObjectives[$key]=true;$insertObjective->execute([$pageId,$name,trim((string)($objective['description']??'')),$index+1]);}
        Qcm::syncPageTag($pdo,$pageId);
        $pdo->commit(); return $pageId;
    }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
}

function export_course_document(PDO $pdo, int $courseId, int $teacherId, bool $includeOptions): array
{
    $query=$pdo->prepare('SELECT * FROM courses WHERE id=?');$query->execute([$courseId]);$course=$query->fetch(PDO::FETCH_ASSOC);
    if(!$course||!teacher_can_access_course($pdo,$courseId,$teacherId))throw new TransferException('Parcours introuvable.');
    $itemsQuery=$pdo->prepare('SELECT pi.*,p.reference AS page_reference,p.title AS page_title FROM pathway_items pi JOIN pages p ON p.id=pi.page_id WHERE pi.course_id=? ORDER BY pi.position');$itemsQuery->execute([$courseId]);
    $items=[];
    foreach($itemsQuery->fetchAll(PDO::FETCH_ASSOC) as $item){
        $exported=['page_reference'=>$item['page_reference'],'page_title'=>$item['page_title'],'position'=>(int)$item['position'],'deadline'=>$item['deadline'],'is_evaluation'=>(bool)$item['is_evaluation'],'evaluation_weight'=>(float)($item['evaluation_weight']??1),'instructions'=>$item['instructions'],'access_mode'=>$item['access_mode']];
        if($includeOptions){
            $skills=$pdo->prepare('SELECT s.code FROM course_skills s JOIN item_skills i ON i.skill_id=s.id WHERE i.pathway_item_id=? ORDER BY s.position');$skills->execute([$item['id']]);
            $exported['skills']=$skills->fetchAll(PDO::FETCH_COLUMN);
        }
        $items[]=$exported;
    }
    $options=null;
    if($includeOptions){
        $skills=$pdo->prepare('SELECT code,title,description,position FROM course_skills WHERE course_id=? ORDER BY position,id');$skills->execute([$courseId]);
        $rewards=$pdo->prepare('SELECT name,icon,color,default_points,active FROM reward_types WHERE course_id=? ORDER BY id');$rewards->execute([$courseId]);
        $options=['skills'=>$skills->fetchAll(PDO::FETCH_ASSOC),'rewards'=>$rewards->fetchAll(PDO::FETCH_ASSOC)];
    }
    return ['format'=>'liike.pathway','version'=>1,'exported_at'=>gmdate(DATE_ATOM),'includes_options'=>$includeOptions,'course'=>[
        'reference'=>$course['reference'],'title'=>$course['title'],'code'=>$course['code'],'description'=>$course['description'],'accent'=>$course['accent'],
    ],'items'=>$items,'options'=>$options];
}

function import_course_document(PDO $pdo, array $document, int $teacherId, string $mode, bool $resetDeadlines): int
{
    transfer_document($document,'liike.pathway');$course=$document['course']??null;$items=$document['items']??null;
    if(!is_array($course)||!is_array($items)||count($items)>500)throw new TransferException('Le parcours ou ses étapes sont invalides.');
    $reference=trim((string)($course['reference']??''));$title=trim((string)($course['title']??''));if($reference===''||$title==='')throw new TransferException('La référence et le titre du parcours sont requis.');
    $pageLookup=$pdo->prepare('SELECT id FROM pages WHERE reference=?');$resolvedPages=[];$missing=[];
    foreach($items as $index=>$item){
        if(!is_array($item)||trim((string)($item['page_reference']??''))==='')throw new TransferException('Une étape ne contient pas de référence de page.');
        $pageReference=trim((string)$item['page_reference']);$pageLookup->execute([$pageReference]);$pageId=$pageLookup->fetchColumn();
        if($pageId===false||!teacher_can_access_page($pdo,(int)$pageId,$teacherId))$missing[]=$pageReference;else $resolvedPages[$index]=(int)$pageId;
    }
    if($missing)throw new TransferException('Import arrêté : page(s) absente(s) de la bibliothèque : '.implode(', ',array_unique($missing)).'.');
    $options=is_array($document['options']??null)?$document['options']:['skills'=>[],'rewards'=>[]];
    foreach(['skills','rewards'] as $key)if(!is_array($options[$key]??[]))throw new TransferException('Les options du parcours sont invalides.');

    $existingQuery=$pdo->prepare('SELECT * FROM courses WHERE reference=?');$existingQuery->execute([$reference]);$existing=$existingQuery->fetch(PDO::FETCH_ASSOC);
    $overwrite=$mode==='overwrite';
    if($existing&&$overwrite&&(int)$existing['teacher_id']!==$teacherId)throw new TransferException('Cette référence de parcours appartient à un autre enseignant.');
    $pdo->beginTransaction();
    try{
        if($overwrite && $existing){
            $courseId=(int)$existing['id'];
            $pdo->prepare("DELETE FROM edit_locks WHERE entity_type='pathway_item' AND entity_id IN (SELECT id FROM pathway_items WHERE course_id=?)")->execute([$courseId]);
            $pdo->prepare('DELETE FROM pathway_items WHERE course_id=?')->execute([$courseId]);
            $pdo->prepare('DELETE FROM course_skills WHERE course_id=?')->execute([$courseId]);
            $pdo->prepare('DELETE FROM reward_types WHERE course_id=?')->execute([$courseId]);
            $pdo->prepare('UPDATE courses SET title=?,description=?,accent=?,archived=0 WHERE id=?')->execute([$title,(string)($course['description']??''),trim((string)($course['accent']??''))?:'#6d5dfc',$courseId]);
        }else{
            $newReference=$overwrite?$reference:new_entity_reference('COURSE');$sourceCode=trim((string)($course['code']??'COURS'));
            $newCode=unique_course_code($pdo,$sourceCode);$importTitle=$overwrite?$title:$title.' · import';
            $pdo->prepare('INSERT INTO courses(reference,title,code,description,teacher_id,accent,archived) VALUES(?,?,?,?,?,?,0)')->execute([$newReference,$importTitle,$newCode,(string)($course['description']??''),$teacherId,trim((string)($course['accent']??''))?:'#6d5dfc']);
            $courseId=(int)$pdo->lastInsertId();
        }
        $skillMap=[];$insertSkill=$pdo->prepare('INSERT INTO course_skills(course_id,code,title,description,position) VALUES(?,?,?,?,?)');
        foreach($options['skills']??[] as $index=>$skill){if(!is_array($skill)||trim((string)($skill['code']??''))===''||trim((string)($skill['title']??''))==='')continue;$code=strtoupper(trim((string)$skill['code']));$insertSkill->execute([$courseId,$code,trim((string)$skill['title']),(string)($skill['description']??''),(int)($skill['position']??$index+1)]);$skillMap[$code]=(int)$pdo->lastInsertId();}
        $insertReward=$pdo->prepare('INSERT INTO reward_types(course_id,name,icon,color,default_points,active) VALUES(?,?,?,?,?,?)');
        foreach($options['rewards']??[] as $reward){if(!is_array($reward)||trim((string)($reward['name']??''))==='')continue;$insertReward->execute([$courseId,trim((string)$reward['name']),trim((string)($reward['icon']??''))?:'✨',trim((string)($reward['color']??''))?:'#6d5dfc',normalize_reward_points($reward['default_points']??1),!empty($reward['active'])?1:0]);}
        $insertItem=$pdo->prepare('INSERT INTO pathway_items(course_id,page_id,position,deadline,is_evaluation,evaluation_weight,instructions,access_mode) VALUES(?,?,?,?,?,?,?,?)');$linkSkill=$pdo->prepare('INSERT INTO item_skills(pathway_item_id,skill_id) VALUES(?,?)');
        foreach(array_values($items) as $index=>$item){$accessMode=in_array($item['access_mode']??'all',['all','restricted','none'],true)?(string)$item['access_mode']:'all';$isEvaluation=!empty($item['is_evaluation']);$weight=normalize_evaluation_weight($item['evaluation_weight']??1);if($isEvaluation&&$weight===null)throw new TransferException('La pondération d’une évaluation est invalide.');$insertItem->execute([$courseId,$resolvedPages[$index],$index+1,$resetDeadlines?null:(trim((string)($item['deadline']??''))?:null),$isEvaluation?1:0,$isEvaluation?$weight:1,(string)($item['instructions']??''),$accessMode]);$itemId=(int)$pdo->lastInsertId();foreach((array)($item['skills']??[]) as $code)if(isset($skillMap[strtoupper((string)$code)]))$linkSkill->execute([$itemId,$skillMap[strtoupper((string)$code)]]);}
        $pdo->commit();return $courseId;
    }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
}

function export_students_document(PDO $pdo, int $teacherId): array
{
    $query=$pdo->prepare("SELECT DISTINCT u.* FROM users u JOIN enrollments e ON e.student_id=u.id JOIN courses c ON c.id=e.course_id WHERE u.role='student' AND c.teacher_id=? ORDER BY u.last_name,u.first_name");$query->execute([$teacherId]);$students=[];
    $courses=$pdo->prepare('SELECT c.reference,e.status FROM enrollments e JOIN courses c ON c.id=e.course_id WHERE e.student_id=? AND c.teacher_id=? ORDER BY c.title');
    foreach($query->fetchAll(PDO::FETCH_ASSOC) as $student){$courses->execute([$student['id'],$teacherId]);$students[]=['first_name'=>$student['first_name'],'last_name'=>$student['last_name'],'email'=>$student['email'],'class_group'=>$student['class_group'],'phone'=>$student['phone'],'login_code'=>$student['login_code'],'language'=>normalize_language((string)($student['language']??'')),'courses'=>$courses->fetchAll(PDO::FETCH_ASSOC)];}
    return ['format'=>'liike.students','version'=>1,'exported_at'=>gmdate(DATE_ATOM),'students'=>$students];
}

function validate_students_document(PDO $pdo, array $document, int $teacherId): array
{
    transfer_document($document,'liike.students');$students=$document['students']??null;if(!is_array($students)||count($students)>500)throw new TransferException('La liste des élèves est invalide ou trop volumineuse.');
    $courseLookup=$pdo->prepare('SELECT id FROM courses WHERE reference=? AND teacher_id=?');$validated=[];$emails=[];
    foreach($students as $student){
        if(!is_array($student))throw new TransferException('Une fiche élève est invalide.');$first=trim((string)($student['first_name']??''));$last=mb_strtoupper(trim((string)($student['last_name']??'')),'UTF-8');$email=mb_strtolower(trim((string)($student['email']??'')),'UTF-8');
        if(mb_strlen(preg_replace('/\s+/u','',$first)??'')<2||mb_strlen(preg_replace('/\s+/u','',$last)??'')<3||!filter_var($email,FILTER_VALIDATE_EMAIL)||isset($emails[$email]))throw new TransferException('Une fiche élève contient un nom ou un courriel invalide ou dupliqué.');$emails[$email]=true;
        $resolved=[];foreach((array)($student['courses']??[]) as $course){if(!is_array($course)||trim((string)($course['reference']??''))==='')throw new TransferException('Une inscription de cours est invalide.');$courseLookup->execute([trim((string)$course['reference']),$teacherId]);$courseId=$courseLookup->fetchColumn();if($courseId===false)throw new TransferException('Import arrêté : parcours absent : '.trim((string)$course['reference']).'.');$resolved[]=['id'=>(int)$courseId,'status'=>($course['status']??'active')==='archived'?'archived':'active'];}
        $student['first_name']=$first;$student['last_name']=$last;$student['email']=$email;$student['language']=normalize_language((string)($student['language']??''));$student['resolved_courses']=$resolved;$validated[]=$student;
    }
    return $validated;
}

function import_students_document(PDO $pdo, array $document, int $teacherId, string $mode, bool $requireEmailVerification=true): array
{
    $students=validate_students_document($pdo,$document,$teacherId);$newUsers=[];$activated=0;$pdo->beginTransaction();
    try{
        $find=$pdo->prepare('SELECT * FROM users WHERE lower(email)=?');$update=$pdo->prepare('UPDATE users SET name=?,first_name=?,last_name=?,initials=?,class_group=?,phone=?,language=COALESCE(?,language) WHERE id=? AND role=?');
        $insert=$pdo->prepare("INSERT INTO users(name,first_name,last_name,email,role,initials,color,class_group,phone,login_code,language,account_status,created_at,managed_by) VALUES(?,?,?,?,'student',?,?,?,?,?,?,?,CURRENT_TIMESTAMP,?)");
        $activate=$pdo->prepare("UPDATE users SET account_status='active',email_verified_at=NULL,verification_token_hash=NULL,verification_expires_at=NULL WHERE id=? AND role='student' AND account_status='pending' AND managed_by=?");
        $deleteVerification=$pdo->prepare("DELETE FROM notification_outbox WHERE event='account.verification' AND recipient=?");
        foreach($students as $student){$find->execute([$student['email']]);$existing=$find->fetch(PDO::FETCH_ASSOC);if($existing&&$existing['role']!=='student')throw new TransferException('Le courriel '.$student['email'].' appartient à un enseignant.');$compactLast=preg_replace('/\s+/u','',$student['last_name'])??'';$initials=mb_strtoupper(mb_substr($student['first_name'],0,1).mb_substr($compactLast,0,1),'UTF-8');
            if($existing){$studentId=(int)$existing['id'];$update->execute([$student['first_name'].' '.$student['last_name'],$student['first_name'],$student['last_name'],$initials,trim((string)($student['class_group']??'')),trim((string)($student['phone']??''))?:null,$student['language'],$studentId,'student']);if(!$requireEmailVerification){$activate->execute([$studentId,$teacherId]);if($activate->rowCount()===1){$deleteVerification->execute([$student['email']]);$activated++;}}}
            else{$base=trim((string)($student['login_code']??''))?:student_code($student['first_name'],$student['last_name']);$code=unique_login_code($base,true);$colors=['#ef6a8a','#2da58d','#e49b35','#4178d0','#7f62d9'];$status=$requireEmailVerification?'pending':'active';$insert->execute([$student['first_name'].' '.$student['last_name'],$student['first_name'],$student['last_name'],$student['email'],$initials,$colors[abs(crc32($student['email']))%count($colors)],trim((string)($student['class_group']??'')),trim((string)($student['phone']??''))?:null,$code,$student['language'],$status,$teacherId]);$studentId=(int)$pdo->lastInsertId();$newUsers[]=['id'=>$studentId,'email'=>$student['email'],'first_name'=>$student['first_name'],'code'=>$code,'language'=>$student['language']];if(!$requireEmailVerification)$activated++;}
            if($mode==='overwrite'){$delete=$pdo->prepare('DELETE FROM enrollments WHERE student_id=? AND course_id IN (SELECT id FROM courses WHERE teacher_id=?)');$delete->execute([$studentId,$teacherId]);}
            $enroll=$pdo->prepare("INSERT INTO enrollments(course_id,student_id,status,archived_at) VALUES(?,?,?,CASE WHEN ?='archived' THEN CURRENT_TIMESTAMP ELSE NULL END) ON CONFLICT(course_id,student_id) DO UPDATE SET status=excluded.status,archived_at=excluded.archived_at");foreach($student['resolved_courses'] as $course)$enroll->execute([$course['id'],$studentId,$course['status'],$course['status']]);
        }
        $pdo->commit();return ['processed'=>count($students),'created'=>$newUsers,'activated'=>$activated];
    }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
}
