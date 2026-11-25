(()=>{
 const menu=document.querySelector('#ga-sidebar');
 const overlay=document.querySelector('[data-menu-close]');
 const button=document.querySelector('[data-menu-toggle]');
 const setMenu=(open)=>{
   menu?.classList.toggle('is-open',open);
   if(overlay)overlay.hidden=!open;
   button?.setAttribute('aria-expanded',String(open));
   document.body.style.overflow=open?'hidden':'';
 };
 button?.addEventListener('click',()=>setMenu(!menu?.classList.contains('is-open')));
 overlay?.addEventListener('click',()=>setMenu(false));
 document.addEventListener('keydown',(e)=>{if(e.key==='Escape')setMenu(false)});
 window.matchMedia('(min-width:991px)').addEventListener('change',ev=>{if(ev.matches)setMenu(false)});
 const feed=window.gaOngFeed;
 if(feed && feed.url){
   const banner=document.querySelector('#ga-network-update');
   const check=async()=>{
     if(document.hidden || !banner || !banner.hidden) return;
     try{
       const response=await fetch(feed.url,{cache:'no-store',credentials:'same-origin'});
       if(!response.ok) return;
       const data=await response.json();
       if(Number(data.latest)>Number(feed.latest) || Number(data.count)>Number(feed.count)) banner.hidden=false;
     }catch(e){ /* conexão temporariamente indisponível; aguardar próxima checagem */ }
   };
   setInterval(check,10000);
 }
})();
