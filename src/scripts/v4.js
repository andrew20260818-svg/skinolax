/* СКИНОЛАКС v4. Заказы уходят на сервер (/api/orders: письмо менеджеру и оплата Т-Банком); заявки из остальных форм пока не передаются.
   __SHOP__ подставляет сборщик (цена, порог бесплатной доставки, способы получения). Сумму к оплате сервер считает сам. */
(()=>{'use strict';
 const root=document.documentElement;root.classList.remove('no-js');root.classList.add('js');
 const SHOP=__SHOP__,PRICE=SHOP.price,VAT=SHOP.vat,FREE=SHOP.free_delivery_from,KEY='skinolax-cart-v5',NAMES={};[['standard','Стандарт',[100,200]],['compact','Компакт',[25,50,100,150]]].forEach(([k,l,ss])=>ss.forEach(z=>NAMES[k+'-'+z]=`СКИНОЛАКС ${l} ${z} см²`));
 const PIC=k=>'assets/pack-'+k.split('-')[0]+'.png';
 const rub=v=>new Intl.NumberFormat('ru-RU',{maximumFractionDigits:2}).format(v)+' ₽';
 const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const ss={get:k=>{try{return sessionStorage.getItem(k)}catch{return null}},set:(k,v)=>{try{sessionStorage.setItem(k,v)}catch{}}};
 const events=window.skinolaxEvents=[];const track=(e,c='')=>events.push({event:e,code:c,page:document.body.dataset.page,time:Date.now()});
 document.addEventListener('click',e=>{const t=e.target.closest('[data-goal]');if(t)track(t.dataset.goal);});

 // Меню
 const burger=document.querySelector('.burger'),mm=document.querySelector('.mobile-menu');
 burger?.addEventListener('click',()=>{const o=!mm.classList.contains('open');mm.classList.toggle('open',o);burger.setAttribute('aria-expanded',o);burger.textContent=o?'Закрыть':'Меню';document.body.style.overflow=o?'hidden':'';});
 document.addEventListener('keydown',e=>{if(e.key==='Escape'&&mm?.classList.contains('open'))burger.click();});
 document.querySelectorAll('.dd>button').forEach(b=>{const dd=b.parentElement;
  b.addEventListener('click',()=>{const o=!dd.classList.contains('open');document.querySelectorAll('.dd.open').forEach(x=>x.classList.remove('open'));dd.classList.toggle('open',o);b.setAttribute('aria-expanded',o);});
  dd.addEventListener('keydown',e=>{if(e.key==='Escape'){dd.classList.remove('open');b.setAttribute('aria-expanded','false');b.focus();}});});
 document.addEventListener('click',e=>{if(!e.target.closest('.dd'))document.querySelectorAll('.dd.open').forEach(x=>{x.classList.remove('open');x.querySelector('button').setAttribute('aria-expanded','false');});});

 // Корзина
 let mem={};
 const clean=c=>{const o={};for(const k in NAMES){const n=Math.floor(Number(c?.[k]));if(n>0)o[k]=Math.min(n,9999);}return o;};
 const load=()=>{try{return clean(JSON.parse(localStorage.getItem(KEY)||'{}'))}catch{return clean(mem)}};
 const save=c=>{mem=clean(c);try{localStorage.setItem(KEY,JSON.stringify(mem))}catch{}render();};
 // Промокод: проверяет /api/promo, итоговую скидку сервер считает сам при оформлении
 const PKEY='skinolax-promo';let promo=(()=>{try{return JSON.parse(ss.get(PKEY)||'null')}catch{return null}})();
 const disc=s=>{if(!promo||s<(promo.min_sum||0))return 0;const d=promo.type==='percent'?Math.floor(s*promo.value/100):Number(promo.value);return Math.max(0,Math.min(d,s-1));};
 const sum=c=>{const q=Object.values(c).reduce((a,b)=>a+b,0),s=q*PRICE,d=q?disc(s):0,p=s-d;return{q,s,d,p,vat:Math.round(p*VAT/(100+VAT)*100)/100};};
 // Доставка: СДЭК до ПВЗ или самовывоз; от FREE ₽ доставка СДЭК бесплатна (Москва до ПВЗ и регионы)
 const pickup=()=>!!document.querySelector('form[data-form=order] [name=delivery]:checked')?.value.startsWith('Самовывоз');
 const ship=s=>pickup()?{label:'Самовывоз',cost:'бесплатно'}:s>=FREE?{label:'Доставка СДЭК',cost:'бесплатно'}
  :{label:'Доставка СДЭК',cost:'по тарифу СДЭК',note:`Добавьте товаров ещё на ${rub(FREE-s)}, и доставка станет бесплатной. Стоимость доставки менеджер сообщит при подтверждении.`};
 function render(){const c=load(),t=sum(c);
  document.querySelectorAll('[data-cart-count]').forEach(x=>x.textContent=t.q);
  const list=document.querySelector('[data-cart-list]');
  if(list){document.querySelector('[data-cart-sum]')?.closest('aside')?.toggleAttribute('hidden',!t.q);list.innerHTML=t.q?Object.entries(c).map(([k,n])=>`<div class="cart-row"><img src="${PIC(k)}" alt=""><div><b>${NAMES[k]}</b><br><small>${rub(PRICE)} за упаковку</small></div><span class="stepper"><button type="button" data-cq="${k}" data-d="-1" aria-label="Меньше">−</button><input type="number" min="1" max="9999" value="${n}" data-cset="${k}" aria-label="Количество ${NAMES[k]}"><button type="button" data-cq="${k}" data-d="1" aria-label="Больше">+</button></span><b>${rub(n*PRICE)}</b><button type="button" class="remove" data-crm="${k}">Удалить</button></div>`).join(''):`<div class="empty-cart"><h3>Корзина пуста</h3><p>СКИНОЛАКС 100 см² — ${rub(PRICE)} за 1 пакет, в линейках Стандарт и Компакт.</p><a class="btn" href="shop.html">Выбрать покрытие</a></div>`;
   document.querySelector('#order-form')?.toggleAttribute('hidden',!t.q);}
  const d=ship(t.p);
  document.querySelectorAll('[data-cart-sum]').forEach(x=>{x.innerHTML=`<div class="row"><span>Товары, ${t.q} шт.</span><span>${rub(t.s)}</span></div>${t.d?`<div class="row promo-row-sum"><span>Промокод ${esc(promo.code)}</span><span>−${rub(t.d)}</span></div>`:''}<div class="row"><span>${d.label}</span><span>${d.cost}</span></div><div class="row total"><span>К оплате</span><span>${rub(t.p)}</span></div><p class="small">в т. ч. НДС ${VAT} %: ${rub(t.vat)}</p>${d.note?`<p class="ship-note">${d.note}</p>`:''}<p class="small">Доставку и самовывоз подтверждает менеджер после согласования.</p>`;});
 }
 document.addEventListener('click',e=>{const q=e.target.closest('[data-cq]'),r=e.target.closest('[data-crm]');
  if(q){const c=load();c[q.dataset.cq]=Math.max(1,(c[q.dataset.cq]||1)+Number(q.dataset.d));save(c);}
  if(r){const c=load();delete c[r.dataset.crm];save(c);}});
 document.addEventListener('change',e=>{const s=e.target.closest('[data-cset]');if(s){const c=load();c[s.dataset.cset]=Math.max(1,Math.floor(Number(s.value))||1);save(c);}});
 document.querySelectorAll('[data-product]').forEach(card=>{const inp=card.querySelector('.stepper input'),note=card.querySelector('.added');
  const val=()=>{let n=Math.floor(Number(inp.value));if(!(n>=1))n=1;inp.value=Math.min(n,9999);return Number(inp.value);};
  card.querySelectorAll('[data-step]').forEach(b=>b.addEventListener('click',()=>{inp.value=Math.max(1,val()+Number(b.dataset.step));}));
  card.querySelector('[data-add]').addEventListener('click',()=>{const c=load(),k=card.dataset.product+'-'+(card.querySelector('[name^=size]:checked')?.value||card.dataset.default);c[k]=(c[k]||0)+val();save(c);note.innerHTML=`${NAMES[k]}: добавлено ${val()} шт. <a href="cart.html">Перейти в корзину →</a>`;track('add_to_cart',k);});});
 addEventListener('storage',e=>{if(e.key===KEY)render();});render();

 // Видео
 document.querySelectorAll('[data-video]').forEach(box=>{const tabs=box.querySelectorAll('[role=tab]'),panes=box.querySelectorAll('[role=tabpanel]');
  tabs.forEach((t,i)=>t.addEventListener('click',()=>{tabs.forEach(x=>x.setAttribute('aria-selected',x===t));panes.forEach((p,j)=>p.hidden=j!==i);}));
  box.querySelectorAll('[data-embed]').forEach(b=>b.addEventListener('click',e=>{e.preventDefault();const f=document.createElement('iframe');f.src=b.dataset.embed;f.title=b.dataset.title;f.allow='autoplay; fullscreen; picture-in-picture';f.allowFullscreen=true;b.replaceWith(f);track('video_play');}));});

 // Плеер RuTube: встраивается по нажатию и сразу запускается
 document.querySelectorAll('[data-rutube]').forEach(b=>b.addEventListener('click',()=>{const f=document.createElement('iframe');
  f.src=b.dataset.rutube+'?autoplay=1';f.title=b.dataset.title;f.allow='clipboard-write; autoplay; encrypted-media; fullscreen; picture-in-picture';f.allowFullscreen=true;
  f.addEventListener('load',()=>{const play=()=>f.contentWindow?.postMessage(JSON.stringify({type:'player:play',data:{}}),'*');play();setTimeout(play,800);});
  b.replaceWith(f);track('video_play');}));
 // Пауза других плееров при переключении вкладок
 // Слои механизма
 document.querySelectorAll('.layers').forEach(l=>{const items=l.querySelectorAll('.layer-legend li');
  l.querySelectorAll('.layer').forEach(g=>{const on=()=>items.forEach(li=>li.classList.toggle('on',li.dataset.l===g.dataset.l));g.addEventListener('mouseenter',on);g.addEventListener('focus',on);g.addEventListener('click',on);});});

 // Шлюз «Я специалист»
 const gate=document.querySelector('#spec-gate');let pending=null;
 const isSpec=()=>ss.get('skinolax-spec')==='1';
 function askSpec(then){if(isSpec()||!gate?.showModal)return then();pending=then;gate.showModal();gate.querySelector('[data-spec-yes]').focus();}
 gate?.querySelector('[data-spec-yes]').addEventListener('click',()=>{ss.set('skinolax-spec','1');gate.close();const p=pending;pending=null;p?.();});
 gate?.querySelector('[data-spec-no]').addEventListener('click',()=>{pending=null;gate.close();});
 gate?.addEventListener('keydown',e=>{if(e.key!=='Tab')return;const f=[...gate.querySelectorAll('button,a[href]')];const a=f[0],z=f[f.length-1];if(e.shiftKey&&document.activeElement===a){e.preventDefault();z.focus();}else if(!e.shiftKey&&document.activeElement===z){e.preventDefault();a.focus();}});
 document.addEventListener('click',e=>{const a=e.target.closest('a[data-spec]');if(!a||isSpec())return;e.preventDefault();askSpec(()=>{a.target==='_blank'?open(a.href,'_blank','noopener'):location.assign(a.href);});});
 document.querySelectorAll('[data-photos-toggle]').forEach(t=>{const area=document.querySelector(t.dataset.photosToggle);
  const set=v=>{t.checked=v;area.classList.toggle('blurred',!v);ss.set('skinolax-photos',v?'1':'0');};
  set(ss.get('skinolax-photos')==='1'&&isSpec());
  t.addEventListener('change',()=>{if(t.checked){t.checked=false;askSpec(()=>set(true));}else set(false);});});

 // Калькулятор для организаций
 const calc=document.querySelector('#qty-calc');
 if(calc){const out=calc.querySelector('[data-calc-out]');
  const run=()=>{const n=k=>Number(calc.elements[k].value);const p=n('patients'),L=n('wl'),W=n('ww'),d=n('days'),cap=n('cap'),iv=n('interval');
   if(!(p>=1&&L>=1&&W>=1&&d>=1&&iv>=1&&iv<=7)){out.innerHTML='<p class="err">Заполните все поля: числа больше нуля, интервал смены от 1 до 7 суток.</p>';return;}
   const need=(L+5)*(W+5),per=Math.ceil(need/cap),apps=Math.ceil(d/iv),total=p*per*apps;
   out.innerHTML=`<p class="kpi"><b>${new Intl.NumberFormat('ru-RU').format(total)}</b><span>упаковок, ориентировочно</span></p><p class="small">Рана ${L}×${W} см с захватом 2,5 см здоровой кожи по краю требует покрытия ≈${Math.round(need)} см²: ${per} уп. по ${cap} см² на одну смену × ${apps} смен за ${d} сут × ${p} пац. Смена не реже 1 раза в 7 суток по инструкции<sup class="ref"><a href="istochniki.html#s1">[1]</a></sup>; при подтекании или отслоении покрытие меняют раньше. Это не клиническая рекомендация.</p><a class="btn small" href="#quote" data-fill-qty="${total}">Запросить КП на это количество</a>`;track('calc_qty');};
  calc.addEventListener('submit',e=>{e.preventDefault();run();});
  out.addEventListener('click',e=>{const b=e.target.closest('[data-fill-qty]');if(b){const f=document.querySelector('#quote [name=qty]');if(f)f.value=b.dataset.fillQty;}});}

 // Подбор размера
 const sz=document.querySelector('#size-help');
 if(sz){const o=sz.querySelector('[data-size-out]');sz.addEventListener('submit',e=>{e.preventDefault();const L=Number(sz.elements.wl.value),W=Number(sz.elements.ww.value);
  if(!(L>0&&W>0&&L<=60&&W<=60)){o.innerHTML='<p class="err">Введите длину и ширину раны в сантиметрах, до 60.</p>';return;}
  const need=(L+5)*(W+5),n=Math.ceil(need/100);
  o.innerHTML=`<p class="kpi"><b>${n} × 100 см²</b><span>${n===1?'пакет':'пакета'} на одну смену</span></p><p class="small">Нужно покрыть ≈${Math.round(need)} см²: рана ${L}×${W} см и 2–2,5 см здоровой кожи с каждой стороны.${n>1?' Второй пакет наносят сразу после первого.':''} Сейчас в продаже размер 100 см²; 25, 50, 150 и 200 см² — скоро.</p>`;track('size_help');});}

 // Формы
 const innOk=v=>{if(!/^\d{10}$|^\d{12}$/.test(v))return false;const d=[...v].map(Number),k=w=>w.reduce((s,x,i)=>s+x*d[i],0)%11%10;return v.length===10?k([2,4,10,3,5,9,4,6,8])===d[9]:k([7,2,4,10,3,5,9,4,6,8])===d[10]&&k([3,7,2,4,10,3,5,9,4,6,8])===d[11];};
 function validate(f){let first=null;
  f.querySelectorAll('input,select,textarea').forEach(x=>{if(!x.name||x.disabled||x.type==='hidden'||x.closest('.hp'))return;x.setCustomValidity('');const v=x.value.trim();
   if(x.type==='checkbox'||x.type==='radio'){if(x.required&&x.type==='checkbox'&&!x.checked)x.setCustomValidity('Отметьте, чтобы продолжить.');}
   else if(x.required&&!v)x.setCustomValidity('Заполните поле.');
   else if(v&&x.type==='email'&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v))x.setCustomValidity('Проверьте e-mail: name@example.ru');
   else if(v&&x.type==='tel'&&(!/^[+\d\s()\-]{10,25}$/.test(v)||v.replace(/\D/g,'').length<10))x.setCustomValidity('Телефон: не меньше 10 цифр.');
   else if(v&&x.name==='inn'&&!innOk(v))x.setCustomValidity('Проверьте ИНН: 10 цифр для организации, 12 для ИП.');
   else if(v&&x.type==='number'&&(!/^\d+$/.test(v)||Number(v)>Number(x.max||1e9)))x.setCustomValidity('Введите целое число'+(x.max?' до '+x.max:'')+'.');
   const ok=x.checkValidity();if(x.type!=='checkbox')x.setAttribute('aria-invalid',!ok);
   const er=f.querySelector(`#${x.id}-err`);if(er)er.textContent=ok?'':x.validationMessage;if(!ok&&!first)first=x;});
  if(first){first.focus();return false;}return true;}
 document.querySelectorAll('form[data-form]').forEach(f=>{const btn=f.querySelector('[type=submit]'),consent=f.querySelector('[name=consent]'),status=f.querySelector('.status');
  f.querySelector('fieldset[disabled]')?.removeAttribute('disabled');

  f.addEventListener('submit',async e=>{e.preventDefault();status.textContent='';
   if(f.dataset.form==='order'&&!sum(load()).q){status.textContent='Корзина пуста.';return;}
   if(!validate(f)){status.textContent='Проверьте отмеченные поля.';return;}
   if(f.querySelector('.hp input')?.value)return;
   const fields={};new FormData(f).forEach((v,k)=>{if(k==='consent'||k==='website'||v instanceof File)return;fields[k]=fields[k]?fields[k]+', '+v:String(v).trim();});
   if(f.dataset.form==='order')fields.items=Object.entries(load()).map(([k,n])=>k+':'+n).join(';');
   let num=(f.dataset.prefix||'T')+'-'+new Date().toISOString().slice(2,10).replace(/-/g,'')+'-'+Math.random().toString(36).slice(2,6).toUpperCase();
   btn.disabled=true;status.textContent='Проверяем…';
   const order=f.dataset.form==='order';if(order)orderSent=true;
   try{if(location.origin==='http://127.0.0.1:8788'||(order&&location.protocol==='https:')){
     // Заказ: сервер проверяет корзину, сам считает сумму по цене из shop.json и вызывает Init Т-Банка. Ключи терминала есть только на сервере.
     const r=await fetch(order?'api/orders':'api/leads',{method:'POST',headers:{'Content-Type':'application/json','Idempotency-Key':crypto.randomUUID?.()||num+'-skinolax'},body:JSON.stringify({form:f.dataset.form,fields,consent:true})});const a=await r.json();
     if(order&&r.ok&&a.payment_url&&a.test!==true){track('payment_redirect');location.assign(a.payment_url);return;}
     if(order&&a.error==='promo'){promo=null;ss.set(PKEY,'null');render();status.textContent=a.message||'Промокод не принят.';const pe=f.querySelector('#o-promo-err');if(pe)pe.textContent=a.message||'';return;}
     if(!r.ok||a.test!==true)throw Error(a.error||'unconfirmed');if(a.order_id)num=a.order_id;}
    track('form_'+f.dataset.form);done(f,num,fields);}
   catch{status.textContent='Не удалось проверить заявку. Данные сохранены в форме, попробуйте ещё раз или позвоните +7 (495) 545-49-50.';}
   finally{btn.disabled=false;}});});
 const of=document.querySelector('form[data-form=order]');
 if(of){const ad=of.querySelector('[name=address]'),lbl=ad.closest('label');const s=()=>{const p=pickup();ad.required=!p;ad.disabled=p;lbl.hidden=p;render();};of.querySelectorAll('[name=delivery]').forEach(r=>r.addEventListener('change',s));s();}
 // Брошенная форма: контакты введены, а заказ не оформлен — сигнал менеджеру в Telegram (через свой сервер, ключей бота в браузере нет).
 // Имя, телефон и e-mail уходят только при отмеченном согласии на обработку ПД; без него — лишь состав корзины и способ получения.
 let orderSent=false;
 if(of){let timer=0,sentSig='';const v=k=>of.elements[k]?.value.trim()||'';
  const contact=()=>v('phone').replace(/\D/g,'').length>=10||/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v('email'));
  const beacon=()=>{clearTimeout(timer);if(orderSent||!contact()||!sum(load()).q)return;if(!(location.origin==='http://127.0.0.1:8788'||location.protocol==='https:'))return;
   const consent=!!of.elements.consent?.checked,fields={delivery:of.querySelector('[name=delivery]:checked')?.value||'',items:Object.entries(load()).map(([k,n])=>k+':'+n).join(';')};
   if(consent)Object.assign(fields,{name:v('name'),phone:v('phone'),email:v('email')});
   const sig=JSON.stringify([fields,consent]);if(sig===sentSig||ss.get('skinolax-abandoned')===sig)return;sentSig=sig;ss.set('skinolax-abandoned',sig);
   fetch('api/abandoned',{method:'POST',keepalive:true,headers:{'Content-Type':'application/json'},body:JSON.stringify({form:'abandoned',fields,consent})}).catch(()=>{});track('form_abandoned');};
  of.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(beacon,120000);});
  addEventListener('pagehide',beacon);document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='hidden')beacon();});}
 // Возврат со страницы оплаты Т-Банка (SuccessURL / FailURL в tbank_config.json)
 const pay=new URLSearchParams(location.search).get('pay');
 if(pay&&document.querySelector('.cart-layout')){const box=document.createElement('div');box.className='success';box.tabIndex=-1;const ok=pay==='success';
  box.innerHTML=ok?'<h2>Оплата прошла</h2><p>Спасибо! Менеджер свяжется с вами, чтобы подтвердить доставку или самовывоз. Чек 54-ФЗ придёт на e-mail.</p>':'<h2>Оплата не прошла</h2><p>Деньги не списаны. Попробуйте оформить заказ ещё раз или позвоните +7 (495) 545-49-50.</p>';
  if(ok)save({});document.querySelector('.cart-layout').before(box);}
 function done(f,num,fields){const t=sum(load());const msg={
   order:`<h2>Заказ принят</h2><p>Сумма ${rub(t.p)}${t.d?` с учётом промокода ${esc(promo.code)}`:''} (в т. ч. НДС ${VAT} %: ${rub(t.vat)}), получение: ${esc(fields.delivery)}, ${ship(t.p).cost}. Менеджер свяжется с вами, чтобы подтвердить заказ и оплату.</p>`,
   trial:'<h2>Заявка на апробацию принята</h2><p>Специалист МЕНОРА-МЕД свяжется с вами, чтобы обсудить сценарий, количество образцов и порядок оценки.</p>',
   quote:'<h2>Запрос КП принят</h2><p>Мы подготовим коммерческое предложение и счёт и направим их на указанный e-mail.</p>',
   adverse:'<h2>Сообщение получено</h2><p>Ответственный специалист по качеству свяжется с вами. При угрозе жизни звоните 112.</p>'}[f.dataset.form];
  const box=document.createElement('div');box.className='success';box.tabIndex=-1;
  box.innerHTML=`<p class="eyebrow">Номер обращения</p><p class="num">${num}</p>${msg}${f.dataset.form==='order'?'':'<p class="test-note">Тестовая версия сайта: обращение не передано производителю.</p>'}<div class="actions"><a class="btn ghost" href="index.html">На главную</a></div>`;
  (f.dataset.form==='order'?document.querySelector('.cart-layout')||f:f).replaceWith(box);box.focus();box.scrollIntoView({block:'start'});if(f.dataset.form==='order'){promo=null;ss.set(PKEY,'null');save({});}}
 // Поле «Промокод» в оформлении заказа
 const pin=document.querySelector('#o-promo'),pbtn=document.querySelector('[data-promo-apply]'),pst=document.querySelector('[data-promo-status]'),perr=document.querySelector('#o-promo-err');
 if(pin&&pbtn){if(promo){pin.value=promo.code;pst.textContent=`Промокод ${promo.code}: ${promo.label}`;}
  const clear=()=>{promo=null;ss.set(PKEY,'null');render();};
  pin.addEventListener('input',()=>{if(promo&&pin.value.trim().toUpperCase()!==promo.code){clear();pst.textContent='';}});
  const apply=async()=>{const code=pin.value.trim();perr.textContent='';pst.textContent='';if(!code){clear();return;}
   pbtn.disabled=true;try{const r=await fetch('api/promo?code='+encodeURIComponent(code)+'&sum='+sum(load()).s);const a=await r.json();
    if(a.ok){promo={code:a.code,type:a.type,value:a.value,min_sum:a.min_sum,label:a.label};ss.set(PKEY,JSON.stringify(promo));render();pst.textContent=`Промокод ${a.code}: ${a.label}`;track('promo_ok',a.code);}
    else{clear();perr.textContent=a.message||'Промокод не найден';}}
   catch{clear();perr.textContent='Не удалось проверить промокод. Попробуйте ещё раз.';}finally{pbtn.disabled=false;}};
  pbtn.addEventListener('click',apply);pin.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();apply();}});}
})();
