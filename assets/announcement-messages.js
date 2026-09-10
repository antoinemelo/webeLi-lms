(() => {
  const tools = document.querySelector('[data-message-tools]');
  if (!tools) return;
  let templates = JSON.parse(tools.dataset.messageTemplates);
  const labels = JSON.parse(tools.dataset.messageLabels);
  const form = document.querySelector('[data-message-compose]'), manager = document.querySelector('[data-message-template-form]');
  const composeModal = bootstrap.Modal.getOrCreateInstance(document.querySelector('#teacher-message-modal'));
  const managerModal = bootstrap.Modal.getOrCreateInstance(document.querySelector('#message-template-modal'));
  const field = (target, name) => target.elements.namedItem(name);
  const choose = form.querySelector('[data-message-template]'), manage = manager.querySelector('[data-message-template-manager]');
  const previewBox = form.querySelector('[data-message-preview]'), previewStudent = form.querySelector('[data-message-preview-student]');
  const send = form.querySelector('[data-message-send]');
  const students = [...form.querySelectorAll('[name="students[]"]')], copies = [...form.querySelectorAll('[name="secondary[]"]')];
  const all = form.querySelector('[data-message-all]'), allCc = form.querySelector('[data-message-all-cc]');
  let prepared = null, serial = 0, sending = false, templatePending = false;
  const show = (target, text = '') => { target.textContent = text; target.hidden = !text; };
  const feedback = form.querySelector('[data-message-feedback]'), templateFeedback = manager.querySelector('[data-template-feedback]');
  const format = (text, values) => Object.entries(values).reduce((text, [key, value]) => text.replace(`:${key}`, String(value)), text);
  const invalidate = () => { serial++; prepared = null; previewBox.hidden = true; send.disabled = true; show(feedback); };
  const count = () => {
    const selected = students.filter(input => input.checked);
    for (const copy of copies) { copy.disabled = !selected.some(input => input.value === copy.value); if (copy.disabled) copy.checked = false; }
    all.checked = !!students.length && selected.length === students.length; all.indeterminate = !!selected.length && !all.checked;
    const available = copies.filter(input => !input.disabled), checked = available.filter(input => input.checked);
    allCc.disabled = !available.length; allCc.checked = !!available.length && available.length === checked.length; allCc.indeterminate = !!checked.length && !allCc.checked;
    form.querySelector('[data-message-count]').textContent = format(labels.count, { count: selected.length, copies: checked.length, email: labels.sender });
    send.textContent = format(labels.send, { count: selected.length });
  };
  const request = async (target, action) => {
    const data = new FormData(target); data.set('action', action); data.set('token', document.body.dataset.csrf || field(target, 'token').value);
    const response = await fetch(location.href, { method: 'POST', body: data, headers: { Accept: 'application/json' } });
    let result;
    try { result = await response.json(); } catch { throw new Error(labels.error); }
    if (!response.ok || result.error) throw new Error(result.error || labels.error);
    return result;
  };
  const options = (select, blank, value) => {
    select.replaceChildren(new Option(blank, '0'), ...templates.map(template => new Option(template.name, String(template.id))));
    select.value = templates.some(template => String(template.id) === String(value)) ? String(value) : '0';
  };
  const fillManager = () => {
    const template = templates.find(template => String(template.id) === manage.value);
    for (const name of ['name', 'title', 'body', 'revision']) field(manager, name).value = template?.[name] ?? (name === 'revision' ? '0' : '');
    manager.querySelector('[data-template-delete]').disabled = !template; show(templateFeedback);
  };
  const refresh = (result) => {
    templates = result.templates; document.dispatchEvent(new CustomEvent('message-templates-updated', { detail: templates })); options(choose, labels.select, choose.value); options(manage, labels.new, result.id); fillManager();
  };
  tools.querySelector('[data-message-action]').addEventListener('change', event => {
    if (event.target.value === 'send') composeModal.show();
    if (event.target.value === 'templates') managerModal.show();
    if (event.target.value.startsWith('followup-')) document.dispatchEvent(new CustomEvent('open-student-followup', { detail: event.target.value.slice(9) }));
    event.target.value = '';
  });
  all.addEventListener('change', () => { students.forEach(input => { input.checked = all.checked; }); count(); invalidate(); });
  allCc.addEventListener('change', () => { copies.filter(input => !input.disabled).forEach(input => { input.checked = allCc.checked; }); count(); invalidate(); });
  form.addEventListener('input', event => { if (![previewStudent, all, allCc].includes(event.target)) { invalidate(); count(); } });
  form.addEventListener('change', event => { if (![previewStudent, all, allCc].includes(event.target)) { invalidate(); count(); } });
  choose.addEventListener('change', () => {
    const template = templates.find(template => String(template.id) === choose.value);
    if (template) { field(form, 'title').value = template.title; field(form, 'body').value = template.body; }
    invalidate();
  });
  manage.addEventListener('change', fillManager);
  for (const target of [form, manager]) {
    let focused = field(target, 'body');
    target.addEventListener('focusin', event => { if (['title', 'body'].includes(event.target.name)) focused = event.target; });
    target.querySelectorAll('[data-message-variable]').forEach(button => button.addEventListener('click', () => {
      focused.setRangeText(`{${button.dataset.messageVariable}}`, focused.selectionStart, focused.selectionEnd, 'end'); focused.focus(); focused.dispatchEvent(new Event('input', { bubbles: true }));
    }));
  }
  const renderPreview = () => {
    const recipient = prepared?.recipients.find(recipient => String(recipient.student_id) === previewStudent.value);
    if (!recipient) return;
    form.querySelector('[data-message-preview-title]').textContent = recipient.title;
    form.querySelector('[data-message-preview-body]').innerHTML = recipient.html;
    form.querySelector('[data-message-preview-addresses]').textContent = `${recipient.email}${recipient.cc ? ' · CC : ' + recipient.cc : ''}`;
  };
  previewStudent.addEventListener('change', renderPreview);
  form.querySelector('[data-message-preview-button]').addEventListener('click', async () => {
    if (!form.reportValidity() || sending) return;
    invalidate(); const current = serial; show(feedback, labels.waiting);
    try {
      const result = await request(form, 'preview_targeted_announcement');
      if (current !== serial) return;
      prepared = result; previewStudent.replaceChildren(...result.recipients.map(recipient => new Option(recipient.student_name, String(recipient.student_id))));
      renderPreview(); previewBox.hidden = false; send.disabled = false; show(feedback);
    } catch (error) { if (current === serial) show(feedback, error.message); }
  });
  form.addEventListener('submit', async event => {
    event.preventDefault(); if (!prepared || sending || !form.reportValidity()) return;
    sending = true; send.disabled = true; show(feedback, labels.waiting);
    const pending = request(form, 'send_targeted_announcement');
    // Capture FormData before disabling controls so the complete selection is submitted.
    const enabled = [...form.querySelectorAll('input,textarea,select,button')].filter(control => !control.disabled);
    enabled.forEach(control => { control.disabled = true; });
    try {
      const result = await pending;
      composeModal.hide();
      const status = tools.querySelector('[data-message-result]'); show(status, labels.sent + ' ');
      const link = document.createElement('a'); link.href = result.url; link.textContent = labels.open; status.append(link);
      form.reset(); const key = [...crypto.getRandomValues(new Uint8Array(24))].map(value => value.toString(16).padStart(2, '0')).join(''); field(form, 'request_key').value = key;
      invalidate();
    } catch (error) { show(feedback, error.message); }
    finally { enabled.forEach(control => { control.disabled = false; }); sending = false; send.disabled = !prepared; count(); }
  });
  manager.addEventListener('submit', async event => {
    event.preventDefault(); if (templatePending || !manager.reportValidity()) return;
    templatePending = true;
    try { refresh(await request(manager, 'save_message_template')); show(templateFeedback, labels.saved); }
    catch (error) { show(templateFeedback, error.message); }
    finally { templatePending = false; }
  });
  manager.querySelector('[data-template-delete]').addEventListener('click', async () => {
    if (templatePending || manage.value === '0' || !confirm(labels.deleteConfirm)) return;
    templatePending = true;
    try { refresh(await request(manager, 'delete_message_template')); invalidate(); show(templateFeedback, labels.deleted); }
    catch (error) { show(templateFeedback, error.message); }
    finally { templatePending = false; }
  });
  form.querySelector('[data-message-save-as-template]').addEventListener('click', () => {
    manage.value = '0'; fillManager(); field(manager, 'title').value = field(form, 'title').value; field(manager, 'body').value = field(form, 'body').value;
    document.querySelector('#teacher-message-modal').addEventListener('hidden.bs.modal', () => managerModal.show(), { once: true }); composeModal.hide();
  });
  count();
})();
