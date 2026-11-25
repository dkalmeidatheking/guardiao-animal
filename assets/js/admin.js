(()=>{
 const list=document.querySelector('#case-list');
 const search=document.querySelector('#case-search');
 const chips=[...document.querySelectorAll('[data-filter-status]')];
 let currentStatus='all';

 const filterCases=()=>{
   const q=(search?.value||'').toLocaleLowerCase('pt-BR').trim();
   document.querySelectorAll('#case-list .case-item').forEach(el=>{
     const text=el.dataset.search||'';
     const status=el.dataset.status||'';
     const statusOk=currentStatus==='all' || status===currentStatus;
     const searchOk=!q || text.includes(q);
     el.hidden=!(statusOk && searchOk);
   });
 };

 search?.addEventListener('input', filterCases);
 chips.forEach(btn=>btn.addEventListener('click',()=>{
   currentStatus=btn.dataset.filterStatus||'all';
   chips.forEach(chip=>chip.classList.remove('is-active'));
   btn.classList.add('is-active');
   filterCases();
 }));
 filterCases();

 const config=window.guardiaoAdmin;
 if(!config) return;
 const initial={
   denuncias:Number(document.querySelector('#stat-denuncias')?.textContent||0),
   pendentes:Number(document.querySelector('#stat-pendentes')?.textContent||0)
 };
 const indicator=document.querySelector('#live-indicator');
 let requested=false;
 setInterval(async()=>{
   if(document.hidden) return;
   try {
     const res=await fetch(config.feed,{cache:'no-store',credentials:'same-origin'});
     if(!res.ok) throw new Error('Não autenticado');
     const data=await res.json();
     if(data.denuncias!==initial.denuncias || data.pendentes!==initial.pendentes){
       const delta=data.denuncias-initial.denuncias;
       indicator.textContent=delta>0 ? `${delta} nova(s) denúncia(s). Atualize a página para visualizar.` : 'Há novos registros no painel. Atualize a página.';
       indicator.classList.add('new-record');
       document.title='● Novo registro · Guardião Animal';
       if(!requested && 'Notification' in window && Notification.permission==='granted'){
         new Notification('Guardião Animal',{body:'Chegaram novos registros ao painel.'});
         requested=true;
       }
     }else{
       indicator.textContent='Atualizado agora · verificando a cada 10 segundos';
       indicator.classList.remove('new-record');
     }
   } catch (e) {
     indicator.textContent='Sem conexão com a atualização automática';
   }
 },10000);
})();
