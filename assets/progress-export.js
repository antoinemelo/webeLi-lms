(() => {
  const form = document.querySelector('[data-progress-export]');
  if (!form) return;
  const students = [...form.querySelectorAll('[name="students[]"]')];
  const all = form.querySelector('[data-progress-all]');
  const update = () => {
    const count = students.filter(input => input.checked).length;
    all.checked = count > 0 && count === students.length;
    all.indeterminate = count > 0 && count < students.length;
    form.querySelector('[data-progress-count]').textContent = (count === 1 ? form.dataset.singleLabel : form.dataset.countLabel).replace(':count', count);
    form.querySelector('[type="submit"]').disabled = count === 0;
    const detailed = form.elements.mode.value === 'detailed';
    form.querySelector('[data-progress-summary]').hidden = detailed;
    form.querySelector('[data-progress-detailed]').hidden = !detailed;
  };
  all.addEventListener('change', () => {
    students.forEach(input => { input.checked = all.checked; });
    update();
  });
  form.addEventListener('change', update);
  const fold = value => value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase();
  form.querySelector('[data-progress-search]').addEventListener('input', event => {
    const search = fold(event.target.value.trim());
    form.querySelectorAll('[data-progress-student]').forEach(label => { label.hidden = !fold(label.textContent).includes(search); });
  });
  update();
})();
