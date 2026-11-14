(()=>{
  const root=document.documentElement;
  root.classList.add('js-reveal');
  const header=document.querySelector('.site-header');
  const menu=document.querySelector('.menu-button');
  const nav=document.querySelector('#main-nav');
  const onScroll=()=>header?.classList.toggle('scrolled',window.scrollY>18);
  onScroll(); window.addEventListener('scroll',onScroll,{passive:true});
  menu?.addEventListener('click',()=>{const open=nav.classList.toggle('open');menu.setAttribute('aria-expanded',String(open))});
  nav?.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>{nav.classList.remove('open');menu?.setAttribute('aria-expanded','false')}));

  const reduced=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const revealEls=[...document.querySelectorAll('[data-reveal]')];
  if(reduced||!('IntersectionObserver'in window)){revealEls.forEach(el=>el.classList.add('visible'))}
  else{
    const io=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('visible');io.unobserve(entry.target)}}),{threshold:.08,rootMargin:'0px 0px -30px 0px'});
    requestAnimationFrame(()=>revealEls.forEach(el=>io.observe(el)));
    setTimeout(()=>revealEls.filter(el=>el.getBoundingClientRect().top<window.innerHeight).forEach(el=>el.classList.add('visible')),180);
  }

  const typed=document.querySelector('#typed-copy');
  const phrases=['Organize os horários da sua equipe.','Tenha seus pacientes sempre à mão.','Acompanhe recebimentos e despesas.','Facilite retornos e lembretes.','Profissionalize sua rotina de atendimento.'];
  if(typed&&!reduced){let i=0;setInterval(()=>{typed.classList.add('swap');setTimeout(()=>{i=(i+1)%phrases.length;typed.textContent=phrases[i];typed.classList.remove('swap')},190)},3200)}
})();
