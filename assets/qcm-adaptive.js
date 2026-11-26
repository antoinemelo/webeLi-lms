/* One request per answer/expiry; no polling. Server time and deadlines remain authoritative. */
document.querySelectorAll('[data-adaptive-qcm]').forEach((root) => {
  const labels = JSON.parse(root.dataset.labels);
  const content = root.querySelector('[data-adaptive-content]');
  const error = root.querySelector('[data-adaptive-error]');
  const preview = root.dataset.preview === '1';
  const restart = root.querySelector('[data-adaptive-restart]');
  let state = null, busy = false, tick = null, retry = null, clockStart = 0, serverStart = 0, displayed = '', viewGeneration = 0;
  const node = (tag, text, className = '') => {
    const element = document.createElement(tag);
    if (text !== undefined) element.textContent = text;
    element.className = className;
    return element;
  };
  const button = (text, action) => {
    const element = node('button', text, 'button primary'); element.type = 'button';
    element.addEventListener('click', action); return element;
  };
  const remaining = (seconds) => {
    seconds = Math.max(0, Math.ceil(seconds));
    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
  };
  const paint = () => {
    clearInterval(tick); content.replaceChildren();
    if (restart) { restart.hidden = !state.id || state.finished; restart.disabled = false; }
    if (state.ready) {
      content.append(node('p', labels.ready), button(labels.start, () => request('start'))); return;
    }
    if (state.finished) {
      content.append(node('h3', labels.done), node('p', labels[state.reason] || ''));
      const number = value => new Intl.NumberFormat(document.documentElement.lang, { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(value);
      const percent = value => new Intl.NumberFormat(document.documentElement.lang, { minimumFractionDigits: 1, maximumFractionDigits: 2 }).format(value);
      if (state.score !== null) content.append(node('p', `${labels.score} : ${number(state.score / 10)} /10`, 'adaptive-score'));
      if (state.grading?.method === 'all_answers') {
        content.append(node('p', `${labels.basis} : ${state.grading.answered}`));
        if (state.score === null) content.append(node('p', state.controlled ? labels.controlled : labels.insufficient, 'adaptive-feedback'));
        else if (state.grading.provisional) content.append(node('p', labels.provisional));
      } else if (state.grading && state.grading.raw_score !== null) {
        content.append(node('p', `${labels.difficulty} : ${percent(state.grading.raw_score)} %`));
        content.append(node('p', state.grading.relative
          ? `${labels.scale} : ${percent(state.grading.min)} % → ${percent(state.grading.max)} %`
          : labels.initial));
      }
      if (state.review?.length) {
        const review = node('section', undefined, 'adaptive-review'); review.dataset.adaptiveReview = '';
        review.append(node('h3', labels.review), node('p', labels.temporary));
        for (const question of state.review) {
          const section = node('section', undefined, 'adaptive-review-question');
          section.append(node('h4', question.title), node('p', labels[question.result]));
          const options = node('ul', undefined, 'adaptive-review-options');
          for (const answer of question.answers) {
            const chosen = question.selected.includes(answer.index);
            if (answer.kind !== 'v' && (question.result === 'correct' || !chosen)) continue;
            const kind = answer.kind === 'v' ? (question.result === 'correct' ? 'correct' : 'expected') : (answer.kind === 'x' ? 'incorrect' : 'neutral');
            const label = kind === 'correct' ? labels.correct : kind === 'expected' ? labels.expected : kind === 'incorrect' ? labels.yourIncorrect : labels.neutral;
            const option = node('li', undefined, `adaptive-review-answer adaptive-review-${kind}`);
            option.append(node('strong', `${label}${chosen && kind === 'expected' ? ` · ${labels.selected}` : ''}`), node('span', answer.text));
            options.append(option);
          }
          section.append(options); review.append(section);
        }
        // The correction is immediately beneath the score, before the calculation notes.
        const score = content.querySelector('.adaptive-score');
        if (score) score.after(review); else content.append(review);
      } else content.append(node('p', labels.reviewGone));
      if (preview || !state.evaluation) content.append(button(labels.retry, () => request('start')));
      return;
    }
    if (state.feedback) content.append(node('p', `${labels[state.feedback.result]} · ${state.feedback.title}\n${labels.expected} : ${state.feedback.correct.join(' ; ')}`, 'adaptive-feedback'));
    const question = state.question; if (!question) return;
    const clocks = node('div', undefined, 'adaptive-clocks');
    const total = node('span'), current = node('span'); clocks.append(total, current); content.append(clocks);
    const form = node('form');
    const group = node('fieldset'); group.append(node('legend', `${labels.question} ${question.ordinal} · ${question.title}`));
    group.append(node('p', question.multiple ? labels.multiple : labels.single));
    for (const option of question.answers) {
      const label = node('label', undefined, 'adaptive-option');
      const input = node('input'); input.type = question.multiple ? 'checkbox' : 'radio'; input.name = 'answers[]'; input.value = option.index;
      label.append(input, node('span', option.text)); group.append(label);
    }
    const submit = node('button', labels.send, 'button primary'); submit.type = 'submit'; form.append(group, submit);
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      request('answer', { run_id: state.id, ordinal: question.ordinal, answers: [...new FormData(form).getAll('answers[]')] });
    });
    content.append(form);
    const questionKey = `${state.id}:${question.ordinal}`;
    if (displayed !== questionKey) {
      displayed = questionKey;
      group.querySelector('legend').tabIndex = -1;
      group.querySelector('legend').focus({ preventScroll: true });
      root.scrollIntoView({ block: 'start', behavior: 'instant' });
    }
    const update = () => {
      const now = serverStart + (performance.now() - clockStart) / 1000;
      total.textContent = `${labels.total} : ${remaining(state.ends_at - now)}`;
      current.textContent = `${labels.remaining} : ${remaining(question.deadline - now)}`;
      if (now >= question.deadline) {
        clearInterval(tick); group.disabled = true; submit.disabled = true;
        if (!busy && !retry) request('state');
      }
    };
    tick = setInterval(update, 250); update();
  };
  async function request(operation = 'state', payload = {}) {
    if (busy) return; busy = true; retry = null; error.hidden = true;
    const generation = viewGeneration;
    root.querySelectorAll('button').forEach((element) => { element.disabled = true; });
    const data = new FormData();
    data.set('token', document.body.dataset.csrf || ''); data.set('item_id', root.dataset.item); data.set('operation', operation);
    data.set('epoch', state?.epoch || '0:0');
    if (preview) data.set('preview', '1');
    for (const [key, value] of Object.entries(payload)) {
      if (Array.isArray(value)) value.forEach((entry) => data.append(`${key}[]`, entry)); else data.set(key, value);
    }
    try {
      const response = await fetch('?view=adaptive-qcm', { method: 'POST', body: data, headers: { Accept: 'application/json' }, cache: 'no-store' });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || labels.error);
      if (generation !== viewGeneration) result.review = [];
      state = result; serverStart = state.now; clockStart = performance.now(); busy = false; paint();
    } catch (failure) {
      busy = false; if (generation !== viewGeneration) return;
      retry = () => request(operation, payload); error.replaceChildren(node('span', failure.message || labels.error), button(labels.reload, () => retry?.())); error.hidden = false;
    }
  }
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden && state && !state.ready && !state.finished && !busy && !retry) request('state');
  });
  restart?.addEventListener('click', () => { if (state?.id) request('restart', { run_id: state.id }); });
  window.addEventListener('pagehide', () => {
    viewGeneration++; clearInterval(tick); retry = null;
    if (state) { state.review = []; delete state.feedback; }
    content.querySelector('[data-adaptive-review]')?.remove();
  });
  window.addEventListener('pageshow', (event) => { if (event.persisted) request('state'); });
  request();
});
