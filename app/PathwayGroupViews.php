<?php
declare(strict_types=1);

function render_pathway_group_start(array $group,array $items,bool $editable=false,string $token=''): void
{
    $groupId=(int)$group['id'];$courseId=(int)$group['course_id'];
    $stateKey=(int)actor()['id'].':'.($editable?'editor':'student').':'.$courseId.':'.$groupId;
    ?><details class="pathway-group" data-pathway-group="<?=$groupId?>" data-group-state-key="<?=e($stateKey)?>" open>
      <summary data-pathway-group-target="<?=$groupId?>"><i class="bi bi-chevron-right pathway-group-chevron" aria-hidden="true"></i><span><?=e($group['title'])?></span><small><?=count($items)?></small></summary>
      <?php if($editable): ?><div class="pathway-group-tools">
        <form method="post" class="pathway-group-rename"><?=csrf_field()?><input type="hidden" name="action" value="save_pathway_group"><input type="hidden" name="operation" value="rename"><input type="hidden" name="course_id" value="<?=$courseId?>"><input type="hidden" name="group_id" value="<?=$groupId?>"><input type="hidden" name="structure_token" value="<?=e($token)?>"><input name="title" value="<?=e($group['title'])?>" required maxlength="120" aria-label="<?=e(t('Titre du regroupement'))?>"><button class="button secondary" type="submit" aria-label="<?=e(t('Renommer le regroupement'))?>" title="<?=e(t('Renommer le regroupement'))?>"><i class="bi bi-check2" aria-hidden="true"></i></button></form>
        <form method="post" onsubmit="return confirm(<?=e(json_encode(t('Supprimer ce regroupement ? Ses étapes seront conservées.'),JSON_HEX_APOS|JSON_HEX_QUOT))?>)"><?=csrf_field()?><input type="hidden" name="action" value="save_pathway_group"><input type="hidden" name="operation" value="delete"><input type="hidden" name="course_id" value="<?=$courseId?>"><input type="hidden" name="group_id" value="<?=$groupId?>"><input type="hidden" name="structure_token" value="<?=e($token)?>"><button class="button secondary" aria-label="<?=e(t('Supprimer le regroupement'))?>" title="<?=e(t('Supprimer le regroupement'))?>"><i class="bi bi-trash3" aria-hidden="true"></i></button></form>
      </div><?php endif; ?>
      <div class="pathway-group-items">
      <?php if($editable&&!$items): ?><p class="pathway-group-empty" data-pathway-group-target="<?=$groupId?>"><?=e(t('Glissez des étapes dans ce regroupement.'))?></p><?php endif;
}

function render_pathway_group_end(): void
{
    echo '</div></details>';
}

function render_pathway_group_choice(array $item,array $groups,string $token): void
{
    if(!$groups)return;
    ?><li><hr class="dropdown-divider"></li><li><form method="post" class="pathway-group-choice px-3 py-2"><?=csrf_field()?><input type="hidden" name="action" value="reorder_pathway_item"><input type="hidden" name="item_id" value="<?=(int)$item['id']?>"><input type="hidden" name="position" value="0"><input type="hidden" name="structure_token" value="<?=e($token)?>"><label class="field"><span><?=e(t('Regroupement'))?></span><select name="group_id"><option value="0"><?=e(t('Sans regroupement'))?></option><?php foreach($groups as $group): ?><option value="<?=(int)$group['id']?>" <?=(int)($item['group_id']??0)===(int)$group['id']?'selected':''?>><?=e($group['title'])?></option><?php endforeach; ?></select></label><button class="button secondary"><?=e(t('Déplacer'))?></button></form></li><?php
}

function render_pathway_group_add(int $courseId,string $token): void
{
    ?><details class="add-panel pathway-group-add"><summary>＋ <?=e(t('Ajouter un regroupement'))?></summary><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="save_pathway_group"><input type="hidden" name="operation" value="create"><input type="hidden" name="course_id" value="<?=$courseId?>"><input type="hidden" name="structure_token" value="<?=e($token)?>"><label class="field"><span><?=e(t('Titre du regroupement'))?></span><input name="title" required maxlength="120"></label><button class="button primary"><?=e(t('Ajouter'))?></button></form></details><?php
}
