const enhanceBootstrap = (root = document) => {
  root.querySelectorAll('.button').forEach((button) => {
    button.classList.add('btn');
    if (button.classList.contains('primary')) button.classList.add('btn-dark');
    if (button.classList.contains('secondary')) button.classList.add('btn-outline-secondary');
  });

  root.querySelectorAll('select').forEach((select) => select.classList.add('form-select'));
  root.querySelectorAll('textarea').forEach((textarea) => textarea.classList.add('form-control'));
  root.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not(.title-input):not(.summary-input)')
    .forEach((input) => input.classList.add('form-control'));
};

let i18n = {};
try {
  i18n = JSON.parse(document.body.dataset.i18n || '{}');
} catch (_) {
  i18n = {};
}
const ui = (key, fallback) => i18n[key] || fallback;
const locale = document.body.dataset.language || 'fr';

enhanceBootstrap();

document.querySelectorAll('input[type="date"]').forEach((input) => {
  const iso = input.value;
  input.type = 'text';
  input.classList.add('date-input');
  input.inputMode = 'numeric';
  input.maxLength = 10;
  input.pattern = '[0-3][0-9]/[01][0-9]/[0-9]{4}';
  input.placeholder = 'jj/mm/aaaa';
  if (/^\d{4}-\d{2}-\d{2}$/.test(iso)) input.value = iso.split('-').reverse().join('/');
  input.addEventListener('blur', () => {
    const digits = input.value.replace(/\D/g, '').slice(0, 8);
    if (digits.length === 8) input.value = `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`;
  });
});

document.addEventListener('click', (event) => {
  const add = event.target.closest('[data-add-block]');
  if (add) {
    const template = document.querySelector('#block-template');
    const clone = template.content.cloneNode(true);
    clone.querySelectorAll('[name]').forEach((element) => {
      if (element.name === 'block_body[]') element.value = '';
    });
    enhanceBootstrap(clone);
    document.querySelector('#blocks').append(clone);
  }

  const remove = event.target.closest('[data-remove-block]');
  if (remove && document.querySelectorAll('.block-editor').length > 1) {
    const block = remove.closest('.block-editor');
    const id = block.querySelector('[name="block_id[]"]')?.value;
    const revision = block.querySelector('[name="block_revision[]"]')?.value;
    if (id && id !== '0') {
      const form = block.closest('form');
      [['deleted_block_id[]', id], ['deleted_block_revision[]', revision || '0']].forEach(([name, value]) => {
        const marker = document.createElement('input');
        marker.type = 'hidden'; marker.name = name; marker.value = value; form.append(marker);
      });
    }
    block.remove();
  }

  const addObjective = event.target.closest('[data-add-page-objective]');
  if (addObjective) {
    const template = document.querySelector('#page-objective-template');
    const target = document.querySelector('[data-page-objectives]');
    if (template && target) {
      const clone = template.content.cloneNode(true);
      enhanceBootstrap(clone);
      target.append(clone);
      target.lastElementChild?.querySelector('input')?.focus();
    }
  }

  const removeObjective = event.target.closest('[data-remove-page-objective]');
  if (removeObjective) removeObjective.closest('.page-objective-editor')?.remove();

  const profile = document.querySelector('.profile-menu[open]');
  if (profile && !event.target.closest('.profile-menu')) profile.removeAttribute('open');
});

let lockSubmissionInProgress = false;
document.addEventListener('submit', (event) => {
  if (event.target.matches?.('[data-edit-lock]') || event.target.querySelector?.('[data-edit-lock]')) lockSubmissionInProgress = true;
});

const heldEditLocks = new Map();
const lockKey = (scope) => `${scope.dataset.lockType}:${scope.dataset.lockId}`;
const lockScopes = (key) => Array.from(document.querySelectorAll('[data-edit-lock]')).filter((scope) => lockKey(scope) === key);
const setLockState = (key, state, owner = '') => {
  lockScopes(key).forEach((scope) => {
    scope.querySelectorAll('[data-lock-status]').forEach((status) => {
      status.textContent = state === 'held' ? ui('lock_active', 'Zone réservée pour votre modification')
        : state === 'blocked' ? ui('locked_by', 'Modification en cours par :name').replace(':name', owner || '—')
          : state === 'released' ? ui('lock_released', 'Zone libérée') : '';
      status.classList.toggle('blocked', state === 'blocked');
    });
    scope.querySelectorAll('[data-release-edit-lock]').forEach((button) => {
      button.dataset.releaseHtml ||= button.innerHTML;
      button.hidden = state !== 'held' && state !== 'released';
      if (state === 'released') button.textContent = ui('lock_resume', 'Reprendre la modification');
      else button.innerHTML = button.dataset.releaseHtml;
    });
    scope.querySelectorAll('input:not([type="hidden"]),textarea,select,button').forEach((control) => {
      const mustDisable = state === 'blocked' || state === 'released';
      if (mustDisable && !control.disabled && !control.matches('[data-release-edit-lock]')) { control.disabled = true; control.dataset.collaborationDisabled = '1'; }
      if (!mustDisable && control.dataset.collaborationDisabled) { control.disabled = false; delete control.dataset.collaborationDisabled; }
    });
  });
};
const editLockRequest = async (scope) => {
  const key = lockKey(scope);
  const data = new FormData();
  data.set('token', document.body.dataset.csrf || '');
  data.set('action', 'acquire_edit_lock');
  data.set('entity_type', scope.dataset.lockType);
  data.set('entity_id', scope.dataset.lockId);
  try {
    const response = await fetch(window.location.href, { method: 'POST', body: data, credentials: 'same-origin' });
    const result = await response.json();
    if (response.ok && result.ok) { heldEditLocks.set(key, scope); setLockState(key, 'held'); return; }
    setLockState(key, 'blocked', result.owner);
    window.setTimeout(() => editLockRequest(scope), 15000);
  } catch (_) {
    lockScopes(key).forEach((part) => part.querySelectorAll('[data-lock-status]').forEach((status) => { status.textContent = ui('lock_error', 'Impossible de réserver cette zone'); }));
  }
};
document.querySelectorAll('[data-edit-lock]').forEach((scope) => scope.addEventListener('focusin', () => {
  if (!heldEditLocks.has(lockKey(scope))) editLockRequest(scope);
}));
document.querySelectorAll('[data-release-edit-lock]').forEach((button) => button.addEventListener('click', async () => {
  const scope = button.closest('[data-edit-lock]');
  if (!scope) return;
  const key = lockKey(scope);
  if (!heldEditLocks.has(key)) { editLockRequest(scope); return; }
  const data = new FormData();
  data.set('token', document.body.dataset.csrf || '');
  data.set('action', 'release_edit_lock');
  data.set('entity_type', scope.dataset.lockType);
  data.set('entity_id', scope.dataset.lockId);
  try {
    const response = await fetch(window.location.href, { method: 'POST', body: data, credentials: 'same-origin' });
    if (!response.ok) throw new Error('release failed');
    heldEditLocks.delete(key);
    setLockState(key, 'released');
    button.blur();
  } catch (_) {
    lockScopes(key).forEach((part) => part.querySelectorAll('[data-lock-status]').forEach((status) => { status.textContent = ui('lock_error', 'Impossible de réserver cette zone'); }));
  }
}));
if (document.querySelector('[data-edit-lock]')) window.setInterval(() => heldEditLocks.forEach((scope) => editLockRequest(scope)), 45000);
window.addEventListener('pagehide', () => { if (!lockSubmissionInProgress) heldEditLocks.forEach((scope) => {
  const data = new FormData();data.set('token', document.body.dataset.csrf || '');data.set('action', 'release_edit_lock');data.set('entity_type', scope.dataset.lockType);data.set('entity_id', scope.dataset.lockId);navigator.sendBeacon(window.location.href, data);
}); });

document.querySelectorAll('.reward-select').forEach((select) => select.addEventListener('change', () => {
  const option = select.selectedOptions[0];
  const points = select.closest('form').querySelector('[name="points"]');
  if (option?.dataset.points) points.value = option.dataset.points;
}));

const libraryFilters = document.querySelector('[data-library-filters]');
if (libraryFilters) {
  const applyLibraryFilters = () => {
    const search = libraryFilters.querySelector('[data-page-search]').value.toLocaleLowerCase(locale).trim();
    const status = libraryFilters.querySelector('[data-page-status]').value;
    const tag = libraryFilters.querySelector('[data-page-tag]').value;
    const objective = libraryFilters.querySelector('[data-page-objective]').value;
    let visible = 0;
    document.querySelectorAll('[data-page-card]').forEach((card) => {
      const matches = (!search || card.dataset.search.includes(search))
        && (!status || card.dataset.status === status)
        && (!tag || card.dataset.tags.includes(` ${tag} `))
        && (!objective || card.dataset.objectives.includes(` ${objective} `));
      card.hidden = !matches;
      if (matches) visible++;
    });
    libraryFilters.querySelector('[data-page-count]').textContent = ui('pages', ':count page(s)').replace(':count', String(visible));
    document.querySelector('[data-library-empty]').hidden = visible !== 0;
  };
  libraryFilters.querySelectorAll('input,select').forEach((control) => {
    control.addEventListener(control.tagName === 'SELECT' ? 'change' : 'input', applyLibraryFilters);
  });
}

const studentFilters = document.querySelector('[data-student-filters]');
if (studentFilters) {
  const fold = (value) => String(value || '').toLocaleLowerCase(locale).normalize('NFD').replace(/\p{Diacritic}/gu, '').trim();
  const applyStudentFilters = () => {
    const search = fold(studentFilters.querySelector('[data-student-search]').value);
    const group = studentFilters.querySelector('[data-student-group]').value;
    const course = studentFilters.querySelector('[data-student-course]').value;
    let visible = 0;
    document.querySelectorAll('[data-student-card]').forEach((card) => {
      const matches = (!search || fold(card.dataset.search).includes(search))
        && (!group || card.dataset.group === group)
        && (!course || card.dataset.courses.includes(` ${course} `));
      card.hidden = !matches;
      if (matches) visible++;
    });
    const count = document.querySelector('[data-student-count]');
    if (count) count.textContent = ui('students', ':count élève(s) sur la plateforme').replace(':count', String(visible));
    const empty = document.querySelector('[data-student-empty]');
    if (empty) empty.hidden = visible !== 0;
  };
  studentFilters.querySelectorAll('input,select').forEach((control) => control.addEventListener(control.tagName === 'SELECT' ? 'change' : 'input', applyStudentFilters));
}

document.querySelectorAll('[data-course-invitation]').forEach((invitation) => {
  const select = invitation.querySelector('[data-invitation-course]');
  const code = invitation.querySelector('[data-invitation-code]');
  const link = invitation.querySelector('[data-invitation-link]');
  const sync = () => {
    const option = select?.selectedOptions[0];
    if (code) code.value = option?.dataset.code || '';
    if (link) link.value = option?.dataset.link || '';
  };
  select?.addEventListener('change', sync);
  sync();
});

const pageEditor = document.querySelector('.editor-form');
if (pageEditor) {
  let dirty = false;
  let submitting = false;
  const markDirty = () => { dirty = true; };
  pageEditor.addEventListener('input', markDirty);
  pageEditor.addEventListener('change', markDirty);
  pageEditor.addEventListener('click', (event) => {
    if (event.target.closest('[data-add-block],[data-remove-block],[data-add-page-objective],[data-remove-page-objective]')) markDirty();
  });
  pageEditor.addEventListener('submit', () => { submitting = true; dirty = false; });
  window.addEventListener('beforeunload', (event) => {
    if (!dirty || submitting) return;
    event.preventDefault();
    event.returnValue = '';
  });
}

document.querySelector('[data-print-pdf]')?.addEventListener('click', () => {
  const frame = document.querySelector('[data-pdf-frame]');
  frame?.contentWindow?.focus();
  frame?.contentWindow?.print();
});

document.querySelectorAll('.student-access').forEach((section) => {
  const students = section.querySelector('[data-allowed-students]');
  const radios = section.querySelectorAll('input[name="access_mode"]');
  if (!students || !radios.length) return;
  const syncStudentSelection = () => {
    const restricted = section.querySelector('input[name="access_mode"]:checked')?.value === 'restricted';
    students.hidden = !restricted;
    students.querySelectorAll('input').forEach((input) => { input.disabled = !restricted; });
  };
  radios.forEach((radio) => radio.addEventListener('change', syncStudentSelection));
  syncStudentSelection();
});

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => navigator.serviceWorker.register('sw.js'));
}

document.querySelectorAll('[data-copy-target]').forEach((button) => button.addEventListener('click', async () => {
  const input = document.getElementById(button.dataset.copyTarget);
  if (!input) return;
  try {
    if (navigator.clipboard?.writeText) await navigator.clipboard.writeText(input.value);
    else {
      input.select();
      document.execCommand('copy');
      input.setSelectionRange(0, 0);
    }
    const label = button.querySelector('span');
    const previous = label?.textContent;
    if (label) label.textContent = button.dataset.copyLabel || 'Copié !';
    window.setTimeout(() => { if (label) label.textContent = previous; }, 1600);
  } catch (_) {
    input.focus();
    input.select();
  }
}));

document.querySelectorAll('form[method="post"]').forEach((form) => {
  if (!form.querySelector('[name="token"]') && document.body.dataset.csrf) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'token';
    input.value = document.body.dataset.csrf;
    form.prepend(input);
  }
});

document.querySelectorAll('form[method="post"]').forEach((form) => form.addEventListener('submit', (event) => {
  const action = form.querySelector('[name="action"]')?.value;
  const overwrite = form.querySelector('[name="mode"]')?.value === 'overwrite';
  if (!overwrite || !['import_page', 'import_course', 'import_students'].includes(action)) return;
  const messages = {
    import_page: ui('confirm_page', 'Écraser cette page et remplacer tous ses blocs et tags ?'),
    import_course: ui('confirm_course', 'Écraser ce parcours ? Ses anciennes étapes, progressions et encouragements liés seront supprimés.'),
    import_students: ui('confirm_students', 'Écraser, pour les élèves importés, leurs inscriptions à vos parcours ?'),
  };
  if (!window.confirm(messages[action])) event.preventDefault();
}));

document.querySelectorAll('[data-password-toggle]').forEach((button) => button.addEventListener('click', () => {
  const input = button.closest('.password-input')?.querySelector('input');
  if (!input) return;
  const visible = input.type === 'text';
  input.type = visible ? 'password' : 'text';
  const label = visible ? button.dataset.showLabel : button.dataset.hideLabel;
  button.setAttribute('aria-label', label || '');
  button.title = label || '';
  const icon = button.querySelector('i');
  icon?.classList.toggle('bi-eye', visible);
  icon?.classList.toggle('bi-eye-slash', !visible);
  input.focus();
}));

const firstName = document.querySelector('[name="first_name"]');
const lastName = document.querySelector('[name="last_name"]');
const preview = document.querySelector('[data-code-preview] b');
const updateCode = () => {
  if (!preview) return;
  const first = Array.from((firstName?.value || '').replace(/\s/gu, '')).slice(0, 2).join('');
  const last = Array.from((lastName?.value || '').replace(/\s/gu, '')).slice(0, 3).join('');
  preview.textContent = (first + last).toLocaleUpperCase(locale) || '— — — — —';
};
[firstName, lastName].forEach((input) => input?.addEventListener('input', () => {
  if (input === lastName) input.value = input.value.toLocaleUpperCase(locale);
  updateCode();
}));

document.querySelectorAll('[data-uppercase]').forEach((input) => input.addEventListener('blur', () => {
  input.value = input.value.toLocaleUpperCase(locale);
}));

const learningTracker = document.querySelector('[data-learning-tracker]');
if (learningTracker && document.body.dataset.csrf) {
  const visitToken = globalThis.crypto?.randomUUID
    ? globalThis.crypto.randomUUID().replaceAll('-', '')
    : (String(Date.now()) + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2));
  let activeSeconds = 0;
  let lastTick = Date.now();
  let lastSent = -1;

  const updateActiveTime = () => {
    const now = Date.now();
    if (!document.hidden) activeSeconds += Math.min(30, Math.max(0, (now - lastTick) / 1000));
    lastTick = now;
  };
  const sendActivity = () => {
    updateActiveTime();
    const rounded = Math.floor(activeSeconds);
    if (rounded === lastSent) return;
    const data = new FormData();
    data.set('token', document.body.dataset.csrf);
    data.set('action', 'track_learning_activity');
    data.set('item_id', learningTracker.dataset.itemId);
    data.set('visit_token', visitToken);
    data.set('active_seconds', String(rounded));
    navigator.sendBeacon(window.location.href, data);
    lastSent = rounded;
  };

  sendActivity();
  const heartbeat = window.setInterval(sendActivity, 60000);
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) sendActivity();
    else lastTick = Date.now();
  });
  window.addEventListener('pagehide', () => {
    sendActivity();
    window.clearInterval(heartbeat);
  });
}
