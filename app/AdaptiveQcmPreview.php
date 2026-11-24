<?php
declare(strict_types=1);

/** Runs the real engine in memory. Only a single teacher preview is kept in the PHP session. */
final class AdaptiveQcmPreview
{
    public readonly AdaptiveQcm $store;
    private const RUN_COLUMNS=['id','student_id','item_id','page_id','evaluation','started_at','ends_at','initial_level','cursor','pool','sources','history','finished_at','reason','score','scoring_version'];
    private const ANSWER_COLUMNS=['run_id','ordinal','question_id','presented_at','deadline','step','selected','result','answered_at','elapsed','cursor_after'];

    public function __construct()
    {
        $this->store=new AdaptiveQcm(new PDO('sqlite::memory:'));
    }

    private function insert(string $table,array $columns,array $rows): void
    {
        $q=$this->store->db->prepare('INSERT INTO '.$table.' ('.implode(',',$columns).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')');
        foreach($rows as $row)$q->execute(array_map(static fn($column)=>$row[$column]??null,$columns));
    }

    private function questions(array $pool): void
    {
        $rows=[];
        foreach($pool as $question)$rows[]=[
            'id'=>$question['id'],'owner_id'=>$question['owner_id'],'page_id'=>$question['page_id'],'block_id'=>$question['block_id'],
            'body'=>json_encode($question,JSON_THROW_ON_ERROR),
            'presentations'=>$question['stats']['presentations']??0,'correct'=>$question['stats']['correct']??0,
            'timed_answers'=>$question['stats']['timed_answers']??0,'time_sum'=>$question['stats']['time_sum']??0,
        ];
        $this->insert('questions',['id','owner_id','page_id','block_id','body','presentations','correct','timed_answers','time_sum'],$rows);
    }

    public function start(array $item,int $teacherId,array $bank,?AdaptiveQcm $reference=null,?float $now=null): array
    {
        $level=$reference?->level((int)$item['page_id'])??1;
        $this->store->setLevel((int)$item['page_id'],$level);
        $pool=$reference?$reference->statistics($bank['questions']):$bank['questions'];
        $this->questions($pool);
        // An existing evaluation keeps exactly the same reference rates in the preview.
        $version=null;
        if($reference&&$item['is_evaluation']){
            $duration=max(1,(int)$item['estimated_minutes'])*60;$revision=$reference->referenceRevision((int)$item['id']);
            if($revision)$this->insert('evaluation_revisions',['item_id','revision'],[['item_id'=>$item['id'],'revision'=>$revision]]);
            $fingerprints=[AdaptiveQcm::fingerprint($pool,$level,$duration,$revision)];
            if(!$revision)$fingerprints[]=hash('sha256',json_encode([array_column($pool,'id'),$level,$duration],JSON_THROW_ON_ERROR));
            foreach($fingerprints as $fingerprint){
                $frozen=$reference->one('SELECT * FROM evaluation_pools WHERE item_id=? AND fingerprint=?',[$item['id'],$fingerprint]);
                if($frozen){
                    $this->insert('evaluation_pools',['item_id','fingerprint','pool'],[$frozen]);
                    if($fingerprint!==$fingerprints[0])$version=(int)($reference->one('SELECT scoring_version FROM runs WHERE item_id=? AND pool=? ORDER BY started_at LIMIT 1',[$item['id'],$frozen['pool']])['scoring_version']??2);
                }
            }
        }
        if($reference)$bank['reference_questions']=$pool;
        return $this->store->start($item,$teacherId,$bank,$now,$version);
    }

    /** The snapshot is server-side session data, never supplied by the browser. */
    public function restore(array $snapshot,int $teacherId,int $itemId): array
    {
        $run=$snapshot['run']+['scoring_version'=>1];
        if((int)$run['student_id']!==$teacherId||(int)$run['item_id']!==$itemId)throw new InvalidArgumentException('Ce test appartient à une autre session.');
        $this->questions(json_decode($run['pool'],true,512,JSON_THROW_ON_ERROR));
        $this->insert('runs',self::RUN_COLUMNS,[$run]);
        $this->insert('answers',self::ANSWER_COLUMNS,$snapshot['answers']);
        return $run;
    }

    public function snapshot(array $run): array
    {
        $q=$this->store->db->prepare('SELECT * FROM answers WHERE run_id=? ORDER BY ordinal');$q->execute([$run['id']]);
        return ['run'=>$run,'answers'=>$q->fetchAll()];
    }
}
