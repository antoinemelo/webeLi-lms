<?php
declare(strict_types=1);
require_once __DIR__.'/AdaptiveQcmPreview.php';

/** Bounded, anonymous practice in the PHP session. No durable attempts or calibration writes. */
final class AdaptiveQcmExternal
{
    private const MAX_SESSIONS=3;
    private const RESULT_SECONDS=3600;

    public static function respond(PDO $core,string $token,array $input,array &$sessions,string $base,?float $now=null): array
    {
        if(!AdaptiveQcm::enabled())throw new DomainException('Le module QCM adaptatif est désactivé.',503);
        $liveClock=$now===null;$now??=microtime(true);
        foreach($sessions as $key=>$entry)if(($entry['expires_at']??0)<$now)unset($sessions[$key]);
        try{
            $share=library_share_resolve($core,$token);library_share_require_mode($share,'external');
            $entry=$sessions[$token]??null;
            foreach($entry['sources']??[] as $sourceToken=>$sourceId){
                $source=library_share_resolve($core,$sourceToken);
                library_share_require_mode($source,'shared');library_share_require_mode($source,'external');
                if((int)$source['page_id']!==(int)$sourceId)throw new InvalidArgumentException('Réservoir partagé indisponible.');
            }
        }catch(InvalidArgumentException $error){unset($sessions[$token]);throw new DomainException($error->getMessage(),404);}
        $operation=$input['operation']??'state';
        if(!in_array($operation,['state','start','answer'],true))throw new InvalidArgumentException('Commande invalide.');
        if($operation==='answer'){
            if(!$entry||!is_string($input['run_id']??null)||!hash_equals($entry['run_id'],$input['run_id']))throw new InvalidArgumentException('Cette réponse appartient à une autre tentative.');
            if(!is_array($input['answers']??[])||count($input['answers']??[])>1000)throw new InvalidArgumentException('Réponse invalide.');
        }
        if(!$entry&&$operation==='state')return ['ready'=>true,'external'=>true];
        if(isset($entry['state'])&&$operation!=='start')return array_replace($entry['state'],['now'=>$now]);

        $engine=new AdaptiveQcmPreview();$pageId=(int)$share['page_id'];
        if($entry&&!isset($entry['state'])){
            $run=$engine->restore($entry['snapshot'],0,$pageId);
        }else{
            if(!isset($sessions[$token])&&count($sessions)>=self::MAX_SESSIONS){
                foreach($sessions as $key=>$candidate)if(isset($candidate['state'])){unset($sessions[$key]);break;}
                if(count($sessions)>=self::MAX_SESSIONS)throw new DomainException('Terminez une activité en cours avant d’en commencer une autre.',429);
            }
            $bank=AdaptiveQcm::bank($core,$pageId,$base,true);
            $item=['id'=>$pageId,'page_id'=>$pageId,'is_evaluation'=>0,'is_controlled_exercise'=>0,'estimated_minutes'=>$share['estimated_minutes']];
            $run=$engine->start($item,0,$bank,AdaptiveQcm::readOnlyStore($core),$liveClock?null:$now);
        }
        $run=$engine->store->advance($run['id'],0,$now,$operation==='answer'?(int)($input['ordinal']??0):null,
            $operation==='answer'?($input['answers']??[]):null,$liveClock?static fn():float=>microtime(true):null);
        $state=$engine->store->publicState($run,$liveClock?microtime(true):$now)+['external'=>true];
        $entry=['run_id'=>$run['id'],'sources'=>json_decode($run['sources'],true,512,JSON_THROW_ON_ERROR),
            'expires_at'=>($run['finished_at']??$run['ends_at'])+self::RESULT_SECONDS];
        if($state['finished']){
            // Keep the temporary score only: no question bank, selections or correction in the session.
            $entry['state']=$state;$entry['state']['review']=[];
        }else $entry['snapshot']=$engine->snapshot($run);
        $sessions[$token]=$entry;
        return $state;
    }
}

function adaptive_qcm_external_http(): never
{
    header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);header('Allow: POST');echo '{}';exit;}
    if(empty($_SESSION['csrf'])||!is_string($_POST['token']??null)||!hash_equals((string)$_SESSION['csrf'],$_POST['token'])){
        http_response_code(403);echo json_encode(['error'=>t('Formulaire expiré. Rechargez la page.')]);exit;
    }
    try{
        $token=is_string($_POST['share']??null)?$_POST['share']:'';
        $_SESSION['adaptive_qcm_external']??=[];
        $state=AdaptiveQcmExternal::respond(db(),$token,$_POST,$_SESSION['adaptive_qcm_external'],app_public_base_url());
        session_write_close();echo json_encode($state,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
    }catch(InvalidArgumentException|DomainException $error){
        http_response_code($error instanceof DomainException?$error->getCode():400);echo json_encode(['error'=>t($error->getMessage())]);
    }catch(Throwable $error){error_log('liike external adaptive: '.$error->getMessage());http_response_code(503);echo json_encode(['error'=>t('Activité indisponible. Réessayez.')]);}
    exit;
}
