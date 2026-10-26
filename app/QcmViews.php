<?php

declare(strict_types=1);

function qcm_teacher_session_links(string $source,int $blockId,int $itemId): string
{
    $html='';
    foreach(Qcm::sections($source) as $section){
        if($section['type']!=='qcm'||empty($section['valid'])||$section['mode']!=='classe')continue;
        $url=route('qcm-class',['item'=>$itemId,'block'=>$blockId,'key'=>Qcm::key($blockId,(int)$section['index'],$section['source'],'classe')]);
        $html.='<p><a class="button primary" href="'.e($url).'">'.e(t('Piloter le QCM en classe')).' · '.((int)$section['index']+1).'</a></p>';
    }
    return $html;
}

function render_qcm_authoring_help(string $example): void
{
    ?><div class="modal-body qcm-authoring-modal" data-qcm-labels="<?=e(json_encode(Qcm::uiLabels(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))?>">
      <label class="field" for="qcm-example-mode"><span><?=e(t('Mode du questionnaire'))?></span><select id="qcm-example-mode" data-qcm-example-mode>
        <option value="qcm"><?=e(t('Classique'))?> · :::qcm</option>
        <option value="qcm_strict"><?=e(t('Strict'))?> · :::qcm_strict</option>
        <option value="qcm_classe"><?=e(t('En classe'))?> · :::qcm_classe</option>
      </select></label>
      <p data-qcm-mode-help="qcm"><?=e(t('Questionnaire individuel dans la page, avec le fonctionnement habituel.'))?></p>
      <p data-qcm-mode-help="qcm_strict" hidden><?=e(t('Interface dédiée, sans navigation dans le cours jusqu’à la remise. Le plein écran dépend du navigateur et ne verrouille pas l’appareil.'))?></p>
      <p data-qcm-mode-help="qcm_classe" hidden><?=e(t('Séance collective formative : l’enseignant ouvre chaque question, ferme les réponses, affiche leur répartition puis passe à la suivante. Ouvrez l’aperçu de l’étape du parcours pour piloter la séance.'))?></p>
      <pre data-qcm-example><?=e($example)?></pre>
      <button class="button secondary" type="button" data-qcm-copy><?=e(t('Copier l’exemple'))?></button><span role="status" data-qcm-copy-status></span>
      <ul class="qcm-marker-help">
        <li><code>[v]</code> <?=e(t('Réponse juste, à sélectionner.'))?></li>
        <li><code>[x]</code> <?=e(t('Réponse fausse, à ne pas sélectionner.'))?></li>
        <li><code>[-]</code> <?=e(t('Réponse neutre, facultative : elle n’ajoute pas de pénalité et ne remplace pas une réponse juste.'))?></li>
      </ul>
      <p><?=e(t('Une seule réponse [v] affiche des boutons radio ; plusieurs affichent des cases à cocher.'))?></p>
      <p><?=e(t('Une question est réussie si toutes les réponses [v] sont sélectionnées et aucune réponse [x] ne l’est.'))?></p>
      <p><?=e(t('Ajoutez un titre # pour chaque nouvelle question et terminez le bloc par :::.'))?></p>
    </div><?php
}

function qcm_class_http(bool $write=false): never
{
    header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store, private');
    $user=actor();
    if(!$user){http_response_code(401);echo json_encode(['status'=>'error','message'=>t('Reconnectez-vous pour continuer.')]);exit;}
    $input=$write?$_POST:$_GET;
    // Release the PHP session lock so polling cannot hold up another tab.
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    try{
        $args=[db(),$user,(int)($input['item']??0),(int)($input['block']??0),(string)($input['key']??'')];
        $result=$write?QcmClass::command(...array_merge($args,[$input])):QcmClass::state(...$args);
        echo json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
    }catch(DomainException $exception){
        http_response_code(in_array($exception->getCode(),[403,409,422],true)?$exception->getCode():409);
        echo json_encode(['status'=>'error','message'=>t($exception->getMessage())],JSON_THROW_ON_ERROR);
    }catch(Throwable $exception){
        error_log('QCM classe: '.$exception->getMessage());http_response_code(503);
        echo json_encode(['status'=>'error','message'=>t('Connexion interrompue. Réessayez dans un instant.')],JSON_THROW_ON_ERROR);
    }
    exit;
}

function render_qcm_class_page(array $user): void
{
    $item=(int)($_GET['item']??0);$block=(int)($_GET['block']??0);$key=(string)($_GET['key']??'');
    header('Cache-Control: no-store, private');
    try{$context=QcmClass::context(db(),$user,$item,$block,$key);}
    catch(DomainException $exception){http_response_code($exception->getCode());echo '<!doctype html><meta charset="utf-8"><p>'.e(t($exception->getMessage())).'</p>';return;}
    $teacher=$context['teacher'];$projection=$teacher&&($_GET['projection']??'')==='1';
    $params=['item'=>$item,'block'=>$block,'key'=>$key];
    $strings=Qcm::uiLabels();
    foreach(['waiting'=>'En attente de l’enseignant…','ready'=>'Préparez la séance, puis ouvrez la première question.',
        'start'=>'Créer une séance','open'=>'Ouvrir la première question','close'=>'Fermer les réponses et afficher les résultats',
        'reveal'=>'Révéler la correction','next'=>'Question suivante','finish'=>'Terminer la séance','restart'=>'Nouvelle séance',
        'send'=>'Envoyer ma réponse','saved'=>'Réponse enregistrée. Vous pouvez la modifier tant que la question est ouverte.',
        'closed'=>'Réponses closes','finished'=>'Séance terminée','question'=>'Question :number sur :total',
        'count'=>'Réponses : :count / :total élèves','multiple'=>'Plusieurs choix possibles. Les nombres indiquent les élèves ayant choisi chaque proposition.',
        'single'=>'Une seule réponse possible.','pending'=>'Envoi en cours…','error'=>'Connexion interrompue. Les réponses sont désactivées jusqu’à la reconnexion.',
        'right'=>'Réponse juste','wrong'=>'Réponse fausse','neutral'=>'Réponse neutre','login'=>'Session expirée. Reconnectez-vous puis revenez à cette séance.',
        'join'=>'Élèves : ouvrez cette étape dans votre parcours et choisissez « Rejoindre la séance ».'] as $name=>$label)$strings[$name]=t($label);
    ?><!doctype html><html lang="<?=e(current_language())?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title><?=e(t('QCM en classe'))?> · <?=e($context['item']['title'])?></title><link rel="stylesheet" href="assets/app.css?v=152"><link rel="stylesheet" href="assets/qcm-modes.css?v=1"></head>
    <body class="qcm-class-page<?=$projection?' qcm-projection':''?>" data-csrf="<?=e(csrf_token())?>">
    <main class="qcm-live" data-qcm-live data-teacher="<?=$teacher?'1':'0'?>" data-projection="<?=$projection?'1':'0'?>" data-context="<?=e(json_encode($params,JSON_THROW_ON_ERROR))?>" data-labels="<?=e(json_encode($strings,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))?>">
      <header class="qcm-live-header"><a class="back" href="<?=e(route($teacher?'teacher-preview-page':'learn',['item'=>$item]))?>">← <?=e(t('Retour à l’étape'))?></a><div class="qcm-live-tools"><?php if($teacher&&!$projection): ?><a class="button secondary" href="<?=e(route('qcm-class',$params+['projection'=>1]))?>" target="_blank" rel="noopener"><?=e(t('Ouvrir la projection'))?></a><?php endif; ?><button type="button" class="button secondary" data-qcm-fullscreen><?=e(t('Plein écran'))?></button></div></header>
      <p class="eyebrow"><?=e(t('QCM en classe'))?> · <?=e($context['item']['title'])?></p>
      <p data-qcm-screen-status role="status"></p><p data-qcm-live-error role="alert" hidden></p>
      <div data-qcm-live-content><p><?=e(t('Chargement de la séance…'))?></p></div>
      <p data-qcm-vote-status role="status"></p><div class="qcm-live-controls" data-qcm-live-controls></div>
      <noscript><?=e(t('Activez JavaScript pour participer à la séance.'))?></noscript>
    </main><script src="assets/qcm-modes.js?v=1"></script></body></html><?php
}
