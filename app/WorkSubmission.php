<?php

declare(strict_types=1);

final class WorkSubmission
{
    public const TEXT_LIMIT=512;
    public const MODES=['link','text','both'];

    public static function current(PDO $pdo,int $studentId,int $itemId,int $blockId): ?array
    {
        $q=$pdo->prepare('SELECT * FROM work_submissions WHERE student_id=? AND pathway_item_id=? AND page_block_id=?');
        $q->execute([$studentId,$itemId,$blockId]);return $q->fetch(PDO::FETCH_ASSOC)?:null;
    }

    /** One query for the whole course, including students with no draft yet. */
    public static function courseStates(PDO $pdo,int $courseId): array
    {
        $q=$pdo->prepare("SELECT e.student_id,pi.id AS item_id,COUNT(b.id) AS total,
            SUM(b.submission_required) AS required,
            SUM(CASE WHEN w.status='submitted' THEN 1 ELSE 0 END) AS submitted,
            SUM(CASE WHEN b.submission_required=1 AND COALESCE(w.status,'draft')<>'submitted' THEN 1 ELSE 0 END) AS missing
            FROM enrollments e JOIN pathway_items pi ON pi.course_id=e.course_id
            JOIN page_blocks b ON b.page_id=pi.page_id AND b.type='submission'
            LEFT JOIN work_submissions w ON w.student_id=e.student_id AND w.pathway_item_id=pi.id AND w.page_block_id=b.id
            WHERE e.course_id=? AND e.status='active' GROUP BY e.student_id,pi.id");
        $q->execute([$courseId]);$states=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$states[(int)$row['student_id']][(int)$row['item_id']]=self::state($row);
        return $states;
    }

    private static function state(array $row): array
    {
        return ['total'=>(int)$row['total'],'required'=>(int)$row['required'],'submitted'=>(int)$row['submitted'],
            'complete'=>(int)$row['missing']===0,'ready'=>(int)$row['missing']===0&&(int)$row['submitted']>0];
    }

    public static function summary(PDO $pdo,int $studentId,int $itemId): array
    {
        $q=$pdo->prepare("SELECT COUNT(b.id) AS total,COALESCE(SUM(b.submission_required),0) AS required,
            SUM(CASE WHEN w.status='submitted' THEN 1 ELSE 0 END) AS submitted,
            SUM(CASE WHEN b.submission_required=1 AND COALESCE(w.status,'draft')<>'submitted' THEN 1 ELSE 0 END) AS missing
            FROM pathway_items pi JOIN page_blocks b ON b.page_id=pi.page_id AND b.type='submission'
            LEFT JOIN work_submissions w ON w.student_id=? AND w.pathway_item_id=pi.id AND w.page_block_id=b.id WHERE pi.id=?");
        $q->execute([$studentId,$itemId]);return self::state($q->fetch(PDO::FETCH_ASSOC));
    }

    public static function canReview(bool $evaluation,bool $selfEvaluation,bool $selfSubmitted,bool $hasQuiz,bool $quizComplete,?array $work): bool
    {
        if($work&&!$work['ready'])return false;
        if($evaluation)return $hasQuiz?$quizComplete:(!$selfEvaluation||$selfSubmitted);
        return $selfEvaluation?$selfSubmitted:(bool)($work['ready']??false);
    }

    private static function context(PDO $pdo,int $studentId,int $itemId,int $blockId): ?array
    {
        $q=$pdo->prepare("SELECT b.*,pi.course_id,pi.self_evaluation_enabled,pi.is_evaluation,e.id AS enrollment_id
            FROM pathway_items pi JOIN courses c ON c.id=pi.course_id AND c.archived=0
            JOIN pages p ON p.id=pi.page_id AND p.status='ready'
            JOIN page_blocks b ON b.page_id=pi.page_id AND b.id=? AND b.type='submission'
            JOIN enrollments e ON e.course_id=pi.course_id AND e.student_id=? AND e.status='active'
            JOIN users u ON u.id=e.student_id AND u.role='student' AND u.account_status='active'
            WHERE pi.id=? AND (pi.access_mode='all' OR (pi.access_mode='restricted' AND EXISTS(
                SELECT 1 FROM pathway_item_students a WHERE a.pathway_item_id=pi.id AND a.student_id=e.student_id)))");
        $q->execute([$blockId,$studentId,$itemId]);return $q->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public static function validUrl(string $url): bool
    {
        $parts=parse_url($url);
        return $parts!==false&&in_array(strtolower($parts['scheme']??''),['http','https'],true)
            &&!empty($parts['host'])&&!isset($parts['user'])&&!isset($parts['pass'])
            &&filter_var($url,FILTER_VALIDATE_URL)!==false&&!preg_match('/[\x00-\x20<>"\'\\\\]/',$url);
    }

    /** Drafts and final submissions use optimistic revisions; stale tabs never replace newer work. */
    public static function save(PDO $pdo,int $studentId,int $itemId,int $blockId,int $blockRevision,int $revision,string $url,string $body,bool $submit): array
    {
        $body=str_replace(["\r\n","\r"],"\n",$body);$url=trim($url);
        if(!mb_check_encoding($body,'UTF-8')||mb_strlen($body,'UTF-8')>self::TEXT_LIMIT||str_contains($body,"\0"))return ['status'=>'invalid','message'=>'Le texte est limité à 512 caractères.'];
        if(strlen($url)>2048)return ['status'=>'invalid','message'=>'Le lien est trop long (2 048 caractères maximum).'];
        $pdo->beginTransaction();
        try{
            $block=self::context($pdo,$studentId,$itemId,$blockId);
            if(!$block)return self::finish($pdo,['status'=>'forbidden']);
            if((int)$block['revision']!==$blockRevision)return self::finish($pdo,['status'=>'changed']);
            $current=self::current($pdo,$studentId,$itemId,$blockId);
            if(($current['status']??'')==='submitted')return self::finish($pdo,['status'=>'already_submitted']);
            if((int)($current['revision']??0)!==$revision)return self::finish($pdo,['status'=>'conflict']);
            $mode=$block['submission_mode'];
            if($mode==='text')$url='';if($mode==='link')$body='';
            if($submit){
                if($mode!=='text'&&!self::validUrl($url))return self::finish($pdo,['status'=>'invalid','message'=>'Saisissez un lien http(s) valide.']);
                if($mode!=='link'&&trim($body)==='')return self::finish($pdo,['status'=>'invalid','message'=>'Saisissez votre texte avant de rendre le travail.']);
            }
            $status=$submit?'submitted':'draft';$date=$submit?gmdate('Y-m-d H:i:s'):null;
            $q=$pdo->prepare("INSERT INTO work_submissions(student_id,pathway_item_id,page_block_id,url,body,status,revision,submitted_at)
                VALUES(?,?,?,?,?,?,?,?) ON CONFLICT(student_id,pathway_item_id,page_block_id) DO UPDATE SET
                url=excluded.url,body=excluded.body,status=excluded.status,revision=excluded.revision,submitted_at=excluded.submitted_at,updated_at=CURRENT_TIMESTAMP");
            $q->execute([$studentId,$itemId,$blockId,$url,$body,$status,$revision+1,$date]);
            if($submit){
                $pdo->prepare('INSERT INTO work_submission_versions(student_id,pathway_item_id,page_block_id,url,body,prompt,title,submitted_at) VALUES(?,?,?,?,?,?,?,?)')
                    ->execute([$studentId,$itemId,$blockId,$url,$body,$block['body'],$block['caption'],$date]);
                self::refreshProgress($pdo,$studentId,$itemId,$block);
            }
            return self::finish($pdo,['status'=>$submit?'submitted':'saved','revision'=>$revision+1]);
        }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
    }

    private static function finish(PDO $pdo,array $result): array {$pdo->commit();return $result;}

    private static function refreshProgress(PDO $pdo,int $studentId,int $itemId,array $item): void
    {
        $work=self::summary($pdo,$studentId,$itemId);
        $completed=$work['complete']&&((!$item['is_evaluation']&&!$item['self_evaluation_enabled'])||($item['self_evaluation_enabled']&&self::selfSubmitted($pdo,(int)$item['enrollment_id'],$itemId)))?gmdate('Y-m-d H:i:s'):null;
        $pdo->prepare("INSERT INTO progress(enrollment_id,pathway_item_id,completed_at) VALUES(?,?,?)
            ON CONFLICT(enrollment_id,pathway_item_id) DO UPDATE SET completed_at=excluded.completed_at,
            teacher_validated_at=NULL,teacher_level=NULL,evaluation_score=NULL,updated_at=CURRENT_TIMESTAMP")
            ->execute([$item['enrollment_id'],$itemId,$completed]);
    }

    private static function selfSubmitted(PDO $pdo,int $enrollmentId,int $itemId): bool
    {
        $q=$pdo->prepare('SELECT student_validated_at FROM progress WHERE enrollment_id=? AND pathway_item_id=?');$q->execute([$enrollmentId,$itemId]);return (bool)$q->fetchColumn();
    }

    public static function reconcilePage(PDO $pdo,int $pageId): void
    {
        $pdo->prepare("UPDATE progress SET completed_at=NULL,teacher_validated_at=NULL,teacher_level=NULL,evaluation_score=NULL
            WHERE pathway_item_id IN (SELECT id FROM pathway_items WHERE page_id=?) AND EXISTS(
                SELECT 1 FROM page_blocks b JOIN enrollments e ON e.id=progress.enrollment_id
                WHERE b.page_id=? AND b.type='submission' AND b.submission_required=1 AND NOT EXISTS(
                    SELECT 1 FROM work_submissions w WHERE w.student_id=e.student_id AND w.pathway_item_id=progress.pathway_item_id AND w.page_block_id=b.id AND w.status='submitted'))")
            ->execute([$pageId,$pageId]);
    }

    public static function reopen(PDO $pdo,int $teacherId,int $enrollmentId,int $itemId,int $blockId,int $revision): bool
    {
        $pdo->beginTransaction();
        try{
            $q=$pdo->prepare("SELECT e.student_id,e.id AS enrollment_id,pi.course_id,pi.self_evaluation_enabled,pi.is_evaluation
                FROM enrollments e JOIN pathway_items pi ON pi.course_id=e.course_id
                JOIN page_blocks b ON b.page_id=pi.page_id AND b.id=? AND b.type='submission'
                WHERE e.id=? AND e.status='active' AND pi.id=?");
            $q->execute([$blockId,$enrollmentId,$itemId]);$item=$q->fetch(PDO::FETCH_ASSOC);
            if(!$item||!teacher_can_access_course($pdo,(int)$item['course_id'],$teacherId)){$pdo->commit();return false;}
            $q=$pdo->prepare("UPDATE work_submissions SET status='draft',submitted_at=NULL,revision=revision+1,updated_at=CURRENT_TIMESTAMP
                WHERE student_id=? AND pathway_item_id=? AND page_block_id=? AND status='submitted' AND revision=?");
            $q->execute([$item['student_id'],$itemId,$blockId,$revision]);
            if($q->rowCount()!==1){$pdo->commit();return false;}
            $pdo->prepare('UPDATE work_submission_versions SET reopened_at=CURRENT_TIMESTAMP,reopened_by=? WHERE id=(SELECT MAX(id) FROM work_submission_versions WHERE student_id=? AND pathway_item_id=? AND page_block_id=?)')
                ->execute([$teacherId,$item['student_id'],$itemId,$blockId]);
            self::refreshProgress($pdo,(int)$item['student_id'],$itemId,$item);
            $pdo->commit();return true;
        }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
    }
}
