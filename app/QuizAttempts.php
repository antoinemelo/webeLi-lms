<?php
declare(strict_types=1);

/** Core-owned generations invalidate old tabs and adaptive attempts across both databases. */
final class QuizAttempts
{
    public static function epoch(array $item): string
    {
        return (int)($item['quiz_revision']??0).':'.(int)($item['quiz_generation']??0);
    }

    public static function transaction(PDO $pdo,callable $operation): mixed
    {
        if($pdo->inTransaction())return $operation(); // Preserve an enclosing caller-owned transaction.
        // No rendering, network or external I/O inside the transaction.
        $pdo->exec('BEGIN IMMEDIATE');
        try{$result=$operation();$pdo->exec('COMMIT');return $result;}
        catch(Throwable $error){$pdo->exec('ROLLBACK');throw $error;}
    }

    public static function clear(PDO $pdo,int $itemId,?int $enrollmentId=null,?int $studentId=null): void
    {
        $where='pathway_item_id=?'.($enrollmentId===null?'':' AND enrollment_id=?');
        $args=$enrollmentId===null?[$itemId]:[$itemId,$enrollmentId];
        $pdo->prepare("UPDATE progress SET student_level=NULL,student_note='',student_validated_at=NULL,completed_at=NULL,
            teacher_level=NULL,evaluation_score=NULL,teacher_note='',teacher_validated_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE $where")->execute($args);
        foreach(['qcm_attempts','qcm_drafts','qcm_exercise_completions'] as $table)$pdo->prepare('DELETE FROM '.$table.' WHERE pathway_item_id=?'.($studentId===null?'':' AND student_id=?'))->execute($studentId===null?[$itemId]:[$itemId,$studentId]);
    }

    public static function authorizeRetake(PDO $pdo,int $teacherId,int $enrollmentId,int $itemId,string $expectedEpoch): void
    {
        self::transaction($pdo,static function()use($pdo,$teacherId,$enrollmentId,$itemId,$expectedEpoch){
            $q=$pdo->prepare("SELECT pi.*,e.student_id,COALESCE(pr.quiz_generation,0) AS quiz_generation FROM pathway_items pi
                JOIN enrollments e ON e.course_id=pi.course_id AND e.id=? AND e.status='active'
                JOIN users u ON u.id=e.student_id AND u.account_status='active'
                LEFT JOIN progress pr ON pr.enrollment_id=e.id AND pr.pathway_item_id=pi.id WHERE pi.id=?");
            $q->execute([$enrollmentId,$itemId]);$item=$q->fetch(PDO::FETCH_ASSOC);
            if(!$item||!$item['is_evaluation']||!teacher_can_access_course($pdo,(int)$item['course_id'],$teacherId))throw new InvalidArgumentException('Cette évaluation n’est pas accessible.');
            if(!hash_equals(self::epoch($item),$expectedEpoch))throw new InvalidArgumentException('Un nouvel essai a déjà été autorisé ou cette étape a changé. Rechargez la page.');
            $pdo->prepare('INSERT INTO progress(enrollment_id,pathway_item_id,quiz_generation) VALUES(?,?,1) ON CONFLICT(enrollment_id,pathway_item_id) DO UPDATE SET quiz_generation=quiz_generation+1')->execute([$enrollmentId,$itemId]);
            self::clear($pdo,$itemId,$enrollmentId,(int)$item['student_id']);
        });
    }
}
