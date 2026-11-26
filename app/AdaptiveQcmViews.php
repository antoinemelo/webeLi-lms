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
    $source=str_replace(["\r\n","\r"],"\n",(string)$block['body']);$store=AdaptiveQcm::store($core,false);
    if(!$store||empty($block['page_id'])||empty($block['id']))return $source;
    $q=$store->db->prepare('SELECT canonical_id,presentations,correct,timed_answers,time_sum FROM questions WHERE page_id=? AND block_id=? ORDER BY presentations DESC,id');
    $q->execute([$block['page_id'],$block['id']]);$stats=[];foreach($q as $row)$stats[$row['canonical_id']]??=$row;
    return preg_replace_callback('/(^[\t ]*:::\s*qcm_(?:adap|adaptatif)(?:\[[^\]\r\n]+\]\[[^\]\r\n]+\])?[\t ]*\R)(.*?)(\R[\t ]*:::[\t ]*(?=\R|$))/msu',static function($match)use($block,$stats){
        $section=Qcm::sections($match[0])[0]??[];
        if(empty($section['valid']))return $match[0];
        $index=0;
        $body=preg_replace_callback('/^[\t ]*\[[0-3]\]\[[0-9]+\]\[[-0-9.,]+\]\[[0-9]+\]\[[0-9]+\][\t ]*$/m',static function($line)use(&$index,$section,$block,$stats){
            $question=$section['questions'][$index++];unset($question['metadata']);
            $question['domain']=$section['domain']??'';$question['theme']=$section['theme']??'';
            $id=AdaptiveQcm::canonicalId($question,(int)$block['page_id'],(int)$block['id']);
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
        'score'=>'Résultat','review'=>'Correction','temporary'=>'Ce corrigé est temporaire : il disparaît lorsque vous quittez ou rechargez cette page.',
        'reviewGone'=>'Le détail des réponses n’est plus conservé.', 'yourIncorrect'=>'Votre réponse incorrecte',
        'selected'=>'Votre choix','neutral'=>'Votre choix sans effet sur le résultat','single'=>'Une seule réponse possible.','multiple'=>'Plusieurs réponses possibles.',
        'question'=>'Question','total'=>'Temps total restant','remaining'=>'Temps de la question','ready'=>'Le chronomètre démarre lorsque vous commencez.','expected'=>'Réponse attendue'] as $key=>$label)$labels[$key]=t($label);
    if(!$preview&&!empty($item['is_controlled_exercise']))$labels['retry']=t('Nouvel exercice');
    if($preview){$labels['start']=t('Commencer le test');$labels['retry']=t('Recommencer le test');}
    else $labels['ready']=t('Le chronomètre démarre au premier clic. Une tentative en cours ne peut pas être recommencée.');
    foreach(['time_remaining'=>'Il ne reste pas assez de temps pour une question complète.',
        'global_timeout'=>'Fin du test : question non prise en compte.',
        'insufficient'=>'Moins de 5 questions traitées : aucune note automatique.',
        'controlled'=>'Exercice terminé, aucune note conservée.',
        'basis'=>'Questions prises en compte',
        'provisional'=>'Les questions disposent encore de peu d’historique. Le résultat reste une estimation.'] as $key=>$label)$labels[$key]=t($label);
    ?><section class="adaptive-qcm" data-adaptive-qcm data-item="<?=(int)$item['id']?>" data-preview="<?=$preview?'1':'0'?>" data-labels="<?=e(json_encode($labels,JSON_UNESCAPED_UNICODE))?>">
      <h2><?=e(t('QCM adaptatif'))?></h2>
      <p><?=e(t(!empty($item['is_controlled_exercise'])?'Exercice contrôlé : statistiques actualisées, aucune note.':($preview?($item['is_evaluation']?'Mode évaluation':'Mode entraînement'):($item['is_evaluation']?'Évaluation : une tentative.':'Entraînement : nouvel essai après la fin.'))))?> · <?=(int)$item['estimated_minutes']?> min</p>
      <?php if($preview): ?><p><?=e(t('Test enseignant : vous pouvez recommencer librement. Vos essais ne modifient ni les résultats des apprenants ni les statistiques des questions.'))?></p><button type="button" class="button secondary" data-adaptive-restart hidden><?=e(t('Recommencer le test'))?></button><?php endif; ?>
      <div data-adaptive-content><p><?=e(t('Chargement…'))?></p></div><p data-adaptive-error role="alert" hidden></p><noscript><?=e(t('Activez JavaScript pour réaliser cette activité chronométrée.'))?></noscript>
    </section><?php
}

/** Teacher-readable facts, separate from the manual level and from the learner's grade. */
function render_adaptive_question_statistics(array $questions): void
{
    ?><div class="adaptive-statistics"><?php foreach($questions as $question):
        $s=$question['stats'];$sample=$question['calibration']??['n'=>0,'correct'=>0,'mean'=>null];$n=(int)$sample['n'];
        $mean=$s['timed_answers']?number_format($s['time_sum']/$s['timed_answers'],1,',',''):'—'; ?>
        <article class="adaptive-question-stat"><strong><?=e($question['title'])?></strong>
        <div class="adaptive-stat-badges"><span><?=e(t('Niveau initial'))?> : <?=(int)$question['level']?></span>
        <span><?=e(t('Difficulté estimée'))?> : <?=e(number_format(100-$question['rate'],1,',',''))?> %</span>
        <span><?=e(t($n===0?'Estimation initiale':($n<20?'Historique limité':'Historique disponible')))?></span></div>
        <p><?=e(t('Réponses retenues'))?> : <?=$n?> · <?=e(t('Réussite observée'))?> : <?=$question['observed_rate']===null?'—':e(number_format($question['observed_rate'],1,',','')).' %'?> · <?=e(t('Temps moyen'))?> : <?=e($mean)?> s</p>
        <small><?=e(t('Présentations en évaluation ou exercice contrôlé'))?> : <?=(int)$s['presentations']?> · <?=e(t('Réponses justes'))?> : <?=(int)$s['correct']?></small></article>
    <?php endforeach; ?></div><?php
}

function render_adaptive_block_statistics(PDO $core,array $block): void
{
    if(!AdaptiveQcm::enabled()||empty($block['page_id'])||empty($block['id'])||!AdaptiveQcm::has((string)$block['body']))return;
    try{
        $store=AdaptiveQcm::store($core,false);$questions=[];
        foreach(Qcm::sections($block['body']) as $section)if($section['type']==='adaptive'&&!empty($section['valid']))foreach($section['questions'] as $question){
            $question['canonical_id']=AdaptiveQcm::canonicalId($question,(int)$block['page_id'],(int)$block['id']);
            $question['id']=$question['canonical_id'];$questions[]=$question;
        }
        if(!$questions)return;
        $questions=$store?$store->statistics($store->resolveVersions($questions)):array_map(static fn($question)=>$question+[
            'stats'=>['presentations'=>0,'correct'=>0,'timed_answers'=>0,'time_sum'=>0],
            'rate'=>AdaptiveQcm::INITIAL_RATES[$question['level']],'observed_rate'=>null,
        ],$questions);
        ?><details class="adaptive-block-statistics" open><summary><?=e(t('Niveaux et statistiques des questions'))?></summary>
        <p><?=e(t('Les évaluations et les exercices contrôlés actualisent la difficulté estimée. Le niveau initial reste votre repère. Une modification du texte, des réponses ou du temps maximal démarre une nouvelle calibration.'))?></p>
        <?php render_adaptive_question_statistics($questions); ?></details><?php
    }catch(InvalidArgumentException){ /* The author is still editing an incomplete bank. */ }
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
            'stats'=>['presentations'=>0,'correct'=>0,'timed_answers'=>0,'time_sum'=>0],'rate'=>AdaptiveQcm::INITIAL_RATES[$question['level']],'observed_rate'=>null,
        ],$bank['questions']);
        ?><h4 class="h6"><?=e(t('Questions enregistrées'))?> · <?=count($questions)?></h4>
        <p><?=e(t('Les statistiques proviennent des évaluations et des exercices contrôlés : temps moyen, présentations et réponses justes. Enregistrez les modifications du contenu pour actualiser ce réservoir.'))?></p>
        <p><?=e(t('Au moins 5 questions traitées sont nécessaires pour attribuer une note. Une réponse correcte raisonnablement rapide peut légèrement faciliter la question pour les futurs tests ; une réponse lente n’ajoute aucune pénalité de difficulté.'))?></p>
        <?php render_adaptive_question_statistics($questions);
        $actor=require_actor();$items=all('SELECT pi.id,pi.course_id,c.title FROM pathway_items pi JOIN courses c ON c.id=pi.course_id WHERE pi.page_id=? AND pi.is_evaluation=1 AND c.archived=0 ORDER BY c.title,pi.position',[$pageId]);
        $items=array_values(array_filter($items,static fn($item)=>teacher_can_access_course(db(),(int)$item['course_id'],(int)$actor['id'])));
        if($items): ?>
        <h4 class="h6"><?=e(t('Référence des évaluations'))?></h4>
        <p><?=e(t('Une évaluation conserve la même référence pour ses apprenants. Après une phase de calibration, actualisez-la pour les prochains participants. Les tentatives et résultats existants restent inchangés ; aucune tentative supplémentaire n’est autorisée.'))?></p>
        <form method="post" class="adaptive-settings" data-error="<?=e(t('Actualisation impossible.'))?>"><?=csrf_field()?><input type="hidden" name="action" value="adaptive_qcm_reference"><input type="hidden" name="page_id" value="<?=$pageId?>">
        <label><?=e(t('Évaluation'))?><select name="item_id"><?php foreach($items as $candidate): ?><option value="<?=(int)$candidate['id']?>"><?=e($candidate['title'])?> · #<?=(int)$candidate['id']?></option><?php endforeach; ?></select></label>
        <button class="button secondary"><?=e(t('Actualiser pour les prochains participants'))?></button><span role="status" data-adaptive-settings-status></span></form>
        <?php endif;
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

function adaptive_qcm_reference(): never
{
    verify_csrf();$user=require_actor();header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store, private');
    $item=one('SELECT id,page_id,course_id,is_evaluation FROM pathway_items WHERE id=?',[(int)($_POST['item_id']??0)]);
    if(!AdaptiveQcm::enabled()||$user['role']!=='teacher'||!$item||!$item['is_evaluation']||(int)$item['page_id']!==(int)($_POST['page_id']??0)||!teacher_can_access_course(db(),(int)$item['course_id'],(int)$user['id'])||!teacher_can_access_page(db(),(int)$item['page_id'],(int)$user['id'])){
        http_response_code(403);echo json_encode(['error'=>t('Accès interdit.')]);exit;
    }
    try{
        $store=AdaptiveQcm::store(db());
        QuizAttempts::transaction(db(),static fn()=>$store->refreshReference((int)$item['id'],db()));
        echo json_encode(['message'=>t('La prochaine tentative utilisera les statistiques actualisées et le nouveau calcul. Les résultats existants sont conservés.')]);
    }catch(InvalidArgumentException $error){http_response_code(409);echo json_encode(['error'=>t($error->getMessage())]);}
    catch(Throwable $error){error_log('liike adaptive reference: '.$error->getMessage());http_response_code(503);echo json_encode(['error'=>t('Activité indisponible. Réessayez.')]);}
    exit;
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
        if($run&&((int)$run['student_id']!==$teacherId||(int)$run['item_id']!==$itemId||($run['epoch']??'0:0')!==QuizAttempts::epoch($item))){$snapshot=null;$run=null;}
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
    if(($_POST['operation']??'')==='restart'){http_response_code(403);echo json_encode(['error'=>t('Vous ne pouvez pas remettre ce test à zéro.')]);exit;}
    try{
        $store=AdaptiveQcm::store(db()); // Migrate before taking the short core write lock.
        $payload=QuizAttempts::transaction(db(),function()use($user,$store){
        $item=AdaptiveQcm::context(db(),(int)($_POST['item_id']??0),(int)$user['id']);
        $operation=$_POST['operation']??'state';
        if($operation==='restart')throw new InvalidArgumentException('Vous ne pouvez pas remettre ce test à zéro.');
        if(!in_array($operation,['state','start','answer'],true))throw new InvalidArgumentException('Commande invalide.');
        $run=$store->latest((int)$user['id'],(int)$item['id'],QuizAttempts::epoch($item));
        if($operation==='start'){
            if(!hash_equals(QuizAttempts::epoch($item),(string)($_POST['epoch']??'0:0')))throw new InvalidArgumentException('Cette activité a changé. Rechargez la page.');
            $bank=AdaptiveQcm::bank(db(),(int)$item['page_id'],app_public_base_url());
            $run=$store->start($item,(int)$user['id'],$bank,present:true);
        }
        if(!$run){
            if($operation==='answer')throw new InvalidArgumentException('Commencez cette activité avant de répondre.');
            $bank=AdaptiveQcm::bank(db(),(int)$item['page_id'],app_public_base_url());
            return ['ready'=>true,'count'=>count($bank['questions']),'epoch'=>QuizAttempts::epoch($item)];
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
        if($run['controlled']&&$run['finished_at']!==null)Qcm::completeControlled(db(),(int)$user['id'],(int)$item['id'],$run);
        return $store->publicState($run,microtime(true))+['epoch'=>QuizAttempts::epoch($item)];
        });
        echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }catch(InvalidArgumentException $error){http_response_code(400);echo json_encode(['error'=>t($error->getMessage())]);}
    catch(Throwable $error){error_log('liike adaptive qcm: '.$error->getMessage());http_response_code(503);echo json_encode(['error'=>t('Activité indisponible. Réessayez.')]);}
    exit;
}

/** Virtual attempts expose the adaptive score, never a fictitious count of correct answers. */
function adaptive_qcm_results(PDO $core,array $itemIds,bool $includeUnscored=false): array
{
    $store=AdaptiveQcm::store($core,false);if(!$store||!$itemIds)return [];
    $out=[];$now=microtime(true);
    foreach(array_chunk(array_values(array_unique(array_map('intval',$itemIds))),400) as $ids){
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $q=$core->prepare("SELECT pi.id,pi.quiz_revision,e.student_id,COALESCE(pr.quiz_generation,0) AS quiz_generation FROM pathway_items pi JOIN enrollments e ON e.course_id=pi.course_id LEFT JOIN progress pr ON pr.enrollment_id=e.id AND pr.pathway_item_id=pi.id WHERE pi.id IN ($marks)");$q->execute($ids);$epochs=[];
        foreach($q as $item)$epochs[(int)$item['id']][(int)$item['student_id']]=QuizAttempts::epoch($item);
        $current=static fn(array $run):bool=>isset($epochs[(int)$run['item_id']][(int)$run['student_id']])&&$epochs[(int)$run['item_id']][(int)$run['student_id']]===$run['epoch'];

        $q=$store->db->prepare("SELECT * FROM runs WHERE item_id IN ($marks) AND finished_at IS NULL AND ends_at<=?");$q->execute([...$ids,$now]);
        foreach($q->fetchAll() as $run)if($current($run))QuizAttempts::transaction($core,static function()use($core,$store,$run,$now){
            $q=$core->prepare('SELECT pi.quiz_revision,COALESCE(pr.quiz_generation,0) AS quiz_generation FROM pathway_items pi JOIN enrollments e ON e.course_id=pi.course_id AND e.student_id=? LEFT JOIN progress pr ON pr.enrollment_id=e.id AND pr.pathway_item_id=pi.id WHERE pi.id=?');$q->execute([$run['student_id'],$run['item_id']]);$item=$q->fetch(PDO::FETCH_ASSOC);
            if($item&&QuizAttempts::epoch($item)===$run['epoch'])$store->advance($run['id'],(int)$run['student_id'],$now);
        });
        $scoreFilter=$includeUnscored?'':' AND r.score IS NOT NULL';
        $q=$store->db->prepare("SELECT r.* FROM runs r WHERE r.item_id IN ($marks) AND r.finished_at IS NOT NULL AND r.controlled=0 $scoreFilter
            AND NOT EXISTS(SELECT 1 FROM runs newer WHERE newer.item_id=r.item_id AND newer.student_id=r.student_id AND newer.epoch=r.epoch AND newer.finished_at IS NOT NULL AND newer.started_at>r.started_at)");$q->execute($ids);
        foreach($q->fetchAll() as $run)if($current($run))$out[(int)$run['item_id']][(int)$run['student_id']]=[
            'score_percent'=>$run['score']===null?null:(float)$run['score'],'correct_questions'=>(int)$run['correct_questions'],'total_questions'=>(int)$run['total_questions'],
            'attempt_count'=>1,'answered_at'=>gmdate('Y-m-d H:i:s',(int)$run['finished_at']),'adaptive'=>true];
    }
    return $out;
}
