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
      button.textContent = active ? text('disableNotifications', 'Désactiver les notifications') : text('enableNotifications', 'Activer les notifications');
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
  if(document.getElementById('new-discussion')&&new URLSearchParams(location.search).has('new'))bootstrap.Modal.getOrCreateInstance(document.getElementById('new-discussion')).show();
  const root=document.querySelector('[data-chat-root]');if(!root)return;
  root.querySelector('[data-chat-back]')?.addEventListener('click',()=>root.classList.remove('has-thread'));
  const thread=root.dataset.thread;if(!thread)return;
  const managed=root.dataset.managed==='1', ownKey=root.dataset.userKey;
  const list=root.querySelector('[data-chat-messages]'), older=root.querySelector('[data-chat-older]'), form=root.querySelector('[data-chat-compose]');
  const body=form?.querySelector('[data-chat-body]'), counter=form?.querySelector('[data-chat-counter]'), submit=form?.querySelector('[data-chat-submit]'), cancel=form?.querySelector('[data-chat-cancel-edit]'), error=root.querySelector('[data-chat-feedback]');
  let editing=null,draft='',busy=false,canWrite=Boolean(form),initialized=false,requestKey=form?.elements.request_key.value;
  const messages=new Map();let serverOffset=0;
  const updateCounter=()=>{if(!form)return;const size=Array.from(body.value).length;counter.textContent=`${size} / 256`;body.setCustomValidity(size>256?labels.length || '256 caractères maximum.':'');submit.disabled=!canWrite||!body.value.trim()||size>256;};
  body?.addEventListener('input',updateCounter);updateCounter();
  const cancelEdit=()=>{editing=null;if(body)body.value=draft;draft='';if(cancel)cancel.hidden=true;if(submit)submit.textContent=text('send','Envoyer');updateCounter();};cancel?.addEventListener('click',cancelEdit);
  const render=(prepend=false)=>{
    const atBottom=!initialized||list.scrollHeight-list.scrollTop-list.clientHeight<60, previousHeight=list.scrollHeight,previousTop=list.scrollTop;
    const fragment=document.createDocumentFragment();
    for(const message of [...messages.values()].sort((a,b)=>a.id-b.id)){
      const article=document.createElement('article');article.className='chat-bubble'+(message.author_key===ownKey?' own':'');article.dataset.messageId=message.id;
      const byline=document.createElement('small');byline.textContent=`${message.author_name} · ${message.date}`;article.append(byline);
      const content=document.createElement('p');content.innerHTML=message.html;article.append(content);
      if(message.edited_at){const edited=document.createElement('em');edited.textContent=text('edited','Modifié');article.append(edited);}
      if(message.editable&&Date.now()/1000+serverOffset<message.created_at+180){const edit=document.createElement('button');edit.className='btn btn-sm btn-light';edit.type='button';edit.textContent=text('edit','Modifier');edit.dataset.editMessage=message.id;edit.addEventListener('click',()=>{if(!editing)draft=body.value;editing=message;body.value=message.body;cancel.hidden=false;submit.textContent=text('save','Enregistrer');updateCounter();body.focus();});article.append(edit);}
      fragment.append(article);
    }
    list.replaceChildren(fragment);if(atBottom)list.scrollTop=list.scrollHeight;else list.scrollTop=previousTop+(prepend?Math.max(0,list.scrollHeight-previousHeight):0);initialized=true;
  };
  let readThrough=0;
  const markRead=async()=>{
    if(managed||document.hidden||!root.classList.contains('has-thread')||list.scrollHeight-list.scrollTop-list.clientHeight>60)return;
    const through=Math.max(0,...messages.keys());if(through<=readThrough)return;
    try{await post('messaging_read',{thread,through:String(through)});readThrough=through;updateBadges();}catch(_){}
  };
  const load=async(before=0)=>{
    if(busy||document.hidden)return;busy=true;
    try{
      const response=await fetch(`?view=discussion-data&thread=${encodeURIComponent(thread)}&managed=${managed?'1':'0'}&before=${before}`,{credentials:'same-origin',cache:'no-store'});
      const data=await response.json();if(!response.ok)throw new Error(data.error||text('error','Impossible de terminer cette opération. Réessayez.'));
      serverOffset=data.server_time-Date.now()/1000;canWrite=data.can_write;for(const message of data.messages)messages.set(message.id,message);
      if(before||!initialized)older.hidden=!data.has_older;
      if(form){form.hidden=!canWrite;updateCounter();}render(before>0);error.textContent='';await markRead();
    }catch(e){error.textContent=e.message;}finally{busy=false;}
  };
  older?.addEventListener('click',()=>load(Math.min(...messages.keys())));list.addEventListener('scroll',markRead);
  form?.addEventListener('submit',async event=>{
    event.preventDefault();submit.disabled=true;
    try{
      await post(editing?'messaging_edit':'messaging_send',{thread,body:body.value,request_key:requestKey,message_id:String(editing?.id||0),revision:String(editing?.revision??0)});
      if(editing)cancelEdit();else{body.value='';requestKey=crypto.randomUUID();form.elements.request_key.value=requestKey;}
      updateCounter();await load();list.scrollTop=list.scrollHeight;await markRead();
    }catch(e){error.textContent=e.message;updateCounter();}
  });
  load();const poll=setInterval(()=>load(),5000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)load();});window.addEventListener('pagehide',()=>clearInterval(poll),{once:true});
})();
