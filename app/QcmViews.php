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

function render_qcm_authoring_help(string $example,int $pageId=0,array $blocks=[]): void
{
    $examples=['qcm'=>$example];
    foreach(['qcm_strict','qcm_classe'] as $mode)$examples[$mode]=preg_replace('/^:::qcm/', ':::'.$mode, $example, 1);
    $examples['qcm_adap']=implode("\n",[':::qcm_adap['.t('Domaine').']['.t('Thématique').']',
        '# '.t('Question'),'$ '.t('Explication facultative de la réponse attendue.'),'[1][120][-][0][0]','[v] '.t('Réponse juste'),'[x] '.t('Réponse fausse'),'[-] '.t('Réponse neutre'),
        '', '# '.t('Autre question'),'[2][60][-][0][0]','[v] '.t('Réponse juste'),'[x] '.t('Réponse fausse'),':::']);
    ?><div class="modal-body qcm-authoring-modal" data-qcm-labels="<?=e(json_encode(Qcm::uiLabels(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))?>" data-qcm-examples="<?=e(json_encode($examples,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))?>">
      <label class="field" for="qcm-example-mode"><span><?=e(t('Mode du questionnaire'))?></span><select id="qcm-example-mode" data-qcm-example-mode>
        <option value="qcm"><?=e(t('Classique'))?> · :::qcm</option>
        <option value="qcm_strict"><?=e(t('Strict'))?> · :::qcm_strict</option>
        <option value="qcm_classe"><?=e(t('En classe'))?> · :::qcm_classe</option>
        <option value="qcm_adap"><?=e(t('Adaptatif'))?> · :::qcm_adap</option>
        <?php if(AdaptiveQcm::enabled()): ?><option value="adaptive_settings"><?=e(t('QCM adaptatif : réservoir et réglages'))?></option><?php endif; ?>
      </select></label>
      <div data-qcm-examples-panel>
      <p data-qcm-mode-help="qcm"><?=e(t('Questionnaire individuel dans la page, avec le fonctionnement habituel.'))?></p>
      <p data-qcm-mode-help="qcm_strict" hidden><?=e(t('Interface dédiée, sans navigation dans le cours jusqu’à la remise. Le plein écran dépend du navigateur et ne verrouille pas l’appareil.'))?></p>
      <p data-qcm-mode-help="qcm_classe" hidden><?=e(t('Séance collective formative : l’enseignant ouvre chaque question, ferme les réponses, affiche leur répartition puis passe à la suivante. Ouvrez l’aperçu de l’étape du parcours pour piloter la séance.'))?></p>
      <p data-qcm-mode-help="qcm_adap" hidden><?=e(t('Une question à la fois, choisie selon les réponses précédentes. Le réservoir reste limité aux questions adaptatives de cette page et aux blocs QCM tiers ajoutés explicitement.'))?></p>
      <pre data-qcm-example><?=e($example)?></pre>
      <button class="button secondary" type="button" data-qcm-copy><?=e(t('Copier l’exemple'))?></button><span role="status" data-qcm-copy-status></span>
      <ul class="qcm-marker-help">
        <li><code>[v]</code> <?=e(t('Réponse juste, à sélectionner.'))?></li>
        <li><code>[x]</code> <?=e(t('Réponse fausse, à ne pas sélectionner.'))?></li>
        <li><code>[-]</code> <?=e(t('Réponse neutre, facultative : elle n’ajoute pas de pénalité et ne remplace pas une réponse juste.'))?></li>
        <li><code>$</code> <?=e(t('Explication facultative, sur une seule ligne sous la question et avant les choix. En adaptatif, elle peut précéder ou suivre les cinq paramètres.'))?></li>
      </ul>
      <p><?=e(t('Une seule réponse [v] affiche des boutons radio ; plusieurs affichent des cases à cocher.'))?></p>
      <p><?=e(t('Une question est réussie si toutes les réponses [v] sont sélectionnées et aucune réponse [x] ne l’est.'))?></p>
      <p><?=e(t('L’explication apparaît en italique dans les résultats uniquement si la réponse est incorrecte, incomplète ou absente. En classe, elle attend la révélation de la correction et reste personnelle. Elle est traitée comme du texte, sans HTML ni mise en forme Markdown.'))?></p>
      <p><?=e(t('Ajouter, corriger ou retirer une explication conserve les notes et les statistiques. Modifier l’énoncé, un choix ou sa correction crée une nouvelle version adaptative sans observations ; le niveau initial enseignant reste le point de départ.'))?></p>
      <p><?=e(t('Ajoutez un titre # pour chaque nouvelle question et terminez le bloc par :::.'))?></p>
      <div data-qcm-mode-help="qcm_adap" data-qcm-adaptive-help hidden>
        <h3 class="h5 mt-4"><?=e(t('Les cinq paramètres de chaque question'))?></h3>
        <p><?=e(t('Après le titre de la question, ajoutez une ligne comme [1][120][-][0][0], dans cet ordre :'))?></p>
        <ol>
          <li><?=e(t('Niveau de 0 à 3, fixé par l’enseignant.'))?></li>
          <li><?=e(t('Temps maximal de réponse, en secondes.'))?></li>
          <li><?=e(t('Temps moyen constaté en évaluation ; - si encore inconnu.'))?></li>
          <li><?=e(t('Nombre de présentations en évaluation.'))?></li>
          <li><?=e(t('Nombre de réponses justes en évaluation.'))?></li>
        </ol>
        <p><?=e(t('Pour une nouvelle question, terminez par [-][0][0]. Le système actualise ces trois valeurs ; les saisir ne remplace pas les statistiques. Les cinq champs sont nécessaires.'))?></p>
        <p><?=e(t('Après :::qcm_adap, indiquez [Domaine][Thématique]. Le marqueur :::qcm_adaptatif est également accepté.'))?></p>
        <h3 class="h5 mt-4"><?=e(t('Durée, mode et résultat'))?></h3>
        <p><?=e(t('La durée estimée du contenu fixe le temps total. Chaque question a son délai maximal. Un délai individuel complet dépassé compte comme non réussi. Aucune nouvelle question n’est lancée si son temps maximal n’est plus disponible.'))?></p>
        <p><?=e(t('Après enregistrement, choisissez le niveau de départ dans « QCM adaptatif : réservoir et réglages ». Il sert à choisir la première question ; les suivantes dépendent des taux de réussite. Sans statistiques : N0 = 80 %, N1 = 60 %, N2 = 40 %, N3 = 20 %.'))?></p>
        <p><?=e(t('Une étape ordinaire permet l’entraînement et les nouveaux essais, sans modifier les statistiques. Une étape Évaluation autorise une tentative par apprenant et alimente les statistiques. Recharger la page ne remet pas les chronomètres à zéro.'))?></p>
        <p><?=e(t('Le résultat tient compte de toutes les réponses et de la difficulté des questions. Les variations deviennent progressivement plus prudentes ; elles ne dépendent plus du tiers de temps écoulé. Moins de 5 questions traitées ne donnent aucune note automatique.'))?></p>
        <p><?=e(t('La note sur 10, arrondie au dixième, estime le résultat sur le réservoir complet : réponses connues et réussite estimée des questions non posées. Si toutes les questions ont été traitées, 6 bonnes réponses sur 8 donnent 7,5 /10. La rapidité ne donne aucun bonus direct.'))?></p>
        <p><?=e(t('Une seule bonne réponse ne donne plus 10/10. Le test se termine lorsque le réservoir est épuisé ou que le temps restant ne permet plus une question complète. Les tentatives existantes gardent leur ancien barème ; actualiser la référence concerne uniquement les prochains participants.'))?></p>
        <p><?=e(t('Pour ajouter les questions d’un collègue, insérez un bloc QCM tiers avec son lien de partage de cette installation. Le propriétaire doit autoriser le mode Contenu partagé.'))?></p>
        <p><?=e(t('Pour un entraînement sans compte, activez Utilisation externe dans Partager ce contenu. Le lien utilise les statistiques existantes sans les modifier et n’enregistre aucune note dans un parcours. Le temps total reste la durée estimée du contenu. Les sources QCM tiers doivent aussi autoriser Utilisation externe.'))?></p>
        <p class="mt-3"><a href="<?=e(route('user-guide'))?>#preparer-qcm-adaptatif" target="_blank" rel="noopener"><?=e(t('Lire le guide du QCM adaptatif'))?></a></p>
      </div>
      </div>
      <?php if(AdaptiveQcm::enabled()): ?><div data-qcm-mode-help="adaptive_settings" hidden><?php render_adaptive_qcm_editor($pageId,$blocks); ?></div><?php endif; ?>
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
    $strings=qcm_class_labels();
    ?><!doctype html><html lang="<?=e(current_language())?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title><?=e(t('QCM en classe'))?> · <?=e($context['item']['title'])?></title><link rel="stylesheet" href="assets/app.css?v=171"><link rel="stylesheet" href="assets/qcm-modes.css?v=4"></head>
    <body class="qcm-class-page<?=$projection?' qcm-projection':''?>" data-csrf="<?=e(csrf_token())?>">
    <main class="qcm-live" data-qcm-live data-teacher="<?=$teacher?'1':'0'?>" data-projection="<?=$projection?'1':'0'?>" data-context="<?=e(json_encode($params,JSON_THROW_ON_ERROR))?>" data-labels="<?=e(json_encode($strings,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))?>">
      <header class="qcm-live-header"><a class="back" href="<?=e(route($teacher?'teacher-preview-page':'learn',['item'=>$item]))?>">← <?=e(t('Retour à l’étape'))?></a><div class="qcm-live-tools"><?php if($teacher&&!$projection): ?><a class="button secondary" href="<?=e(route('qcm-class',$params+['projection'=>1]))?>" target="_blank" rel="noopener"><?=e(t('Ouvrir la projection'))?></a><?php endif; ?><button type="button" class="button secondary" data-qcm-fullscreen><?=e(t('Plein écran'))?></button></div></header>
      <p class="eyebrow"><?=e(t('QCM en classe'))?> · <?=e($context['item']['title'])?></p>
      <p data-qcm-screen-status role="status"></p><p data-qcm-live-error role="alert" hidden></p>
      <div data-qcm-live-content><p><?=e(t('Chargement de la séance…'))?></p></div>
      <p data-qcm-vote-status role="status"></p><div class="qcm-live-controls" data-qcm-live-controls></div>
      <noscript><?=e(t('Activez JavaScript pour participer à la séance.'))?></noscript>
    </main><script src="assets/qcm-modes.js?v=6"></script></body></html><?php
}

function qcm_class_labels(): array
{
    $strings=Qcm::uiLabels();
    foreach(['waiting'=>'En attente de l’enseignant…','ready'=>'Préparez la séance, puis ouvrez la première question.',
        'start'=>'Créer une séance','open'=>'Ouvrir la première question','close'=>'Fermer les réponses et afficher les résultats',
        'reveal'=>'Révéler la correction','next'=>'Question suivante','finish'=>'Terminer la séance','restart'=>'Nouvelle séance',
        'send'=>'Envoyer ma réponse','saved'=>'Réponse enregistrée. Vous pouvez la modifier tant que la question est ouverte.',
        'closed'=>'Réponses closes','finished'=>'Séance terminée','question'=>'Question :number sur :total',
        'count'=>'Réponses : :count / :total apprenants','multiple'=>'Plusieurs choix possibles. Les nombres indiquent les apprenants ayant choisi chaque proposition.',
        'single'=>'Une seule réponse possible.','pending'=>'Envoi en cours…','error'=>'Connexion interrompue. Les réponses sont désactivées jusqu’à la reconnexion.',
        'right'=>'Réponse juste','wrong'=>'Réponse fausse','neutral'=>'Réponse neutre','login'=>'Session expirée. Reconnectez-vous puis revenez à cette séance.',
        'join'=>'Apprenants : ouvrez cette étape dans votre parcours et choisissez « Rejoindre la séance ».'] as $name=>$label)$strings[$name]=t($label);
    return $strings;
}
