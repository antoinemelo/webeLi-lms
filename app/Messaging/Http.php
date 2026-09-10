<?php
declare(strict_types=1);
require_once __DIR__.'/Lms.php';require_once __DIR__.'/Push.php';require_once __DIR__.'/Views.php';
function messaging_json(array $payload,int $status=200): never {http_response_code($status);header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store, private');echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
function messaging_http_action(string $action): never
{
    $user=require_actor();$key=messaging_key($user);$json=str_contains($_SERVER['HTTP_ACCEPT']??'','application/json');
    try{
        if($action==='messaging_open')redirect('discussions',['thread'=>messaging_open($user,(int)($_POST['course_id']??0),(int)($_POST['recipient_id']??0))]);
        if($action==='messaging_send'||$action==='messaging_edit'){
            $id=(string)($_POST['thread']??'');messaging_thread($user,$id,true);
            if($action==='messaging_send')messaging_store()->send($id,$key,$user['name'],(string)($_POST['body']??''),(string)($_POST['request_key']??''));
            else messaging_store()->edit($id,(int)($_POST['message_id']??0),$key,(string)($_POST['body']??''),(int)($_POST['revision']??-1));
            if($json)messaging_json(['ok'=>true,'csrf'=>csrf_token()]);redirect('discussions',['thread'=>$id]);
        }
        if($action==='messaging_read'){
            $id=(string)($_POST['thread']??'');messaging_thread($user,$id);messaging_store()->read($id,$key,(int)($_POST['through']??0));messaging_json(['ok'=>true]);
        }
        if($action==='messaging_request_delete'){messaging_store()->requestDeletion($key,$user['name']);flash('Votre demande est en attente.');redirect('discussions');}
        if($action==='messaging_erase'){
            $ids=array_values(array_unique(array_map('strval',(array)($_POST['threads']??[]))));
            messaging_erase($user,(string)($_POST['person']??''),$ids,(string)($_POST['erase_version']??''));flash('Les discussions sélectionnées ont été effacées.');redirect('discussions');
        }
        if($action==='messaging_push_prepare'){messaging_json(['publicKey'=>messaging_push_config(true)['publicKey']]);}
        if($action==='messaging_push_subscribe'){$data=json_decode((string)($_POST['subscription']??''),true,16,JSON_THROW_ON_ERROR);$id=messaging_push_register($user,(array)$data);messaging_json(['ok'=>true,'subscription'=>$id]);}
        if($action==='messaging_push_disable'){messaging_push_forget();messaging_json(['ok'=>true]);}
        throw new InvalidArgumentException('Requête invalide.');
    }catch(Throwable $e){if($json)messaging_json(['ok'=>false,'error'=>t($e instanceof InvalidArgumentException?$e->getMessage():'Impossible de terminer cette opération. Réessayez.')],422);flash($e instanceof InvalidArgumentException?$e->getMessage():'Impossible de terminer cette opération. Réessayez.','error');redirect('discussions');}
}
function messaging_http_get(string $view): never
{
    $user=actor();if(!$user)messaging_json(['authenticated'=>false],401);
    try{
        if($view==='notification-status'){$unread=messaging_unread($user);messaging_json(['count'=>$unread+count(unread_announcements_for_user(db(),(int)$user['id'])),'messages'=>$unread]);}
        if($view==='discussion-data'){
            $id=(string)($_GET['thread']??'');$managed=($_GET['managed']??'')==='1';$thread=messaging_thread($user,$id,false,$managed);$messages=messaging_store()->messages($id,(int)($_GET['before']??0));
            foreach($messages as &$m){$m['html']=messaging_body_html($m['body']);$m['editable']=!$managed&&messaging_authorize($user,$thread,true)&&$m['author_key']===messaging_key($user)&&(int)$m['created_at']>time()-180;$m['date']=messaging_timestamp((int)$m['created_at']);}unset($m);
            messaging_json(['messages'=>$messages,'can_write'=>!$managed&&messaging_authorize($user,$thread,true),'has_older'=>count($messages)===50,'server_time'=>time()]);
        }
        if($view==='discussion-export'){
            $ids=isset($_GET['thread'])?[(string)$_GET['thread']]:array_column(messaging_threads($user,(string)($_GET['person']??'')),'id');
            $data=messaging_export($user,$ids);$markdown=messaging_export_markdown($data);$name='discussions-'.gmdate('Ymd-His');
            if(($_GET['format']??'')==='pdf')send_pdf_download(pdf_document(t('Export de discussions'),Markdown::render($markdown)),$name.'.pdf');
            if(($_GET['format']??'')==='json'){header('Cache-Control: private, no-store');header('Content-Type: application/json; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$name.'.json"');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);exit;}
            send_markdown_download($markdown,$name.'.md');
        }
        messaging_json(['error'=>t('Requête invalide.')],400);
    }catch(Throwable $e){messaging_json(['error'=>t($e instanceof InvalidArgumentException?$e->getMessage():'Impossible de terminer cette opération. Réessayez.')],403);}
}
