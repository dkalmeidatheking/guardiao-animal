(()=>{
  'use strict';
  const menu=document.querySelector('#ga-sidebar');
  const overlay=document.querySelector('[data-menu-close]');
  const toggle=document.querySelector('[data-menu-toggle]');
  function setMenu(open){
    menu?.classList.toggle('is-open',open);
    if(overlay) overlay.hidden=!open;
    toggle?.setAttribute('aria-expanded',String(open));
    document.body.style.overflow=open?'hidden':'';
  }
  toggle?.addEventListener('click',()=>setMenu(!menu?.classList.contains('is-open')));
  overlay?.addEventListener('click',()=>setMenu(false));
  document.addEventListener('keydown',e=>{if(e.key==='Escape')setMenu(false);});
  window.matchMedia('(min-width: 991px)').addEventListener('change',ev=>{if(ev.matches)setMenu(false);});

  document.querySelectorAll('.ga-case-list-row').forEach(link=>{
    link.addEventListener('click',()=>{
      if(window.matchMedia('(max-width: 760px)').matches) link.href=link.href.split('#')[0]+'#detalhes';
    });
  });
  const filter=document.querySelector('#ga-status-filter');
  filter?.addEventListener('change',()=>{
    const select=new URL(window.location.href);
    select.searchParams.set('view','denuncias');
    select.searchParams.delete('case');
    select.searchParams.delete('page');
    if(filter.value==='Todas') select.searchParams.delete('status');
    else select.searchParams.set('status',filter.value);
    window.location.assign(select.toString());
  });

  const config=window.guardiaoAdmin;
  const indicator=document.querySelector('#live-indicator');
  if(!config || !indicator)return;
  const baseline={denuncias:Number(config.denuncias||0),pendentes:Number(config.pendentes||0)};
  async function refresh(){
    if(document.hidden)return;
    try {
      const res=await fetch(config.feed,{cache:'no-store',credentials:'same-origin'});
      if(!res.ok)throw new Error('Status '+res.status);
      const data=await res.json();
      if(typeof data.denuncias!=='number'||typeof data.pendentes!=='number')throw new Error('Resposta inválida');
      if(data.denuncias!==baseline.denuncias||data.pendentes!==baseline.pendentes){
        indicator.classList.add('ga-sync-warning');
        indicator.textContent='Há novos registros · atualize a página';
        document.title='● Novos registros · Guardião Animal';
      }else {
        indicator.classList.remove('ga-sync-warning');
        indicator.innerHTML='<i></i> Dados locais conferidos agora';
      }
    } catch(error){
      indicator.classList.add('ga-sync-warning');
      indicator.textContent='Não foi possível atualizar os indicadores';
    }
  }
  window.setInterval(refresh,10000);
})();
