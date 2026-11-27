<?php
declare(strict_types=1);

function course_enrollment_candidates_http(): never
{
    header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: private, no-store');
    $teacher=actor();
    if(!$teacher||$teacher['role']!=='teacher'){http_response_code($teacher?403:401);echo '{}';exit;}
    session_write_close();
    try{
        foreach(['course','search','group','filter_course','facets'] as $key)if(isset($_GET[$key])&&!is_string($_GET[$key]))throw new InvalidArgumentException('Requête invalide.');
        echo json_encode(course_enrollment_candidates(db(),(int)($_GET['course']??0),(int)$teacher['id'],$_GET),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
    }catch(InvalidArgumentException $error){http_response_code(403);echo json_encode(['error'=>t($error->getMessage())]);}
    catch(Throwable $error){error_log('liike enrollment directory: '.$error->getMessage());http_response_code(503);echo json_encode(['error'=>t('Chargement impossible. Réessayez.')]);}
    exit;
}

function render_pathway_enrollment_modal(array $course): void
{
    $labels=['loading'=>t('Chargement…'),'error'=>t('Chargement impossible. Réessayez.'),'empty'=>t('Aucun apprenant ne correspond à ces critères.'),
        'enrolled'=>t('Déjà inscrit au parcours'),'more'=>t('Affinez les filtres pour voir les autres apprenants.'),'selected'=>t(':count apprenant(s) sélectionné(s)'),'remove'=>t('Retirer')];
    ?><div class="modal fade" id="pathway-enrollment-modal" tabindex="-1" aria-labelledby="pathway-enrollment-title" aria-hidden="true" data-course-enrollment="<?=(int)$course['id']?>" data-labels="<?=e(json_encode($labels,JSON_UNESCAPED_UNICODE))?>">
      <div class="modal-dialog modal-lg modal-dialog-scrollable"><form method="post" class="modal-content">
        <?=csrf_field()?><input type="hidden" name="action" value="enroll_students"><input type="hidden" name="course_id" value="<?=(int)$course['id']?>"><input type="hidden" name="return_view" value="pathway">
        <div class="modal-header"><div><p class="eyebrow mb-1"><?=e($course['title'])?></p><h2 class="modal-title fs-5" id="pathway-enrollment-title"><?=e(t('Inscrire des apprenants'))?></h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?=e(t('Fermer'))?>"></button></div>
        <div class="modal-body">
          <label class="field"><span><?=e(t('Rechercher'))?></span><input type="search" data-enrollment-search placeholder="<?=e(t('Rechercher par nom ou groupe'))?>" autocomplete="off"></label>
          <div class="two-fields"><label class="field"><span><?=e(t('Parcours'))?></span><select data-enrollment-course><option value="0"><?=e(t('Tous les parcours'))?></option></select></label><label class="field"><span><?=e(t('Groupe classe'))?></span><select data-enrollment-group><option value=""><?=e(t('Tous les groupes'))?></option></select></label></div>
          <p data-enrollment-feedback role="status"></p><button type="button" class="btn btn-outline-secondary mb-2" data-enrollment-retry hidden><?=e(t('Réessayer'))?></button>
          <div class="bulk-list enrollment-candidates" data-enrollment-list></div>
          <p data-enrollment-count aria-live="polite"></p><div class="enrollment-selection" data-enrollment-selected></div>
          <noscript><p><?=e(t('Activez JavaScript pour sélectionner les apprenants.'))?></p></noscript>
          <details class="mt-3"><summary><?=e(t('Invitation au parcours'))?></summary>
            <label class="field mt-2"><span><?=e(t('Code du parcours'))?></span><div class="copy-field"><input id="pathway-invite-code" readonly value="<?=e($course['code'])?>"><button type="button" class="btn btn-outline-secondary" data-copy-target="pathway-invite-code" data-copy-label="<?=e(t('Copié !'))?>"><i class="bi bi-copy" aria-hidden="true"></i><span><?=e(t('Copier'))?></span></button></div></label>
            <label class="field"><span><?=e(t('Lien d’invitation'))?></span><div class="copy-field"><input id="pathway-invite-link" readonly value="<?=e(course_invitation_url((string)$course['code']))?>"><button type="button" class="btn btn-outline-secondary" data-copy-target="pathway-invite-link" data-copy-label="<?=e(t('Copié !'))?>"><i class="bi bi-copy" aria-hidden="true"></i><span><?=e(t('Copier'))?></span></button></div></label>
            <p class="muted-copy"><?=e(t('Un apprenant connecté rejoint directement le parcours ; un nouvel apprenant crée d’abord son compte et valide son courriel.'))?></p>
          </details>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal"><?=e(t('Fermer'))?></button><button class="button primary" data-enrollment-submit disabled><?=e(t('Inscrire la sélection'))?></button></div>
      </form></div>
    </div><script src="assets/course-enrollment.js?v=1" defer></script><?php
}
