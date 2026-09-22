/* Shared date editor: manual Swiss-format entry and an application-localized calendar.
 * The original named control keeps its ISO value and remains the form/API contract. */
(() => {
  let labels = {};
  try { labels = JSON.parse(document.body.dataset.i18n || '{}'); } catch {}
  const message = (key, fallback) => labels[key] || fallback;
  const fields = new WeakMap(), forms = new WeakSet();
  const selector = 'input[type="date"],input[type="datetime-local"]';
  const language = document.body.dataset.language || document.documentElement.lang || 'fr';
  // Match the application's locale_code(), rather than the browser/OS language.
  const locale = ({fr:'fr-CH',en:'en-GB',de:'de-CH',it:'it-CH',es:'es-ES'})[language] || 'fr-CH';
  const calendarLocale = new Intl.Locale(locale);
  const firstDay = (calendarLocale.getWeekInfo?.() || calendarLocale.weekInfo || {firstDay:1}).firstDay % 7;
  const monthLabel = new Intl.DateTimeFormat(locale,{month:'long',year:'numeric',timeZone:'UTC'});
  const weekdayLabel = new Intl.DateTimeFormat(locale,{weekday:'short',timeZone:'UTC'});
  const fullDateLabel = new Intl.DateTimeFormat(locale,{dateStyle:'full',timeZone:'UTC'});
  const display = value => value.replace(/^(\d{4})-(\d{2})-(\d{2})(?:T(.*))?$/, (_, y, m, d, time) => `${d}/${m}/${y}${time ? ` ${time}` : ''}`);
  const parse = (value, withTime) => {
    value = value.trim();
    if (!value) return '';
    if (/^\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}(?::\d{2})?)?$/.test(value)) value = display(value);
    if (/^\d{8}$/.test(value)) value = `${value.slice(0,2)}/${value.slice(2,4)}/${value.slice(4)}`;
    const match = value.match(withTime
      ? /^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})\s+(\d{1,2}):(\d{2})(?::(\d{2}))?$/
      : /^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})$/);
    if (!match) return null;
    const [,day,month,year,hour,minute,second] = match;
    const date = new Date(0); date.setUTCFullYear(Number(year), Number(month)-1, Number(day));
    if (Number(year)<1 || date.getUTCFullYear()!==Number(year) || date.getUTCMonth()!==Number(month)-1 || date.getUTCDate()!==Number(day)) return null;
    if (withTime && (Number(hour)>23 || Number(minute)>59 || Number(second || 0)>59)) return null;
    const pad = part => part.padStart(2,'0');
    return `${year}-${pad(month)}-${pad(day)}${withTime ? `T${pad(hour)}:${minute}${second ? `:${second}` : ''}` : ''}`;
  };
  const dayDate = (year,month,day) => { const date=new Date(0);date.setUTCFullYear(year,month,day);return date; };
  const isoDay = date => date.toISOString().slice(0,10);
  const dateFromIso = value => { const [y,m,d]=value.slice(0,10).split('-').map(Number);return dayDate(y,m-1,d); };
  const today = () => {
    const parts=Object.fromEntries(new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/Zurich',year:'numeric',month:'numeric',day:'numeric'}).formatToParts(new Date()).map(part=>[part.type,part.value]));
    return dayDate(Number(parts.year),Number(parts.month)-1,Number(parts.day));
  };
  const shiftMonth = (date,offset) => {
    const first=dayDate(date.getUTCFullYear(),date.getUTCMonth()+offset,1);
    return dayDate(first.getUTCFullYear(),first.getUTCMonth(),Math.min(date.getUTCDate(),dayDate(first.getUTCFullYear(),first.getUTCMonth()+1,0).getUTCDate()));
  };
  let active = null, focusDay = null, viewedMonth = null;
  const popup=document.createElement('div');popup.className='date-calendar-popup';popup.hidden=true;popup.id='liike-date-calendar';popup.setAttribute('role','dialog');popup.setAttribute('aria-label',message('date_calendar','Choisir dans le calendrier'));
  if (typeof popup.showPopover==='function') popup.popover='manual';
  const button = (text,className,action) => { const node=document.createElement('button');node.type='button';node.className=className;node.textContent=text;node.addEventListener('click',action);return node; };
  const heading=document.createElement('div');heading.className='date-calendar-heading';
  const title=document.createElement('strong');title.id='liike-date-calendar-title';title.setAttribute('aria-live','polite');
  const previous=button('‹','date-calendar-arrow',()=>navigateMonth(-1));previous.setAttribute('aria-label',message('date_previous','Mois précédent'));
  const next=button('›','date-calendar-arrow',()=>navigateMonth(1));next.setAttribute('aria-label',message('date_next','Mois suivant'));heading.append(previous,title,next);
  const grid=document.createElement('div');grid.className='date-calendar-grid';grid.setAttribute('role','grid');grid.setAttribute('aria-labelledby',title.id);
  const timeLabel=document.createElement('label');timeLabel.className='date-calendar-time';
  const timeText=document.createElement('span');timeText.textContent=message('date_time','Heure');
  const timeInput=document.createElement('input');timeInput.type='text';timeInput.placeholder='HH:mm';timeInput.className='form-control';timeInput.pattern='([01][0-9]|2[0-3]):[0-5][0-9]';timeInput.maxLength=5;timeInput.disabled=true;timeLabel.append(timeText,timeInput);
  const footer=document.createElement('div');footer.className='date-calendar-footer';
  const clear=button(message('date_clear','Effacer la date'),'date-calendar-clear',()=>{ if(active){active.native.value='';changed();} });
  const now=button(message('date_today','Aujourd’hui'),'date-calendar-today',()=>chooseDay(isoDay(today())));
  const apply=button(message('date_apply','Appliquer'),'date-calendar-apply',()=>saveDay(isoDay(focusDay)));footer.append(clear,now,apply);
  popup.append(heading,grid,timeLabel,footer);
  const closeCalendar = (restoreFocus=false) => {
    if(!active)return;const picker=active.picker;active=null;
    if(popup.hidePopover&&popup.matches(':popover-open'))popup.hidePopover();
    popup.hidden=true;timeInput.disabled=true;picker.setAttribute('aria-expanded','false');if(restoreFocus)picker.focus();
  };
  const changed = () => {
    const native=active.native;
    native.dispatchEvent(new Event('input',{bubbles:true}));native.dispatchEvent(new Event('change',{bubbles:true}));closeCalendar(true);
  };
  const allowedDay = value => active && (!active.native.min || value>=active.native.min.slice(0,10)) && (!active.native.max || value<=active.native.max.slice(0,10));
  const saveDay = value => {
    if(!active||!allowedDay(value))return;
    if(active.withTime&&!timeInput.reportValidity())return;
    let candidate=value+(active.withTime?'T'+timeInput.value:'');
    if(active.native.min&&candidate<active.native.min)candidate=active.native.min;
    if(active.native.max&&candidate>active.native.max)candidate=active.native.max;
    active.native.value=candidate;changed();
  };
  const chooseDay = value => {
    if(!allowedDay(value))return;
    if(!active.withTime){saveDay(value);return;}
    focusDay=dateFromIso(value);viewedMonth=dayDate(focusDay.getUTCFullYear(),focusDay.getUTCMonth(),1);renderCalendar();focusCell();
  };
  timeInput.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();saveDay(isoDay(focusDay));}});
  const positionCalendar = () => {
    if(!active)return;const anchor=active.wrapper.getBoundingClientRect();
    const width=popup.offsetWidth,height=popup.offsetHeight;
    popup.style.left=Math.max(8,Math.min(anchor.right-width,innerWidth-width-8))+'px';
    popup.style.top=Math.max(8,Math.min(anchor.bottom+5+height<=innerHeight?anchor.bottom+5:anchor.top-height-5,innerHeight-height-8))+'px';
  };
  const focusCell = () => grid.querySelector(`[data-date-day="${isoDay(focusDay)}"]`)?.focus({preventScroll:true});
  const renderCalendar = () => {
    if(!active)return;title.textContent=monthLabel.format(viewedMonth);grid.replaceChildren();
    previous.disabled=viewedMonth.getUTCFullYear()===1&&viewedMonth.getUTCMonth()===0;
    next.disabled=viewedMonth.getUTCFullYear()===9999&&viewedMonth.getUTCMonth()===11;
    const weekdays=document.createElement('div');weekdays.className='date-calendar-row date-calendar-weekdays';weekdays.setAttribute('role','row');
    for(let i=0;i<7;i++){const cell=document.createElement('span');cell.setAttribute('role','columnheader');cell.textContent=weekdayLabel.format(dayDate(2024,0,7+firstDay+i));weekdays.append(cell);}grid.append(weekdays);
    const start=dayDate(viewedMonth.getUTCFullYear(),viewedMonth.getUTCMonth(),1-(viewedMonth.getUTCDay()-firstDay+7)%7);
    const todayValue=isoDay(today()),selected=active.withTime?isoDay(focusDay):active.native.value;
    for(let row=0;row<6;row++){
      const line=document.createElement('div');line.className='date-calendar-row';line.setAttribute('role','row');
      for(let col=0;col<7;col++){
        const date=dayDate(start.getUTCFullYear(),start.getUTCMonth(),start.getUTCDate()+row*7+col),value=isoDay(date);
        const cell=button(String(date.getUTCDate()),'date-calendar-day',()=>chooseDay(value));cell.dataset.dateDay=value;cell.setAttribute('role','gridcell');cell.setAttribute('aria-label',fullDateLabel.format(date));cell.setAttribute('aria-selected',String(value===selected));cell.tabIndex=value===isoDay(focusDay)?0:-1;
        cell.disabled=date.getUTCFullYear()<1||date.getUTCFullYear()>9999||!allowedDay(value);cell.classList.toggle('outside-month',date.getUTCMonth()!==viewedMonth.getUTCMonth());if(value===todayValue)cell.setAttribute('aria-current','date');line.append(cell);
      }grid.append(line);
    }
    now.disabled=!allowedDay(todayValue);positionCalendar();
  };
  const navigateMonth = offset => {if(!active)return;viewedMonth=shiftMonth(viewedMonth,offset);focusDay=shiftMonth(focusDay,offset);renderCalendar();};
  grid.addEventListener('keydown',event=>{
    if(!active)return;
    if(event.key==='Enter'||event.key===' '){event.preventDefault();chooseDay(isoDay(focusDay));return;}
    let target=null;const weekday=(focusDay.getUTCDay()-firstDay+7)%7;
    const shifts={ArrowLeft:-1,ArrowRight:1,ArrowUp:-7,ArrowDown:7,Home:-weekday,End:6-weekday};
    if(event.key in shifts)target=dayDate(focusDay.getUTCFullYear(),focusDay.getUTCMonth(),focusDay.getUTCDate()+shifts[event.key]);
    if(event.key==='PageUp'||event.key==='PageDown')target=shiftMonth(focusDay,event.key==='PageUp'?-1:1);
    if(!target)return;event.preventDefault();if(target.getUTCFullYear()<1||target.getUTCFullYear()>9999||!allowedDay(isoDay(target)))return;
    focusDay=target;viewedMonth=dayDate(target.getUTCFullYear(),target.getUTCMonth(),1);renderCalendar();focusCell();
  });
  const openCalendar = state => {
    if(state.native.disabled||state.native.readOnly)return;
    if(active?.native===state.native){closeCalendar();return;}closeCalendar();active=state;
    focusDay=state.native.value?dateFromIso(state.native.value):today();
    if(state.native.min&&isoDay(focusDay)<state.native.min.slice(0,10))focusDay=dateFromIso(state.native.min);
    if(state.native.max&&isoDay(focusDay)>state.native.max.slice(0,10))focusDay=dateFromIso(state.native.max);
    viewedMonth=dayDate(focusDay.getUTCFullYear(),focusDay.getUTCMonth(),1);
    timeLabel.hidden=apply.hidden=!state.withTime;timeInput.disabled=!state.withTime;timeInput.required=state.withTime;timeInput.value=state.native.value.slice(11,16)||'00:00';
    state.wrapper.append(popup);popup.hidden=false;state.picker.setAttribute('aria-expanded','true');renderCalendar();if(popup.showPopover)popup.showPopover();positionCalendar();focusCell();
  };
  document.addEventListener('pointerdown',event=>{if(active&&!active.wrapper.contains(event.target))closeCalendar();});
  document.addEventListener('focusin',event=>{if(active&&!active.wrapper.contains(event.target))closeCalendar();});
  document.addEventListener('hide.bs.modal',event=>{if(active&&event.target.contains(active.wrapper))closeCalendar();});
  document.addEventListener('keydown',event=>{if(active&&event.key==='Escape'){event.preventDefault();event.stopImmediatePropagation();closeCalendar(true);}},true);
  window.addEventListener('resize',positionCalendar);
  document.addEventListener('scroll',event=>{if(active&&!popup.contains(event.target))closeCalendar();},true);
  const enhance = (root = document) => {
    const inputs = [...(root.matches?.(selector) ? [root] : []), ...root.querySelectorAll(selector)];
    inputs.forEach(native => {
      if (fields.has(native)) return;
      const withTime = native.type === 'datetime-local';
      const invalid = message(withTime ? 'datetime_invalid' : 'date_invalid',withTime ? 'Saisissez une date et une heure valides (jj/mm/aaaa hh:mm).' : 'Saisissez une date valide (jj/mm/aaaa).');
      const label = native.getAttribute('aria-label') || [...native.labels].map(node => node.textContent.trim()).join(' ') || message('date_label','Date');
      const wrapper = document.createElement('span'); wrapper.className = 'date-field';
      const manual = document.createElement('input'); manual.type = 'text'; manual.className = 'form-control date-input';
      manual.dataset.dateManual = ''; manual.autocomplete = 'off';
      manual.placeholder = withTime ? 'jj/mm/aaaa hh:mm' : 'jj/mm/aaaa';
      // A text keyboard keeps slash, colon and space available on mobile.
      manual.inputMode = 'text';
      manual.setAttribute('aria-label', label);
      for (const attribute of ['aria-describedby','aria-labelledby']) if (native.hasAttribute(attribute)) manual.setAttribute(attribute,native.getAttribute(attribute));
      if (native.id) { manual.id = native.id; native.id += '-calendar'; }
      const picker = document.createElement('button'); picker.type='button';picker.className = 'date-picker-control';picker.setAttribute('aria-haspopup','dialog');picker.setAttribute('aria-controls',popup.id);picker.setAttribute('aria-expanded','false');
      const icon = document.createElement('i'); icon.className = 'bi bi-calendar3'; icon.setAttribute('aria-hidden','true');
      native.before(wrapper); wrapper.append(manual,picker,native); picker.append(icon);
      native.classList.add('date-picker-native'); native.dataset.dateNative = '';
      native.tabIndex=-1;native.setAttribute('aria-hidden','true');picker.setAttribute('aria-label', `${message('date_calendar','Choisir dans le calendrier')} — ${label}`);
      let forwarding = false;
      const constraints = () => {
        manual.disabled = native.disabled; manual.readOnly = native.readOnly; manual.required = native.required;
        picker.classList.toggle('is-disabled',native.disabled || native.readOnly);
        picker.disabled=native.disabled||native.readOnly;
        if(picker.disabled&&active?.native===native)closeCalendar();
        manual.setCustomValidity(parse(manual.value,withTime) === null ? invalid : native.validationMessage);
      };
      const sync = () => { manual.value = display(native.value); constraints(); };
      const notify = type => {
        forwarding = true;
        try { native.dispatchEvent(new Event(type,{bubbles:true})); } finally { forwarding = false; }
      };
      const commit = (format = false) => {
        const value = parse(manual.value,withTime);
        native.value = value ?? '';
        manual.setCustomValidity(value === null ? invalid : native.validationMessage);
        if (format && value !== null) manual.value = display(value);
      };
      fields.set(native,{sync,constraints}); sync();
      manual.addEventListener('input',() => { commit(); notify('input'); });
      manual.addEventListener('change',() => { commit(true); notify('change'); });
      manual.addEventListener('blur',() => commit(true));
      for (const type of ['input','change']) native.addEventListener(type,() => { if (!forwarding) sync(); });
      picker.addEventListener('click',()=>openCalendar({native,manual,picker,wrapper,withTime}));
      manual.addEventListener('keydown',event=>{if(event.altKey&&event.key==='ArrowDown'){event.preventDefault();openCalendar({native,manual,picker,wrapper,withTime});}});
      if (native.form && !forms.has(native.form)) {
        forms.add(native.form);
        native.form.addEventListener('reset',event => setTimeout(() => syncAll(event.target),0));
      }
    });
  };
  const syncAll = (root = document) => {
    enhance(root);
    root.querySelectorAll(selector).forEach(input => fields.get(input)?.sync());
  };
  window.liikeDateFields = { enhance, sync: syncAll };
  enhance();
  new MutationObserver(changes => {
    for (const change of changes) {
      if (change.type === 'attributes') {
        const field = fields.get(change.target);
        if (change.attributeName === 'value') field?.sync(); else field?.constraints();
      } else for (const node of change.addedNodes) if (node.nodeType === Node.ELEMENT_NODE) enhance(node);
    }
  }).observe(document.body,{subtree:true,childList:true,attributes:true,attributeFilter:['required','disabled','readonly','min','max','step','value']});
})();
