document.addEventListener('DOMContentLoaded',()=>{
 const sidebar=document.querySelector('.sidebar'),button=document.querySelector('.mobile-menu-toggle');
 if(!sidebar||!button)return;
 document.documentElement.classList.add('mobile-nav-ready');
 const setOpen=open=>{sidebar.classList.toggle('is-open',open);button.setAttribute('aria-expanded',String(open));button.querySelector('span').textContent=open?'Fechar':'Menu';};
 button.addEventListener('click',()=>setOpen(button.getAttribute('aria-expanded')!=='true'));
 document.addEventListener('keydown',event=>{if(event.key==='Escape'&&button.getAttribute('aria-expanded')==='true'){setOpen(false);button.focus();}});
 const media=matchMedia('(max-width:850px)');media.addEventListener('change',()=>setOpen(false));
});
