/* Contextual page editor. Hidden text inputs stay enabled to preserve array indexes. */
(() => {
  const initialize = () => {
    document.querySelectorAll('#blocks [data-content-config]').forEach((block, index) => {
      const file = block.querySelector('[data-block-file]');
      file.name = `block_file[${index}]`;
      if (block.dataset.contentReady) return;
      block.dataset.contentReady = 'true';
      const config = JSON.parse(block.dataset.contentConfig);
      const get = (name) => block.querySelector(`[data-block-${name}]`);
      const type = get('type'), source = get('source'), body = get('body');
      const caption = get('caption'), alt = get('alt'), height = get('height');
      const preview = get('author-preview'), feedback = get('feedback');
      let kind = type.value, previewRequest = 0, objectUrl = null;
      const states = new Map(), sources = new Map([[source.value, body.value]]);
      const initialBody = body.value, initialFile = get('current-file').textContent;
      const family = (value) => ['image', 'file'].includes(value) ? 'asset' : ['iframe', 'media'].includes(value) ? 'embed' : 'text';
      const snapshot = () => ({ body: body.value, caption: caption.value, alt: alt.value, height: height.value, source: source.value });
      const report = (message = '') => { feedback.textContent = message; feedback.hidden = !message; };
      const hidePreview = () => {
        previewRequest++; preview.hidden = true; preview.replaceChildren(); get('preview-close').hidden = true;
        if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
      };
      const imagePreview = () => {
        hidePreview();
        if (kind !== 'image') return;
        const address = source.value === 'upload' && file.files.length ? (objectUrl = URL.createObjectURL(file.files[0])) : body.value.trim();
        if (!address || !(objectUrl || /^(https?:\/\/|uploads\/|assets\/)/i.test(address))) return;
        const img = new Image(); img.alt = alt.value;
        img.addEventListener('load', () => { if (preview.contains(img)) preview.hidden = false; });
        img.addEventListener('error', () => { if (preview.contains(img)) { preview.hidden = true; report(config.imageError); } });
        preview.append(img); img.src = address;
      };
      const clearFile = () => { if (file.files.length) { file.value = ''; report(config.fileCleared); } };
      const update = () => {
        const settings = config.types[kind], asset = family(kind) === 'asset', importing = asset && source.value === 'upload';
        block.dataset.contentKind = kind;
        for (const [name, show] of Object.entries({ 'source-group': asset, 'body-group': !importing, 'upload-group': importing, 'alt-group': kind === 'image', 'caption-group': kind !== 'markdown', 'height-group': kind === 'iframe', 'markdown-actions': kind === 'markdown' || kind === 'submission' })) get(name).hidden = !show;
        block.querySelector('.submission-settings').hidden = kind !== 'submission';
        get('body-label').textContent = settings.body;
        get('body-label').hidden = kind === 'markdown' || kind === 'media';
        body.setAttribute('aria-label', settings.body); body.rows = settings.rows;
        get('caption-label').textContent = settings.caption;
        const importLabel = kind === 'image' ? config.importImage : config.importFile;
        get('upload-label').textContent = importLabel; source.options[0].textContent = importLabel;
        file.accept = kind === 'image' ? 'image/jpeg,image/png,image/gif,image/webp,image/avif,image/bmp' : '';
        file.disabled = !importing || !!file.dataset.collaborationDisabled;
        if (kind === 'iframe') { height.min = '100'; height.max = '2000'; }
        else { height.removeAttribute('min'); height.removeAttribute('max'); }
        get('current-file').textContent = body.value === initialBody && !file.files.length ? initialFile : '';
        hidePreview(); imagePreview();
      };
      type.addEventListener('change', () => {
        const next = type.value;
        if (next === kind) return;
        if (body.value.trim() && family(next) !== family(kind) && !window.confirm(config.switchWarning)) { type.value = kind; return; }
        states.set(kind, snapshot()); report(); clearFile();
        const previous = kind;
        const restored = states.get(next) || { ...snapshot(), body: family(next) === family(previous) ? body.value : '', source: 'upload' };
        kind = next;
        body.value = restored.body; caption.value = restored.caption; alt.value = restored.alt; height.value = restored.height; source.value = restored.source;
        if (!states.has(next) && family(next) === 'asset' && /^https?:\/\//i.test(body.value)) source.value = 'url';
        sources.clear(); sources.set(source.value, body.value);
        update();
      });
      let lastSource = source.value;
      source.addEventListener('change', () => {
        sources.set(lastSource, body.value); report(); clearFile();
        body.value = sources.get(source.value) || ''; lastSource = source.value; update();
      });
      type.addEventListener('change', () => { lastSource = source.value; });
      file.addEventListener('change', () => { report(); update(); });
      body.addEventListener('input', () => { report(); hidePreview(); });
      body.addEventListener('change', imagePreview);
      alt.addEventListener('input', () => { const img = preview.querySelector('img'); if (img) img.alt = alt.value; });
      get('preview-close').addEventListener('click', hidePreview);
      get('preview').addEventListener('click', async () => {
        hidePreview(); report(); const request = previewRequest;
        const data = new FormData(); data.set('action', 'preview_content_block'); data.set('token', document.body.dataset.csrf || ''); data.set('body', body.value);
        try {
          const response = await fetch(location.href, { method: 'POST', body: data, headers: { Accept: 'application/json' } });
          const result = await response.json();
          if (request !== previewRequest) return;
          if (!response.ok) throw new Error(result.error || config.previewError);
          preview.innerHTML = result.html;
          preview.querySelectorAll('input,textarea,select,button').forEach((control) => { control.disabled = true; control.removeAttribute('name'); });
          preview.querySelectorAll('form').forEach((form) => form.replaceWith(...form.childNodes));
          preview.hidden = false; get('preview-close').hidden = false;
        } catch (error) { if (request === previewRequest) report(error.message || config.previewError); }
      });
      update();
    });
  };
  initialize();
  document.addEventListener('click', (event) => { if (event.target.closest('[data-add-block],[data-remove-block]')) initialize(); });
})();
