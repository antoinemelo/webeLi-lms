<?php
declare(strict_types=1);
namespace Liike\Messaging;
final class Store
{
    public const EDIT_SECONDS=180;
    public const MAX_LENGTH=256;
    public function __construct(public readonly \PDO $db){}
    public function query(string $sql,array $args=[]): array {$q=$this->db->prepare($sql);$q->execute($args);return $q->fetchAll();}
    public function run(string $sql,array $args=[]): int {$q=$this->db->prepare($sql);$q->execute($args);return $q->rowCount();}
    public function transaction(callable $work): mixed
    {
        $this->db->exec('BEGIN IMMEDIATE');try{$result=$work();$this->db->exec('COMMIT');return $result;}catch(\Throwable $e){$this->db->exec('ROLLBACK');throw $e;}
    }
    public function open(string $scope,array $student,array $teacher,string $title): string
    {
        return $this->transaction(function()use($scope,$student,$teacher,$title){
            $id=bin2hex(random_bytes(16));$now=time();
            $this->run('INSERT OR IGNORE INTO conversations VALUES(?,?,?,?,?,?,?)',[$id,$scope,$student['key'],$teacher['key'],$title,$now,$now]);
            $id=$this->query('SELECT id FROM conversations WHERE scope_key=? AND student_key=? AND teacher_key=?',[$scope,$student['key'],$teacher['key']])[0]['id'];
            foreach([$student,$teacher] as $person)$this->run('INSERT INTO participants(conversation_id,user_key,name) VALUES(?,?,?) ON CONFLICT(conversation_id,user_key) DO UPDATE SET name=excluded.name',[$id,$person['key'],$person['name']]);
            return $id;
        });
    }
    public function thread(string $id): array
    {
        $row=$this->query('SELECT * FROM conversations WHERE id=?',[$id])[0]??null;
        if(!$row)throw new \InvalidArgumentException('Discussion introuvable.');
        $row['participants']=$this->query('SELECT user_key,name,last_read_id FROM participants WHERE conversation_id=?',[$id]);return $row;
    }
    public function member(string $thread,string $key): array
    {
        $row=$this->query('SELECT * FROM participants WHERE conversation_id=? AND user_key=?',[$thread,$key])[0]??null;
        if(!$row)throw new \InvalidArgumentException('Accès interdit.');return $row;
    }
    public static function body(string $body): string
    {
        $body=trim(str_replace(["\r\n","\r"],"\n",$body));
        if(!mb_check_encoding($body,'UTF-8')||$body===''||mb_strlen($body,'UTF-8')>self::MAX_LENGTH||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$body))throw new \InvalidArgumentException('Le message doit contenir entre 1 et 256 caractères.');
        return $body;
    }
    public function send(string $thread,string $key,string $name,string $body,string $request): int
    {
        $body=self::body($body);if(!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$request))throw new \InvalidArgumentException('Requête invalide.');
        return $this->transaction(function()use($thread,$key,$name,$body,$request){
            $this->member($thread,$key);
            $old=$this->query('SELECT id,body FROM messages WHERE conversation_id=? AND author_key=? AND request_key=?',[$thread,$key,$request])[0]??null;
            if($old){if($old['body']!==$body)throw new \InvalidArgumentException('Requête déjà utilisée.');return (int)$old['id'];}
            if(count($this->query('SELECT id FROM messages WHERE author_key=? AND created_at>? LIMIT 21',[$key,time()-60]))>=20)throw new \InvalidArgumentException('Veuillez patienter avant d’envoyer un autre message.');
            $this->run('INSERT INTO messages(conversation_id,author_key,author_name,body,created_at,request_key) VALUES(?,?,?,?,?,?)',[$thread,$key,$name,$body,time(),$request]);
            $id=(int)$this->db->lastInsertId();$this->run('UPDATE conversations SET updated_at=? WHERE id=?',[time(),$thread]);
            foreach($this->query('SELECT user_key FROM participants WHERE conversation_id=? AND user_key<>?',[$thread,$key]) as $recipient)$this->enqueue($recipient['user_key'],'message:'.$id,'discussion',$thread);
            return $id;
        });
    }
    public function edit(string $thread,int $id,string $key,string $body,int $revision): void
    {
        $body=self::body($body);
        $this->transaction(function()use($thread,$id,$key,$body,$revision){
            $this->member($thread,$key);
            $changed=$this->run('UPDATE messages SET body=?,edited_at=?,revision=revision+1 WHERE id=? AND conversation_id=? AND author_key=? AND revision=? AND created_at>?',[$body,time(),$id,$thread,$key,$revision,time()-self::EDIT_SECONDS]);
            if(!$changed)throw new \InvalidArgumentException('Modification impossible : délai de 3 minutes dépassé ou message déjà modifié.');
        });
    }
    public function messages(string $thread,int $before=0): array
    {
        return array_reverse($this->query('SELECT * FROM messages WHERE conversation_id=?'.($before>0?' AND id<?':'').' ORDER BY id DESC LIMIT 50',$before>0?[$thread,$before]:[$thread]));
    }
    public function read(string $thread,string $key,int $through): void
    {
        $this->member($thread,$key);
        $max=(int)($this->query('SELECT MAX(id) AS n FROM messages WHERE conversation_id=?',[$thread])[0]['n']??0);
        $this->run('UPDATE participants SET last_read_id=MAX(last_read_id,?) WHERE conversation_id=? AND user_key=?',[max(0,min($through,$max)),$thread,$key]);
    }
    public function requestDeletion(string $key,string $name): int
    {
        if(!$this->query('SELECT 1 FROM participants WHERE user_key=? LIMIT 1',[$key]))throw new \InvalidArgumentException('Aucune discussion à effacer.');
        $this->run("INSERT OR IGNORE INTO deletion_requests(user_key,name,requested_at) VALUES(?,?,?)",[$key,$name,time()]);
        return (int)$this->query("SELECT id FROM deletion_requests WHERE user_key=? AND status='pending'",[$key])[0]['id'];
    }
    /** The adapter authorizes the exact list; all other conversations are untouched. */
    public function erase(array $ids,string $key,string $responsible,?string $version=null): int
    {
        return $this->transaction(function()use($ids,$key,$responsible,$version){
            if($version!==null&&!hash_equals($this->eraseVersion($ids),$version))throw new \InvalidArgumentException('Les discussions ont changé. Rechargez la page avant de confirmer leur effacement.');
            $count=0;$affected=[$key];
            foreach($ids as $id){
                $this->member($id,$key);
                $affected=array_merge($affected,array_column($this->query('SELECT user_key FROM participants WHERE conversation_id=?',[$id]),'user_key'));
                $this->run("DELETE FROM push_events WHERE kind='discussion' AND reference=?",[$id]);
                $count+=$this->run('DELETE FROM conversations WHERE id=?',[$id]);
            }
            foreach(array_unique($affected) as $person)if(!$this->query('SELECT 1 FROM participants WHERE user_key=? LIMIT 1',[$person]))$this->run("UPDATE deletion_requests SET status='approved',resolved_at=?,resolved_by=? WHERE user_key=? AND status='pending'",[time(),$responsible,$person]);
            return $count;
        });
    }
    public function eraseVersion(array $ids): string
    {
        sort($ids);$rows=[];
        foreach($ids as $id)$rows[]=[$id,$this->query('SELECT COUNT(*) AS n,MAX(id) AS last,SUM(revision) AS edits FROM messages WHERE conversation_id=?',[$id])[0]];
        return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
    }
    public function enqueue(string $key,string $event,string $kind,string $reference): void
    {
        $this->run('INSERT OR IGNORE INTO push_events(user_key,event_key,kind,reference,created_at) VALUES(?,?,?,?,?)',[$key,$event,$kind,$reference,time()]);
        $id=$this->query('SELECT id FROM push_events WHERE user_key=? AND event_key=?',[$key,$event])[0]['id'];
        $this->run('INSERT OR IGNORE INTO push_deliveries(event_id,subscription_id) SELECT ?,id FROM push_subscriptions WHERE user_key=?',[$id,$key]);
    }
}
