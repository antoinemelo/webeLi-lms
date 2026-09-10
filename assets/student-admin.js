(() => {
  const root = document.querySelector('#student-admin-modal');
  if (!root) return;
  const labels = JSON.parse(root.dataset.adminLabels), content = root.querySelector('[data-student-admin-content]');
  const editorRoot = document.querySelector('#student-followup-modal'), form = editorRoot.querySelector('form');
  const modal = bootstrap.Modal.getOrCreateInstance(root), editor = bootstrap.Modal.getOrCreateInstance(editorRoot);
  let templates = JSON.parse(editorRoot.dataset.followupTemplates);
  const input = name => form.elements.namedItem(name);
  const feedback = form.querySelector('[data-followup-feedback]'), preview = form.querySelector('[data-followup-preview]');
  let student = 0, page = 1, tab = 'information', loadSerial = 0, historySerial = 0, previewSerial = 0;
  let dirty = false, saving = false, returnToStudent = 0;
  const text = (node, value = '') => { node.textContent = value; node.hidden = !value; };
  const request = async (action, data = new FormData()) => {
    data.set('action', action); data.set('token', document.body.dataset.csrf || '');
    const response = await fetch(location.href, { method: 'POST', body: data, headers: { Accept: 'application/json' } });
    let result; try { result = await response.json(); } catch { throw new Error(labels.error); }
    if (!response.ok || result.error) throw new Error(result.error || labels.error); return result;
  };
  const payload = values => { const data = new FormData(); for (const [key, value] of Object.entries(values)) data.set(key, String(value)); return data; };
  const loadHistory = async (next = 1) => {
    const target = content.querySelector('[data-student-history]'); if (!target) return;
    page = next; const serial = ++historySerial; text(target, labels.loading);
    try { const result = await request('load_student_history', payload({ student_id: student, page })); if (serial === historySerial && target.isConnected) target.innerHTML = result.html; }
    catch (error) { if (serial === historySerial) text(target, error.message); }
  };
  const openStudent = async (id, desiredTab = 'information') => {
    student = Number(id); tab = desiredTab; const serial = ++loadSerial; historySerial++;
    content.innerHTML = '<div class="modal-header"><h2 class="modal-title fs-5" id="student-admin-title"></h2><div class="dropdown"><button type="button" class="btn btn-light" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><button type="button" class="dropdown-item" data-bs-dismiss="modal"></button></li></ul></div></div><div class="modal-body"></div>';
    content.querySelector('h2').textContent = labels.loading;
    content.querySelector('[data-bs-toggle]').setAttribute('aria-label', labels.actions);
    content.querySelector('[data-bs-dismiss]').textContent = labels.close;
    modal.show();
    try {
      const result = await request('load_student_admin', payload({ student_id: student })); if (serial !== loadSerial) return;
      content.innerHTML = result.html;
      if (desiredTab !== 'information') bootstrap.Tab.getOrCreateInstance(content.querySelector(`[data-admin-tab="${desiredTab}"]`)).show();
    } catch (error) { if (serial === loadSerial) content.querySelector('.modal-body').textContent = error.message; }
  };
  root.addEventListener('shown.bs.tab', event => { tab = event.target.dataset.adminTab; if (tab === 'history') void loadHistory(1); });
  root.addEventListener('hidden.bs.modal', () => { loadSerial++; historySerial++; });
  const invalidate = () => { previewSerial++; preview.hidden = true; text(feedback); dirty = true; };
  const participants = [...form.querySelectorAll('[name="students[]"]')];
  const all = form.querySelector('[data-followup-all]');
  const syncAll = () => {
    const visible = participants.filter(control => !control.closest('[data-followup-participant]').hidden);
    const checked = visible.filter(control => control.checked).length;
    all.checked = !!visible.length && checked === visible.length; all.indeterminate = checked > 0 && !all.checked;
  };
  const freshKey = () => [...crypto.getRandomValues(new Uint8Array(24))].map(n => n.toString(16).padStart(2, '0')).join('');
  const openEditor = (selected = [], entry = null, kind = 'meeting') => {
    form.reset(); input('request_key').value = freshKey(); input('followup_id').value = entry?.id || '0'; input('revision').value = entry?.revision || '0';
    input('kind').value = kind; input('course_id').value = editorRoot.dataset.followupCourse || '0';
    for (const key of ['kind', 'title', 'body', 'course_id', 'occurred_at']) if (entry && entry[key] != null) input(key).value = entry[key];
    participants.forEach(control => { control.checked = selected.includes(Number(control.value)); control.closest('[data-followup-participant]').hidden = false; });
    text(feedback); preview.hidden = true; previewSerial++; dirty = false; filterParticipants();
    returnToStudent = root.classList.contains('show') ? student : 0;
    if (returnToStudent) { root.addEventListener('hidden.bs.modal', () => editor.show(), { once: true }); modal.hide(); }
    else editor.show();
  };
  document.addEventListener('open-student-followup', event => {
    if ([...input('kind').options].some(option => option.value === event.detail)) openEditor([], null, event.detail);
  });
  document.addEventListener('message-templates-updated', event => {
    templates = event.detail; const choose = input('template_id'), value = choose.value;
    choose.replaceChildren(choose.options[0], ...templates.map(template => new Option(template.name, String(template.id))));
    choose.value = templates.some(template => String(template.id) === value) ? value : '0';
  });
  document.addEventListener('click', event => {
    const manage = event.target.closest('[data-student-manage]');
    if (manage) void openStudent(manage.dataset.studentManage);
    const add = event.target.closest('[data-add-followup]');
    if (add) openEditor(add.dataset.addFollowup ? [Number(add.dataset.addFollowup)] : []);
    const edit = event.target.closest('[data-edit-followup]');
    if (edit) { const entry = JSON.parse(edit.dataset.editFollowup); openEditor(entry.students, entry); }
    const pagination = event.target.closest('[data-history-page]');
    if (pagination && !pagination.disabled) void loadHistory(Number(pagination.dataset.historyPage));
  });
  root.addEventListener('click', async event => {
    const button = event.target.closest('[data-delete-followup]');
    if (!button || button.disabled || !confirm(labels.deleteConfirm)) return;
    button.disabled = true;
    try { await request('delete_student_followup', payload({ followup_id: button.dataset.deleteFollowup, revision: button.dataset.revision })); await loadHistory(page); }
    catch (error) { button.disabled = false; alert(error.message); }
  });
  root.addEventListener('submit', event => {
    const target = event.target;
    const filters = document.querySelector('[data-student-filters]');
    const values = { return_search: filters?.querySelector('[data-student-search]')?.value || '', return_group: filters?.querySelector('[data-student-group]')?.value || '', return_course: filters?.querySelector('[data-student-course]')?.value || '', return_admin_tab: tab };
    for (const [name, value] of Object.entries(values)) { let control = target.elements.namedItem(name); if (!control) { control = document.createElement('input'); control.type = 'hidden'; control.name = name; target.append(control); } control.value = value; }
  });
  form.addEventListener('input', event => { if (event.target !== all && !event.target.matches('[data-followup-search]')) { invalidate(); syncAll(); } });
  form.addEventListener('change', event => { if (event.target !== all) { invalidate(); syncAll(); } });
  all.addEventListener('change', () => { const checked = all.checked; participants.filter(control => !control.closest('[data-followup-participant]').hidden).forEach(control => { control.checked = checked; }); invalidate(); syncAll(); });
  const filterParticipants = () => {
    const fold = value => value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase();
    const search = fold(form.querySelector('[data-followup-search]').value.trim()), course = input('course_id').value;
    participants.forEach(control => {
      const row = control.closest('[data-followup-participant]');
      row.hidden = !fold(row.textContent).includes(search) || (course !== '0' && !row.dataset.participantCourses.split(',').includes(course));
    }); syncAll();
  };
  form.querySelector('[data-followup-search]').addEventListener('input', filterParticipants);
  input('course_id').addEventListener('change', () => {
    const course = input('course_id').value;
    if (course !== '0') participants.forEach(control => {
      if (!control.closest('[data-followup-participant]').dataset.participantCourses.split(',').includes(course)) control.checked = false;
    });
    filterParticipants();
  });
  input('template_id').addEventListener('change', () => {
    const template = templates.find(row => String(row.id) === input('template_id').value);
    if (template) { input('title').value = template.title; input('body').value = template.body; invalidate(); }
  });
  let focused = input('body');
  form.addEventListener('focusin', event => { if (['title', 'body'].includes(event.target.name)) focused = event.target; });
  form.querySelectorAll('[data-message-variable]').forEach(button => button.addEventListener('click', () => { focused.setRangeText(`{${button.dataset.messageVariable}}`, focused.selectionStart, focused.selectionEnd, 'end'); focused.focus(); invalidate(); }));
  form.querySelector('[data-followup-preview-button]').addEventListener('click', async () => {
    if (!form.reportValidity() || saving) return;
    if (!participants.some(input => input.checked)) { text(feedback, labels.empty); return; }
    const serial = ++previewSerial; text(feedback, labels.loading);
    try {
      const result = await request('preview_student_followup', new FormData(form)); if (serial !== previewSerial) return;
      preview.replaceChildren(); const heading = document.createElement('h3'); heading.textContent = result.title; preview.append(heading);
      const body = document.createElement('div'); body.innerHTML = result.html; preview.append(body); preview.hidden = false; text(feedback);
    } catch (error) { if (serial === previewSerial) text(feedback, error.message); }
  });
  form.addEventListener('submit', async event => {
    event.preventDefault(); if (saving || !form.reportValidity()) return;
    if (!participants.some(control => control.checked)) { text(feedback, labels.empty); return; }
    saving = true; text(feedback, labels.loading); const pending = request('save_student_followup', new FormData(form));
    const enabled = [...form.querySelectorAll('input,select,textarea,button')].filter(control => !control.disabled); enabled.forEach(control => { control.disabled = true; });
    try {
      await pending; dirty = false;
      if (!returnToStudent) returnToStudent = Number(participants.find(control => control.checked).value);
      saving = false; editor.hide();
    } catch (error) { text(feedback, error.message); }
    finally { saving = false; enabled.forEach(control => { control.disabled = false; }); }
  });
  editorRoot.addEventListener('hide.bs.modal', event => { if (saving || (dirty && !confirm(labels.discard))) event.preventDefault(); });
  editorRoot.addEventListener('hidden.bs.modal', () => { if (returnToStudent) void openStudent(returnToStudent, 'history'); returnToStudent = 0; });
  const initial = document.querySelector('[data-student-auto-open]');
  if (initial) { const selectedTab = new URLSearchParams(location.search).get('admin_tab'); void openStudent(initial.dataset.studentManage, ['information', 'enrollments', 'history'].includes(selectedTab) ? selectedTab : 'information'); }
})();
