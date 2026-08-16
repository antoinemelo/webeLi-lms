const enhanceBootstrap = (root = document) => {
  root.querySelectorAll('.button').forEach((button) => {
    button.classList.add('btn');
    if (button.classList.contains('primary')) button.classList.add('btn-dark');
    if (button.classList.contains('secondary')) button.classList.add('btn-outline-secondary');
  });

  root.querySelectorAll('select').forEach((select) => select.classList.add('form-select'));
  root.querySelectorAll('textarea').forEach((textarea) => textarea.classList.add('form-control'));
  root.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not(.title-input):not(.summary-input):not(.pathway-position-input)')
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

const installedPwa = window.matchMedia('(display-mode: standalone)').matches
  || window.matchMedia('(display-mode: fullscreen)').matches
  || window.navigator.standalone === true;

if (installedPwa) {
  const pdfLinks = Array.from(document.querySelectorAll('.reader-pdf-download'));
  if (pdfLinks.length > 0) {
    let pdfJsPromise = null;
    let overlay = null;
    let pages = null;
    let loading = null;
    let message = null;
    let status = null;
    let title = null;
    let closeButton = null;
    let shareButton = null;
    let downloadLink = null;
    let retryButton = null;
    let currentLink = null;
    let currentFile = null;
    let objectUrl = null;
    let loadingTask = null;
    let pdfDocument = null;
    let fetchController = null;
    let requestNumber = 0;
    let viewerHistoryActive = false;

    const progressLabel = (current, total) => ui('pdf_progress', 'Page :current sur :total')
      .replace(':current', String(current))
      .replace(':total', String(total));

    const loadPdfJs = () => {
      if (!pdfJsPromise) {
        pdfJsPromise = import('./vendor/pdfjs/pdf.min.mjs').then((pdfjs) => {
          pdfjs.GlobalWorkerOptions.workerSrc = new URL('assets/vendor/pdfjs/pdf.worker.min.mjs', document.baseURI).href;
          return pdfjs;
        });
      }
      return pdfJsPromise;
    };

    const releasePdf = () => {
      fetchController?.abort();
      fetchController = null;
      try {
        if (pdfDocument) pdfDocument.destroy();
        else loadingTask?.destroy();
      } catch (_) {
        // The viewer is already closing; PDF.js may have released the worker first.
      }
      loadingTask = null;
      pdfDocument = null;
      currentFile = null;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
      objectUrl = null;
      pages?.replaceChildren();
    };

    const closeViewer = () => {
      if (!overlay || overlay.hidden) return;
      requestNumber++;
      releasePdf();
      overlay.hidden = true;
      document.documentElement.classList.remove('pwa-pdf-open');
      const link = currentLink;
      currentLink = null;
      link?.focus({ preventScroll: true });
    };

    const requestClose = () => {
      if (viewerHistoryActive) {
        window.history.back();
        return;
      }
      closeViewer();
    };

    const createViewer = () => {
      overlay = document.createElement('section');
      overlay.className = 'pwa-pdf-viewer';
      overlay.hidden = true;
      overlay.setAttribute('role', 'dialog');
      overlay.setAttribute('aria-modal', 'true');
      overlay.setAttribute('aria-labelledby', 'pwa-pdf-title');

      const toolbar = document.createElement('header');
      toolbar.className = 'pwa-pdf-toolbar';

      closeButton = document.createElement('button');
      closeButton.className = 'pwa-pdf-close';
      closeButton.type = 'button';
      closeButton.setAttribute('aria-label', ui('pdf_close', 'Fermer l’aperçu PDF'));
      closeButton.title = ui('pdf_close', 'Fermer l’aperçu PDF');
      const closeIcon = document.createElement('i');
      closeIcon.className = 'bi bi-x-lg';
      closeIcon.setAttribute('aria-hidden', 'true');
      closeButton.append(closeIcon);

      const heading = document.createElement('div');
      heading.className = 'pwa-pdf-heading';
      const eyebrow = document.createElement('span');
      eyebrow.textContent = ui('pdf_preview', 'Aperçu PDF');
      title = document.createElement('h2');
      title.id = 'pwa-pdf-title';
      status = document.createElement('small');
      status.setAttribute('role', 'status');
      status.setAttribute('aria-live', 'polite');
      heading.append(eyebrow, title, status);

      const actions = document.createElement('div');
      actions.className = 'pwa-pdf-actions';
      shareButton = document.createElement('button');
      shareButton.className = 'pwa-pdf-action';
      shareButton.type = 'button';
      shareButton.hidden = true;
      shareButton.setAttribute('aria-label', ui('pdf_share', 'Partager ou enregistrer'));
      shareButton.title = ui('pdf_share', 'Partager ou enregistrer');
      const shareIcon = document.createElement('i');
      shareIcon.className = 'bi bi-share';
      shareIcon.setAttribute('aria-hidden', 'true');
      shareButton.append(shareIcon);

      downloadLink = document.createElement('a');
      downloadLink.className = 'pwa-pdf-action';
      downloadLink.hidden = true;
      downloadLink.setAttribute('aria-label', ui('pdf_download', 'Télécharger le PDF'));
      downloadLink.title = ui('pdf_download', 'Télécharger le PDF');
      const downloadIcon = document.createElement('i');
      downloadIcon.className = 'bi bi-download';
      downloadIcon.setAttribute('aria-hidden', 'true');
      downloadLink.append(downloadIcon);
      actions.append(shareButton, downloadLink);
      toolbar.append(closeButton, heading, actions);

      const viewport = document.createElement('div');
      viewport.className = 'pwa-pdf-viewport';
      viewport.dataset.noPullRefresh = '1';
      loading = document.createElement('div');
      loading.className = 'pwa-pdf-loading';
      loading.setAttribute('role', 'status');
      const spinner = document.createElement('span');
      spinner.setAttribute('aria-hidden', 'true');
      const loadingText = document.createElement('b');
      loadingText.textContent = ui('pdf_loading', 'Chargement du PDF…');
      loading.append(spinner, loadingText);
      message = document.createElement('div');
      message.className = 'pwa-pdf-message';
      message.hidden = true;
      const errorIcon = document.createElement('i');
      errorIcon.className = 'bi bi-file-earmark-x';
      errorIcon.setAttribute('aria-hidden', 'true');
      const errorText = document.createElement('p');
      errorText.textContent = ui('pdf_error', 'Impossible d’afficher le PDF.');
      retryButton = document.createElement('button');
      retryButton.className = 'btn btn-outline-secondary';
      retryButton.type = 'button';
      retryButton.textContent = ui('retry', 'Réessayer');
      message.append(errorIcon, errorText, retryButton);
      pages = document.createElement('div');
      pages.className = 'pwa-pdf-pages';
      viewport.append(loading, message, pages);
      overlay.append(toolbar, viewport);
      document.body.append(overlay);

      closeButton.addEventListener('click', requestClose);
      retryButton.addEventListener('click', () => { if (currentLink) openPdf(currentLink, false); });
      shareButton.addEventListener('click', async () => {
        if (!currentFile || !navigator.share) return;
        try {
          await navigator.share({ files: [currentFile], title: title.textContent || currentFile.name });
        } catch (error) {
          if (error?.name !== 'AbortError') downloadLink.click();
        }
      });
    };

    const responseFilename = (response, fallback) => {
      const disposition = response.headers.get('Content-Disposition') || '';
      const utf8 = disposition.match(/filename\*=UTF-8''([^;]+)/i);
      if (utf8) {
        try { return decodeURIComponent(utf8[1]); } catch (_) { /* Use the regular filename. */ }
      }
      const regular = disposition.match(/filename="?([^";]+)"?/i);
      return regular?.[1] || fallback;
    };

    const renderPdf = async (blob, ownRequest) => {
      const pdfjs = await loadPdfJs();
      if (ownRequest !== requestNumber) return;
      loadingTask = pdfjs.getDocument({ data: new Uint8Array(await blob.arrayBuffer()) });
      pdfDocument = await loadingTask.promise;
      const total = pdfDocument.numPages;
      await new Promise((resolve) => window.requestAnimationFrame(resolve));
      for (let number = 1; number <= total; number++) {
        if (ownRequest !== requestNumber) return;
        status.textContent = progressLabel(number, total);
        const page = await pdfDocument.getPage(number);
        const original = page.getViewport({ scale: 1 });
        const availableWidth = Math.max(280, Math.min(920, pages.clientWidth - 24));
        const viewport = page.getViewport({ scale: availableWidth / original.width });
        const outputScale = Math.min(window.devicePixelRatio || 1, 2.5);
        const sheet = document.createElement('figure');
        sheet.className = 'pwa-pdf-page';
        const canvas = document.createElement('canvas');
        canvas.width = Math.floor(viewport.width * outputScale);
        canvas.height = Math.floor(viewport.height * outputScale);
        canvas.style.width = `${Math.floor(viewport.width)}px`;
        canvas.style.height = `${Math.floor(viewport.height)}px`;
        canvas.setAttribute('role', 'img');
        canvas.setAttribute('aria-label', progressLabel(number, total));
        sheet.append(canvas);
        pages.append(sheet);
        await page.render({
          canvasContext: canvas.getContext('2d', { alpha: false }),
          transform: outputScale === 1 ? null : [outputScale, 0, 0, outputScale, 0, 0],
          viewport,
        }).promise;
        page.cleanup();
        if (number === 1 && ownRequest === requestNumber) loading.hidden = true;
      }
      if (ownRequest === requestNumber) loading.hidden = true;
    };

    const openPdf = async (link, addHistory = true) => {
      if (!overlay) createViewer();
      releasePdf();
      const ownRequest = ++requestNumber;
      currentLink = link;
      title.textContent = link.dataset.pdfTitle || ui('pdf_preview', 'Aperçu PDF');
      status.textContent = '';
      pages.replaceChildren();
      message.hidden = true;
      loading.hidden = false;
      shareButton.hidden = true;
      downloadLink.hidden = true;
      overlay.hidden = false;
      document.documentElement.classList.add('pwa-pdf-open');
      closeButton.focus({ preventScroll: true });
      if (addHistory && !viewerHistoryActive) {
        const previous = window.history.state && typeof window.history.state === 'object' ? window.history.state : {};
        window.history.pushState({ ...previous, pwaPdfViewer: true }, '', window.location.href);
        viewerHistoryActive = true;
      }
      fetchController = new AbortController();
      try {
        const response = await fetch(link.href, {
          credentials: 'same-origin',
          cache: 'no-store',
          headers: { Accept: 'application/pdf' },
          signal: fetchController.signal,
        });
        if (!response.ok) throw new Error(`PDF ${response.status}`);
        const blob = await response.blob();
        if (ownRequest !== requestNumber) return;
        const filename = responseFilename(response, 'etape.pdf');
        objectUrl = URL.createObjectURL(blob);
        downloadLink.href = objectUrl;
        downloadLink.download = filename;
        downloadLink.hidden = false;
        currentFile = new File([blob], filename, { type: 'application/pdf' });
        shareButton.hidden = !(navigator.share && navigator.canShare?.({ files: [currentFile] }));
        await renderPdf(blob, ownRequest);
      } catch (error) {
        if (ownRequest !== requestNumber || error?.name === 'AbortError') return;
        loading.hidden = true;
        status.textContent = '';
        message.hidden = false;
      }
    };

    pdfLinks.forEach((link) => link.addEventListener('click', (event) => {
      event.preventDefault();
      openPdf(link);
    }));
    window.addEventListener('popstate', () => {
      if (!overlay || overlay.hidden || !viewerHistoryActive) return;
      viewerHistoryActive = false;
      closeViewer();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && overlay && !overlay.hidden) requestClose();
    });
  }
}

if (installedPwa && window.navigator.maxTouchPoints > 0) {
  const PULL_REFRESH_THRESHOLD = 96;
  let pullStartX = 0;
  let pullStartY = 0;
  let pullDistance = 0;
  let pullTracking = false;

  const pageIsAtTop = () => (document.scrollingElement?.scrollTop || window.scrollY || 0) <= 0;
  const hasScrollableParent = (target) => {
    let element = target instanceof Element ? target : null;
    while (element && element !== document.body) {
      const overflowY = window.getComputedStyle(element).overflowY;
      if ((overflowY === 'auto' || overflowY === 'scroll') && element.scrollHeight > element.clientHeight + 1) return true;
      element = element.parentElement;
    }
    return false;
  };
  const cancelPull = () => {
    pullTracking = false;
    pullDistance = 0;
  };

  document.addEventListener('touchstart', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (event.touches.length !== 1 || !pageIsAtTop() || document.querySelector('.modal.show,.offcanvas.show')) return;
    if (target?.closest('input,textarea,select,button,a,summary,label,[contenteditable],[data-no-pull-refresh]')) return;
    if (hasScrollableParent(target)) return;
    pullStartX = event.touches[0].clientX;
    pullStartY = event.touches[0].clientY;
    pullDistance = 0;
    pullTracking = true;
  }, { passive: true });

  document.addEventListener('touchmove', (event) => {
    if (!pullTracking || event.touches.length !== 1) return;
    const deltaX = Math.abs(event.touches[0].clientX - pullStartX);
    const deltaY = event.touches[0].clientY - pullStartY;
    if (!pageIsAtTop() || deltaY <= 0 || deltaX > deltaY * 0.6) { cancelPull(); return; }
    pullDistance = deltaY;
  }, { passive: true });

  document.addEventListener('touchend', () => {
    if (!pullTracking) return;
    const shouldRefresh = pageIsAtTop() && pullDistance >= PULL_REFRESH_THRESHOLD;
    cancelPull();
    if (shouldRefresh) window.location.reload();
  }, { passive: true });
  document.addEventListener('touchcancel', cancelPull, { passive: true });
}

document.querySelector('.announcement-admin .section-title > button')?.remove();

const sessionGuardEnabled = Boolean(document.body.dataset.csrf);
if (sessionGuardEnabled) {
  const SESSION_CHECK_URL = '?view=session-status';
  const SESSION_CHECK_FRESHNESS = 300000;
  let sessionState = 'valid';
  let sessionCheckedAt = Date.now();
  let sessionCheckPromise = null;
  const approvedForms = new WeakSet();
  const approvedActions = new WeakSet();

  const overlay = document.createElement('div');
  overlay.className = 'session-guard-overlay';
  overlay.hidden = true;
  overlay.setAttribute('role', 'dialog');
  overlay.setAttribute('aria-modal', 'true');
  overlay.setAttribute('aria-labelledby', 'session-guard-title');
  const card = document.createElement('section');
  card.className = 'session-guard-card';
  const icon = document.createElement('i');
  icon.className = 'bi bi-shield-lock';
  icon.setAttribute('aria-hidden', 'true');
  const title = document.createElement('h2');
  title.id = 'session-guard-title';
  const explanation = document.createElement('p');
  explanation.textContent = ui('session_preserved', 'Votre saisie reste affichée sur cette page. Reconnectez-vous dans un nouvel onglet, puis revenez ici pour reprendre sans perdre votre travail.');
  const actions = document.createElement('div');
  actions.className = 'session-guard-actions';
  const reconnect = document.createElement('a');
  reconnect.className = 'btn btn-dark';
  reconnect.href = '?view=login';
  reconnect.target = '_blank';
  reconnect.rel = 'noopener';
  reconnect.textContent = ui('session_reconnect', 'Se reconnecter dans un nouvel onglet');
  const recheck = document.createElement('button');
  recheck.className = 'btn btn-outline-secondary';
  recheck.type = 'button';
  recheck.dataset.sessionRecheck = '1';
  recheck.textContent = ui('session_recheck', 'J’ai rétabli ma session');
  const status = document.createElement('small');
  status.className = 'session-guard-status';
  status.setAttribute('role', 'status');
  actions.append(reconnect, recheck);
  card.append(icon, title, explanation, actions, status);
  overlay.append(card);
  document.body.append(overlay);

  const showSessionGuard = (unavailable = false) => {
    sessionState = unavailable ? 'unavailable' : 'expired';
    title.textContent = unavailable
      ? ui('session_unavailable', 'La validité de la session ne peut pas être vérifiée pour le moment.')
      : ui('session_expired', 'Votre session a expiré');
    status.textContent = '';
    overlay.hidden = false;
    recheck.focus();
  };
  const hideSessionGuard = () => {
    overlay.hidden = true;
    status.textContent = '';
  };
  const checkSession = async (force = false) => {
    if (!force && sessionState === 'valid' && Date.now() - sessionCheckedAt < SESSION_CHECK_FRESHNESS) return true;
    if (sessionCheckPromise) return sessionCheckPromise;
    sessionCheckPromise = (async () => {
      try {
        const response = await fetch(SESSION_CHECK_URL, {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
          cache: 'no-store',
        });
        const result = await response.json();
        if (!response.ok || result.authenticated !== true) {
          showSessionGuard(false);
          return false;
        }
        if (typeof result.csrf === 'string' && result.csrf) {
          document.body.dataset.csrf = result.csrf;
          document.querySelectorAll('form[method="post"] input[name="token"]').forEach((token) => { token.value = result.csrf; });
        }
        sessionState = 'valid';
        sessionCheckedAt = Date.now();
        hideSessionGuard();
        return true;
      } catch (_) {
        showSessionGuard(true);
        return false;
      } finally {
        sessionCheckPromise = null;
      }
    })();
    return sessionCheckPromise;
  };
  const isSensitiveAction = (element) => element?.matches?.(
    '[data-bs-toggle="modal"],a[href*="view=page-edit"],a[href*="&edit="],a[href*="?edit="]',
  );

  recheck.addEventListener('click', async () => {
    recheck.disabled = true;
    status.textContent = ui('session_checking', 'Vérification de la session…');
    await checkSession(true);
    recheck.disabled = false;
  });
  document.addEventListener('focusin', (event) => {
    if (!event.target.closest('form[method="post"]')) return;
    if (sessionState === 'expired' || sessionState === 'unavailable') {
      event.target.blur();
      showSessionGuard(sessionState === 'unavailable');
      return;
    }
    checkSession();
  }, true);
  document.addEventListener('click', async (event) => {
    const action = event.target.closest('[data-bs-toggle="modal"],a[href*="view=page-edit"],a[href*="&edit="],a[href*="?edit="]');
    if (!isSensitiveAction(action)) return;
    if (approvedActions.has(action)) {
      approvedActions.delete(action);
      return;
    }
    event.preventDefault();
    event.stopImmediatePropagation();
    if (await checkSession(true)) {
      approvedActions.add(action);
      action.click();
    }
  }, true);
  document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!form.matches?.('form[method="post"]')) return;
    if (approvedForms.has(form)) {
      approvedForms.delete(form);
      return;
    }
    event.preventDefault();
    event.stopImmediatePropagation();
    const submitter = event.submitter;
    if (await checkSession(true)) {
      approvedForms.add(form);
      if (submitter && !submitter.disabled) form.requestSubmit(submitter);
      else form.requestSubmit();
    }
  }, true);
  const checkVisibleSession = () => { if (document.visibilityState === 'visible') checkSession(); };
  window.addEventListener('focus', checkVisibleSession);
  document.addEventListener('visibilitychange', checkVisibleSession);
}

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
  const announcements = document.querySelector('.global-announcement-menu[open]');
  if (announcements && !event.target.closest('.global-announcement-menu')) announcements.removeAttribute('open');
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
      const isPrimary = status.hasAttribute('data-lock-status-primary');
      status.textContent = state === 'held' ? (isPrimary ? ui('lock_active', 'Zone réservée') : '')
        : state === 'blocked' ? ui('locked_by', 'Modification en cours par :name').replace(':name', owner || '—')
          : state === 'released' && isPrimary ? ui('lock_released', 'Zone libérée') : '';
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
document.querySelectorAll('[data-edit-lock]').forEach((scope) => scope.addEventListener('focusin', (event) => {
  if (event.target.closest('[data-no-edit-lock]')) return;
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
  document.querySelectorAll('[data-student-card] form').forEach((form) => form.addEventListener('submit', () => {
    const values = {
      return_search: studentFilters.querySelector('[data-student-search]').value,
      return_group: studentFilters.querySelector('[data-student-group]').value,
      return_course: studentFilters.querySelector('[data-student-course]').value,
    };
    Object.entries(values).forEach(([name, value]) => {
      let input = form.querySelector(`input[name="${name}"]`);
      if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        form.append(input);
      }
      input.value = value;
    });
  }));
  applyStudentFilters();
  const selectedStudent = document.querySelector('[data-student-card-selected]');
  if (selectedStudent) requestAnimationFrame(() => selectedStudent.scrollIntoView({ block: 'center' }));
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
  const unsavedIndicator = pageEditor.querySelector('[data-unsaved-indicator]');
  const setDirty = (value) => {
    dirty = value;
    if (unsavedIndicator) unsavedIndicator.classList.toggle('visible', value);
  };
  const markDirty = () => setDirty(true);
  pageEditor.addEventListener('input', markDirty);
  pageEditor.addEventListener('change', markDirty);
  pageEditor.addEventListener('click', (event) => {
    if (event.target.closest('[data-add-block],[data-remove-block],[data-add-page-objective],[data-remove-page-objective]')) markDirty();
  });
  pageEditor.querySelectorAll('[data-comment-jump]').forEach((link) => link.addEventListener('click', (event) => {
    const target = document.querySelector(link.getAttribute('href'));
    if (!target) return;
    event.preventDefault();
    target.scrollIntoView({ block: 'start' });
  }));
  pageEditor.addEventListener('submit', () => {
    if (window.location.hash.startsWith('#collaboration-comments-')) {
      window.history.replaceState(window.history.state, '', `${window.location.pathname}${window.location.search}`);
    }
    submitting = true;
    setDirty(false);
  });
  window.addEventListener('beforeunload', (event) => {
    if (!dirty || submitting) return;
    event.preventDefault();
    event.returnValue = '';
  });
}

const pathwaySortable = document.querySelector('[data-pathway-sortable]');
if (pathwaySortable) {
  const rows = () => Array.from(pathwaySortable.children).filter((child) => child.matches?.('[data-pathway-row]'));
  const clearDropMarkers = () => rows().forEach((row) => row.classList.remove('pathway-drop-before', 'pathway-drop-after'));

  pathwaySortable.querySelectorAll('[data-pathway-position-form]').forEach((form) => {
    const input = form.querySelector('[data-pathway-drag-handle]');
    const row = form.closest('[data-pathway-row]');
    if (!input || !row) return;
    const initialPosition = Number(row.dataset.position || input.value);

    input.addEventListener('change', () => {
      const target = Number.parseInt(input.value, 10);
      const maximum = Number.parseInt(input.max, 10);
      if (!Number.isInteger(target) || target < 1 || target > maximum) { input.value = String(initialPosition); return; }
      if (target !== initialPosition) form.requestSubmit();
    });

    let gesture = null;
    input.addEventListener('pointerdown', (event) => {
      if (event.button !== 0) return;
      gesture = { pointerId: event.pointerId, startY: event.clientY, target: initialPosition, active: false };
      input.setPointerCapture?.(event.pointerId);
    });
    input.addEventListener('pointermove', (event) => {
      if (!gesture || gesture.pointerId !== event.pointerId) return;
      if (!gesture.active && Math.abs(event.clientY - gesture.startY) < 8) return;
      if (!gesture.active) {
        gesture.active = true;
        row.classList.add('pathway-dragging');
        pathwaySortable.classList.add('pathway-sorting');
      }
      event.preventDefault();
      clearDropMarkers();
      const candidates = rows().filter((candidate) => candidate !== row);
      let target = candidates.length + 1;
      for (let index = 0; index < candidates.length; index++) {
        const bounds = candidates[index].getBoundingClientRect();
        if (event.clientY < bounds.top + bounds.height / 2) { target = index + 1; break; }
      }
      gesture.target = target;
      if (target <= candidates.length) candidates[target - 1].classList.add('pathway-drop-before');
      else candidates[candidates.length - 1]?.classList.add('pathway-drop-after');
      if (event.clientY < 72) window.scrollBy(0, -14);
      else if (event.clientY > window.innerHeight - 72) window.scrollBy(0, 14);
    });
    const finishGesture = (event, cancelled = false) => {
      if (!gesture || gesture.pointerId !== event.pointerId) return;
      const target = gesture.target;
      const active = gesture.active;
      gesture = null;
      row.classList.remove('pathway-dragging');
      pathwaySortable.classList.remove('pathway-sorting');
      clearDropMarkers();
      try { input.releasePointerCapture?.(event.pointerId); } catch (_) { /* already released */ }
      if (!cancelled && active && target !== initialPosition) {
        input.value = String(target);
        form.requestSubmit();
      }
    };
    input.addEventListener('pointerup', (event) => finishGesture(event));
    input.addEventListener('pointercancel', (event) => finishGesture(event, true));
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

document.querySelectorAll('[data-evaluation-toggle]').forEach((toggle) => {
  const form = toggle.closest('form');
  const weight = form?.querySelector('[data-evaluation-weight]');
  if (!weight) return;
  const syncEvaluationWeight = () => { weight.hidden = !toggle.checked; };
  toggle.addEventListener('change', syncEvaluationWeight);
  syncEvaluationWeight();
});

document.querySelectorAll('[data-page-picker]').forEach((picker) => {
  const select = picker.querySelector('[data-page-picker-select]');
  const fallback = picker.querySelector('.page-picker-fallback');
  const enhanced = picker.querySelector('[data-page-picker-enhanced]');
  const search = picker.querySelector('[data-page-picker-search]');
  const empty = picker.querySelector('[data-page-picker-empty]');
  const results = Array.from(picker.querySelectorAll('[data-page-picker-result]'));
  if (!select || !enhanced || !search) return;
  fallback.hidden = true;
  enhanced.hidden = false;
  const fold = (value) => String(value || '').toLocaleLowerCase(locale).normalize('NFD').replace(/\p{Diacritic}/gu, '').trim();
  let chosenPageId = '';
  const selectPage = (id, collapse = false) => {
    select.value = String(id);
    results.forEach((result) => {
      const selected = result.dataset.pageId === String(id);
      result.classList.toggle('selected', selected);
      result.setAttribute('aria-pressed', selected ? 'true' : 'false');
      if (collapse) result.hidden = !selected;
    });
    if (collapse) {
      chosenPageId = String(id);
      search.value = '';
      if (empty) empty.hidden = true;
    }
  };
  const filterPages = () => {
    const query = fold(search.value);
    let visible = 0;
    results.forEach((result) => {
      const matches = chosenPageId !== ''
        ? result.dataset.pageId === chosenPageId
        : query !== '' && fold(result.dataset.pageSearchText).includes(query);
      result.hidden = !matches;
      if (matches) visible++;
    });
    if (empty) empty.hidden = chosenPageId !== '' || query === '' || visible !== 0;
  };
  results.forEach((result) => result.addEventListener('click', () => selectPage(result.dataset.pageId, true)));
  search.addEventListener('input', () => { chosenPageId = ''; filterPages(); });
  selectPage(select.value);
  filterPages();
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

document.querySelectorAll('[data-student-import]').forEach((form) => form.addEventListener('submit', (event) => {
  if (event.defaultPrevented) return;
  if (form.querySelector('[name="account_activation"]')?.value !== 'immediate') return;
  if (!window.confirm(form.dataset.immediateConfirm || '')) event.preventDefault();
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
