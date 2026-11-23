<?php
declare(strict_types=1);

/** Standalone question bank and timed adaptive runner. No writes to the LMS database. */
final class AdaptiveQcm
{
    public const INITIAL_RATES=[80.0,60.0,40.0,20.0];
    public const SCORING_VERSION=2;
    public function __construct(public readonly PDO $db)
    {
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
        $db->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=10000');
        $version=(int)$db->query('PRAGMA user_version')->fetchColumn();
        if($version===2)return;
        if($version>2)throw new RuntimeException('La base QCM adaptatif est plus récente que ce module.');
        $db->exec('BEGIN IMMEDIATE');
        try{
            $version=(int)$db->query('PRAGMA user_version')->fetchColumn();
            if($version===0)$db->exec("CREATE TABLE IF NOT EXISTS settings(page_id INTEGER PRIMARY KEY,initial_level INTEGER NOT NULL CHECK(initial_level BETWEEN 0 AND 3));
                CREATE TABLE IF NOT EXISTS questions(id TEXT PRIMARY KEY,owner_id INTEGER NOT NULL,page_id INTEGER NOT NULL,block_id INTEGER NOT NULL,body TEXT NOT NULL,
                    presentations INTEGER NOT NULL DEFAULT 0,correct INTEGER NOT NULL DEFAULT 0,timed_answers INTEGER NOT NULL DEFAULT 0,time_sum REAL NOT NULL DEFAULT 0);
                CREATE INDEX IF NOT EXISTS question_page ON questions(page_id,block_id);
                CREATE TABLE IF NOT EXISTS evaluation_pools(item_id INTEGER NOT NULL,fingerprint TEXT NOT NULL,pool TEXT NOT NULL,PRIMARY KEY(item_id,fingerprint));
                CREATE TABLE IF NOT EXISTS runs(id TEXT PRIMARY KEY,student_id INTEGER NOT NULL,item_id INTEGER NOT NULL,page_id INTEGER NOT NULL,evaluation INTEGER NOT NULL,
                    started_at REAL NOT NULL,ends_at REAL NOT NULL,initial_level INTEGER NOT NULL,cursor REAL NOT NULL,pool TEXT NOT NULL,sources TEXT NOT NULL,
                    history TEXT NOT NULL DEFAULT '[]',finished_at REAL,reason TEXT,score REAL,scoring_version INTEGER NOT NULL DEFAULT 1);
                CREATE INDEX IF NOT EXISTS run_student_item ON runs(student_id,item_id,started_at DESC);
                CREATE UNIQUE INDEX IF NOT EXISTS one_evaluation ON runs(student_id,item_id) WHERE evaluation=1;
                CREATE UNIQUE INDEX IF NOT EXISTS one_running ON runs(student_id,item_id) WHERE finished_at IS NULL;
                CREATE TABLE IF NOT EXISTS answers(run_id TEXT NOT NULL REFERENCES runs(id) ON DELETE CASCADE,ordinal INTEGER NOT NULL,
                    question_id TEXT NOT NULL REFERENCES questions(id),presented_at REAL NOT NULL,deadline REAL NOT NULL,step INTEGER NOT NULL,
                    selected TEXT,result TEXT,answered_at REAL,elapsed REAL,cursor_after REAL,PRIMARY KEY(run_id,ordinal),UNIQUE(run_id,question_id));
                PRAGMA user_version=2");
            elseif($version===1)$db->exec('ALTER TABLE runs ADD COLUMN scoring_version INTEGER NOT NULL DEFAULT 1; PRAGMA user_version=2');
            $db->exec('COMMIT');
        }catch(Throwable $error){$db->exec('ROLLBACK');throw $error;}
    }

    public static function enabled(): bool {return getenv('APP_ADAPTIVE_QCM')!=='0';}
    public static function store(PDO $core,bool $create=true): ?self
    {
        if(!self::enabled())return null;
        $file='';foreach($core->query('PRAGMA database_list') as $row)if($row['name']==='main')$file=(string)$row['file'];
        if($file==='')return null; // In-memory callers supply an isolated store explicitly.
        $path=dirname($file).'/adaptive.sqlite';
        if(!$create&&!is_file($path))return null;
        static $stores=[];
        if(!isset($stores[$path])){
            $pdo=new PDO('sqlite:'.$path);$pdo->exec('PRAGMA busy_timeout=5000; PRAGMA journal_mode=WAL');
            $stores[$path]=new self($pdo);
        }
        return $stores[$path];
    }
    public function one(string $sql,array $args=[]): ?array
    {
        $q=$this->db->prepare($sql);$q->execute($args);return $q->fetch()?:null;
    }
    public function level(int $pageId): int{return (int)($this->one('SELECT initial_level FROM settings WHERE page_id=?',[$pageId])['initial_level']??1);}
    public function setLevel(int $pageId,int $level): void
    {
        if($level<0||$level>3)throw new InvalidArgumentException('Le niveau initial doit être compris entre 0 et 3.');
        $this->db->prepare('INSERT INTO settings VALUES(?,?) ON CONFLICT(page_id) DO UPDATE SET initial_level=excluded.initial_level')->execute([$pageId,$level]);
    }
    public static function has(string $body): bool{return (bool)preg_match('/^\s*:::\s*qcm_(?:adap|adaptatif|tiers)(?:\[|\s|$)/mu',$body);}
    public static function reference(string $body): ?string
    {
        return preg_match('/\A\s*:::qcm_tiers\s*\n([^\r\n]+)\s*\n:::\s*\z/u',$body,$m)?trim($m[1]):null;
    }
    public static function shareToken(string $url,string $base): string
    {
        $parts=parse_url($url);$origin=parse_url(rtrim($base,'/').'/');
        if(!$parts||!$origin||!in_array($parts['scheme']??'',['https','http'],true)||isset($parts['user'])||isset($parts['pass'])
            ||strtolower($parts['host']??'')!==strtolower($origin['host']??'')||($parts['port']??null)!==($origin['port']??null)
            ||rtrim($parts['path']??'','/')!==rtrim($origin['path']??'','/'))throw new InvalidArgumentException('Utilisez un lien de partage de cette installation liike.');
        parse_str($parts['query']??'',$query);
        if(($query['view']??'')!=='shared-content'||!is_string($query['share']??null)||!preg_match('/^[a-f0-9]{64}$/D',$query['share']))throw new InvalidArgumentException('Lien de partage invalide.');
        return $query['share'];
    }
    /** Metadata imported from text is descriptive only: counters are never trusted. */
    public static function parse(string $source,bool $metadataRequired=true): array
    {
        $questions=[];$current=null;$valid=true;
        foreach(explode("\n",$source) as $line){
            $line=trim($line);if($line==='')continue;
            if(preg_match('/^#{1,4}\s+(.+)$/u',$line,$m)){
                if($current!==null)$questions[]=$current;
                $current=['title'=>$m[1],'level'=>1,'seconds'=>120,'metadata'=>false,'answers'=>[]];continue;
            }
            if($current!==null&&!$current['answers']&&!$current['metadata']&&preg_match('/^\[([0-3])\]\[([1-9][0-9]{0,4})\]\[(-|[0-9]+(?:[.,][0-9]+)?)\]\[([0-9]+)\]\[([0-9]+)\]$/D',$line,$m)){
                $current['level']=(int)$m[1];$current['seconds']=(int)$m[2];$current['metadata']=true;
                if($current['seconds']>86400)$valid=false;
                continue;
            }
            if($current!==null&&preg_match('/^(?:[-*+]\s+)?\[(v|x|-)\]\s+(.+)$/iu',$line,$m)){
                $current['answers'][]=['kind'=>strtolower($m[1]),'text'=>$m[2],'index'=>count($current['answers'])];continue;
            }
            $valid=false;
        }
        if($current!==null)$questions[]=$current;
        foreach($questions as &$question){
            $correct=count(array_filter($question['answers'],static fn($a)=>$a['kind']==='v'));
            $question['multiple']=$correct>1;
            if(($metadataRequired&&!$question['metadata'])||count($question['answers'])<2||$correct<1)$valid=false;
        }unset($question);
        return ['valid'=>$valid&&(bool)$questions,'questions'=>$questions];
    }
    public static function context(PDO $core,int $itemId,int $studentId): array
    {
        $q=$core->prepare("SELECT pi.*,p.estimated_minutes,p.owner_id,p.title FROM pathway_items pi JOIN pages p ON p.id=pi.page_id
            JOIN courses c ON c.id=pi.course_id AND c.archived=0
            JOIN enrollments e ON e.course_id=pi.course_id AND e.student_id=? AND e.status='active'
            JOIN users u ON u.id=e.student_id AND u.role='student' AND u.account_status='active'
            WHERE pi.id=? AND NOT EXISTS(SELECT 1 FROM pathway_groups g WHERE g.id=pi.group_id AND g.is_visible=0)
            AND (pi.access_mode='all' OR (pi.access_mode='restricted' AND EXISTS(SELECT 1 FROM pathway_item_students a WHERE a.pathway_item_id=pi.id AND a.student_id=?)))");
        $q->execute([$studentId,$itemId,$studentId]);$item=$q->fetch(PDO::FETCH_ASSOC);
        if(!$item)throw new InvalidArgumentException('Cette activité n’est pas accessible.');
        return $item;
    }
    /** Only this page and its explicit, direct references. No network requests or recursive imports. */
    public static function bank(PDO $core,int $pageId,string $base): array
    {
        $q=$core->prepare('SELECT b.*,p.owner_id FROM page_blocks b JOIN pages p ON p.id=b.page_id WHERE b.page_id=? AND b.type=\'markdown\' ORDER BY b.position,b.id');
        $q->execute([$pageId]);$local=$q->fetchAll(PDO::FETCH_ASSOC);$blocks=[];$sources=[];
        foreach($local as $block){
            $url=self::reference($block['body']);
            if($url!==null){
                $token=self::shareToken($url,$base);$share=library_share_resolve($core,$token);library_share_require_mode($share,'shared');
                if((int)$share['page_id']===$pageId)throw new InvalidArgumentException('Un QCM tiers doit désigner une autre page.');
                $sources[$token]=(int)$share['page_id'];
                $q->execute([(int)$share['page_id']]);
                foreach($q->fetchAll(PDO::FETCH_ASSOC) as $other)if(self::reference($other['body'])===null)$blocks[]=[$other,true];
            }else $blocks[]=[$block,false];
        }
        $pool=[];$seen=[];
        foreach($blocks as [$block,$third])foreach(Qcm::sections($block['body']) as $section){
            if($section['type']!=='adaptive'&& !($third&&$section['type']==='qcm'))continue;
            if(empty($section['valid']))throw new InvalidArgumentException('Un bloc QCM du réservoir est incomplet. Vérifiez les questions et leurs cinq paramètres.');
            foreach($section['questions'] as $question){
                $question+=['level'=>1,'seconds'=>120];unset($question['metadata']);
                $question['domain']=$section['domain']??'';$question['theme']=$section['theme']??'';
                $identity=json_encode($question,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
                $duplicate=hash('sha256',$identity);if(isset($seen[$duplicate]))continue;$seen[$duplicate]=true;
                $question['id']=hash('sha256',$block['page_id']."\0".$block['id']."\0".$identity);
                $question['page_id']=(int)$block['page_id'];$question['block_id']=(int)$block['id'];$question['owner_id']=(int)$block['owner_id'];
                $pool[]=$question;
            }
        }
        if(!$pool)throw new InvalidArgumentException('Ajoutez des questions adaptatives ou un bloc QCM tiers à cette page.');
        if(count($pool)>2000)throw new InvalidArgumentException('Le réservoir est limité à 2 000 questions par activité.');
        return ['questions'=>$pool,'sources'=>$sources];
    }
    public function statistics(array $questions): array
    {
        $stats=[];
        foreach(array_chunk(array_column($questions,'id'),400) as $ids){
            $get=$this->db->prepare('SELECT id,presentations,correct,timed_answers,time_sum FROM questions WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).')');
            $get->execute($ids);foreach($get->fetchAll() as $row)$stats[$row['id']]=$row;
        }
        foreach($questions as &$question){
            $s=$stats[$question['id']]??['presentations'=>0,'correct'=>0,'timed_answers'=>0,'time_sum'=>0];
            $question['stats']=$s;$question['rate']=$s['presentations']?100*$s['correct']/$s['presentations']:self::INITIAL_RATES[$question['level']];
        }unset($question);return $questions;
    }
    public function latest(int $studentId,int $itemId): ?array
    {
        return $this->one('SELECT * FROM runs WHERE student_id=? AND item_id=? ORDER BY started_at DESC,id DESC LIMIT 1',[$studentId,$itemId]);
    }
    public function start(array $item,int $studentId,array $bank,?float $now=null): array
    {
        return $this->transaction(function()use($item,$studentId,$bank,$now){
            $now??=microtime(true);
            if($item['is_evaluation']){
                $evaluated=$this->one('SELECT * FROM runs WHERE student_id=? AND item_id=? AND evaluation=1',[$studentId,$item['id']]);
                if($evaluated)return $evaluated;
            }
            $last=$this->latest($studentId,(int)$item['id']);
            if($last&&($last['finished_at']===null||((int)$item['is_evaluation']&&(int)$last['evaluation'])))return $last;
            $level=$this->level((int)$item['page_id']);$duration=max(1,(int)$item['estimated_minutes'])*60;
            $pool=$bank['questions'];
            $fingerprint=hash('sha256',json_encode([array_column($pool,'id'),$level,$duration],JSON_THROW_ON_ERROR));
            $frozen=$item['is_evaluation']?$this->one('SELECT pool FROM evaluation_pools WHERE item_id=? AND fingerprint=?',[$item['id'],$fingerprint]):null;
            if($frozen){$poolJson=$frozen['pool'];}
            else{
                foreach(array_chunk($pool,100) as $batch){
                    $values=[];foreach($batch as $question)array_push($values,$question['id'],$question['owner_id'],$question['page_id'],$question['block_id'],json_encode($question,JSON_THROW_ON_ERROR));
                    $this->db->prepare('INSERT OR IGNORE INTO questions(id,owner_id,page_id,block_id,body) VALUES '.implode(',',array_fill(0,count($batch),'(?,?,?,?,?)')))->execute($values);
                }
                $pool=$this->statistics($pool);
                $poolJson=json_encode($pool,JSON_THROW_ON_ERROR);
                if($item['is_evaluation'])$this->db->prepare('INSERT INTO evaluation_pools VALUES(?,?,?)')->execute([$item['id'],$fingerprint,$poolJson]);
            }
            $id=bin2hex(random_bytes(16));
            $this->db->prepare('INSERT INTO runs(id,student_id,item_id,page_id,evaluation,started_at,ends_at,initial_level,cursor,pool,sources,scoring_version) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$id,$studentId,$item['id'],$item['page_id'],(int)(bool)$item['is_evaluation'],$now,$now+$duration,$level,100-self::INITIAL_RATES[$level],$poolJson,json_encode($bank['sources'],JSON_THROW_ON_ERROR),self::SCORING_VERSION]);
            return $this->one('SELECT * FROM runs WHERE id=?',[$id]);
        });
    }
    private function transaction(callable $operation): mixed
    {
        $this->db->exec('BEGIN IMMEDIATE');
        try{$result=$operation();$this->db->exec('COMMIT');return $result;}
        catch(Throwable $error){$this->db->exec('ROLLBACK');throw $error;}
    }
    public static function step(float $elapsed,float $duration): int
    {
        return $elapsed<$duration/3?20:($elapsed<2*$duration/3?10:5);
    }
    public static function finalScore(array $history,bool $roundResult=true): ?float
    {
        $last=array_slice($history,-2);if(!$last)return null;
        $average=array_sum($last)/count($last);return $roundResult?round($average,1):$average;
    }
    public static function difficultyRange(array $pool): array
    {
        $values=array_map(static fn($question)=>100-(float)$question['rate'],$pool);
        return ['min'=>min($values),'max'=>max($values)];
    }
    /** Store the rounded mark as a percentage, so all existing /10 exports agree. */
    public static function scaledScore(float $raw,array $range): float
    {
        if($range['max']-$range['min']<1e-9)return $raw;
        return round(max(0,min(10,10*($raw-$range['min'])/($range['max']-$range['min']))),1)*10;
    }
    private function finish(array $run,float $now,string $reason): array
    {
        $range=self::difficultyRange(json_decode($run['pool'],true,512,JSON_THROW_ON_ERROR));
        $relative=(int)($run['scoring_version']??1)>=2&&$range['max']-$range['min']>=1e-9;
        $score=self::finalScore(json_decode($run['history'],true,512,JSON_THROW_ON_ERROR),!$relative);
        if($score!==null&&(int)($run['scoring_version']??1)>=2){
            $score=$reason==='hardest'?100.0:self::scaledScore($score,$range);
        }
        $this->db->prepare('UPDATE runs SET finished_at=?,reason=?,score=? WHERE id=? AND finished_at IS NULL')->execute([$now,$reason,$score,$run['id']]);
        return $this->one('SELECT * FROM runs WHERE id=?',[$run['id']]);
    }
    /** All decisions, expiry and statistics are atomic and server-timed. Repeated submissions are harmless. */
    public function advance(string $id,int $studentId,float $now,?int $ordinal=null,?array $selected=null,?callable $clock=null): array
    {
        return $this->transaction(function()use($id,$studentId,$now,$ordinal,$selected,$clock){
            $issuedAt=$clock?$clock():$now;
            $run=$this->one('SELECT * FROM runs WHERE id=? AND student_id=?',[$id,$studentId]);
            if(!$run)throw new InvalidArgumentException('Séance introuvable.');
            if($run['finished_at']!==null)return $run;
            $pool=json_decode($run['pool'],true,512,JSON_THROW_ON_ERROR);$byId=array_column($pool,null,'id');
            $pending=$this->one('SELECT * FROM answers WHERE run_id=? AND result IS NULL',[$id]);
            if($pending){
                $submit=$selected!==null&&$ordinal===(int)$pending['ordinal'];
                if($submit&&$now<(float)$pending['presented_at'])throw new InvalidArgumentException('Cette question n’était pas encore présentée.');
                // Timely requests stay timely while waiting for a writer. New questions get fresh clocks.
                $expired=($submit?$now:$issuedAt)>=(float)$pending['deadline'];
                if(!$expired&&!$submit)return $run;
                $question=$byId[$pending['question_id']];$chosen=[];
                if(!$expired){
                    foreach($selected as $choice){
                        if(!is_scalar($choice)||!preg_match('/^\d+$/D',(string)$choice)||!isset($question['answers'][(int)$choice]))throw new InvalidArgumentException('Réponse invalide.');
                        $chosen[]=(int)$choice;
                    }
                    $chosen=array_values(array_unique($chosen));
                    if(!$question['multiple']&&count($chosen)>1)throw new InvalidArgumentException('Une seule réponse est possible.');
                }
                $right=!$expired;
                foreach($question['answers'] as $answer)if(($answer['kind']==='v'&&!in_array($answer['index'],$chosen,true))||($answer['kind']==='x'&&in_array($answer['index'],$chosen,true)))$right=false;
                $cursor=max(0,min(100,(float)$run['cursor']+($right?1:-1)*(int)$pending['step']));
                $history=json_decode($run['history'],true,512,JSON_THROW_ON_ERROR);$history[]=$cursor;
                $result=$expired?'timeout':($right?'correct':'incorrect');$elapsed=$expired?null:max(0,$now-(float)$pending['presented_at']);
                $this->db->prepare('UPDATE answers SET selected=?,result=?,answered_at=?,elapsed=?,cursor_after=? WHERE run_id=? AND ordinal=? AND result IS NULL')
                    ->execute([json_encode($chosen),$result,$now,$elapsed,$cursor,$id,$pending['ordinal']]);
                if($run['evaluation'])$this->db->prepare('UPDATE questions SET correct=correct+?,timed_answers=timed_answers+?,time_sum=time_sum+? WHERE id=?')->execute([(int)$right,(int)!$expired,$elapsed??0,$question['id']]);
                $this->db->prepare('UPDATE runs SET cursor=?,history=? WHERE id=?')->execute([$cursor,json_encode($history),$id]);
                $run['cursor']=$cursor;$run['history']=json_encode($history);
                if((int)($run['scoring_version']??1)>=2){
                    $range=self::difficultyRange($pool);$difficulty=100-(float)$question['rate'];
                    if($range['max']-$range['min']>=1e-9){
                        if($right&&abs($difficulty-$range['max'])<1e-9)return $this->finish($run,$issuedAt,'hardest');
                        if(!$right&&$issuedAt<(float)$run['ends_at']&&abs($difficulty-$range['min'])<1e-9){
                            $usedQuery=$this->db->prepare('SELECT question_id FROM answers WHERE run_id=?');$usedQuery->execute([$id]);$usedIds=array_flip($usedQuery->fetchAll(PDO::FETCH_COLUMN));
                            $easier=array_filter($pool,static fn($candidate)=>!isset($usedIds[$candidate['id']])&&100-(float)$candidate['rate']<=$difficulty+1e-9);
                            if(!$easier)return $this->finish($run,$issuedAt,'easiest');
                        }
                    }
                }
            }
            if($issuedAt>=(float)$run['ends_at'])return $this->finish($run,$issuedAt,'time');
            $q=$this->db->prepare('SELECT question_id FROM answers WHERE run_id=?');$q->execute([$id]);$used=$q->fetchAll(PDO::FETCH_COLUMN);
            $remaining=array_values(array_filter($pool,static fn($question)=>!in_array($question['id'],$used,true)));
            if(!$remaining)return $this->finish($run,$issuedAt,'empty');
            $first=!$used;shuffle($remaining);
            usort($remaining,static function($a,$b)use($first,$run){
                return $first?abs($a['level']-$run['initial_level'])<=>abs($b['level']-$run['initial_level']):abs($a['rate']-(100-$run['cursor']))<=>abs($b['rate']-(100-$run['cursor']));
            });
            $question=$remaining[0];
            if($first){$run['cursor']=100-$question['rate'];$this->db->prepare('UPDATE runs SET cursor=? WHERE id=?')->execute([$run['cursor'],$id]);}
            $this->db->prepare('INSERT INTO answers(run_id,ordinal,question_id,presented_at,deadline,step) VALUES(?,?,?,?,?,?)')
                ->execute([$id,count($used)+1,$question['id'],$issuedAt,min($issuedAt+$question['seconds'],(float)$run['ends_at']),self::step($issuedAt-$run['started_at'],$run['ends_at']-$run['started_at'])]);
            if($run['evaluation'])$this->db->prepare('UPDATE questions SET presentations=presentations+1 WHERE id=?')->execute([$question['id']]);
            return $run;
        });
    }
    public function publicState(array $run,float $now): array
    {
        $state=['id'=>$run['id'],'evaluation'=>(bool)$run['evaluation'],'now'=>$now,'ends_at'=>(float)$run['ends_at'],'finished'=>$run['finished_at']!==null];
        $pool=array_column(json_decode($run['pool'],true,512,JSON_THROW_ON_ERROR),null,'id');
        if($state['finished']){
            $state+=['score'=>$run['score']===null?null:(float)$run['score'],'reason'=>$run['reason'],'review'=>[]];
            $range=self::difficultyRange(array_values($pool));
            $relative=(int)($run['scoring_version']??1)>=2&&$range['max']-$range['min']>=1e-9;
            $state['grading']=['relative'=>$relative,'min'=>$range['min'],'max'=>$range['max'],
                'raw_score'=>self::finalScore(json_decode($run['history'],true,512,JSON_THROW_ON_ERROR),!$relative)];
            $q=$this->db->prepare('SELECT * FROM answers WHERE run_id=? ORDER BY ordinal');$q->execute([$run['id']]);
            foreach($q->fetchAll() as $answer){$question=$pool[$answer['question_id']];$state['review'][]=['title'=>$question['title'],'result'=>$answer['result'],'answers'=>$question['answers'],'selected'=>json_decode($answer['selected']??'[]',true)];}
            return $state;
        }
        $pending=$this->one('SELECT * FROM answers WHERE run_id=? AND result IS NULL',[$run['id']]);
        if($pending){
            $question=$pool[$pending['question_id']];$options=[];
            foreach($question['answers'] as $answer)$options[]=['index'=>$answer['index'],'text'=>$answer['text']];shuffle($options);
            $state['question']=['ordinal'=>(int)$pending['ordinal'],'title'=>$question['title'],'domain'=>$question['domain'],'theme'=>$question['theme'],'multiple'=>$question['multiple'],'answers'=>$options,'deadline'=>(float)$pending['deadline']];
        }
        if(!$run['evaluation']){
            $last=$this->one('SELECT * FROM answers WHERE run_id=? AND result IS NOT NULL ORDER BY ordinal DESC LIMIT 1',[$run['id']]);
            if($last)$state['feedback']=['result'=>$last['result'],'title'=>$pool[$last['question_id']]['title'],'correct'=>array_column(array_filter($pool[$last['question_id']]['answers'],static fn($answer)=>$answer['kind']==='v'),'text')];
        }
        return $state;
    }
}
