<?php

declare(strict_types=1);

function course_enrollment_teacher_course(PDO $pdo,int $courseId,int $teacherId): array
{
    $q=$pdo->prepare("SELECT c.* FROM courses c JOIN users u ON u.id=? AND u.role='teacher' AND u.account_status='active' WHERE c.id=? AND c.archived=0");
    $q->execute([$teacherId,$courseId]);$course=$q->fetch(PDO::FETCH_ASSOC);
    if(!$course||!teacher_can_access_course($pdo,$courseId,$teacherId))throw new InvalidArgumentException('Parcours introuvable.');
    return $course;
}

/** The directory is loaded only when the enrollment dialog opens, in bounded batches. */
function course_enrollment_candidates(PDO $pdo,int $courseId,int $teacherId,array $filters): array
{
    course_enrollment_teacher_course($pdo,$courseId,$teacherId);
    $where=["u.role='student'","u.account_status='active'"];$params=[':target'=>$courseId];
    $search=mb_substr(trim((string)($filters['search']??'')),0,200);
    foreach(preg_split('/\s+/u',$search,-1,PREG_SPLIT_NO_EMPTY) as $index=>$word){
        $key=':word'.$index;$where[]="(u.name LIKE $key ESCAPE '\\' OR u.first_name LIKE $key ESCAPE '\\' OR u.last_name LIKE $key ESCAPE '\\' OR u.class_group LIKE $key ESCAPE '\\')";
        $params[$key]='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$word).'%';
    }
    $group=mb_substr(trim((string)($filters['group']??'')),0,100);
    if($group!==''){$where[]='trim(u.class_group)=:group';$params[':group']=$group;}
    $filterCourse=max(0,(int)($filters['filter_course']??0));
    if($filterCourse){$where[]="EXISTS(SELECT 1 FROM enrollments f JOIN courses c ON c.id=f.course_id AND c.archived=0 WHERE f.student_id=u.id AND f.course_id=:filter_course AND f.status='active')";$params[':filter_course']=$filterCourse;}
    $q=$pdo->prepare("SELECT u.id,u.first_name,u.last_name,u.class_group,u.initials,u.color,e.status AS enrollment_status FROM users u LEFT JOIN enrollments e ON e.student_id=u.id AND e.course_id=:target WHERE ".implode(' AND ',$where).' ORDER BY u.last_name COLLATE NOCASE,u.first_name COLLATE NOCASE,u.id LIMIT 101');
    $q->execute($params);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    $result=['students'=>array_slice($rows,0,100),'has_more'=>count($rows)>100];
    if(!empty($filters['facets'])){
        $result['groups']=$pdo->query("SELECT DISTINCT trim(class_group) FROM users WHERE role='student' AND account_status='active' AND trim(class_group)<>'' ORDER BY 1 COLLATE NOCASE")->fetchAll(PDO::FETCH_COLUMN);
        $result['courses']=$pdo->query("SELECT c.id,c.title FROM courses c WHERE c.archived=0 AND EXISTS(SELECT 1 FROM enrollments e JOIN users u ON u.id=e.student_id AND u.account_status='active' WHERE e.course_id=c.id AND e.status='active') ORDER BY c.title COLLATE NOCASE,c.id")->fetchAll(PDO::FETCH_ASSOC);
    }
    return $result;
}

/** Enroll existing accounts, atomically and without duplicate notifications on repeat submission. */
function course_enroll_existing_students(PDO $pdo,int $courseId,int $teacherId,array $studentIds): array
{
    $studentIds=array_values(array_unique(array_filter(array_map('intval',$studentIds),static fn(int $id):bool=>$id>0)));
    if(!$studentIds||count($studentIds)>500)throw new InvalidArgumentException('Choisissez un cours et au moins un apprenant.');
    $pdo->exec('BEGIN IMMEDIATE');
    try{
        $course=course_enrollment_teacher_course($pdo,$courseId,$teacherId);
        $q=$pdo->prepare("SELECT u.id,u.email,u.language,e.id AS enrollment_id,e.status FROM users u LEFT JOIN enrollments e ON e.student_id=u.id AND e.course_id=? WHERE u.role='student' AND u.account_status='active' AND u.id IN (".implode(',',array_fill(0,count($studentIds),'?')).')');
        $q->execute([$courseId,...$studentIds]);$students=$q->fetchAll(PDO::FETCH_ASSOC);$changed=[];
        $restore=$pdo->prepare("UPDATE enrollments SET status='active',archived_at=NULL WHERE id=?");
        $insert=$pdo->prepare('INSERT INTO enrollments(course_id,student_id) VALUES(?,?)');
        foreach($students as $student){
            if($student['status']==='active')continue;
            if($student['enrollment_id'])$restore->execute([$student['enrollment_id']]);else $insert->execute([$courseId,$student['id']]);
            $changed[]=$student;
        }
        $pdo->exec('COMMIT');return ['course'=>$course,'changed'=>$changed];
    }catch(Throwable $error){$pdo->exec('ROLLBACK');throw $error;}
}

function find_joinable_course(PDO $pdo, string $code): ?array
{
    $code=trim($code);
    if($code===''||mb_strlen($code)>64)return null;
    $query=$pdo->prepare("SELECT c.*,u.name AS teacher_name,u.email AS teacher_email,u.language AS teacher_language
        FROM courses c JOIN users u ON u.id=c.teacher_id
        WHERE lower(c.code)=lower(?) AND c.archived=0 AND u.account_status='active'");
    $query->execute([$code]);
    $course=$query->fetch(PDO::FETCH_ASSOC);
    return $course===false?null:$course;
}

/**
 * @return array{status:string,course:?array}
 */
function enroll_student_with_course_code(PDO $pdo, int $studentId, string $code): array
{
    $course=find_joinable_course($pdo,$code);
    if(!$course)return ['status'=>'invalid','course'=>null];

    $student=$pdo->prepare("SELECT id FROM users WHERE id=? AND role='student' AND account_status='active'");
    $student->execute([$studentId]);
    if(!$student->fetchColumn())return ['status'=>'forbidden','course'=>$course];

    $existing=$pdo->prepare('SELECT id,status FROM enrollments WHERE course_id=? AND student_id=?');
    $existing->execute([$course['id'],$studentId]);
    $enrollment=$existing->fetch(PDO::FETCH_ASSOC);
    if($enrollment)return ['status'=>$enrollment['status']==='active'?'already_joined':'archived','course'=>$course];

    $insert=$pdo->prepare('INSERT OR IGNORE INTO enrollments(course_id,student_id) VALUES(?,?)');
    $insert->execute([$course['id'],$studentId]);
    return ['status'=>$insert->rowCount()===1?'joined':'already_joined','course'=>$course];
}
