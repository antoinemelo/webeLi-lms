<?php
declare(strict_types=1);
require_once __DIR__.'/AdaptiveQcmPreview.php';

function adaptive_qcm_present(array $blocks): bool
{
    foreach($blocks as $block)if($block['type']==='markdown'&&AdaptiveQcm::has($block['body']))return true;
    return false;
}

/** Refresh calculated author metadata on opening the editor; never update statistics from text. */
function adaptive_qcm_author_source(PDO $core,array $block): string
{
    $source=(string)$block['body'];$store=AdaptiveQcm::store($core,false);
    if(!$store||empty($block['page_id'])||empty($block['id']))return $source;
    $q=$store->db->prepare('SELECT id,presentations,correct,timed_answers,time_sum FROM questions WHERE page_id=? AND block_id=?');
    $q->execute([$block['page_id'],$block['id']]);$stats=array_column($q->fetchAll(),null,'id');
    return preg_replace_callback('/(^[\t ]*:::\s*qcm_(?:adap|adaptatif)(?:\[[^\]\r\n]+\]\[[^\]\r\n]+\])?[\t ]*\R)(.*?)(\R[\t ]*:::[\t ]*(?=\R|$))/msu',static function($match)use($block,$stats){
        $section=Qcm::sections($match[0])[0]??[];
        if(empty($section['valid']))return $match[0];
        $index=0;
        $body=preg_replace_callback('/^[\t ]*\[[0-3]\]\[[0-9]+\]\[[-0-9.,]+\]\[[0-9]+\]\[[0-9]+\][\t ]*$/m',static function($line)use(&$index,$section,$block,$stats){
            $question=$section['questions'][$index++];unset($question['metadata']);
            $question['domain']=$section['domain']??'';$question['theme']=$section['theme']??'';
            $id=hash('sha256',$block['page_id']."\0".$block['id']."\0".json_encode($question,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
            $s=$stats[$id]??['presentations'=>0,'correct'=>0,'timed_answers'=>0,'time_sum'=>0];
            $mean=$s['timed_answers']?number_format($s['time_sum']/$s['timed_answers'],1,'.',''):'-';
            return '['.$question['level'].']['.$question['seconds'].']['.$mean.']['.$s['presentations'].']['.$s['correct'].']';
        },$match[2]);
        return $match[1].$body.$match[3];
    },$source)??$source;
}

function render_adaptive_qcm(array $item,array $blocks,bool $preview=false): void
{
    if(!adaptive_qcm_present($blocks))return;
    if(!AdaptiveQcm::enabled()){echo '<p class="alert alert-secondary">'.e(t('Le module QCM adaptatif est désactivé.')).'</p>';return;}
    $labels=[];
    foreach(['start'=>'Commencer','resume'=>'Reprendre','retry'=>'Nouvel entraînement','send'=>'Valider ma réponse','pending'=>'Chargement…','error'=>'Connexion interrompue. Réessayez ; le temps continue de s’écouler.',
        'reload'=>'Réessayer','correct'=>'Réponse juste','incorrect'=>'Réponse incorrecte','timeout'=>'Temps écoulé pour cette question','done'=>'Activité terminée','time'=>'Le temps total est écoulé.',
        'empty'=>'Toutes les questions disponibles ont été présentées.','hardest'=>'La question la plus difficile a été réussie : 10/10.',
        'easiest'=>'La question la plus facile n’a pas été réussie et aucune question aussi facile ne reste disponible.',
        'difficulty'=>'Score de difficulté','scale'=>'Bornes de difficulté','initial'=>'Barème initial',
        'score'=>'Résultat','review'=>'Correction','single'=>'Une seule réponse possible.','multiple'=>'Plusieurs réponses possibles.',
        'question'=>'Question','total'=>'Temps total restant','remaining'=>'Temps de la question','ready'=>'Le chronomètre démarre lorsque vous commencez.','expected'=>'Réponse attendue'] as $key=>$label)$labels[$key]=t($label);
    if($preview){$labels['start']=t('Commencer le test');$labels['retry']=t('Recommencer le test');}
    ?><section class="adaptive-qcm" data-adaptive-qcm data-item="<?=(int)$item['id']?>" data-preview="<?=$preview?'1':'0'?>" data-labels="<?=e(json_encode($labels,JSON_UNESCAPED_UNICODE))?>">
      <h2><?=e(t('QCM adaptatif'))?></h2>
      <p><?=e(t($preview?($item['is_evaluation']?'Mode évaluation':'Mode entraînement'):($item['is_evaluation']?'Évaluation : une tentative.':'Entraînement : vous pouvez recommencer.')))?> · <?=(int)$item['estimated_minutes']?> min</p>
      <?php if($preview): ?><p><?=e(t('Test enseignant : vous pouvez recommencer librement. Vos essais ne modifient ni les résultats des apprenants ni les statistiques des questions.'))?></p><button type="button" class="button secondary" data-adaptive-restart hidden><?=e(t('Recommencer le test'))?></button><?php endif; ?>
      <div data-adaptive-content><p><?=e(t('Chargement…'))?></p></div><p data-adaptive-error role="alert" hidden></p><noscript><?=e(t('Activez JavaScript pour réaliser cette activité chronométrée.'))?></noscript>
    </section><?php
}

function render_adaptive_qcm_editor(int $pageId,array $blocks): void
{
    if(!AdaptiveQcm::enabled())return;
    $present=adaptive_qcm_present($blocks);
    ?><section class="adaptive-authoring">
    <h3 class="h5"><?=e(t('QCM adaptatif : réservoir et réglages'))?></h3>
    <p><?=e(t('La durée estimée du contenu fixe le temps total. Le niveau initial sert à choisir la première question.'))?></p>
    <?php if($pageId): $store=AdaptiveQcm::store(db(),false); ?>
      <form method="post" class="adaptive-settings" data-error="<?=e(t('Enregistrement impossible. Réessayez.'))?>"><?=csrf_field()?><input type="hidden" name="action" value="adaptive_qcm_settings"><input type="hidden" name="page_id" value="<?=$pageId?>"><input type="hidden" name="course_id" value="<?=(int)($_GET['course']??0)?>">
        <label><?=e(t('Niveau initial'))?><select name="initial_level"><?php for($level=0;$level<=3;$level++): ?><option value="<?=$level?>" <?=($store?->level($pageId)??1)===$level?'selected':''?>><?=$level?></option><?php endfor; ?></select></label>
        <button class="button secondary"><?=e(t('Enregistrer'))?></button><span role="status" data-adaptive-settings-status></span>
      </form>
      <?php if($present): try{
        $bank=AdaptiveQcm::bank(db(),$pageId,app_public_base_url());
        $questions=$store?$store->statistics($bank['questions']):array_map(static fn($question)=>$question+[
            'stats'=>['presentations'=>0,'correct'=>0,'timed_answers'=>0,'time_sum'=>0],'rate'=>AdaptiveQcm::INITIAL_RATES[$question['level']],
        ],$bank['questions']);
        ?><h4 class="h6"><?=e(t('Questions enregistrées'))?> · <?=count($questions)?></h4>
        <p><?=e(t('Les statistiques proviennent uniquement des évaluations : temps moyen, présentations et réponses justes. Enregistrez les modifications du contenu pour actualiser ce réservoir.'))?></p>
        <div class="adaptive-statistics"><?php foreach($questions as $question): $s=$question['stats'];$mean=$s['timed_answers']?number_format($s['time_sum']/$s['timed_answers'],1,'.',''):'-'; ?>
          <p><strong><?=e($question['title'])?></strong><br><code><?=e('['.$question['level'].']['.$question['seconds'].']['.$mean.']['.$s['presentations'].']['.$s['correct'].']')?></code> · <?=e(number_format($question['rate'],1,',',''))?> % <?=e(t($s['presentations']?'de réussite':'provisoire'))?></p>
        <?php endforeach; ?></div><?php
      }catch(InvalidArgumentException $error){?><p class="alert alert-warning"><?=e(t($error->getMessage()))?></p><?php }
      else: ?><p><?=e(t('Ajoutez des questions adaptatives ou un bloc QCM tiers, puis enregistrez le contenu pour voir le réservoir.'))?></p><?php endif;
    else: ?><p><?=e(t('Enregistrez d’abord cette nouvelle page pour accéder aux réglages et au réservoir.'))?></p><?php endif; ?>
    </section><?php
}

function adaptive_qcm_settings(): never
{
    verify_csrf();$user=require_actor();$pageId=(int)($_POST['page_id']??0);
    $json=str_contains($_SERVER['HTTP_ACCEPT']??'','application/json');
    if($json){header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store, private');}
    if(!AdaptiveQcm::enabled()||$user['role']!=='teacher'||!teacher_can_access_page(db(),$pageId,(int)$user['id'])){http_response_code(403);if($json)echo json_encode(['error'=>t('Accès interdit.')]);exit;}
    try{
        $raw=$_POST['initial_level']??'';if(!is_string($raw)||!preg_match('/^[0-3]$/D',$raw))throw new InvalidArgumentException('Niveau initial invalide.');
        AdaptiveQcm::store(db())->setLevel($pageId,(int)$raw);
        if($json){echo json_encode(['message'=>t('Réglage du QCM adaptatif enregistré.')]);exit;}
        flash(t('Réglage du QCM adaptatif enregistré.'));
    }catch(InvalidArgumentException $error){
        if($json){http_response_code(400);echo json_encode(['error'=>t($error->getMessage())]);exit;}
        flash(t($error->getMessage()),'error');
    }
    redirect('page-edit',['id'=>$pageId,'course'=>(int)($_POST['course_id']??0)]);
}

/** Teacher-only simulation, with the same engine and no persistent attempts or statistic writes. */
function adaptive_qcm_preview_http(array $user): never
{
    try{
        $itemId=(int)($_POST['item_id']??0);$teacherId=(int)$user['id'];
        $q=db()->prepare('SELECT pi.*,p.estimated_minutes FROM pathway_items pi JOIN pages p ON p.id=pi.page_id WHERE pi.id=?');
        $q->execute([$itemId]);$item=$q->fetch(PDO::FETCH_ASSOC);
        if(!$item||$item['access_mode']==='none'||!teacher_can_access_course(db(),(int)$item['course_id'],$teacherId)){
            http_response_code(403);echo json_encode(['error'=>t('Cette activité n’est pas accessible.')]);exit;
        }
        $operation=$_POST['operation']??'state';
        if(!in_array($operation,['state','start','answer','restart'],true))throw new InvalidArgumentException('Commande invalide.');
        $snapshot=$_SESSION['adaptive_qcm_preview']??null;$run=$snapshot['run']??null;
        if($run&&((int)$run['student_id']!==$teacherId||(int)$run['item_id']!==$itemId)){$snapshot=null;$run=null;}
        $restartFrom=$snapshot['restart_from']??null;
        $restart=false;
        if($operation==='restart'){
            $requested=$_POST['run_id']??null;
            if(!$run||!is_string($requested))throw new InvalidArgumentException('Commencez cette activité avant de recommencer.');
            $restart=hash_equals($run['id'],$requested);
            // Retrying a restart after a lost response returns the already-created test.
            if(!$restart&&$restartFrom!==$requested)throw new InvalidArgumentException('Cette commande appartient à un autre test.');
            if($restart)$restartFrom=$run['id'];
        }
        $preview=new AdaptiveQcmPreview();
        if($restart||($operation==='start'&&(!$run||$run['finished_at']!==null))){
            $bank=AdaptiveQcm::bank(db(),(int)$item['page_id'],app_public_base_url());
            $run=$preview->start($item,$teacherId,$bank,AdaptiveQcm::store(db(),false));
        }elseif($run){$run=$preview->restore($snapshot,$teacherId,$itemId);}
        else{
            if($operation==='answer')throw new InvalidArgumentException('Commencez cette activité avant de répondre.');
            $bank=AdaptiveQcm::bank(db(),(int)$item['page_id'],app_public_base_url());
            session_write_close();echo json_encode(['ready'=>true,'preview'=>true,'count'=>count($bank['questions'])]);exit;
        }
        foreach(json_decode($run['sources'],true,512,JSON_THROW_ON_ERROR) as $token=>$sourceId){
            $share=library_share_resolve(db(),$token);library_share_require_mode($share,'shared');
            if((int)$share['page_id']!==(int)$sourceId)throw new InvalidArgumentException('Réservoir partagé indisponible.');
        }
        $ordinal=null;$selected=null;
        if($operation==='answer'){
            if(!is_string($_POST['run_id']??null)||!hash_equals($run['id'],$_POST['run_id']))throw new InvalidArgumentException('Cette réponse appartient à une autre tentative.');
            $ordinal=(int)($_POST['ordinal']??0);$selected=$_POST['answers']??[];
            if(!is_array($selected)||count($selected)>1000)throw new InvalidArgumentException('Réponse invalide.');
        }
        $received=(float)($_SERVER['REQUEST_TIME_FLOAT']??microtime(true));
        $run=$preview->store->advance($run['id'],$teacherId,$received,$ordinal,$selected,static fn():float=>microtime(true));
        $_SESSION['adaptive_qcm_preview']=$preview->snapshot($run)+['restart_from'=>$restartFrom];
        session_write_close();
        echo json_encode($preview->store->publicState($run,microtime(true))+['preview'=>true],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }catch(InvalidArgumentException $error){http_response_code(400);echo json_encode(['error'=>t($error->getMessage())]);}
    catch(Throwable $error){error_log('liike adaptive preview: '.$error->getMessage());http_response_code(503);echo json_encode(['error'=>t('Activité indisponible. Réessayez.')]);}
    exit;
}

function adaptive_qcm_http(): never
{
    header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store, private');
    if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');echo '{}';exit;}
    verify_csrf();$user=actor();
    $preview=($_POST['preview']??'')==='1';
    if(!$user||$user['role']!==($preview?'teacher':'student')){http_response_code($user?403:401);echo json_encode(['error'=>t('Accès interdit.')]);exit;}
    if(!AdaptiveQcm::enabled()){http_response_code(503);echo json_encode(['error'=>t('Le module QCM adaptatif est désactivé.')]);exit;}
    if($preview)adaptive_qcm_preview_http($user);
    session_write_close();
    try{
        $item=AdaptiveQcm::context(db(),(int)($_POST['item_id']??0),(int)$user['id']);$store=AdaptiveQcm::store(db());
        $operation=$_POST['operation']??'state';if(!in_array($operation,['state','start','answer'],true))throw new InvalidArgumentException('Commande invalide.');
        $run=$store->latest((int)$user['id'],(int)$item['id']);
        if($operation==='start'){
            $bank=AdaptiveQcm::bank(db(),(int)$item['page_id'],app_public_base_url());
            $run=$store->start($item,(int)$user['id'],$bank);
        }
        if(!$run){
            if($operation==='answer')throw new InvalidArgumentException('Commencez cette activité avant de répondre.');
            $bank=AdaptiveQcm::bank(db(),(int)$item['page_id'],app_public_base_url());
            echo json_encode(['ready'=>true,'count'=>count($bank['questions'])],JSON_THROW_ON_ERROR);exit;
        }
        foreach(json_decode($run['sources'],true,512,JSON_THROW_ON_ERROR) as $token=>$sourceId){
            $share=library_share_resolve(db(),$token);library_share_require_mode($share,'shared');
            if((int)$share['page_id']!==(int)$sourceId)throw new InvalidArgumentException('Réservoir partagé indisponible.');
        }
        $ordinal=null;$selected=null;
        if($operation==='answer'){
            if(!is_string($_POST['run_id']??null)||!hash_equals($run['id'],$_POST['run_id']))throw new InvalidArgumentException('Cette réponse appartient à une autre tentative.');
            $ordinal=(int)($_POST['ordinal']??0);$selected=$_POST['answers']??[];
            if(!is_array($selected)||count($selected)>1000)throw new InvalidArgumentException('Réponse invalide.');
        }
        $received=(float)($_SERVER['REQUEST_TIME_FLOAT']??microtime(true));
        $run=$store->advance($run['id'],(int)$user['id'],$received,$ordinal,$selected,static fn():float=>microtime(true));
        echo json_encode($store->publicState($run,microtime(true)),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }catch(InvalidArgumentException $error){http_response_code(400);echo json_encode(['error'=>t($error->getMessage())]);}
    catch(Throwable $error){error_log('liike adaptive qcm: '.$error->getMessage());http_response_code(503);echo json_encode(['error'=>t('Activité indisponible. Réessayez.')]);}
    exit;
}

/** Virtual attempts expose the adaptive score, never a fictitious count of correct answers. */
function adaptive_qcm_results(PDO $core,array $itemIds): array
{
    $store=AdaptiveQcm::store($core,false);if(!$store||!$itemIds)return [];
    $out=[];$now=microtime(true);
    foreach(array_chunk(array_values(array_unique(array_map('intval',$itemIds))),400) as $ids){
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $q=$store->db->prepare("SELECT * FROM runs WHERE item_id IN ($marks) AND finished_at IS NULL AND ends_at<=?");$q->execute([...$ids,$now]);
        foreach($q->fetchAll() as $run)$store->advance($run['id'],(int)$run['student_id'],$now);
        $q=$store->db->prepare("SELECT r.*,COUNT(a.ordinal) AS total_questions,SUM(a.result='correct') AS correct_questions
            FROM runs r JOIN answers a ON a.run_id=r.id WHERE r.item_id IN ($marks) AND r.finished_at IS NOT NULL AND r.score IS NOT NULL
            AND NOT EXISTS(SELECT 1 FROM runs newer WHERE newer.item_id=r.item_id AND newer.student_id=r.student_id AND newer.finished_at IS NOT NULL AND newer.started_at>r.started_at)
            GROUP BY r.id");$q->execute($ids);
        foreach($q->fetchAll() as $run)$out[(int)$run['item_id']][(int)$run['student_id']]=[
            'score_percent'=>(float)$run['score'],'correct_questions'=>(int)$run['correct_questions'],'total_questions'=>(int)$run['total_questions'],
            'attempt_count'=>1,'answered_at'=>gmdate('Y-m-d H:i:s',(int)$run['finished_at']),'adaptive'=>true];
    }
    return $out;
}
