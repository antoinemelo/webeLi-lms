<?php

declare(strict_types=1);

final class StudentAdminHistory
{
    public const KINDS=['meeting'=>'Réunion','correspondence'=>'Correspondance','payment'=>'Paiement'];
    public const PAGE_SIZE=50;

    public static function teacher(PDO $pdo,int $id): array
    {
        $query=$pdo->prepare("SELECT id,is_superadmin FROM users WHERE id=? AND role='teacher' AND account_status='active'");$query->execute([$id]);
        $teacher=$query->fetch(PDO::FETCH_ASSOC);if(!$teacher)throw new InvalidArgumentException('Accès interdit.');return $teacher;
    }

    public static function requireStudent(PDO $pdo,int $studentId,int $teacherId): array
    {
        $teacher=self::teacher($pdo,$teacherId);
        if(!teacher_can_manage_student($pdo,$studentId,$teacherId,(bool)$teacher['is_superadmin']))throw new InvalidArgumentException('Élève introuvable ou accès non autorisé.');
        $query=$pdo->prepare("SELECT * FROM users WHERE id=? AND role='student'");$query->execute([$studentId]);return $query->fetch(PDO::FETCH_ASSOC);
    }

    public static function prepare(PDO $pdo,int $teacherId,array $input): array
    {
        self::teacher($pdo,$teacherId);$kind=(string)($input['kind']??'');
        if(!isset(self::KINDS[$kind]))throw new InvalidArgumentException('Choisissez un type de suivi.');
        $ids=array_values(array_unique(array_map('intval',(array)($input['students']??[]))));sort($ids);
        if(!$ids)throw new InvalidArgumentException('Sélectionnez au moins un élève.');
        $students=[];foreach($ids as $id)$students[]=self::requireStudent($pdo,$id,$teacherId);
        $courseId=(int)($input['course_id']??0);$courseTitle='';
        if($courseId){
            if(!teacher_can_access_course($pdo,$courseId,$teacherId))throw new InvalidArgumentException('Parcours introuvable.');
            $query=$pdo->prepare('SELECT title FROM courses WHERE id=?');$query->execute([$courseId]);$courseTitle=(string)$query->fetchColumn();
            $query=$pdo->prepare('SELECT 1 FROM enrollments WHERE course_id=? AND student_id=?');
            foreach($ids as $id){$query->execute([$courseId,$id]);if(!$query->fetchColumn())throw new InvalidArgumentException('Tous les participants doivent être inscrits au parcours choisi.');}
        }
        $templateId=(int)($input['template_id']??0);
        if($templateId){$query=$pdo->prepare('SELECT 1 FROM message_templates WHERE id=? AND teacher_id=?');$query->execute([$templateId,$teacherId]);if(!$query->fetchColumn())throw new InvalidArgumentException('Modèle introuvable.');}
        $title=trim((string)($input['title']??''));$body=trim(str_replace(["\r\n","\r"],"\n",(string)($input['body']??'')));
        AnnouncementMessages::validateText($title,$body);
        if(!$courseId&&str_contains($title.$body,'{cours}'))throw new InvalidArgumentException('Choisissez un parcours pour utiliser {cours}.');
        $values=['prenom'=>implode(', ',array_column($students,'first_name')),'nom'=>implode(', ',array_column($students,'last_name')),'cours'=>$courseTitle];
        $title=AnnouncementMessages::substitute($title,$values);$body=AnnouncementMessages::substitute($body,$values,true);
        if(mb_strlen($title)>160||mb_strlen($body)>5000)throw new InvalidArgumentException('Le texte personnalisé dépasse la limite autorisée.');
        $raw=(string)($input['occurred_at']??'');$date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$raw,new DateTimeZone('Europe/Zurich'));
        if(!$date||$date->format('Y-m-d\TH:i')!==$raw)throw new InvalidArgumentException('Saisissez une date et une heure valides.');
        return ['kind'=>$kind,'course_id'=>$courseId?:null,'course_title'=>$courseTitle,'title'=>$title,'body'=>$body,'occurred_at'=>$date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),'students'=>$ids,'participants'=>array_map(static fn(array $student):array=>['id'=>(int)$student['id'],'name'=>$student['name']],$students)];
    }

    public static function save(PDO $pdo,int $teacherId,array $input): int
    {
        $data=self::prepare($pdo,$teacherId,$input);$id=(int)($input['followup_id']??0);$revision=(int)($input['revision']??-1);
        $key=(string)($input['request_key']??'');if(!$id&&!preg_match('/^[a-f0-9]{32,64}$/',$key))throw new InvalidArgumentException('Rechargez la page avant d’enregistrer le suivi.');
        $hash=hash('sha256',json_encode(array_diff_key($data,['participants'=>true]),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        $pdo->beginTransaction();
        try{
            if($id){
                $query=$pdo->prepare('UPDATE student_followups SET kind=?,course_id=?,course_title=?,title=?,body=?,occurred_at=?,revision=revision+1,updated_at=CURRENT_TIMESTAMP WHERE id=? AND created_by=? AND revision=?');
                $query->execute([$data['kind'],$data['course_id'],$data['course_title'],$data['title'],$data['body'],$data['occurred_at'],$id,$teacherId,$revision]);
                if(!$query->rowCount())throw new InvalidArgumentException('Ce suivi a changé ou ne vous appartient pas. Rechargez l’historique.');
            }else{
                $query=$pdo->prepare('SELECT id,request_hash FROM student_followups WHERE created_by=? AND request_key=?');$query->execute([$teacherId,$key]);$existing=$query->fetch(PDO::FETCH_ASSOC);
                if($existing){if(!hash_equals($existing['request_hash'],$hash))throw new InvalidArgumentException('Ce suivi a déjà été enregistré avec un autre contenu. Rouvrez le formulaire.');$pdo->commit();return (int)$existing['id'];}
                $pdo->prepare('INSERT INTO student_followups(created_by,kind,course_id,course_title,title,body,occurred_at,request_key,request_hash) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$teacherId,$data['kind'],$data['course_id'],$data['course_title'],$data['title'],$data['body'],$data['occurred_at'],$key,$hash]);$id=(int)$pdo->lastInsertId();
            }
            $insert=$pdo->prepare('INSERT OR IGNORE INTO student_followup_participants(followup_id,student_id) VALUES(?,?)');foreach($data['students'] as $studentId)$insert->execute([$id,$studentId]);
            // Add the new links before removing old ones; the cleanup trigger must never see an empty edited entry.
            $pdo->prepare('DELETE FROM student_followup_participants WHERE followup_id=? AND student_id NOT IN ('.implode(',',array_fill(0,count($data['students']),'?')).')')->execute(array_merge([$id],$data['students']));
            $pdo->commit();return $id;
        }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
    }

    public static function delete(PDO $pdo,int $teacherId,int $id,int $revision): void
    {
        self::teacher($pdo,$teacherId);$query=$pdo->prepare('DELETE FROM student_followups WHERE id=? AND created_by=? AND revision=?');$query->execute([$id,$teacherId,$revision]);
        if(!$query->rowCount())throw new InvalidArgumentException('Ce suivi a changé ou ne vous appartient pas. Rechargez l’historique.');
    }

    public static function otherParticipants(array $entry,int $studentId): array
    {
        return array_values(array_filter($entry['participants'],static fn(array $participant):bool=>(int)$participant['id']!==$studentId&&($participant['account_status']??'active')==='active'));
    }

    public static function history(PDO $pdo,int $studentId,int $teacherId,int $page=1): array
    {
        self::requireStudent($pdo,$studentId,$teacherId);$teacher=self::teacher($pdo,$teacherId);$super=(int)$teacher['is_superadmin'];$page=max(1,min(100000,$page));$offset=($page-1)*self::PAGE_SIZE;
        // Course entries follow course-team access; entries without a course follow the participant's management access.
        $courseAccess="(c.teacher_id=:teacher OR EXISTS(SELECT 1 FROM course_teachers ct WHERE ct.course_id=c.id AND ct.teacher_id=:teacher))";
        $sql="SELECT * FROM (
            SELECT 'followup' AS source,f.id,f.kind,f.title,f.body,f.occurred_at,f.created_at,f.updated_at,f.created_by,u.name AS author_name,f.course_id,f.course_title,f.revision,NULL AS read_at,'' AS email,'' AS cc,'' AS bcc,'' AS mail_status,NULL AS sent_at
            FROM student_followup_participants fp JOIN student_followups f ON f.id=fp.followup_id LEFT JOIN users u ON u.id=f.created_by LEFT JOIN courses c ON c.id=f.course_id
            WHERE fp.student_id=:student AND (:super=1 OR f.created_by=:teacher OR f.course_id IS NULL OR $courseAccess)
            UNION ALL
            SELECT 'announcement',a.id,'message',ar.title,ar.body,a.created_at,a.created_at,a.created_at,a.created_by,u.name,a.course_id,c.title,0,r.read_at,ar.email,ar.cc,COALESCE(o.bcc,''),COALESCE(o.status,''),o.sent_at
            FROM announcement_recipients ar JOIN course_announcements a ON a.id=ar.announcement_id JOIN courses c ON c.id=a.course_id LEFT JOIN users u ON u.id=a.created_by LEFT JOIN announcement_reads r ON r.announcement_id=a.id AND r.student_id=ar.student_id
            LEFT JOIN notification_outbox o ON o.id=(SELECT latest.id FROM notification_outbox latest WHERE latest.announcement_id=a.id AND latest.recipient=ar.email ORDER BY latest.id DESC LIMIT 1)
            WHERE ar.student_id=:student AND (:super=1 OR $courseAccess)
        ) ORDER BY occurred_at DESC,source DESC,id DESC LIMIT :limit OFFSET :offset";
        $query=$pdo->prepare($sql);$query->bindValue(':teacher',$teacherId,PDO::PARAM_INT);$query->bindValue(':student',$studentId,PDO::PARAM_INT);$query->bindValue(':super',$super,PDO::PARAM_INT);$query->bindValue(':limit',self::PAGE_SIZE+1,PDO::PARAM_INT);$query->bindValue(':offset',$offset,PDO::PARAM_INT);$query->execute();$rows=$query->fetchAll(PDO::FETCH_ASSOC);$more=count($rows)>self::PAGE_SIZE;$rows=array_slice($rows,0,self::PAGE_SIZE);
        foreach($rows as &$row){
            $table=$row['source']==='followup'?'student_followup_participants':'announcement_recipients';$column=$row['source']==='followup'?'followup_id':'announcement_id';
            $query=$pdo->prepare('SELECT u.id,u.name,u.account_status FROM '.$table.' p JOIN users u ON u.id=p.student_id WHERE p.'.$column.'=? ORDER BY u.last_name,u.first_name');$query->execute([(int)$row['id']]);$row['participants']=$query->fetchAll(PDO::FETCH_ASSOC);
            $row['editable']=$row['source']==='followup'&&(int)$row['created_by']===$teacherId;
        }unset($row);
        return ['student_id'=>$studentId,'items'=>$rows,'page'=>$page,'has_more'=>$more];
    }
}
