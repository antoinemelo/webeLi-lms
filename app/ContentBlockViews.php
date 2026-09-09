<?php

declare(strict_types=1);

function content_block_labels(): array
{
    return ['markdown'=>'Texte Markdown','image'=>'Image','file'=>'Document','media'=>'Vidéo / audio','iframe'=>'Intégration externe (iframe)','submission'=>'Travail à rendre'];
}

function content_block_editor_config(): array
{
    $labels=[
        'markdown'=>['body'=>'Texte Markdown','caption'=>'Titre','rows'=>8],
        'image'=>['body'=>'Adresse de l’image','caption'=>'Légende (facultative)','rows'=>2],
        'file'=>['body'=>'Adresse du document','caption'=>'Intitulé du téléchargement','rows'=>2],
        'media'=>['body'=>'Lien du média ou code du lecteur','caption'=>'Titre du média (facultatif)','rows'=>3],
        'iframe'=>['body'=>'Adresse ou code iframe','caption'=>'Titre de l’intégration','rows'=>4],
        'submission'=>['body'=>'Consigne en Markdown (texte élève limité à 512 caractères)','caption'=>'Titre du travail','rows'=>6],
    ];
    foreach($labels as &$config)foreach(['body','caption'] as $key)$config[$key]=t($config[$key]);unset($config);
    return ['types'=>$labels,'importImage'=>t('Importer une image'),'importFile'=>t('Importer un document'),
        'switchWarning'=>t('Le contenu actuel n’est pas compatible avec ce type. Changer de type ? Vous pourrez retrouver votre saisie en revenant au type précédent avant d’enregistrer.'),
        'fileCleared'=>t('Le fichier sélectionné a été retiré. Sélectionnez-le à nouveau si nécessaire.'),
        'previewError'=>t('L’aperçu ne peut pas être chargé.'),'imageError'=>t('L’image ne peut pas être affichée. Vérifiez son adresse ou son format.')];
}

function render_content_block_editor(int $index,array $block): void
{
    $id=(int)($block['id']??0);$kind=ContentBlock::kind($block);$config=content_block_editor_config();$labels=$config['types'][$kind];
    $body=(string)($block['body']??'');$upload=in_array($kind,['image','file'],true);$source=$block['_source']??(str_starts_with($body,'uploads/')||$body===''?'upload':'url');
    $showBody=!$upload||$source==='url';$parsed=$kind==='iframe'?Embed::parse($body):null;
    $height=$block['embed_height']??($parsed['height']??450);$height=max(100,min(2000,(int)$height));
    $alt=$block['image_alt']??($block['caption']??'');
    ?><article class="block-editor" data-content-kind="<?=e($kind)?>" data-content-config="<?=e(json_encode($config,JSON_UNESCAPED_UNICODE))?>"<?=$id?' data-edit-lock data-lock-type="page_block" data-lock-id="'.$id.'"':''?>>
      <input type="hidden" name="block_id[]" value="<?=$id?>"><input type="hidden" name="block_revision[]" value="<?=(int)($block['revision']??0)?>">
      <header><span class="drag">⠿</span><select name="block_type[]" data-block-type aria-label="<?=e(t('Type de bloc'))?>"><?php foreach(content_block_labels() as $value=>$label): ?><option value="<?=$value?>" <?=$kind===$value?'selected':''?>><?=e(t($label))?></option><?php endforeach; ?></select><span class="edit-lock-status" data-lock-status></span><button type="button" class="release-edit-lock btn btn-sm btn-outline-secondary" data-release-edit-lock hidden><i class="bi bi-unlock"></i> <?=e(t('Libérer'))?></button><button type="button" data-remove-block aria-label="<?=e(t('Supprimer'))?>">×</button></header>
      <div class="block-fields">
        <label class="field" data-block-source-group<?=$upload?'':' hidden'?>><span><?=e(t('Source'))?></span><select name="block_source[]" data-block-source><option value="upload" <?=$source==='upload'?'selected':''?>><?=e(t($kind==='image'?'Importer une image':'Importer un document'))?></option><option value="url" <?=$source==='url'?'selected':''?>><?=e(t('Utiliser une adresse'))?></option></select></label>
        <label class="field block-body-field" data-block-body-group<?=$showBody?'':' hidden'?>><span data-block-body-label<?=in_array($kind,['markdown','media'],true)?' hidden':''?>><?=e($labels['body'])?></span><textarea name="block_body[]" data-block-body aria-label="<?=e($labels['body'])?>" rows="<?=$labels['rows']?>"><?=e($body)?></textarea></label>
        <label class="file-upload field" data-block-upload-group<?=$upload&&$source==='upload'?'':' hidden'?>><span data-block-upload-label><?=e(t($kind==='image'?'Importer une image':'Importer un document'))?></span><input type="file" name="block_file[<?=$index?>]" data-block-file <?=$kind==='image'?'accept="image/jpeg,image/png,image/gif,image/webp,image/avif,image/bmp"':''?><?=$upload&&$source==='upload'?'':' disabled'?>><small><?=e(t('Taille maximale : :size',['size'=>ContentBlock::sizeLabel(ContentBlock::uploadLimit())]))?></small><small data-block-current-file><?=e($upload&&$body!==''?basename($body).' · '.ContentBlock::fileInfo($block,APR_PUBLIC_ROOT):'')?></small></label>
        <label class="field" data-block-alt-group<?=$kind==='image'?'':' hidden'?>><span><?=e(t('Description alternative de l’image'))?></span><input name="block_image_alt[]" data-block-alt maxlength="512" value="<?=e($alt)?>"></label>
        <label class="field" data-block-caption-group<?=$kind==='markdown'?' hidden':''?>><span data-block-caption-label><?=e($labels['caption'])?></span><input name="block_caption[]" data-block-caption value="<?=e($block['caption']??'')?>"></label>
        <label class="field block-height-field" data-block-height-group<?=$kind==='iframe'?'':' hidden'?>><span><?=e(t('Hauteur d’affichage (pixels)'))?></span><input type="number" name="block_embed_height[]" data-block-height min="100" max="2000" value="<?=$height?>"></label>
        <div class="submission-settings"<?=$kind==='submission'?'':' hidden'?>><label class="field"><span><?=e(t('Réponse attendue'))?></span><select name="block_submission_mode[]"><?php foreach(WorkSubmission::MODES as $mode): ?><option value="<?=$mode?>" <?=($block['submission_mode']??'link')===$mode?'selected':''?>><?=e(work_mode_label($mode))?></option><?php endforeach; ?></select></label><label class="field"><span><?=e(t('Remise'))?></span><select name="block_submission_required[]"><option value="1" <?=($block['submission_required']??1)?'selected':''?>><?=e(t('Obligatoire'))?></option><option value="0" <?=!($block['submission_required']??1)?'selected':''?>><?=e(t('Facultatif'))?></option></select></label></div>
        <div class="block-preview-actions" data-block-markdown-actions<?=$kind==='markdown'?'':' hidden'?>><button type="button" class="button secondary" data-block-preview><?=e(t('Aperçu'))?></button><button type="button" class="button secondary" data-block-preview-close hidden><?=e(t('Fermer l’aperçu'))?></button></div>
        <div class="content-block block-author-preview" data-block-author-preview hidden></div>
        <p class="block-feedback" data-block-feedback role="status" hidden></p>
      </div>
    </article><?php
}
