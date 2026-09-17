<?php
declare(strict_types=1);

function render_pathway_group_start(array $group,array $items,bool $editable=false,string $token=''): void
{
    $groupId=(int)$group['id'];$courseId=(int)$group['course_id'];
    $stateKey=(int)actor()['id'].':'.($editable?'editor':'student').':'.$courseId.':'.$groupId;
    ?><section id="pathway-group-<?=$groupId?>" class="pathway-group" data-pathway-group="<?=$groupId?>" data-group-state-key="<?=e($stateKey)?>">
      <div class="pathway-group-header d-flex align-items-center gap-2" data-pathway-group-target="<?=$groupId?>" <?=$editable?'data-group-drag-handle tabindex="0"':''?> <?=$editable?'aria-label="'.e(t('Déplacer le regroupement')).'"':''?>>
        <?php if($editable): ?><i class="bi bi-grip-vertical pathway-group-grip" aria-hidden="true"></i><?php endif; ?>
        <button class="pathway-group-toggle btn d-flex align-items-center gap-2 flex-grow-1 text-start" type="button" data-group-toggle aria-expanded="true" aria-controls="pathway-group-content-<?=$groupId?>"><i class="bi bi-chevron-down pathway-group-chevron" aria-hidden="true"></i><span class="pathway-group-title"><?=e($group['title'])?></span><span class="badge rounded-pill text-bg-light pathway-group-count"><?=count($items)?></span></button>
        <?php if($editable): ?><div class="pathway-group-actions d-flex align-items-center gap-1" data-group-actions>
          <button class="btn btn-sm btn-light" type="button" data-bs-toggle="modal" data-bs-target="#pathway-group-edit-<?=$groupId?>" aria-label="<?=e(t('Renommer le regroupement'))?>" title="<?=e(t('Renommer le regroupement'))?>"><i class="bi bi-pencil" aria-hidden="true"></i></button>
          <div class="dropdown"><button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?=e(t('Actions du regroupement'))?>"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button><ul class="dropdown-menu dropdown-menu-end">
            <li><button class="dropdown-item" type="submit" form="pathway-group-order-<?=$groupId?>" name="direction" value="previous"><i class="bi bi-arrow-up me-2" aria-hidden="true"></i><?=e(t('Monter'))?></button></li>
            <li><button class="dropdown-item" type="submit" form="pathway-group-order-<?=$groupId?>" name="direction" value="next"><i class="bi bi-arrow-down me-2" aria-hidden="true"></i><?=e(t('Descendre'))?></button></li>
            <li><hr class="dropdown-divider"></li><li><form method="post" onsubmit="return confirm(<?=e(json_encode(t('Supprimer ce regroupement ? Ses étapes seront conservées.'),JSON_HEX_APOS|JSON_HEX_QUOT))?>)"><?=csrf_field()?><input type="hidden" name="action" value="save_pathway_group"><input type="hidden" name="operation" value="delete"><input type="hidden" name="course_id" value="<?=$courseId?>"><input type="hidden" name="group_id" value="<?=$groupId?>"><input type="hidden" name="structure_token" value="<?=e($token)?>"><button class="dropdown-item text-danger"><i class="bi bi-trash3 me-2" aria-hidden="true"></i><?=e(t('Supprimer le regroupement'))?></button></form></li>
          </ul></div>
        </div><?php endif; ?>
      </div>
      <?php if($editable): ?><form method="post" id="pathway-group-order-<?=$groupId?>" data-group-order-form hidden><?=csrf_field()?><input type="hidden" name="action" value="reorder_pathway_group"><input type="hidden" name="course_id" value="<?=$courseId?>"><input type="hidden" name="group_id" value="<?=$groupId?>"><input type="hidden" name="structure_token" value="<?=e($token)?>"><input type="hidden" name="target" value=""><input type="hidden" name="after" value="0"></form><?php endif; ?>
      <div id="pathway-group-content-<?=$groupId?>" class="collapse show pathway-group-content"><div class="pathway-group-items">
      <?php if($editable&&!$items): ?><p class="pathway-group-empty" data-pathway-group-target="<?=$groupId?>"><?=e(t('Glissez des étapes dans ce regroupement.'))?></p><?php endif;
}

function render_pathway_group_end(): void
{
    echo '</div></div></section>';
}

function render_pathway_group_modals(array $groups,string $token): void
{
    foreach($groups as $group): $groupId=(int)$group['id']; ?>
    <div class="modal fade" id="pathway-group-edit-<?=$groupId?>" tabindex="-1" aria-labelledby="pathway-group-edit-title-<?=$groupId?>" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post" class="pathway-group-rename"><?=csrf_field()?><input type="hidden" name="action" value="save_pathway_group"><input type="hidden" name="operation" value="rename"><input type="hidden" name="course_id" value="<?=(int)$group['course_id']?>"><input type="hidden" name="group_id" value="<?=$groupId?>"><input type="hidden" name="structure_token" value="<?=e($token)?>"><div class="modal-header"><h2 class="modal-title fs-5" id="pathway-group-edit-title-<?=$groupId?>"><?=e(t('Renommer le regroupement'))?></h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="<?=e(t('Fermer'))?>"></button></div><div class="modal-body"><label class="form-label" for="pathway-group-title-<?=$groupId?>"><?=e(t('Titre du regroupement'))?></label><input class="form-control" id="pathway-group-title-<?=$groupId?>" name="title" value="<?=e($group['title'])?>" required maxlength="120"></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal"><?=e(t('Annuler'))?></button><button class="btn btn-primary"><?=e(t('Enregistrer'))?></button></div></form></div></div></div>
    <?php endforeach;
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
