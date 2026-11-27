/* Load the directory on demand. Filters share one request; selections survive filtering. */
document.querySelectorAll('[data-course-enrollment]').forEach(modal => {
  const labels = JSON.parse(modal.dataset.labels), selected = new Map();
  const search = modal.querySelector('[data-enrollment-search]'), group = modal.querySelector('[data-enrollment-group]'), course = modal.querySelector('[data-enrollment-course]');
  const list = modal.querySelector('[data-enrollment-list]'), chips = modal.querySelector('[data-enrollment-selected]'), count = modal.querySelector('[data-enrollment-count]');
  const feedback = modal.querySelector('[data-enrollment-feedback]'), retry = modal.querySelector('[data-enrollment-retry]'), submit = modal.querySelector('[data-enrollment-submit]');
  let timer, controller, generation = 0, facetsLoaded = false;
  const node = (tag, text, cls = '') => { const el = document.createElement(tag); el.textContent = text; el.className = cls; return el; };
  const updateSelection = () => {
    chips.replaceChildren(); count.textContent = labels.selected.replace(':count', selected.size); submit.disabled = !selected.size;
    for (const [id, name] of selected) {
      const input = document.createElement('input'); input.type = 'hidden'; input.name = 'students[]'; input.value = id;
      const button = node('button', name, 'btn btn-outline-secondary btn-sm'); button.type = 'button';
      button.setAttribute('aria-label', `${labels.remove} : ${name}`); button.title = `${labels.remove} : ${name}`;
      const icon = node('i', '', 'bi bi-x-lg'); icon.setAttribute('aria-hidden', 'true'); button.append(icon);
      button.addEventListener('click', () => { selected.delete(id); const check = list.querySelector(`input[value="${id}"]`); if (check) check.checked = false; updateSelection(); });
      chips.append(input, button);
    }
  };
  async function load() {
    clearTimeout(timer); controller?.abort(); controller = new AbortController(); const current = ++generation;
    feedback.textContent = labels.loading; retry.hidden = true; list.replaceChildren(); list.setAttribute('aria-busy', 'true');
    const params = new URLSearchParams({ view: 'course-enrollment-candidates', course: modal.dataset.courseEnrollment, search: search.value, group: group.value, filter_course: course.value, facets: facetsLoaded ? '0' : '1' });
    try {
      const response = await fetch(`?${params}`, { signal: controller.signal, cache: 'no-store', headers: { Accept: 'application/json' } });
      const data = await response.json(); if (!response.ok) throw new Error(data.error || labels.error);
      if (current !== generation) return;
      if (!facetsLoaded) {
        for (const value of data.groups) { const option = node('option', value); option.value = value; group.append(option); }
        for (const value of data.courses) { const option = node('option', value.title); option.value = value.id; course.append(option); }
        facetsLoaded = true;
      }
      list.replaceChildren();
      for (const student of data.students) {
        const id = String(student.id), name = `${student.first_name} ${student.last_name}`, enrolled = student.enrollment_status === 'active';
        if (enrolled) selected.delete(id);
        const row = node('label', ''), check = document.createElement('input'); check.type = 'checkbox'; check.value = id; check.checked = selected.has(id); check.disabled = enrolled;
        const avatar = node('span', student.initials, 'avatar mini'); avatar.style.setProperty('--avatar', student.color);
        const identity = node('span', ''); identity.append(node('b', name), node('small', [student.class_group, enrolled ? labels.enrolled : ''].filter(Boolean).join(' · ')));
        check.addEventListener('change', () => { if (check.checked) selected.set(id, name); else selected.delete(id); updateSelection(); });
        row.append(check, avatar, identity); list.append(row);
      }
      feedback.textContent = !data.students.length ? labels.empty : data.has_more ? labels.more : '';
      updateSelection();
    } catch (error) {
      if (error.name === 'AbortError' || current !== generation) return;
      feedback.textContent = error.message || labels.error; retry.hidden = false;
    } finally { if (current === generation) list.removeAttribute('aria-busy'); }
  }
  search.addEventListener('input', () => { clearTimeout(timer); controller?.abort(); generation++; timer = setTimeout(load, 250); });
  group.addEventListener('change', load); course.addEventListener('change', load); retry.addEventListener('click', load);
  modal.addEventListener('shown.bs.modal', load);
  modal.addEventListener('hidden.bs.modal', () => { clearTimeout(timer); controller?.abort(); generation++; });
  modal.querySelector('form').addEventListener('submit', event => { if (!selected.size) event.preventDefault(); else submit.disabled = true; });
  updateSelection();
});
