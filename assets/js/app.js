document.addEventListener('DOMContentLoaded', () => {
  const menu = document.querySelector('.menu');
  const nav = document.querySelector('.main-nav');
  const header = document.querySelector('.site-header');
  const closeMenu = () => { if (!menu || !nav) return; nav.classList.remove('open'); menu.setAttribute('aria-expanded','false'); menu.setAttribute('aria-label','Abrir menu'); document.body.classList.remove('menu-open'); };
  if (menu && nav) {
    menu.addEventListener('click', () => {
      const next = !nav.classList.contains('open');
      nav.classList.toggle('open',next);menu.setAttribute('aria-expanded',String(next));
      menu.setAttribute('aria-label',next?'Fechar menu':'Abrir menu');
      document.body.classList.toggle('menu-open',next);
    });
    nav.querySelectorAll('a').forEach(a => a.addEventListener('click',closeMenu));
    document.addEventListener('keydown',e => {if(e.key==='Escape')closeMenu();});
    document.addEventListener('click',e => {if(nav.classList.contains('open') && !nav.contains(e.target) && !menu.contains(e.target))closeMenu();});
    window.addEventListener('resize',()=>{if(window.innerWidth>900)closeMenu();});
  }
  const onScroll=()=>{if(header)header.classList.toggle('scrolled',window.scrollY>12);};
  window.addEventListener('scroll',onScroll,{passive:true});onScroll();
  document.querySelectorAll('.ong-grid .ong-card, .about-decisions .decision, .flow-line .flow-step').forEach(el=>el.classList.add('reveal'));
  const items=document.querySelectorAll('.reveal');
  items.forEach((item,index)=>item.style.setProperty('--reveal-delay',Math.min(index%5,4)*65+'ms'));
  if('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches){
    const obs=new IntersectionObserver((entries,o)=>{entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('is-visible');o.unobserve(entry.target);}});},{threshold:.06,rootMargin:'0px 0px -15px 0px'});
    items.forEach(x=>obs.observe(x));
  } else items.forEach(x=>x.classList.add('is-visible'));
  document.querySelectorAll('[data-toast]').forEach(el=>{
    const removeToast=()=>{
      if(el.classList.contains('toast-leaving'))return;
      el.classList.add('toast-leaving');
      setTimeout(()=>el.remove(),380);
    };
    el.querySelector('[data-toast-close]')?.addEventListener('click',removeToast);
    if(el.classList.contains('ok')){
      const burst=document.createElement('span');burst.className='toast-confetti';burst.setAttribute('aria-hidden','true');
      const palette=['#b4efc4','#ffffff','#f4dc84','#83d6b0','#d7f4e5','#f3b6a7','#c5e7ff','#ffffff'];
      const dx=['-28px','-14px','8px','26px','42px','-42px','18px','-4px'];
      for(let i=0;i<palette.length;i++){
        const part=document.createElement('i');
        part.style.setProperty('--x',(8+i*11)+'%');part.style.setProperty('--c',palette[i]);
        part.style.setProperty('--d',(i*35)+'ms');part.style.setProperty('--dx',dx[i]);
        burst.appendChild(part);
      }
      el.appendChild(burst);
    }
    setTimeout(removeToast,8000);
  });
  // Each listing controls only its own cards. Search and filter operate together.
  document.querySelectorAll('[data-listing]').forEach(panel=>{
    const parent=panel.parentElement;
    const cards=[...parent.querySelectorAll('.listing-results [data-search]')];
    const field=panel.querySelector('[data-list-search]');
    const filters=[...panel.querySelectorAll('.filter[data-filter]')];
    const countEl=panel.querySelector('[data-count-label]');
    const empty=parent.querySelector('[data-list-empty]');
    let current='todos';
    const normalize=s=>(s||'').toLocaleLowerCase('pt-BR').normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim();
    const refresh=()=>{
      const q=normalize(field?.value||'');let visible=0;
      cards.forEach(card=>{
        const okSpecies=current==='todos'||card.dataset.item===current;
        const okSearch=normalize(card.dataset.search).includes(q);
        card.hidden=!(okSpecies&&okSearch);if(!card.hidden)visible++;
      });
      if(countEl)countEl.textContent=visible+(panel.dataset.listing==='ongs'?' organizações encontradas':' animais encontrados');
      if(empty)empty.hidden=visible!==0;
    };
    filters.forEach(button=>button.addEventListener('click',()=>{
      current=button.dataset.filter;
      filters.forEach(x=>{const active=x===button;x.classList.toggle('active',active);x.setAttribute('aria-pressed',String(active));});refresh();
    }));
    field?.addEventListener('input',refresh);
    empty?.querySelector('[data-clear-list]')?.addEventListener('click',()=>{if(field)field.value='';current='todos';filters.forEach(x=>{const active=x.dataset.filter==='todos';x.classList.toggle('active',active);x.setAttribute('aria-pressed',String(active));});refresh();field?.focus();});
    refresh();
  });
  document.querySelectorAll('[data-copy]').forEach(btn=>btn.addEventListener('click',async()=>{
    try{await navigator.clipboard.writeText(btn.dataset.copy);const orig=btn.textContent;btn.textContent='Copiado ✓';setTimeout(()=>btn.textContent=orig,2000);}catch(e){window.prompt('Copie a chave:',btn.dataset.copy);}
  }));
  const identity=document.getElementById('report-identificacao');
  const identityFields=document.getElementById('report-contact-fields');
  if(identity && identityFields){
    const name=identityFields.querySelector('[name="nome"]');
    const toggleIdentity=()=>{
      const active=identity.checked;
      identityFields.hidden=!active;
      identity.setAttribute('aria-expanded',String(active));
      identityFields.querySelectorAll('input').forEach(field=>{field.disabled=!active;if(!active)field.value='';});
      if(name)name.required=active;
    };
    identity.addEventListener('change',toggleIdentity);
    toggleIdentity();
  }
  const sponsorAmount=document.getElementById('sponsor-amount');
  const customSponsor=document.getElementById('sponsor-custom-row');
  if(sponsorAmount && customSponsor){
    const amountField=customSponsor.querySelector('input');
    const updateCustomAmount=()=>{
      const custom=sponsorAmount.value==='outro';
      customSponsor.hidden=!custom;
      amountField.disabled=!custom;
      amountField.required=custom;
      if(!custom)amountField.value='';
    };
    sponsorAmount.addEventListener('change',updateCustomAmount);
    updateCustomAmount();
  }
  document.querySelectorAll('form[data-enhanced-form]').forEach(form=>form.addEventListener('submit',()=>{
    if(!form.checkValidity())return;
    const button=form.querySelector('button[type=submit]');if(!button||button.disabled)return;
    button.disabled=true;button.setAttribute('aria-busy','true');button.textContent=button.dataset.submitLabel||'Enviando...';
  }));
  const counters=document.querySelectorAll('.count-up[data-count]');
  if('IntersectionObserver' in window && counters.length && !window.matchMedia('(prefers-reduced-motion: reduce)').matches){
    const ob=new IntersectionObserver(entries=>entries.forEach(entry=>{
      if(!entry.isIntersecting||entry.target.dataset.done)return;
      entry.target.dataset.done='1';const target=Number(entry.target.dataset.count||0);const start=performance.now();
      const tick=now=>{const p=Math.min((now-start)/900,1);entry.target.textContent=Math.round(target*(1-Math.pow(1-p,3)));if(p<1)requestAnimationFrame(tick);};requestAnimationFrame(tick);ob.unobserve(entry.target);
    }),{threshold:.4});counters.forEach(x=>ob.observe(x));
  }else counters.forEach(x=>x.textContent=x.dataset.count);
});
