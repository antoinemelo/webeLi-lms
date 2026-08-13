<?php

declare(strict_types=1);

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
