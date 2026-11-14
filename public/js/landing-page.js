(()=>{
  const root=document.documentElement;
  root.classList.add('js-reveal');

  const header=document.querySelector('.site-header');
  const menu=document.querySelector('.menu-button');
  const nav=document.querySelector('#main-nav');

  const onScroll=()=>{
    if(header) header.classList.toggle('scrolled',window.scrollY>18);
  };
  onScroll();
  window.addEventListener('scroll',onScroll,{passive:true});

  if(menu&&nav){
    menu.addEventListener('click',()=>{
      const open=nav.classList.toggle('open');
      menu.setAttribute('aria-expanded',String(open));
    });
    nav.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>{
      nav.classList.remove('open');
      menu.setAttribute('aria-expanded','false');
    }));
  }

  const reduced=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const revealEls=[...document.querySelectorAll('[data-reveal]')];
  if(reduced||!('IntersectionObserver' in window)){
    revealEls.forEach(el=>el.classList.add('visible'));
  }else{
    const io=new IntersectionObserver(entries=>{
      entries.forEach(entry=>{
        if(entry.isIntersecting){
          entry.target.classList.add('visible');
          io.unobserve(entry.target);
        }
      });
    },{threshold:.08,rootMargin:'0px 0px -30px 0px'});
    requestAnimationFrame(()=>revealEls.forEach(el=>io.observe(el)));
    setTimeout(()=>{
      revealEls
        .filter(el=>el.getBoundingClientRect().top<window.innerHeight)
        .forEach(el=>el.classList.add('visible'));
    },180);
  }

  function typeCycle(el,items,options){
    if(!el||!items||!items.length) return;

    const opts=options||{};
    const typeSpeed=opts.typeSpeed||42;
    const deleteSpeed=opts.deleteSpeed||24;
    const holdDelay=opts.holdDelay||1700;
    const betweenDelay=opts.betweenDelay||240;
    const startDelay=opts.startDelay||0;

    let itemIndex=0;
    let charIndex=0;
    let deleting=false;

    el.textContent='';

    function tick(){
      const current=items[itemIndex];

      if(!deleting){
        charIndex++;
        el.textContent=current.substring(0,charIndex);

        if(charIndex>=current.length){
          deleting=true;
          setTimeout(tick,holdDelay);
          return;
        }

        setTimeout(tick,typeSpeed);
        return;
      }

      charIndex--;
      el.textContent=current.substring(0,Math.max(0,charIndex));

      if(charIndex<=0){
        deleting=false;
        itemIndex=(itemIndex+1)%items.length;
        setTimeout(tick,betweenDelay);
        return;
      }

      setTimeout(tick,deleteSpeed);
    }

    setTimeout(tick,startDelay);
  }

  // A escrita abaixo roda sempre, inclusive quando o sistema operacional
  // está configurado para reduzir animações. Assim o Chrome não bloqueia o efeito.
  typeCycle(document.querySelector('#typed-copy'),[
    'Organize os horários da sua equipe.',
    'Tenha seus pacientes sempre à mão.',
    'Acompanhe recebimentos e despesas.',
    'Facilite retornos e lembretes.',
    'Profissionalize sua rotina de atendimento.'
  ],{typeSpeed:34,deleteSpeed:20,holdDelay:1500,startDelay:180});

  const bigTitles=[
    ['#title-hero',[
      'Sua agenda organizada para atender melhor.',
      'Seus horários, pacientes e gestão em um só lugar.',
      'Mais organização para uma rotina mais profissional.'
    ]],
    ['#title-recursos',[
      'Uma agenda para organizar o dia.',
      'Um sistema para organizar o negócio.',
      'Tudo o que sua rotina precisa em um só lugar.'
    ]],
    ['#title-fluxo',[
      'Mais organização em cada etapa.',
      'Do agendamento ao pós-atendimento.',
      'Uma rotina clara do começo ao fim.'
    ]],
    ['#title-prontuario',[
      'O paciente chega e o histórico já está ali.',
      'Prontuários e informações sempre à mão.',
      'Mais contexto para atender com segurança.'
    ]],
    ['#title-segmentos',[
      'Uma rotina mais simples em diferentes negócios.',
      'Feito para quem trabalha com hora marcada.',
      'Organização para clínicas e profissionais.'
    ]],
    ['#title-plano',[
      'Organize seu atendimento por R$ 30 ao mês.',
      'Gestão completa sem pesar no seu orçamento.',
      'Mais controle por um valor simples e acessível.'
    ]],
    ['#title-faq',[
      'Vamos tirar suas dúvidas?',
      'Veja como a Agenda Bacuri funciona.',
      'Tudo o que você precisa saber antes de começar.'
    ]],
    ['#title-final',[
      'Menos tempo controlando a agenda. Mais tempo para atender.',
      'Organize sua rotina e foque no atendimento.',
      'Sua agenda pode trabalhar a favor do seu negócio.'
    ]]
  ];


  function reserveTitleHeight(el,items){
    if(!el||!items||!items.length) return;
    const title=el.closest('.typed-big-title');
    if(!title) return;

    const original=el.textContent;
    title.style.minHeight='0px';
    const originalVisibility=el.style.visibility;
    el.style.visibility='hidden';
    let maxHeight=0;

    items.forEach(text=>{
      el.textContent=text;
      maxHeight=Math.max(maxHeight,title.offsetHeight);
    });

    el.textContent=original;
    el.style.visibility=originalVisibility;
    title.style.minHeight=Math.ceil(maxHeight)+'px';
    title.dataset.reservedHeight=String(Math.ceil(maxHeight));
  }

  function reserveAllBigTitleHeights(){
    bigTitles.forEach(([selector,phrases])=>{
      reserveTitleHeight(document.querySelector(selector),phrases);
    });
  }
  reserveAllBigTitleHeights();

  if(document.fonts&&document.fonts.ready){
    document.fonts.ready.then(reserveAllBigTitleHeights);
  }

  let resizeTimer;
  window.addEventListener('resize',()=>{
    clearTimeout(resizeTimer);
    resizeTimer=setTimeout(reserveAllBigTitleHeights,120);
  },{passive:true});

  bigTitles.forEach(([selector,phrases],index)=>{
    typeCycle(document.querySelector(selector),phrases,{
      typeSpeed:34,
      deleteSpeed:18,
      holdDelay:1900,
      startDelay:280+(index*90)
    });
  });
})();
