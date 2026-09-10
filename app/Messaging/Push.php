<?php
declare(strict_types=1);
require_once __DIR__.'/Lms.php';
function messaging_push_config(bool $create=false): ?array
{
    $path=dirname(__DIR__,2).'/storage/messaging-push.json';
    if(is_file($path))return json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    if(!$create)return null;
    require_once __DIR__.'/vendor/autoload.php';
    $lock=fopen($path.'.lock','c+');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Notifications indisponibles.');
    try{
        if(is_file($path))return json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        $keys=\Minishlink\WebPush\VAPID::createVapidKeys();
        $host=(string)($_SERVER['HTTP_HOST']??'localhost');if(!preg_match('/^[a-zA-Z0-9.:-]+$/D',$host))throw new RuntimeException('Adresse du site invalide.');
        $config=$keys+['subject'=>'https://'.$host.pwa_cookie_path()];
        $temporary=$path.'.'.bin2hex(random_bytes(5));file_put_contents($temporary,json_encode($config,JSON_THROW_ON_ERROR));chmod($temporary,0600);rename($temporary,$path);return $config;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function messaging_push_endpoint(string $endpoint): bool
{
    $url=parse_url($endpoint);if(!$url||($url['scheme']??'')!=='https'||isset($url['user'])||isset($url['pass'])||isset($url['fragment'])||isset($url['port'])&&$url['port']!==443||strlen($endpoint)>2048)return false;
    $host=strtolower($url['host']??'');
    if(!preg_match('/^[a-z0-9]+(?:[a-z0-9.-]*[a-z0-9])?$/D',$host))return false;
    // Browser providers: Google FCM, Mozilla Autopush, Apple APNs and Microsoft WNS.
    // Chromium also exposes the Google staging endpoint jmt17.google.com.
    // WNS assigns regional hosts beneath notify.windows.com.
    return in_array($host,['fcm.googleapis.com','jmt17.google.com','push.services.mozilla.com','updates.push.services.mozilla.com','web.push.apple.com'],true)
        ||str_ends_with($host,'.push.apple.com')||str_ends_with($host,'.notify.windows.com');
}
function messaging_push_key(mixed $value,int $bytes): string
{
    if(!is_string($value)||strlen($value)>4*(int)ceil($bytes/3)||!preg_match('~^[A-Za-z0-9_+/-]+={0,2}$~D',$value))throw new InvalidArgumentException('Clés de notification invalides. Réactivez les notifications dans les paramètres du navigateur.');
    $decoded=base64_decode(strtr($value,'-_','+/'),true);
    if($decoded===false||strlen($decoded)!==$bytes)throw new InvalidArgumentException('Clés de notification invalides. Réactivez les notifications dans les paramètres du navigateur.');
    return rtrim(strtr(base64_encode($decoded),'+/','-_'),'=');
}
function messaging_push_register(array $user,array $data): string
{
    $endpoint=$data['endpoint']??null;$keys=$data['keys']??null;
    if(!is_string($endpoint)||!is_array($keys))throw new InvalidArgumentException('Abonnement aux notifications invalide.');
    if(!messaging_push_endpoint($endpoint)){
        $host=parse_url($endpoint,PHP_URL_HOST);
        $host=is_string($host)&&preg_match('/^[a-zA-Z0-9.-]{1,253}$/D',$host)?$host:'?';
        throw new InvalidArgumentException(t('Service de notifications non pris en charge : :service.',['service'=>$host]));
    }
    // Accept padded/unpadded Base64 and Base64URL, then store the canonical URL-safe form.
    // The decoded Web Push key sizes remain mandatory.
    $key=messaging_push_key($keys['p256dh']??null,65);
    $auth=messaging_push_key($keys['auth']??null,16);
    $store=messaging_store();$id=bin2hex(random_bytes(24));
    $store->transaction(function()use($store,$endpoint,$user,$key,$auth,$id){
        $store->run('DELETE FROM push_subscriptions WHERE endpoint=?',[$endpoint]);
        $store->run('INSERT INTO push_subscriptions VALUES(?,?,?,?,?,?)',[$id,messaging_key($user),$endpoint,$key,$auth,time()]);
    });
    setcookie('liike_push_'.substr(hash('sha256',pwa_cookie_path()),0,12),$id,['expires'=>time()+90*86400,'path'=>pwa_cookie_path(),'secure'=>!in_array($_SERVER['SERVER_NAME']??'',['localhost','127.0.0.1','::1'],true),'httponly'=>true,'samesite'=>'Lax']);
    return $id;
}
function messaging_push_subscription(array $user,string $endpoint): ?string
{
    $id=$_COOKIE['liike_push_'.substr(hash('sha256',pwa_cookie_path()),0,12)]??'';
    if(!is_string($id)||!preg_match('/^[a-f0-9]{48}$/D',$id)||!messaging_push_endpoint($endpoint))return null;
    $rows=messaging_store()->query('SELECT id FROM push_subscriptions WHERE id=? AND user_key=? AND endpoint=? AND created_at>=?',[$id,messaging_key($user),$endpoint,time()-90*86400]);
    return $rows?(string)$rows[0]['id']:null;
}
function messaging_push_forget(?array $user=null): void
{
    $name='liike_push_'.substr(hash('sha256',pwa_cookie_path()),0,12);$id=$_COOKIE[$name]??'';
    if(is_string($id)&&preg_match('/^[a-f0-9]{48}$/D',$id))messaging_store()->run('DELETE FROM push_subscriptions WHERE id=?'.($user?' AND user_key=?':''),$user?[$id,messaging_key($user)]:[$id]);
    setcookie($name,'',['expires'=>time()-3600,'path'=>pwa_cookie_path(),'httponly'=>true,'samesite'=>'Lax','secure'=>!in_array($_SERVER['SERVER_NAME']??'',['localhost','127.0.0.1','::1'],true)]);
}
function messaging_push_batch(int $limit=10,?callable $transport=null): array
{
    $config=messaging_push_config();if(!$config)return ['sent'=>0,'failed'=>0];
    $lock=fopen(dirname(__DIR__,2).'/storage/messaging-push-worker.lock','c+');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))return ['sent'=>0,'failed'=>0];
    $store=messaging_store();$result=['sent'=>0,'failed'=>0];
    try{
        $store->run('DELETE FROM push_subscriptions WHERE created_at<?',[time()-90*86400]);
        foreach($store->query('SELECT user_key,MIN(created_at) AS since FROM push_subscriptions GROUP BY user_key') as $sub){
            $user=messaging_person($sub['user_key']);if(!$user||$user['account_status']!=='active'){$store->run('DELETE FROM push_subscriptions WHERE user_key=?',[$sub['user_key']]);continue;}
            foreach(unread_announcements_for_user(db(),(int)$user['id']) as $a){if(strtotime($a['created_at'].' UTC')<max(time()-7*86400,(int)$sub['since']))continue;$store->enqueue($sub['user_key'],'announcement:'.$a['id'],'announcement',(string)$a['id']);}
        }
        if(!$transport){
            require_once __DIR__.'/vendor/autoload.php';
            $webPush=new \Minishlink\WebPush\WebPush(['VAPID'=>$config],['TTL'=>3600],5,['allow_redirects'=>false,'connect_timeout'=>3]);
            $transport=static function(array $subscription,array $payload)use($webPush): array {
                $s=\Minishlink\WebPush\Subscription::create(['endpoint'=>$subscription['endpoint'],'publicKey'=>$subscription['public_key'],'authToken'=>$subscription['auth_token'],'contentEncoding'=>'aes128gcm']);
                $report=$webPush->sendOneNotification($s,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));return ['ok'=>$report->isSuccess(),'expired'=>$report->isSubscriptionExpired()];
            };
        }
        $rows=$store->query("SELECT d.*,s.*,e.kind,e.reference,e.user_key FROM push_deliveries d JOIN push_subscriptions s ON s.id=d.subscription_id JOIN push_events e ON e.id=d.event_id WHERE d.state='pending' AND d.available_at<=? ORDER BY d.event_id LIMIT ?",[time(),$limit]);
        $deadline=microtime(true)+10;
        foreach($rows as $row){
            if(microtime(true)>$deadline)break;
            $user=messaging_person($row['user_key']);$valid=$user&&$user['account_status']==='active';$announcements=$valid?unread_announcements_for_user(db(),(int)$user['id']):[];
            if($valid&&$row['kind']==='discussion'){
                try{$thread=messaging_thread($user,$row['reference']);$member=$store->member($thread['id'],$row['user_key']);$valid=(bool)$store->query('SELECT 1 FROM messages WHERE conversation_id=? AND author_key<>? AND id>? LIMIT 1',[$thread['id'],$row['user_key'],$member['last_read_id']]);}catch(Throwable){$valid=false;}
            }elseif($valid){$valid=in_array((int)$row['reference'],array_map('intval',array_column($announcements,'id')),true);}
            if(!$valid){$store->run("UPDATE push_deliveries SET state='skipped' WHERE event_id=? AND subscription_id=?",[$row['event_id'],$row['subscription_id']]);continue;}
            $language=$user['language']??'fr';
            $payload=['title'=>'liike','body'=>t($row['kind']==='discussion'?'Nouveau message':'Nouvelle annonce',[],$language),'count'=>count($announcements)+messaging_unread($user),'subscription'=>$row['subscription_id'],'url'=>$row['kind']==='discussion'?'?view=discussions&thread='.$row['reference']:'?view='.($user['role']==='teacher'?'pathway':'announcements').'&announcement='.$row['reference'].'&course='.(int)(array_values(array_filter($announcements,fn($a)=>(int)$a['id']===(int)$row['reference']))[0]['course_id']??0)];
            // No identity or message content is copied into the push payload or technical queue.
            try{$report=$transport($row,$payload);}catch(Throwable){$report=['ok'=>false,'expired'=>false];}
            if($report['expired']??false){$store->run('DELETE FROM push_subscriptions WHERE id=?',[$row['subscription_id']]);continue;}
            $attempt=(int)$row['attempts']+1;$ok=(bool)($report['ok']??false);$state=$ok?'sent':($attempt>=5?'failed':'pending');
            $store->run('UPDATE push_deliveries SET state=?,attempts=?,available_at=? WHERE event_id=? AND subscription_id=?',[$state,$attempt,time()+min(3600,30*(2**$attempt)),$row['event_id'],$row['subscription_id']]);$result[$ok?'sent':'failed']++;
        }
        $store->run('DELETE FROM push_events WHERE created_at<?',[time()-7*86400]);return $result;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
