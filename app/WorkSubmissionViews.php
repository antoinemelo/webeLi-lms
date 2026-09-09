<?php

declare(strict_types=1);

function work_mode_label(string $mode): string
{
    return t(match($mode){'text'=>'Texte court','both'=>'Lien et commentaire',default=>'Lien vers un document'});
}

function render_work_content(array $work): void
{
    if($work['url']!==''&&WorkSubmission::validUrl($work['url'])){?><p><a href="<?=e($work['url'])?>" target="_blank" rel="noopener noreferrer"><?=e($work['url'])?></a></p><?php }
    if($work['body']!==''){?><p class="work-answer"><?=nl2br(e($work['body']))?></p><?php }
}

function render_work_block(array $block,?int $studentId=null,?int $itemId=null): void
{
    $preview=$studentId===null;$work=$preview?null:WorkSubmission::current(db(),$studentId,$itemId,(int)$block['id']);
    $rejected=$_SESSION['work_rejected']??null;
    if(!$preview&&$rejected&&(int)$rejected['student_id']===$studentId&&(int)$rejected['item_id']===$itemId&&(int)$rejected['block_id']===(int)$block['id']){
        if(($work['status']??'draft')!=='submitted'){$work??=[];$work['url']=$rejected['url'];$work['body']=$rejected['body'];}
        unset($_SESSION['work_rejected']);
    }
    $submitted=($work['status']??'')==='submitted';$mode=$block['submission_mode'];
    $storage=$preview?'':$studentId.':'.$itemId.':'.$block['id'];
    $messages=[];
    foreach(['saving'=>'Enregistrement du brouillon…','saved'=>'Brouillon enregistré','error'=>'Enregistrement impossible. Votre saisie est conservée dans ce navigateur.','conflict'=>'Le travail a changé ailleurs. Copiez votre saisie puis rechargez la page.','limit'=>'Le texte est limité à 512 caractères.','confirm'=>'Rendre ce travail ? Vous pourrez le modifier si votre enseignant autorise une nouvelle remise.'] as $key=>$label)$messages[$key]=t($label);
    ?><section class="work-card" id="work-<?=$block['id']?>"<?=$preview?'':' data-work-storage="'.e($storage).'"'?>>
      <header><b><i class="bi bi-send" aria-hidden="true"></i> <?=e($block['caption']?:t('Travail à rendre'))?></b><small><?=e(work_mode_label($mode))?> · <?=e(t($block['submission_required']?'Obligatoire':'Facultatif'))?></small></header>
      <div class="work-prompt"><?=Markdown::render($block['body'])?></div>
      <?php if($submitted): ?><p class="work-submitted"><i class="bi bi-check-circle" aria-hidden="true"></i> <?=e(t('Travail rendu'))?> · <?=e(date_fr($work['submitted_at'],true))?></p><?php render_work_content($work); ?>
      <?php else: ?>
      <?php if(!$preview): ?><form method="post" data-work-form data-work-messages="<?=e(json_encode($messages,JSON_UNESCAPED_UNICODE))?>"><?=csrf_field()?><input type="hidden" name="action" value="submit_work"><input type="hidden" name="item_id" value="<?=$itemId?>"><input type="hidden" name="block_id" value="<?=$block['id']?>"><input type="hidden" name="block_revision" value="<?=$block['revision']?>"><input type="hidden" name="revision" value="<?=(int)($work['revision']??0)?>"><?php endif; ?>
        <fieldset<?=$preview?' disabled':''?>>
        <?php if($mode!=='text'): ?><label class="field"><span><?=e(t('Lien vers un document'))?></span><input type="url" name="url" data-work-url maxlength="2048" placeholder="https://…" value="<?=e($work['url']??'')?>" required></label><?php endif; ?>
        <?php if($mode!=='link'): ?><label class="field"><span><?=e(t($mode==='both'?'Commentaire':'Texte court'))?></span><textarea name="body" data-work-text rows="5" required aria-describedby="work-count-<?=$block['id']?>"><?=e($work['body']??'')?></textarea><small id="work-count-<?=$block['id']?>" data-work-counter><?=mb_strlen($work['body']??'')?> / 512</small></label><?php endif; ?>
        <div class="work-actions"><button type="submit" class="button primary"<?=$preview?' disabled':''?>><?=e(t('Rendre mon travail'))?></button><?php if(!$preview): ?><button type="submit" name="action" value="save_work" data-work-save formnovalidate class="button secondary"><?=e(t('Enregistrer le brouillon'))?></button><?php endif; ?></div>
        </fieldset>
        <?php if($preview): ?><small><?=e(t('Interaction désactivée dans l’aperçu'))?></small><?php else: ?><p class="work-save-status" data-work-status role="status"><?=e(t($work?'Brouillon enregistré':'Brouillon privé, visible uniquement par vous.'))?></p><button type="button" class="button secondary" data-work-reload hidden><?=e(t('Recharger la version enregistrée'))?></button></form><?php endif; ?>
      <?php endif; ?>
    </section><?php
}

function render_work_reviews(PDO $pdo,int $studentId,int $itemId,int $enrollmentId): void
{
    $q=$pdo->prepare("SELECT b.*,w.url,w.body AS answer,w.status,w.revision AS answer_revision,w.submitted_at FROM pathway_items pi
        JOIN page_blocks b ON b.page_id=pi.page_id AND b.type='submission'
        LEFT JOIN work_submissions w ON w.page_block_id=b.id AND w.pathway_item_id=pi.id AND w.student_id=?
        WHERE pi.id=? ORDER BY b.position,b.id");$q->execute([$studentId,$itemId]);
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $block){
        ?><section class="work-review"><b><?=e($block['caption']?:t('Travail à rendre'))?></b>
        <?php if($block['status']==='submitted'): ?><small><?=e(t('Travail rendu'))?> · <?=e(date_fr($block['submitted_at'],true))?></small>
          <?php render_work_content(['url'=>$block['url'],'body'=>$block['answer']]); ?>
          <form method="post"><?=csrf_field()?><input type="hidden" name="action" value="reopen_work"><input type="hidden" name="enrollment_id" value="<?=$enrollmentId?>"><input type="hidden" name="item_id" value="<?=$itemId?>"><input type="hidden" name="block_id" value="<?=$block['id']?>"><input type="hidden" name="revision" value="<?=$block['answer_revision']?>"><button class="button secondary btn-sm"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> <?=e(t('Autoriser une nouvelle remise'))?></button></form>
        <?php else: ?><small><?=e(t('En attente de remise'))?> · <?=e(t($block['submission_required']?'Obligatoire':'Facultatif'))?></small><?php endif; ?>
        <?php $history=$pdo->prepare('SELECT * FROM work_submission_versions WHERE student_id=? AND pathway_item_id=? AND page_block_id=? ORDER BY id DESC');$history->execute([$studentId,$itemId,$block['id']]);$versions=$history->fetchAll(PDO::FETCH_ASSOC);if($block['status']==='submitted')array_shift($versions);if($versions): ?>
          <details class="work-history"><summary><?=e(t('Remises précédentes'))?> (<?=count($versions)?>)</summary><?php foreach($versions as $version): ?><article><small><?=e(date_fr($version['submitted_at'],true))?><?= $version['reopened_at']?' · '.e(t('Réouvert')).' '.e(date_fr($version['reopened_at'],true)):''?></small><?php render_work_content($version); ?></article><?php endforeach; ?></details>
        <?php endif; ?></section><?php
    }
}
