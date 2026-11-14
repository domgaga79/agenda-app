(() => {
 'use strict';
 const mode=document.getElementById('odonto-mode');if(!mode)return;
 function filter(){document.querySelectorAll('[data-dentition]').forEach(el=>el.hidden=mode.value!=='mixed'&&el.dataset.dentition!==mode.value);}
 mode.addEventListener('change',filter);filter();
 const form=document.getElementById('odontogram-form');if(!form)return;
 const dialog=document.getElementById('odonto-dialog'),status=document.getElementById('odonto-editor-status'),notes=document.getElementById('odonto-editor-notes'),feedback=document.getElementById('odonto-feedback');
 const symbols={unassessed:'?',sound:'✓',caries:'●',restored:'■',missing:'×',unerupted:'…',implant:'⊙',other:'!'};
 let selected=null,dirty=false;
 function update(code){
  const fields=form.querySelector(`[data-tooth-fields="${code}"]`),select=fields.querySelector('[data-tooth-status]'),text=fields.querySelector('[data-tooth-notes]').value;
  const button=form.querySelector(`[data-tooth="${code}"]`),label=`Dente ${code}: ${select.selectedOptions[0].textContent}`;
  button.className='odonto-tooth state-'+select.value;button.setAttribute('aria-label',label);button.title=label;
  button.querySelector('.odonto-symbol').textContent=symbols[select.value];button.querySelector('.odonto-note-dot').hidden=!text.trim();
 }
 function modified(){dirty=true;feedback.textContent='Há alterações nesta versão. Clique em Salvar versão para gravar.';feedback.classList.add('unsaved');}
 form.addEventListener('input',e=>{const fields=e.target.closest('[data-tooth-fields]');if(fields)update(fields.dataset.toothFields);modified();});
 form.addEventListener('change',e=>{const fields=e.target.closest('[data-tooth-fields]');if(fields)update(fields.dataset.toothFields);modified();});
 form.querySelectorAll('[data-tooth]').forEach(button=>button.addEventListener('click',()=>{
  selected=button.dataset.tooth;const fields=form.querySelector(`[data-tooth-fields="${selected}"]`);
  if(typeof dialog.showModal!=='function'){form.querySelector('.odonto-fallback').open=true;fields.scrollIntoView({block:'center'});fields.querySelector('select').focus();return;}
  document.getElementById('odonto-dialog-title').textContent='Dente '+selected;
  status.value=fields.querySelector('[data-tooth-status]').value;notes.value=fields.querySelector('[data-tooth-notes]').value;dialog.showModal();status.focus();
 }));
 document.getElementById('odonto-close').addEventListener('click',()=>dialog.close());
 document.getElementById('odonto-apply').addEventListener('click',()=>{if(!selected||!notes.reportValidity())return;const fields=form.querySelector(`[data-tooth-fields="${selected}"]`);fields.querySelector('[data-tooth-status]').value=status.value;fields.querySelector('[data-tooth-notes]').value=notes.value;update(selected);modified();feedback.textContent=`Dente ${selected} atualizado nesta versão. Salve para gravar.`;dialog.close();});
 dialog.addEventListener('close',()=>selected=null);
 form.addEventListener('submit',()=>dirty=false);
 window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
})();
