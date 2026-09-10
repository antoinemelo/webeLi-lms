<?php

declare(strict_types=1);

final class AnnouncementMessages
{
    public const VARIABLES=['prenom','nom','cours'];

    private static function body(string $body): string {return trim(str_replace(["\r\n","\r"],"\n",$body));}

    public static function templates(PDO $pdo,int $teacherId): array
    {
        $query=$pdo->prepare('SELECT id,name,title,body,revision FROM message_templates WHERE teacher_id=? ORDER BY name,id');$query->execute([$teacherId]);return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function validateText(string $title,string $body): void
    {
        if(trim($title)===''||trim($body)===''||mb_strlen($title)>160||mb_strlen($body)>5000||preg_match('/[\r\n]/',$title))throw new InvalidArgumentException('Saisissez un titre (160 caractères maximum) et un message (5 000 caractères maximum).');
        preg_match_all('/\{([^{}\r\n]+)\}/u',$title."\n".$body,$matches);
        foreach($matches[1] as $variable)if(!in_array($variable,self::VARIABLES,true))throw new InvalidArgumentException('Variable inconnue. Utilisez {prenom}, {nom} ou {cours}.');
    }

    public static function saveTemplate(PDO $pdo,int $teacherId,array $input): int
    {
        $name=trim((string)($input['name']??''));$title=trim((string)($input['title']??''));$body=self::body((string)($input['body']??''));
        if($name===''||mb_strlen($name)>100)throw new InvalidArgumentException('Le nom du modèle est obligatoire (100 caractères maximum).');
        self::validateText($title,$body);$id=(int)($input['template_id']??0);
        if($id){
            $query=$pdo->prepare('UPDATE message_templates SET name=?,title=?,body=?,revision=revision+1,updated_at=CURRENT_TIMESTAMP WHERE id=? AND teacher_id=? AND revision=?');
            $query->execute([$name,$title,$body,$id,$teacherId,(int)($input['revision']??-1)]);
            if(!$query->rowCount())throw new InvalidArgumentException('Le modèle a changé ou ne vous appartient pas. Rechargez la page.');
        }else{
            $pdo->prepare('INSERT INTO message_templates(teacher_id,name,title,body) VALUES(?,?,?,?)')->execute([$teacherId,$name,$title,$body]);$id=(int)$pdo->lastInsertId();
        }
        return $id;
    }

    public static function deleteTemplate(PDO $pdo,int $teacherId,int $id,int $revision): void
    {
        $query=$pdo->prepare('DELETE FROM message_templates WHERE id=? AND teacher_id=? AND revision=?');$query->execute([$id,$teacherId,$revision]);
        if(!$query->rowCount())throw new InvalidArgumentException('Le modèle a changé ou ne vous appartient pas. Rechargez la page.');
    }

    private static function email(string $value): string
    {
        $value=trim($value);
        if(!filter_var($value,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$value))throw new InvalidArgumentException('Une adresse électronique est absente ou invalide. Vérifiez les destinataires et votre profil.');
        return $value;
    }

    public static function substitute(string $text,array $values,bool $markdown=false): string
    {
        $replacements=[];
        foreach($values as $name=>$value){
            if($markdown)$value=str_replace(['\\','`','*','_','[',']','<','>'],['\\\\','\\`','\\*','\\_','\\[','\\]','&lt;','&gt;'],$value);
            $replacements['{'.$name.'}']=$value;
        }
        return strtr($text,$replacements);
    }

    /** Builds exactly the snapshots used for the app announcement and the outgoing mail. */
    public static function prepare(PDO $pdo,int $teacherId,array $input): array
    {
        $courseId=(int)($input['course_id']??0);
        if(!teacher_can_access_course($pdo,$courseId,$teacherId))throw new InvalidArgumentException('Parcours introuvable.');
        $query=$pdo->prepare('SELECT title FROM courses WHERE id=? AND archived=0');$query->execute([$courseId]);$course=$query->fetchColumn();
        if($course===false)throw new InvalidArgumentException('Parcours introuvable.');
        $templateId=(int)($input['template_id']??0);
        if($templateId){$query=$pdo->prepare('SELECT 1 FROM message_templates WHERE id=? AND teacher_id=?');$query->execute([$templateId,$teacherId]);if(!$query->fetchColumn())throw new InvalidArgumentException('Modèle introuvable.');}
        $title=trim((string)($input['title']??''));$body=self::body((string)($input['body']??''));self::validateText($title,$body);
        $selected=array_values(array_unique(array_map('intval',(array)($input['students']??[]))));
        $secondary=array_values(array_unique(array_map('intval',(array)($input['secondary']??[]))));
        if(!$selected)throw new InvalidArgumentException('Sélectionnez au moins un élève.');
        if(array_diff($secondary,$selected))throw new InvalidArgumentException('La copie CC doit concerner un élève sélectionné.');
        $query=$pdo->prepare("SELECT u.id,u.first_name,u.last_name,u.name,u.email,u.secondary_email FROM enrollments e JOIN users u ON u.id=e.student_id WHERE e.course_id=? AND e.status='active' AND u.account_status='active' ORDER BY u.name,u.id");
        $query->execute([$courseId]);$students=$query->fetchAll(PDO::FETCH_ASSOC);
        if(array_diff($selected,array_map('intval',array_column($students,'id'))))throw new InvalidArgumentException('Un destinataire n’est plus inscrit à ce parcours. Rechargez la page.');
        $query=$pdo->prepare("SELECT email FROM users WHERE id=? AND role='teacher' AND account_status='active'");$query->execute([$teacherId]);$bcc=self::email((string)($query->fetchColumn()?:''));
        $recipients=[];
        foreach($students as $student){
            if(!in_array((int)$student['id'],$selected,true))continue;
            $email=self::email((string)$student['email']);$cc=in_array((int)$student['id'],$secondary,true)?self::email((string)$student['secondary_email']):'';
            if(strcasecmp($email,$cc)===0)$cc='';
            $values=['prenom'=>trim((string)$student['first_name'])?:$student['name'],'nom'=>(string)$student['last_name'],'cours'=>(string)$course];
            $personalTitle=self::substitute($title,$values);$personalBody=self::substitute($body,$values,true);
            if(mb_strlen($personalTitle)>160||mb_strlen($personalBody)>5000||preg_match('/[\r\n]/',$personalTitle))throw new InvalidArgumentException('Le message personnalisé dépasse la limite autorisée. Raccourcissez le modèle.');
            $recipients[]=['student_id'=>(int)$student['id'],'student_name'=>$student['name'],'email'=>$email,'cc'=>$cc,'title'=>$personalTitle,'body'=>$personalBody];
        }
        return ['course_id'=>$courseId,'course_title'=>$course,'title'=>$title,'body'=>$body,'bcc'=>$bcc,'audience'=>count($selected)===count($students)?'class':'selected','recipients'=>$recipients];
    }

    public static function send(PDO $pdo,int $teacherId,array $input,?bool $mailCronActive=null): int
    {
        $request=(string)($input['request_key']??'');
        if(!preg_match('/^[a-f0-9]{32,64}$/',$request))throw new InvalidArgumentException('Rechargez la page avant d’envoyer le message.');
        if(!teacher_can_access_course($pdo,(int)($input['course_id']??0),$teacherId))throw new InvalidArgumentException('Parcours introuvable.');
        $selected=array_values(array_unique(array_map('intval',(array)($input['students']??[]))));sort($selected);
        $secondary=array_values(array_unique(array_map('intval',(array)($input['secondary']??[]))));sort($secondary);
        $hash=hash('sha256',json_encode([(int)($input['course_id']??0),trim((string)($input['title']??'')),self::body((string)($input['body']??'')),$selected,$secondary],JSON_THROW_ON_ERROR));
        $pdo->beginTransaction();
        try{
            $query=$pdo->prepare('SELECT id,course_id,request_hash FROM course_announcements WHERE created_by=? AND request_key=?');$query->execute([$teacherId,$request]);$existing=$query->fetch(PDO::FETCH_ASSOC);
            if($existing){if((int)$existing['course_id']!==(int)$input['course_id']||!hash_equals((string)$existing['request_hash'],$hash))throw new InvalidArgumentException('Rechargez la page avant d’envoyer le message.');$pdo->commit();return (int)$existing['id'];}
            $message=self::prepare($pdo,$teacherId,$input);
            $pdo->prepare('INSERT INTO course_announcements(course_id,created_by,title,body,audience,request_key,request_hash) VALUES(?,?,?,?,?,?,?)')->execute([$message['course_id'],$teacherId,$message['title'],$message['body'],$message['audience'],$request,$hash]);
            $id=(int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO announcement_reads(announcement_id,student_id) VALUES(?,?)')->execute([$id,$teacherId]);
            $insert=$pdo->prepare('INSERT INTO announcement_recipients(announcement_id,student_id,student_name,title,body,email,cc) VALUES(?,?,?,?,?,?,?)');
            $queue=$pdo->prepare("INSERT INTO notification_outbox(event,recipient,subject,body,announcement_id,cc,bcc,available_at) VALUES('course.announcement',?,?,?,?,?,?,datetime('now',?))");
            $mailCronActive??=mail_cron_is_active(dirname(__DIR__));$delay=$mailCronActive?'+'.ANNOUNCEMENT_MAIL_DELAY_SECONDS.' seconds':'+0 seconds';
            foreach($message['recipients'] as $recipient){
                $insert->execute([$id,$recipient['student_id'],$recipient['student_name'],$recipient['title'],$recipient['body'],$recipient['email'],$recipient['cc']]);
                $queue->execute([$recipient['email'],$recipient['title'],$recipient['body'],$id,$recipient['cc'],$message['bcc'],$delay]);
            }
            $pdo->commit();return $id;
        }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
    }
}
