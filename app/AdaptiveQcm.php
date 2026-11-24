<?php
declare(strict_types=1);
require_once __DIR__.'/AdaptiveQcmModel.php';

/** Standalone question bank and timed adaptive runner. No writes to the LMS database. */
final class AdaptiveQcm
{
    public const INITIAL_RATES=[80.0,60.0,40.0,20.0];
    public const SCORING_VERSION=3;
    public const SCHEMA_VERSION=4;
    private bool $writing=false;
    public function __construct(public readonly PDO $db)
    {
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
        $db->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=10000');
        $version=(int)$db->query('PRAGMA user_version')->fetchColumn();
        if($version===self::SCHEMA_VERSION)return;
        if($version>self::SCHEMA_VERSION)throw new RuntimeException('La base QCM adaptatif est plus récente que ce module.');
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
            if($version<3){
                $db->exec('ALTER TABLE questions ADD COLUMN canonical_id TEXT;
                    CREATE INDEX question_canonical ON questions(canonical_id);
                    CREATE TABLE calibration_samples(question_id TEXT NOT NULL REFERENCES questions(id),student_id INTEGER NOT NULL,run_id TEXT NOT NULL REFERENCES runs(id),correct INTEGER NOT NULL,elapsed REAL,weight REAL NOT NULL DEFAULT 1,PRIMARY KEY(question_id,student_id));
                    CREATE TABLE evaluation_revisions(item_id INTEGER PRIMARY KEY,revision INTEGER NOT NULL DEFAULT 1,fingerprint TEXT);
                    CREATE INDEX answer_question ON answers(question_id);
                    CREATE INDEX run_unfinished ON runs(item_id,ends_at) WHERE finished_at IS NULL');
                $update=$db->prepare('UPDATE questions SET canonical_id=? WHERE id=?');
                foreach($db->query('SELECT * FROM questions')->fetchAll() as $row){
                    $question=json_decode($row['body'],true,512,JSON_THROW_ON_ERROR);
                    $update->execute([self::canonicalId($question,(int)$row['page_id'],(int)$row['block_id']),$row['id']]);
                }
                // Old grades are untouched. Future calibration excludes truncated windows and repeated pupils.
                $insert=$db->prepare('INSERT OR IGNORE INTO calibration_samples(question_id,student_id,run_id,correct,elapsed) VALUES(?,?,?,?,?)');
                $rows=$db->query("SELECT a.*,r.student_id,q.body FROM answers a JOIN runs r ON r.id=a.run_id JOIN questions q ON q.id=a.question_id WHERE r.evaluation=1 AND a.result IN ('correct','incorrect','timeout') ORDER BY a.presented_at,a.run_id,a.ordinal");
                foreach($rows as $row){
                    $seconds=(int)(json_decode($row['body'],true)['seconds']??120);
                    if((float)$row['deadline']-(float)$row['presented_at']<$seconds-0.01)continue;
                    $insert->execute([$row['question_id'],$row['student_id'],$row['run_id'],(int)($row['result']==='correct'),$row['elapsed']]);
                }
                $db->exec('PRAGMA user_version=3');
            }
            if($version<4){
                $columns=$db->query('PRAGMA table_info(evaluation_revisions)')->fetchAll();
                if(!in_array('fingerprint',array_column($columns,'name'),true))$db->exec('ALTER TABLE evaluation_revisions ADD COLUMN fingerprint TEXT');
                $db->exec('PRAGMA user_version=4');
            }
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
            if(in_array((int)$pdo->query('PRAGMA user_version')->fetchColumn(),[1,2,3],true)){
                $directory=dirname($path).'/backups';
                if(!is_dir($directory)&&!mkdir($directory,0775,true)&&!is_dir($directory))throw new RuntimeException('Impossible de sauvegarder la base adaptative avant mise à niveau.');
                $pdo->exec('VACUUM INTO '.$pdo->quote($directory.'/adaptive-before-v4-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sqlite'));
            }
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
    /** Identify assessed content within its source page and block. */
    public static function canonicalId(array $question,int $pageId,int $blockId): string
    {
        $clean=static fn(string $text):string=>preg_replace('/\s+/u',' ',trim($text))??trim($text);
        $answers=array_map(static fn($answer)=>[$answer['kind'],$clean($answer['text'])],$question['answers']);
        sort($answers);
        // Classification, teacher level, counters and option order do not change the assessed content.
        return hash('sha256',json_encode([$pageId,$blockId,$clean($question['title']),(int)$question['seconds'],$answers],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }

    public function resolveVersions(array $questions): array
    {
        $known=[];
        foreach(array_chunk(array_column($questions,'canonical_id'),400) as $ids){
            $q=$this->db->prepare('SELECT id,canonical_id FROM questions WHERE canonical_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY presentations DESC,id');
            $q->execute($ids);foreach($q as $row)$known[$row['canonical_id']]??=$row['id'];
        }
        foreach($questions as &$question)$question['id']=$known[$question['canonical_id']]??$question['id'];unset($question);
        return $questions;
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
                $duplicate=self::canonicalId($question,0,0);if(isset($seen[$duplicate]))continue;$seen[$duplicate]=true;
                $question['page_id']=(int)$block['page_id'];$question['block_id']=(int)$block['id'];$question['owner_id']=(int)$block['owner_id'];
                $question['canonical_id']=self::canonicalId($question,$question['page_id'],$question['block_id']);
                $question['id']=$question['canonical_id'];
                $pool[]=$question;
            }
        }
        if(!$pool)throw new InvalidArgumentException('Ajoutez des questions adaptatives ou un bloc QCM tiers à cette page.');
        if(count($pool)>2000)throw new InvalidArgumentException('Le réservoir est limité à 2 000 questions par activité.');
        $store=self::store($core,false);
        return ['questions'=>$store?$store->resolveVersions($pool):$pool,'sources'=>$sources];
    }
    public function statistics(array $questions,bool $legacy=false): array
    {
        $stats=[];
        foreach(array_chunk(array_column($questions,'id'),400) as $ids){
            $get=$this->db->prepare('SELECT id,presentations,correct,timed_answers,time_sum FROM questions WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).')');
            $get->execute($ids);foreach($get->fetchAll() as $row)$stats[$row['id']]=$row;
        }
        foreach($questions as &$question){
            $s=$stats[$question['id']]??['presentations'=>0,'correct'=>0,'timed_answers'=>0,'time_sum'=>0];
            $question['stats']=$s;$question['rate']=$s['presentations']?100*$s['correct']/$s['presentations']:self::INITIAL_RATES[$question['level']];
        }unset($question);
        if($legacy)return $questions;
        $samples=[];
        foreach(array_chunk(array_column($questions,'id'),400) as $ids){
            $q=$this->db->prepare('SELECT question_id,COUNT(*) n,SUM(correct) correct,SUM(weight) weight,SUM(correct*weight) weighted_correct,COUNT(elapsed) timed,AVG(elapsed) mean FROM calibration_samples WHERE question_id IN ('.implode(',',array_fill(0,count($ids),'?')).') GROUP BY question_id');
            $q->execute($ids);foreach($q as $row)$samples[$row['question_id']]=$row;
        }
        foreach($questions as &$question){
            $sample=$samples[$question['id']]??['n'=>0,'correct'=>0,'weight'=>0,'weighted_correct'=>0,'timed'=>0,'mean'=>null];
            $question['calibration']=$sample;
            $question['observed_rate']=$sample['n']?100*$sample['correct']/$sample['n']:null;
            $question['rate']=100*($sample['weighted_correct']+AdaptiveQcmModel::PRIOR_WEIGHT*self::INITIAL_RATES[$question['level']]/100)/($sample['weight']+AdaptiveQcmModel::PRIOR_WEIGHT);
        }unset($question);return $questions;
    }

    public function referenceRevision(int $itemId): int
    {
        return (int)($this->one('SELECT revision FROM evaluation_revisions WHERE item_id=?',[$itemId])['revision']??0);
    }

    public static function fingerprint(array $pool,int $level,int $duration,int $revision): string
    {
        $ids=array_column($pool,'id');sort($ids);
        return hash('sha256',json_encode([self::SCORING_VERSION,$ids,$level,$duration,$revision],JSON_THROW_ON_ERROR));
    }

    public function refreshReference(int $itemId): void
    {
        $this->transaction(function()use($itemId){
            if($this->one('SELECT id FROM runs WHERE item_id=? AND finished_at IS NULL AND ends_at>?',[$itemId,microtime(true)]))throw new InvalidArgumentException('Attendez la fin des tentatives en cours avant d’actualiser la référence.');
            $this->db->prepare('INSERT INTO evaluation_revisions(item_id,revision) VALUES(?,1) ON CONFLICT(item_id) DO UPDATE SET revision=revision+1,fingerprint=NULL')->execute([$itemId]);
        });
    }
    public function latest(int $studentId,int $itemId): ?array
    {
        return $this->one('SELECT * FROM runs WHERE student_id=? AND item_id=? ORDER BY started_at DESC,id DESC LIMIT 1',[$studentId,$itemId]);
    }
    /** Optionally issue the first question in the same durable transaction as a NEW attempt. */
    public function start(array $item,int $studentId,array $bank,?float $now=null,?int $version=null,bool $present=false): array
    {
        return $this->transaction(function()use($item,$studentId,$bank,$now,$version,$present){
            $liveClock=$now===null;
            $now??=microtime(true);
            if($item['is_evaluation']){
                $evaluated=$this->one('SELECT * FROM runs WHERE student_id=? AND item_id=? AND evaluation=1',[$studentId,$item['id']]);
                if($evaluated)return $evaluated;
            }
            $last=$this->latest($studentId,(int)$item['id']);
            if($last&&($last['finished_at']===null||((int)$item['is_evaluation']&&(int)$last['evaluation'])))return $last;
            $level=$this->level((int)$item['page_id']);$duration=max(1,(int)$item['estimated_minutes'])*60;
            $pool=$bank['questions'];
            $revision=$this->referenceRevision((int)$item['id']);
            $oldFingerprint=hash('sha256',json_encode([array_column($pool,'id'),$level,$duration],JSON_THROW_ON_ERROR));
            $legacy=(!$revision&&$item['is_evaluation'])?$this->one('SELECT pool FROM evaluation_pools WHERE item_id=? AND fingerprint=?',[$item['id'],$oldFingerprint]):null;
            if($version===null&&$legacy){
                $previous=$this->one('SELECT scoring_version FROM runs WHERE item_id=? AND pool=? ORDER BY started_at LIMIT 1',[$item['id'],$legacy['pool']]);
                $version=(int)($previous['scoring_version']??2);
            }
            $version??=self::SCORING_VERSION;
            $fingerprint=$version<3?$oldFingerprint:self::fingerprint($pool,$level,$duration,$revision);
            $frozen=$item['is_evaluation']?$this->one('SELECT pool FROM evaluation_pools WHERE item_id=? AND fingerprint=?',[$item['id'],$fingerprint]):null;
            if($item['is_evaluation']&&$version>=3){
                $reference=$this->one('SELECT fingerprint FROM evaluation_revisions WHERE item_id=?',[$item['id']]);
                if((!empty($reference['fingerprint'])&&$reference['fingerprint']!==$fingerprint)
                    ||(!$revision&&!$reference&&!$frozen&&$this->one('SELECT 1 FROM evaluation_pools WHERE item_id=? LIMIT 1',[$item['id']])))
                    throw new InvalidArgumentException('Cette évaluation a été modifiée. L’enseignant doit actualiser sa référence avant les prochaines tentatives.');
                $this->db->prepare('INSERT INTO evaluation_revisions(item_id,revision,fingerprint) VALUES(?,?,?) ON CONFLICT(item_id) DO UPDATE SET fingerprint=excluded.fingerprint')->execute([$item['id'],$revision,$fingerprint]);
            }
            if($frozen){$poolJson=$frozen['pool'];}
            else{
                foreach(array_chunk($pool,100) as $batch){
                    $values=[];foreach($batch as $question)array_push($values,$question['id'],$question['owner_id'],$question['page_id'],$question['block_id'],json_encode($question,JSON_THROW_ON_ERROR),$question['canonical_id']??self::canonicalId($question,$question['page_id'],$question['block_id']));
                    $this->db->prepare('INSERT OR IGNORE INTO questions(id,owner_id,page_id,block_id,body,canonical_id) VALUES '.implode(',',array_fill(0,count($batch),'(?,?,?,?,?,?)')))->execute($values);
                }
                $pool=$bank['reference_questions']??$this->statistics($pool,$version<3);
                $poolJson=json_encode($pool,JSON_THROW_ON_ERROR);
                if($item['is_evaluation'])$this->db->prepare('INSERT INTO evaluation_pools VALUES(?,?,?)')->execute([$item['id'],$fingerprint,$poolJson]);
            }
            $id=bin2hex(random_bytes(16));
            $this->db->prepare('INSERT INTO runs(id,student_id,item_id,page_id,evaluation,started_at,ends_at,initial_level,cursor,pool,sources,scoring_version) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$id,$studentId,$item['id'],$item['page_id'],(int)(bool)$item['is_evaluation'],$now,$now+$duration,$level,$version<3?100-self::INITIAL_RATES[$level]:50,$poolJson,json_encode($bank['sources'],JSON_THROW_ON_ERROR),$version]);
            $run=$this->one('SELECT * FROM runs WHERE id=?',[$id]);
            return $present?$this->advance($id,$studentId,$now,null,null,$liveClock?static fn():float=>microtime(true):null):$run;
        });
    }
    private function transaction(callable $operation): mixed
    {
        // start(present:true) and its first advance share one commit; failures roll back both.
        if($this->writing)return $operation();
        $this->db->exec('BEGIN IMMEDIATE');
        $this->writing=true;
        try{$result=$operation();$this->db->exec('COMMIT');return $result;}
        catch(Throwable $error){$this->db->exec('ROLLBACK');throw $error;}
        finally{$this->writing=false;}
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
        if((int)$run['scoring_version']>=3){
            $q=$this->db->prepare('SELECT question_id,result FROM answers WHERE run_id=? ORDER BY ordinal');$q->execute([$run['id']]);
            $estimate=AdaptiveQcmModel::estimate(json_decode($run['pool'],true),$q->fetchAll(),true);
            $this->db->prepare('UPDATE runs SET finished_at=?,reason=?,score=? WHERE id=? AND finished_at IS NULL')->execute([$now,$reason,$estimate['score'],$run['id']]);
            return $this->one('SELECT * FROM runs WHERE id=?',[$run['id']]);
        }
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
        // A simple resume while the current question is still open needs no writer lock.
        if($selected===null){
            $current=$this->one('SELECT * FROM runs WHERE id=? AND student_id=?',[$id,$studentId]);
            if(!$current)throw new InvalidArgumentException('Séance introuvable.');
            if($current['finished_at']!==null)return $current;
            $pending=$this->one('SELECT deadline FROM answers WHERE run_id=? AND result IS NULL',[$id]);
            $observed=$clock?$clock():$now;
            if($pending&&$observed<(float)$pending['deadline']&&$observed<(float)$current['ends_at'])return $current;
        }
        return $this->transaction(function()use($id,$studentId,$now,$ordinal,$selected,$clock){
            $issuedAt=$clock?$clock():$now;
            $run=$this->one('SELECT * FROM runs WHERE id=? AND student_id=?',[$id,$studentId]);
            if(!$run)throw new InvalidArgumentException('Séance introuvable.');
            if($run['finished_at']!==null)return $run;
            $pool=json_decode($run['pool'],true,512,JSON_THROW_ON_ERROR);$byId=array_column($pool,null,'id');
            $pending=$this->one('SELECT * FROM answers WHERE run_id=? AND result IS NULL',[$id]);
            if((int)$run['scoring_version']>=3&&!$pending&&!$this->one('SELECT 1 FROM answers WHERE run_id=? LIMIT 1',[$id])){
                // Start the usable time when the first question is actually issued, excluding any writer queue.
                $duration=(float)$run['ends_at']-(float)$run['started_at'];$run['started_at']=$issuedAt;$run['ends_at']=$issuedAt+$duration;
                $this->db->prepare('UPDATE runs SET started_at=?,ends_at=? WHERE id=?')->execute([$run['started_at'],$run['ends_at'],$id]);
            }
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
                $fullWindow=(float)$pending['deadline']-(float)$pending['presented_at']>=(int)$question['seconds']-0.01;
                if((int)$run['scoring_version']>=3&&$expired&&!$fullWindow)$result='global_timeout';
                $this->db->prepare('UPDATE answers SET selected=?,result=?,answered_at=?,elapsed=?,cursor_after=? WHERE run_id=? AND ordinal=? AND result IS NULL')
                    ->execute([json_encode($chosen),$result,$now,$elapsed,$cursor,$id,$pending['ordinal']]);
                if($run['evaluation']){
                    $this->db->prepare('UPDATE questions SET correct=correct+?,timed_answers=timed_answers+?,time_sum=time_sum+? WHERE id=?')->execute([(int)$right,(int)!$expired,$elapsed??0,$question['id']]);
                    if($fullWindow){
                        $reference=$question['calibration']??[];$weight=1.0;
                        $minimum=max(3.0,0.05*(int)$question['seconds']);
                        if($right&&($reference['timed']??0)>=5&&$elapsed>=$minimum&&$elapsed<=0.5*(float)$reference['mean'])$weight=1.25;
                        $this->db->prepare('INSERT OR IGNORE INTO calibration_samples(question_id,student_id,run_id,correct,elapsed,weight) VALUES(?,?,?,?,?,?)')->execute([$question['id'],$studentId,$id,(int)$right,$elapsed,$weight]);
                    }
                }
                if((int)$run['scoring_version']>=3){
                    $q=$this->db->prepare('SELECT question_id,result FROM answers WHERE run_id=? ORDER BY ordinal');$q->execute([$id]);
                    $estimate=AdaptiveQcmModel::estimate($pool,$q->fetchAll());$cursor=$estimate['cursor'];
                    $history=json_decode($run['history'],true);if($result!=='global_timeout')$history[]=$cursor;
                    $this->db->prepare('UPDATE answers SET cursor_after=? WHERE run_id=? AND ordinal=?')->execute([$cursor,$id,$pending['ordinal']]);
                }
                $this->db->prepare('UPDATE runs SET cursor=?,history=? WHERE id=?')->execute([$cursor,json_encode($history),$id]);
                $run['cursor']=$cursor;$run['history']=json_encode($history);
                if((int)($run['scoring_version']??1)===2){
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
            if((int)$run['scoring_version']>=3){
                $available=(float)$run['ends_at']-$issuedAt;
                $remaining=array_values(array_filter($remaining,static fn($question)=>(int)$question['seconds']<=$available));
                if(!$remaining)return $this->finish($run,$issuedAt,'time_remaining');
            }
            $first=!$used;shuffle($remaining);
            usort($remaining,static function($a,$b)use($first,$run){
                if($first)return abs($a['level']-$run['initial_level'])<=>abs($b['level']-$run['initial_level']);
                if((int)$run['scoring_version']>=3){
                    $theta=log(max(0.001,$run['cursor'])/max(0.001,100-$run['cursor']));
                    return abs(AdaptiveQcmModel::difficulty($a['rate'])-$theta)<=>abs(AdaptiveQcmModel::difficulty($b['rate'])-$theta);
                }
                return abs($a['rate']-(100-$run['cursor']))<=>abs($b['rate']-(100-$run['cursor']));
            });
            $question=$remaining[0];
            if($first&&(int)$run['scoring_version']<3){$run['cursor']=100-$question['rate'];$this->db->prepare('UPDATE runs SET cursor=? WHERE id=?')->execute([$run['cursor'],$id]);}
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
            if((int)$run['scoring_version']>=3)$state['grading']=[
                'method'=>'all_answers','answered'=>count(array_filter($state['review'],static fn($answer)=>$answer['result']!=='global_timeout')),
                'minimum'=>AdaptiveQcmModel::MIN_ANSWERS,'provisional'=>(bool)array_filter($pool,static fn($question)=>(int)($question['calibration']['n']??0)<20),
            ];
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
