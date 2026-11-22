<?php

declare(strict_types=1);

function teacher_can_manage_student(PDO $pdo,int $studentId,int $teacherId,bool $superadmin=false): bool
{
    if($superadmin){$query=$pdo->prepare("SELECT 1 FROM users WHERE id=? AND role='student'");$query->execute([$studentId]);return (bool)$query->fetchColumn();}
    $query=$pdo->prepare("SELECT 1 FROM users student WHERE student.id=? AND student.role='student' AND (
        student.managed_by=? OR EXISTS(SELECT 1 FROM enrollments e JOIN courses c ON c.id=e.course_id
          WHERE e.student_id=student.id AND (c.teacher_id=? OR EXISTS(SELECT 1 FROM course_teachers ct WHERE ct.course_id=c.id AND ct.teacher_id=?)))
    )");
    $query->execute([$studentId,$teacherId,$teacherId,$teacherId]);
    return (bool)$query->fetchColumn();
}

function student_identity_revision(array $student): string
{
    return hash('sha256',json_encode(array_map(static fn(string $key):string=>(string)$student[$key],['first_name','last_name','email','class_group']),JSON_THROW_ON_ERROR));
}

/** Administrative identity edits never change the student's phone or login code. */
function update_student_identity(PDO $pdo,int $studentId,int $teacherId,array $input): array
{
    foreach(['first_name','last_name','email','class_group','identity_revision'] as $key)if(!is_string($input[$key]??null))throw new InvalidArgumentException('Les informations de l’apprenant sont incomplètes.');
    $first=trim($input['first_name']);$last=mb_strtoupper(trim($input['last_name']),'UTF-8');
    $email=mb_strtolower(trim($input['email']),'UTF-8');$group=trim($input['class_group']);
    $compactFirst=preg_replace('/\s+/u','',$first)??'';$compactLast=preg_replace('/\s+/u','',$last)??'';
    if(mb_strlen($compactFirst)<2||mb_strlen($compactLast)<2||mb_strlen($first)>80||mb_strlen($last)>80)throw new InvalidArgumentException('Le prénom et le nom doivent contenir entre 2 et 80 caractères.');
    if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Indiquez une adresse courriel valide.');
    if($group===''||mb_strlen($group)>100)throw new InvalidArgumentException('Indiquez un groupe classe de 1 à 100 caractères.');
    $pdo->exec('BEGIN IMMEDIATE');
    try{
        $query=$pdo->prepare("SELECT is_superadmin FROM users WHERE id=? AND role='teacher' AND account_status='active'");$query->execute([$teacherId]);$teacher=$query->fetch(PDO::FETCH_ASSOC);$query->closeCursor();
        if(!$teacher||!teacher_can_manage_student($pdo,$studentId,$teacherId,(bool)$teacher['is_superadmin']))throw new InvalidArgumentException('Apprenant introuvable ou accès non autorisé.');
        $query=$pdo->prepare("SELECT * FROM users WHERE id=? AND role='student'");$query->execute([$studentId]);$student=$query->fetch(PDO::FETCH_ASSOC);$query->closeCursor();
        if(!hash_equals(student_identity_revision($student),$input['identity_revision']))throw new InvalidArgumentException('Les informations de cet apprenant ont changé. Rouvrez sa fiche avant de réessayer.');
        $query=$pdo->prepare('SELECT 1 FROM users WHERE lower(email)=? AND id<>?');$query->execute([$email,$studentId]);$duplicate=(bool)$query->fetchColumn();$query->closeCursor();
        if($duplicate)throw new InvalidArgumentException('Ce courriel est déjà utilisé.');
        $initials=mb_strtoupper(mb_substr($compactFirst,0,1).mb_substr($compactLast,0,1),'UTF-8');
        $pdo->prepare("UPDATE users SET name=?,first_name=?,last_name=?,email=?,class_group=?,initials=? WHERE id=? AND role='student'")->execute([$first.' '.$last,$first,$last,$email,$group,$initials,$studentId]);
        $emailChanged=$email!==mb_strtolower($student['email'],'UTF-8');
        if($emailChanged){
            $pdo->prepare("UPDATE users SET email_verified_at=NULL,verification_token_hash=NULL,verification_expires_at=CASE WHEN account_status='pending' THEN verification_expires_at ELSE NULL END,password_reset_token_hash=NULL,password_reset_expires_at=NULL WHERE id=?")->execute([$studentId]);
            $pdo->prepare("DELETE FROM notification_outbox WHERE event IN ('account.verification','credentials.reminder') AND recipient=? AND status='pending'")->execute([$student['email']]);
        }
        $pdo->exec('COMMIT');
        return ['id'=>$studentId,'name'=>$first.' '.$last,'first_name'=>$first,'last_name'=>$last,'email'=>$email,'class_group'=>$group,'initials'=>$initials,'account_status'=>$student['account_status'],'email_changed'=>$emailChanged,'login_code'=>$student['login_code'],'language'=>$student['language']];
    }catch(Throwable $exception){$pdo->exec('ROLLBACK');throw $exception;}
}

function update_student_secondary_email(PDO $pdo,int $studentId,int $teacherId,bool $superadmin,string $email): string
{
    if(!teacher_can_manage_student($pdo,$studentId,$teacherId,$superadmin))return 'forbidden';
    $email=mb_strtolower(trim($email),'UTF-8');
    if($email!==''&&(mb_strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL)))return 'invalid';
    $statement=$pdo->prepare("UPDATE users SET secondary_email=? WHERE id=? AND role='student'");
    $statement->execute([$email!==''?$email:null,$studentId]);
    return 'updated';
}

function superadmin_delete_page(PDO $pdo, int $pageId): bool
{
    $exists=$pdo->prepare('SELECT id FROM pages WHERE id=?');$exists->execute([$pageId]);if(!$exists->fetchColumn())return false;
    $pdo->beginTransaction();
    try{$pdo->prepare("DELETE FROM collaboration_comments WHERE subject_type='page' AND subject_id=?")->execute([$pageId]);$pdo->prepare("DELETE FROM edit_locks WHERE entity_type='page_metadata' AND entity_id=?")->execute([$pageId]);$pdo->prepare("DELETE FROM edit_locks WHERE entity_type='page_block' AND entity_id IN (SELECT id FROM page_blocks WHERE page_id=?)")->execute([$pageId]);$pdo->prepare("DELETE FROM edit_locks WHERE entity_type='pathway_item' AND entity_id IN (SELECT id FROM pathway_items WHERE page_id=?)")->execute([$pageId]);$pdo->prepare('DELETE FROM pathway_items WHERE page_id=?')->execute([$pageId]);$pdo->prepare('DELETE FROM pages WHERE id=?')->execute([$pageId]);$pdo->commit();return true;}
    catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
}

function superadmin_delete_course(PDO $pdo, int $courseId): bool
{
    $pdo->beginTransaction();
    try{$pdo->prepare("DELETE FROM collaboration_comments WHERE subject_type='course' AND subject_id=?")->execute([$courseId]);$pdo->prepare("DELETE FROM edit_locks WHERE entity_type='course_structure' AND entity_id=?")->execute([$courseId]);$pdo->prepare("DELETE FROM edit_locks WHERE entity_type='pathway_item' AND entity_id IN (SELECT id FROM pathway_items WHERE course_id=?)")->execute([$courseId]);$delete=$pdo->prepare('DELETE FROM courses WHERE id=?');$delete->execute([$courseId]);$pdo->commit();return $delete->rowCount()===1;}
    catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
}

function superadmin_delete_user(PDO $pdo, int $userId): bool
{
    $query=$pdo->prepare('SELECT * FROM users WHERE id=?');$query->execute([$userId]);$user=$query->fetch(PDO::FETCH_ASSOC);if(!$user)return false;
    $pdo->beginTransaction();
    try{
        if($user['role']==='teacher'){
            $pdo->prepare('DELETE FROM course_teachers WHERE teacher_id=? OR added_by=?')->execute([$userId,$userId]);
            $pdo->prepare("DELETE FROM collaboration_comments WHERE subject_type='course' AND subject_id IN (SELECT id FROM courses WHERE teacher_id=?)")->execute([$userId]);
            $pdo->prepare("DELETE FROM collaboration_comments WHERE subject_type='page' AND subject_id IN (SELECT id FROM pages WHERE owner_id=?)")->execute([$userId]);
            $pdo->prepare("DELETE FROM edit_locks WHERE entity_type='course_structure' AND entity_id IN (SELECT id FROM courses WHERE teacher_id=?)")->execute([$userId]);
            $pdo->prepare("DELETE FROM edit_locks WHERE entity_type='pathway_item' AND entity_id IN (SELECT pi.id FROM pathway_items pi JOIN courses c ON c.id=pi.course_id WHERE c.teacher_id=? UNION SELECT pi.id FROM pathway_items pi JOIN pages p ON p.id=pi.page_id WHERE p.owner_id=?)")->execute([$userId,$userId]);
            $pdo->prepare("DELETE FROM edit_locks WHERE entity_type='page_metadata' AND entity_id IN (SELECT id FROM pages WHERE owner_id=?)")->execute([$userId]);
            $pdo->prepare("DELETE FROM edit_locks WHERE entity_type='page_block' AND entity_id IN (SELECT b.id FROM page_blocks b JOIN pages p ON p.id=b.page_id WHERE p.owner_id=?)")->execute([$userId]);
            $pdo->prepare('DELETE FROM pathway_items WHERE page_id IN (SELECT id FROM pages WHERE owner_id=?)')->execute([$userId]);
            $pdo->prepare('DELETE FROM courses WHERE teacher_id=?')->execute([$userId]);
            $pdo->prepare('DELETE FROM reward_awards WHERE awarded_by=?')->execute([$userId]);
            $pdo->prepare('DELETE FROM pages WHERE owner_id=?')->execute([$userId]);
        }
        $pdo->prepare('DELETE FROM notification_outbox WHERE recipient=?')->execute([$user['email']]);
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$userId]);
        $pdo->commit();return true;
    }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
}
