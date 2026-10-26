/* New QCM modes are isolated from the classic form and its draft/submission handlers. */
(() => {
  const ui = (scope, key) => JSON.parse(scope.dataset.qcmLabels || scope.dataset.labels)[key];
  const fullscreen = async (scope) => {
    const status = scope.querySelector('[data-qcm-screen-status]');
    try {
      if (!document.fullscreenElement) {
        if (!document.documentElement.requestFullscreen) throw new Error('unsupported');
        await document.documentElement.requestFullscreen();
      }
      if (status) status.textContent = '';
    } catch (_) {
      if (status) status.textContent = ui(scope, 'fullscreenUnavailable');
    }
  };
  document.querySelectorAll('[data-qcm-fullscreen]').forEach((button) => {
    button.addEventListener('click', () => fullscreen(button.closest('[data-qcm-strict], [data-qcm-live]')));
  });
  document.querySelectorAll('[data-qcm-strict]').forEach((scope) => {
    const dialog = scope.querySelector('dialog');
    const start = scope.querySelector('[data-qcm-start]');
    start.addEventListener('click', () => {
      dialog.showModal();
      document.body.classList.add('qcm-strict-active');
      void fullscreen(scope);
      dialog.querySelector('.qcm-options input')?.focus();
    });
    dialog.addEventListener('cancel', (event) => event.preventDefault());
    document.addEventListener('fullscreenchange', () => {
      if (dialog.open && !document.fullscreenElement) {
        scope.querySelector('[data-qcm-screen-status]').textContent = ui(scope, 'fullscreenExited');
      }
    });
  });

  const help = document.querySelector('.qcm-authoring-modal');
  if (help) {
    const select = help.querySelector('[data-qcm-example-mode]');
    const example = help.querySelector('[data-qcm-example]');
    const status = help.querySelector('[data-qcm-copy-status]');
    select.addEventListener('change', () => {
      example.textContent = example.textContent.replace(/^:::qcm(?:_strict|_classe)?/, `:::${select.value}`);
      help.querySelectorAll('[data-qcm-mode-help]').forEach((text) => { text.hidden = text.dataset.qcmModeHelp !== select.value; });
      status.textContent = '';
    });
    help.querySelector('[data-qcm-copy]').addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(example.textContent);
        status.textContent = ui(help, 'copied');
      } catch (_) {
        const range = document.createRange(); range.selectNodeContents(example);
        const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(range);
        status.textContent = ui(help, 'copyFallback');
      }
    });
  }

  const root = document.querySelector('[data-qcm-live]');
  if (!root) return;
  const context = JSON.parse(root.dataset.context);
  const labels = JSON.parse(root.dataset.labels);
  const teacher = root.dataset.teacher === '1';
  const projection = root.dataset.projection === '1';
  const content = root.querySelector('[data-qcm-live-content]');
  const controls = root.querySelector('[data-qcm-live-controls]');
  const error = root.querySelector('[data-qcm-live-error]');
  const voteStatus = root.querySelector('[data-qcm-vote-status]');
  let state = null, viewKey = '', fetching = false, sending = false, dirty = false, online = false, generation = 0, timer;
  const label = (key, values = {}) => Object.entries(values).reduce((text, [name, value]) => text.replace(`:${name}`, value), labels[key]);
  const element = (tag, text, className) => {
    const node = document.createElement(tag);
    if (text !== undefined) node.textContent = text;
    if (className) node.className = className;
    return node;
  };
  const enable = () => {
    root.querySelectorAll('[data-live-action], [data-qcm-vote] input, [data-qcm-vote] button').forEach((node) => {
      node.disabled = !online || sending;
    });
  };
  const button = (key, command) => {
    const node = element('button', label(key), 'button primary'); node.type = 'button'; node.dataset.liveAction = command;
    node.addEventListener('click', () => send(command)); controls.append(node);
  };
  const render = () => {
    const key = state ? `${state.id}:${state.revision}:${state.phase}` : 'none';
    if (key !== viewKey) {
      viewKey = key; dirty = false; voteStatus.textContent = ''; content.replaceChildren(); controls.replaceChildren();
      if (!state || state.phase === 'waiting') {
        content.append(element('h1', label(teacher && !projection ? 'ready' : 'waiting')));
        content.append(element('p', label('join')));
        if (teacher && !projection) button(state ? 'open' : 'start', state ? 'open' : 'start');
      } else {
        content.append(element('p', label('question', { number: state.index + 1, total: state.total }), 'qcm-live-progress'));
        if (state.phase === 'finished') content.append(element('h1', label('finished')));
        content.append(element('h2', state.question.title));
        content.append(element('p', label(state.question.multiple ? 'multiple' : 'single'), 'muted'));
        const count = element('p', '', 'qcm-live-count'); count.dataset.liveCount = ''; count.setAttribute('aria-live', 'polite'); content.append(count);
        if (state.phase === 'open' && !teacher) {
          const form = element('form'); form.dataset.qcmVote = '';
          const group = element('fieldset', undefined, 'qcm-live-options');
          const legend = element('legend', state.question.title, 'qcm-visually-hidden'); group.append(legend);
          for (const answer of state.question.answers) {
            const choice = element('label', undefined, 'qcm-live-choice');
            const input = element('input'); input.type = state.question.multiple ? 'checkbox' : 'radio'; input.name = 'answers[]'; input.value = answer.index;
            choice.append(input, element('span', answer.text)); group.append(choice);
          }
          const submit = element('button', label('send'), 'button primary'); submit.type = 'submit'; form.append(group, submit);
          form.addEventListener('change', () => { dirty = true; voteStatus.textContent = ''; });
          form.addEventListener('submit', (event) => {
            event.preventDefault();
            const selected = [...form.querySelectorAll('input:checked')].map((input) => input.value);
            if (!selected.length) { voteStatus.textContent = label('choose'); return; }
            void send('vote', selected);
          });
          content.append(form);
        } else {
          const answers = element('div', undefined, 'qcm-live-options');
          for (const answer of state.question.answers) {
            const row = element('div', undefined, 'qcm-live-result'); row.dataset.answerIndex = answer.index;
            const text = element('span', answer.text); row.append(text);
            if (state.phase !== 'open') {
              const tally = element('strong'); tally.dataset.answerCount = ''; row.append(tally);
              const bar = element('progress'); bar.max = Math.max(1, state.responded); bar.value = answer.count; bar.setAttribute('aria-label', answer.text); row.append(bar);
              if (answer.kind) row.append(element('span', label({ v: 'right', x: 'wrong', '-': 'neutral' }[answer.kind]), `qcm-answer-kind qcm-kind-${answer.kind === 'v' ? 'right' : answer.kind === 'x' ? 'wrong' : 'neutral'}`));
            }
            answers.append(row);
          }
          content.append(answers);
          if (state.phase === 'results') content.append(element('p', label('closed')));
        }
        if (teacher && !projection) {
          if (state.phase === 'open') button('close', 'close');
          if (state.phase === 'results') {
            if (!state.revealed) button('reveal', 'reveal');
            button(state.index + 1 === state.total ? 'finish' : 'next', state.index + 1 === state.total ? 'finish' : 'next');
          }
          if (state.phase === 'finished') button('restart', 'start');
        }
      }
    }
    if (state?.question) {
      const count = content.querySelector('[data-live-count]');
      const countText = label('count', { count: state.responded, total: state.eligible });
      if (count && count.textContent !== countText) count.textContent = countText;
      content.querySelectorAll('[data-answer-index]').forEach((row) => {
        const answer = state.question.answers.find((entry) => entry.index === Number(row.dataset.answerIndex));
        const tally = row.querySelector('[data-answer-count]');
        if (tally) { tally.textContent = String(answer.count); const bar = row.querySelector('progress'); bar.max = Math.max(1, state.responded); bar.value = answer.count; }
      });
      if (!dirty) {
        content.querySelectorAll('[data-qcm-vote] input').forEach((input) => { input.checked = state.selected.includes(Number(input.value)); });
        if (!teacher && state.phase === 'open' && state.selected.length) voteStatus.textContent = label('saved');
      }
    }
    enable();
  };
  const refresh = async () => {
    if (fetching || sending) return;
    fetching = true; const requestGeneration = generation;
    try {
      const url = new URL(location.href); url.search = new URLSearchParams({ view: 'qcm-class-state', ...context });
      const response = await fetch(url, { cache: 'no-store', headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(10000) });
      const data = await response.json();
      if (requestGeneration !== generation) return;
      if (!response.ok) throw new Error(response.status === 401 ? label('login') : data.message || label('error'));
      state = data.session; online = true; error.hidden = true; render();
    } catch (exception) {
      if (requestGeneration !== generation) return;
      online = false; error.hidden = false; error.textContent = exception.name === 'TypeError' || exception.name === 'TimeoutError' ? label('error') : exception.message; enable();
    } finally { fetching = false; }
  };
  const send = async (command, answers = []) => {
    if (sending || !online) return;
    sending = true; generation++; enable();
    const data = new FormData();
    Object.entries({ action: 'qcm_class_command', token: document.body.dataset.csrf, ...context, command, session: state?.id || 0, revision: state?.revision || 0, question: state?.index ?? -1 }).forEach(([key, value]) => data.set(key, value));
    answers.forEach((value) => data.append('answers[]', value));
    if (command === 'vote') voteStatus.textContent = label('pending');
    try {
      const response = await fetch(location.href, { method: 'POST', body: data, headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(10000) });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || label('error'));
      dirty = false;
      if (command === 'vote') voteStatus.textContent = label('saved');
    } catch (exception) {
      voteStatus.textContent = exception.message || label('error');
    } finally {
      sending = false;
      await refresh(); enable();
    }
  };
  const poll = async () => { await refresh(); timer = window.setTimeout(poll, 1500); };
  document.addEventListener('visibilitychange', () => { if (!document.hidden) void refresh(); });
  window.addEventListener('online', () => { void refresh(); });
  window.addEventListener('offline', () => { online = false; error.hidden = false; error.textContent = label('error'); enable(); });
  window.addEventListener('pagehide', () => { window.clearTimeout(timer); });
  window.addEventListener('pageshow', (event) => { if (event.persisted) { window.clearTimeout(timer); void poll(); } });
  void poll();
})();
