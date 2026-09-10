(() => {
  const userId = document.body.dataset.userId;
  if (!userId) return;
  const dictionary = document.getElementById('chat-i18n');
  const labels = dictionary ? JSON.parse(dictionary.textContent) : {};
  const text = (key, fallback) => labels[key] || fallback;
  const post = async (action, values = {}) => {
    const response = await fetch('.', { method: 'POST', credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, body: new URLSearchParams({ action, token: document.body.dataset.csrf, ...values }) });
    const data = await response.json();
    if (!response.ok || data.ok === false) throw new Error(data.error || text('error', 'Impossible de terminer cette opération. Réessayez.'));
    return data;
  };
  let badgeBusy = false;
  const updateBadges = async () => {
    if (document.hidden || badgeBusy) return;
    badgeBusy = true;
    try {
      const response = await fetch('?view=notification-status', { credentials: 'same-origin', cache: 'no-store' });
      if (!response.ok) return;
      const data = await response.json();
      document.querySelectorAll('[data-chat-unread]').forEach(n => { n.textContent = data.messages || ''; n.hidden = !data.messages; });
      if ('setAppBadge' in navigator) await (data.count ? navigator.setAppBadge(data.count) : navigator.clearAppBadge());
    } catch (_) {} finally { badgeBusy = false; }
  };
  updateBadges();const badgeTimer=setInterval(updateBadges,30000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) updateBadges(); });
  window.addEventListener('pageshow',event=>{if(event.persisted)location.reload();});
  window.addEventListener('pagehide',()=>clearInterval(badgeTimer),{once:true});
  const feedback = document.querySelector('[data-push-feedback]');
  document.querySelectorAll('[data-enable-push]').forEach(button => {
    let active = false, busy = false;
    const supported = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    const render = () => {
      (button.querySelector('[data-push-label]') || button).textContent = active ? text('disableNotifications', 'Désactiver les notifications') : text('enableNotifications', 'Activer les notifications');
      const icon = button.querySelector('[data-push-icon]');
      if (icon) icon.className = active ? 'bi bi-bell-slash' : 'bi bi-bell';
      button.dataset.pushActive = active ? '1' : '0';
      button.disabled = busy;
    };
    const refresh = async () => {
      if (busy || document.hidden) return;
      busy = true; render();
      try {
        const registration = supported() ? await navigator.serviceWorker.getRegistration() : null;
        const subscription = await registration?.pushManager.getSubscription();
        const state = subscription ? await post('messaging_push_status', { endpoint: subscription.endpoint }) : null;
        active = supported() && Notification.permission === 'granted' && !!state?.active;
        registration?.active?.postMessage({ type: 'notification-owner', subscription: active ? state.subscription : null });
      } catch (_) { /* Keep the last known state if the network is unavailable. */ }
      finally { busy = false; render(); }
    };
    button.addEventListener('click', async () => {
      if (busy) return;
      busy = true; render();
      if (feedback) feedback.textContent = '';
      try {
        if (active) {
          await post('messaging_push_disable');
          active = false;
          const registration = await navigator.serviceWorker.getRegistration();
          registration?.active?.postMessage({ type: 'notification-owner', subscription: null });
          // Server removal stops delivery even if the browser cannot clean up its subscription.
          try { const subscription = await registration?.pushManager.getSubscription(); await subscription?.unsubscribe(); } catch (_) {}
          if (feedback) feedback.textContent = text('notificationsOff', 'Notifications désactivées sur cet appareil.');
        } else {
          if (!supported()) throw new Error(text('permission', 'Les notifications ne sont pas autorisées sur cet appareil.'));
          // Request permission directly from the click, as required by iOS.
          const permission = await Notification.requestPermission();
          if (permission !== 'granted') throw new Error(text('permission', 'Les notifications ne sont pas autorisées sur cet appareil.'));
          const { publicKey } = await post('messaging_push_prepare');
          const registration = await navigator.serviceWorker.ready;
          const bytes = Uint8Array.from(atob(publicKey.replace(/-/g,'+').replace(/_/g,'/')), c=>c.charCodeAt(0));
          let subscription = await registration.pushManager.getSubscription();
          if (!subscription) subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: bytes });
          const result = await post('messaging_push_subscribe', { subscription: JSON.stringify(subscription.toJSON()) });
          registration.active?.postMessage({ type: 'notification-owner', subscription: result.subscription });
          active = true;
          if (feedback) feedback.textContent = text('notifications', 'Notifications activées.');
          await updateBadges();
        }
      } catch (e) { if (feedback) feedback.textContent = e.message; }
      finally { busy = false; render(); }
    });
    refresh();
    window.addEventListener('focus', refresh);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
  });
  document.addEventListener('submit', event => {
    if (event.target.querySelector('[name=action]')?.value === 'logout') {
      navigator.clearAppBadge?.().catch(()=>{});
      navigator.serviceWorker?.controller?.postMessage({type:'notification-owner',subscription:null});
    }
    if(event.target.matches('[data-chat-erase]')&&!confirm(event.target.dataset.confirm))event.preventDefault();
  });
  const course=document.querySelector('[data-chat-course]');const recipient=document.querySelector('[data-chat-recipient]');
  const filterRecipients=()=>{if(!course||!recipient)return;for(const option of recipient.options){option.disabled=!JSON.parse(option.dataset.courses).includes(Number(course.value));option.hidden=option.disabled;}if(recipient.selectedOptions[0]?.disabled)recipient.value=[...recipient.options].find(o=>!o.disabled)?.value||'';};
  course?.addEventListener('change',filterRecipients);filterRecipients();
  document.getElementById('new-discussion')?.addEventListener('show.bs.modal', () => {
    const toggle = document.querySelector('.chat-actions-toggle');
    if (toggle) bootstrap.Dropdown.getInstance(toggle)?.hide();
  });
  if(document.getElementById('new-discussion')&&new URLSearchParams(location.search).has('new'))bootstrap.Modal.getOrCreateInstance(document.getElementById('new-discussion')).show();
  const root=document.querySelector('[data-chat-root]');if(!root)return;
  const mobile = matchMedia('(max-width:700px)');
  if (mobile.matches && !new URLSearchParams(location.search).has('thread')) root.classList.remove('has-thread');
  let viewportFrame;
  const fitViewport = () => {
    const open = mobile.matches && root.classList.contains('has-thread');
    const messages = root.querySelector('[data-chat-messages]');
    const pinned = messages && messages.scrollHeight - messages.scrollTop - messages.clientHeight < 60;
    document.body.classList.toggle('chat-thread-open', open);
    if (open) {
      const viewport = window.visualViewport;
      root.style.setProperty('--chat-viewport-height', `${viewport?.height || innerHeight}px`);
      root.style.setProperty('--chat-viewport-top', `${viewport?.offsetTop || 0}px`);
      root.style.setProperty('--chat-viewport-left', `${viewport?.offsetLeft || 0}px`);
      root.style.setProperty('--chat-viewport-width', `${viewport?.width || innerWidth}px`);
      if (pinned) messages.scrollTop = messages.scrollHeight;
    }
  };
  const queueViewport = () => { cancelAnimationFrame(viewportFrame); viewportFrame = requestAnimationFrame(fitViewport); };
  window.visualViewport?.addEventListener('resize', queueViewport);
  window.visualViewport?.addEventListener('scroll', queueViewport);
  window.addEventListener('resize', queueViewport);mobile.addEventListener('change', queueViewport);
  root.querySelector('[data-chat-back]')?.addEventListener('click', () => {
    root.querySelector('[data-chat-body]')?.blur();root.classList.remove('has-thread');
    const url = new URL(location.href);url.searchParams.delete('thread');history.replaceState(null, '', url);fitViewport();
  });
  root.querySelectorAll('.chat-preview').forEach(link => link.addEventListener('click', event => {
    if (!mobile.matches || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    if (new URL(link.href).searchParams.get('thread') !== root.dataset.thread) return;
    event.preventDefault();root.classList.add('has-thread');history.replaceState(null, '', link.href);fitViewport();
    const messages=root.querySelector('[data-chat-messages]');if(messages){messages.scrollTop=messages.scrollHeight;messages.dispatchEvent(new Event('scroll'));}
  }));
  fitViewport();
  const thread=root.dataset.thread;if(!thread)return;
  const managed=root.dataset.managed==='1', ownKey=root.dataset.userKey;
  const list=root.querySelector('[data-chat-messages]'), older=root.querySelector('[data-chat-older]'), form=root.querySelector('[data-chat-compose]');
  const body=form?.querySelector('[data-chat-body]'), counter=form?.querySelector('[data-chat-counter]'), submit=form?.querySelector('[data-chat-submit]'), cancel=form?.querySelector('[data-chat-cancel-edit]'), error=root.querySelector('[data-chat-feedback]');
  let editing=null,draft='',busy=false,canWrite=Boolean(form),initialized=false,sending=false,requestKey=form?.elements.request_key.value;
  const messages=new Map();let serverOffset=0;
  const setSubmitLabel = label => { if (!submit) return; submit.setAttribute('aria-label', label);submit.title=label;submit.querySelector('i').className=editing?'bi bi-check-lg':'bi bi-send-fill'; };
  const fitInput = () => {
    if (!body) return;
    const pinned=list.scrollHeight-list.scrollTop-list.clientHeight<60;
    body.style.height='0px';body.style.height=Math.min(96,Math.max(40,body.scrollHeight))+'px';
    if(pinned)list.scrollTop=list.scrollHeight;
  };
  const updateCounter=()=>{if(!form)return;const size=Array.from(body.value).length;counter.textContent=`${size} / 256`;counter.hidden=size<224;body.setCustomValidity(size>256?labels.length || '256 caractères maximum.':'');submit.disabled=sending||!canWrite||!body.value.trim()||size>256;fitInput();};
  body?.addEventListener('input',updateCounter);body?.addEventListener('focus',queueViewport);body?.addEventListener('blur',queueViewport);updateCounter();
  const cancelEdit=()=>{editing=null;if(body)body.value=draft;draft='';if(cancel)cancel.hidden=true;setSubmitLabel(text('send','Envoyer'));updateCounter();};cancel?.addEventListener('click',cancelEdit);
  const timeFormat=new Intl.DateTimeFormat(document.documentElement.lang,{timeZone:'Europe/Zurich',hour:'2-digit',minute:'2-digit',hourCycle:'h23'});
  const dayFormat=new Intl.DateTimeFormat(document.documentElement.lang,{timeZone:'Europe/Zurich',day:'numeric',month:'long',year:'numeric'});
  let lastRender='';
  const render=(prepend=false)=>{
    const ordered=[...messages.values()].sort((a,b)=>a.id-b.id);
    const signature=JSON.stringify(ordered.map(m=>[m.id,m.revision,m.html,m.edited_at,m.editable]));
    if(signature===lastRender)return;lastRender=signature;
    const atBottom=!initialized||list.scrollHeight-list.scrollTop-list.clientHeight<60, previousHeight=list.scrollHeight,previousTop=list.scrollTop;
    const fragment=document.createDocumentFragment();
    let previousDay='';
    for(const message of ordered){
      const date=new Date(message.created_at*1000),day=dayFormat.format(date);
      if(day!==previousDay){const divider=document.createElement('div');divider.className='chat-day';const dayLabel=document.createElement('time');dayLabel.dateTime=date.toISOString();dayLabel.textContent=day;divider.append(dayLabel);fragment.append(divider);previousDay=day;}
      const article=document.createElement('article');article.className='chat-bubble'+(message.author_key===ownKey?' own':'');article.dataset.messageId=message.id;
      article.setAttribute('aria-label',`${message.author_name} · ${day} · ${timeFormat.format(date)}`);
      const meta=document.createElement('div');meta.className='chat-message-meta';const time=document.createElement('time');time.dateTime=date.toISOString();time.textContent=timeFormat.format(date);meta.append(time);article.append(meta);
      const content=document.createElement('p');content.innerHTML=message.html;article.append(content);
      if(message.edited_at){const edited=document.createElement('em');edited.textContent=text('edited','Modifié');edited.className='visually-hidden';time.title=text('edited','Modifié');meta.prepend(edited);}
      if(message.editable&&Date.now()/1000+serverOffset<message.created_at+180){const edit=document.createElement('button');edit.className='chat-edit';edit.type='button';edit.title=text('edit','Modifier');edit.setAttribute('aria-label',text('edit','Modifier'));const icon=document.createElement('i');icon.className='bi bi-pencil';icon.setAttribute('aria-hidden','true');edit.append(icon);edit.dataset.editMessage=message.id;edit.addEventListener('click',()=>{if(sending)return;if(!editing)draft=body.value;editing=message;body.value=message.body;cancel.hidden=false;setSubmitLabel(text('save','Enregistrer'));updateCounter();body.focus();});meta.prepend(edit);}
      fragment.append(article);
    }
    list.replaceChildren(fragment);if(atBottom)list.scrollTop=list.scrollHeight;else list.scrollTop=previousTop+(prepend?Math.max(0,list.scrollHeight-previousHeight):0);initialized=true;
  };
  let readThrough=0;
  const markRead=async()=>{
    if(managed||document.hidden||(mobile.matches&&!root.classList.contains('has-thread'))||list.scrollHeight-list.scrollTop-list.clientHeight>60)return;
    const through=Math.max(0,...messages.keys());if(through<=readThrough)return;
    try{await post('messaging_read',{thread,through:String(through)});readThrough=through;updateBadges();}catch(_){}
  };
  let loadAgain=false;
  const load=async(before=0)=>{
    if(document.hidden)return;if(busy){if(!before)loadAgain=true;return;}busy=true;
    try{
      const response=await fetch(`?view=discussion-data&thread=${encodeURIComponent(thread)}&managed=${managed?'1':'0'}&before=${before}`,{credentials:'same-origin',cache:'no-store'});
      const data=await response.json();if(!response.ok)throw new Error(data.error||text('error','Impossible de terminer cette opération. Réessayez.'));
      serverOffset=data.server_time-Date.now()/1000;canWrite=data.can_write;for(const message of data.messages)messages.set(message.id,message);
      if(before||!initialized)older.hidden=!data.has_older;
      if(form){form.hidden=!canWrite;updateCounter();}render(before>0);error.textContent='';await markRead();
    }catch(e){error.textContent=e.message;}finally{busy=false;if(loadAgain){loadAgain=false;load();}}
  };
  older?.addEventListener('click',()=>load(Math.min(...messages.keys())));list.addEventListener('scroll',markRead);
  submit?.addEventListener('pointerdown',event=>{if(mobile.matches&&document.activeElement===body)event.preventDefault();});
  form?.addEventListener('submit',async event=>{
    event.preventDefault();if(sending||!canWrite||!body.reportValidity())return;sending=true;submit.disabled=true;const sentBody=body.value;
    try{
      await post(editing?'messaging_edit':'messaging_send',{thread,body:sentBody,request_key:requestKey,message_id:String(editing?.id||0),revision:String(editing?.revision??0)});
      if(editing)cancelEdit();else{if(body.value===sentBody)body.value='';requestKey=crypto.randomUUID();form.elements.request_key.value=requestKey;}
      updateCounter();await load();list.scrollTop=list.scrollHeight;await markRead();
    }catch(e){error.textContent=e.message;}finally{sending=false;updateCounter();}
  });
  load();const poll=setInterval(()=>load(),5000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)load();});window.addEventListener('pagehide',()=>clearInterval(poll),{once:true});
})();
