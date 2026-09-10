<?php
declare(strict_types=1);
require_once __DIR__.'/Database.php';require_once __DIR__.'/Store.php';
function messaging_store(): \Liike\Messaging\Store
{
    static $store=null;return $store??=new \Liike\Messaging\Store(\Liike\Messaging\Database::connect(dirname(__DIR__,2)));
}
function messaging_key(array $user): string
{
    $instance=one('SELECT uuid FROM messaging_instance WHERE id=1')['uuid'];
    return $instance.':'.$user['messaging_uuid'];
}
function messaging_person(string $key): ?array
{
    $instance=one('SELECT uuid FROM messaging_instance WHERE id=1')['uuid'];
    if(!str_starts_with($key,$instance.':'))return null;
    return one('SELECT * FROM users WHERE messaging_uuid=?',[substr($key,33)]);
}
function messaging_course(string $scope): ?array
{
    $instance=one('SELECT uuid FROM messaging_instance WHERE id=1')['uuid'];
    return str_starts_with($scope,$instance.':')?one('SELECT * FROM courses WHERE messaging_uuid=?',[substr($scope,33)]):null;
}
function messaging_super(array $user): bool{return $user['role']==='teacher'&&(int)($user['is_superadmin']??0)===1;}
function messaging_teacher(array $course,int $id): bool
{
    return (int)$course['teacher_id']===$id||(bool)one('SELECT 1 FROM course_teachers WHERE course_id=? AND teacher_id=?',[$course['id'],$id]);
}
function messaging_authorize(array $user,array $thread,bool $write=false,bool $manage=false): bool
{
    if(($user['account_status']??'')!=='active')return false;
    $course=messaging_course($thread['scope_key']);$key=messaging_key($user);
    if($manage)return messaging_super($user)||($course&&$user['role']==='teacher'&&(int)$course['teacher_id']===(int)$user['id']);
    if(!in_array($key,array_column($thread['participants'],'user_key'),true))return false;
    if(!$course)return false;
    if($write&&(!(int)$course['messaging_enabled']||(int)$course['archived']))return false;
    if($user['role']==='teacher')return messaging_teacher($course,(int)$user['id']);
    return (bool)one("SELECT 1 FROM enrollments WHERE student_id=? AND course_id=?".($write?" AND status='active'":''),[$user['id'],$course['id']]);
}
function messaging_thread(array $user,string $id,bool $write=false,bool $manage=false): array
{
    $thread=messaging_store()->thread($id);
    if(!messaging_authorize($user,$thread,$write,$manage))throw new InvalidArgumentException('Accès interdit.');return $thread;
}
function messaging_open(array $user,int $courseId,int $otherId): string
{
    $course=one('SELECT * FROM courses WHERE id=?',[$courseId]);$other=one("SELECT * FROM users WHERE id=? AND account_status='active'",[$otherId]);
    if(!$course||!$other||!(int)$course['messaging_enabled']||(int)$course['archived'])throw new InvalidArgumentException('Les discussions sont désactivées pour ce parcours.');
    [$student,$teacher]=$user['role']==='student'?[$user,$other]:[$other,$user];
    if($student['role']!=='student'||$teacher['role']!=='teacher'||!messaging_teacher($course,(int)$teacher['id'])||!one("SELECT 1 FROM enrollments WHERE student_id=? AND course_id=? AND status='active'",[$student['id'],$courseId]))throw new InvalidArgumentException('Accès interdit.');
    $person=fn($u)=>['key'=>messaging_key($u),'name'=>$u['name']];
    return messaging_store()->open(messaging_key($course),$person($student),$person($teacher),$course['title']);
}
function messaging_threads(array $user,?string $person=null): array
{
    $store=messaging_store();$key=messaging_key($user);
    $rows=$store->query('SELECT c.* FROM conversations c JOIN participants p ON p.conversation_id=c.id WHERE p.user_key=? ORDER BY c.updated_at DESC,c.id',[$person??$key]);$result=[];
    foreach($rows as $row){
        $row['participants']=$store->query('SELECT user_key,name,last_read_id FROM participants WHERE conversation_id=?',[$row['id']]);
        if(!messaging_authorize($user,$row,false,$person!==null))continue;
        $row['last']=$store->query('SELECT body,created_at FROM messages WHERE conversation_id=? ORDER BY id DESC LIMIT 1',[$row['id']])[0]??null;
        $read=0;foreach($row['participants'] as $p)if($p['user_key']===$key)$read=(int)$p['last_read_id'];
        $row['unread']=(int)$store->query('SELECT COUNT(*) AS n FROM messages WHERE conversation_id=? AND author_key<>? AND id>?',[$row['id'],$key,$read])[0]['n'];
        $row['count']=(int)$store->query('SELECT COUNT(*) AS n FROM messages WHERE conversation_id=?',[$row['id']])[0]['n'];$result[]=$row;
    }return $result;
}
function messaging_unread(array $user): int
{
    if(($user['account_status']??'')!=='active')return 0;
    $courses=$user['role']==='teacher'?all('SELECT c.* FROM courses c WHERE c.teacher_id=? OR EXISTS(SELECT 1 FROM course_teachers ct WHERE ct.course_id=c.id AND ct.teacher_id=?)',[$user['id'],$user['id']]):all('SELECT c.* FROM courses c JOIN enrollments e ON e.course_id=c.id WHERE e.student_id=?',[$user['id']]);
    $scopes=array_map('messaging_key',$courses);if(!$scopes)return 0;$key=messaging_key($user);
    return (int)messaging_store()->query('SELECT COUNT(*) AS n FROM messages m JOIN conversations c ON c.id=m.conversation_id JOIN participants p ON p.conversation_id=c.id AND p.user_key=? WHERE m.author_key<>? AND m.id>p.last_read_id AND c.scope_key IN ('.implode(',',array_fill(0,count($scopes),'?')).')',[$key,$key,...$scopes])[0]['n'];
}
function messaging_requests(array $user): array
{
    $requests=messaging_store()->query("SELECT * FROM deletion_requests WHERE status='pending' ORDER BY requested_at");
    return array_values(array_filter($requests,fn($r)=>messaging_super($user)||count(messaging_threads($user,$r['user_key']))>0));
}
function messaging_erase(array $user,string $person,array $ids,?string $version=null): int
{
    if(!$ids)throw new InvalidArgumentException('Aucune discussion à effacer.');
    foreach($ids as $id){$thread=messaging_thread($user,(string)$id,false,true);if(!in_array($person,array_column($thread['participants'],'user_key'),true))throw new InvalidArgumentException('Accès interdit.');}
    return messaging_store()->erase($ids,$person,messaging_key($user),$version);
}
function messaging_timestamp(int $time): string{return (new DateTimeImmutable('@'.$time))->setTimezone(new DateTimeZone('Europe/Zurich'))->format('d/m/Y H:i:s');}
function messaging_export(array $user,array $ids): array
{
    $store=messaging_store();$store->db->beginTransaction();
    try{
        $threads=[];foreach($ids as $id){$row=messaging_thread($user,(string)$id,false,true);$row['messages']=$store->query('SELECT id,author_key,author_name,body,created_at,edited_at,revision FROM messages WHERE conversation_id=? ORDER BY id',[$id]);$threads[]=$row;}
        if(!$threads)throw new InvalidArgumentException('Discussion introuvable.');
        $store->db->commit();return ['exported_at'=>time(),'exported_by'=>$user['name'],'threads'=>$threads];
    }catch(Throwable $e){$store->db->rollBack();throw $e;}
}
function messaging_export_markdown(array $data): string
{
    $plain=fn($s)=>document_markdown_text((string)$s);$lines=['# '.t('Export de discussions'),'','**'.t('Exporté par').'** : '.$plain($data['exported_by']).'  ','**'.t('Date et heure').'** : '.messaging_timestamp($data['exported_at']),'',t('Version conservée des messages. Les versions antérieures aux modifications ne sont pas enregistrées.'),''];
    foreach($data['threads'] as $thread){
        $lines[]='## '.$plain($thread['scope_title']);$lines[]='';$lines[]='**'.t('Discussion').'** : '.$thread['id'].'  ';$lines[]='**'.t('Participants').'** : '.$plain(implode(' / ',array_column($thread['participants'],'name')));$lines[]='';
        foreach($thread['messages'] as $m){$lines[]='**'.$plain($m['author_name']).'** · '.messaging_timestamp((int)$m['created_at']).' · #'.$m['id'].'  ';if($m['edited_at'])$lines[]='*'.t('Modifié le :date',['date'=>messaging_timestamp((int)$m['edited_at'])]).'*  ';$lines[]=$plain($m['body']);$lines[]='';}
        $lines[]='---';$lines[]='';
    }
    $lines[]='SHA-256 : '.hash('sha256',json_encode($data['threads'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    return implode("\n",$lines)."\n";
}
