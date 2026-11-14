(() => {
 'use strict';
 const form=document.getElementById('treatment-form');if(!form)return;
 const items=document.getElementById('treatment-items'),add=document.getElementById('add-treatment-item'),template=document.getElementById('treatment-item-template');
 let nextIndex=items.children.length;
 const money=new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'});
 const uuid=()=>{if(crypto.randomUUID)return crypto.randomUUID();const b=crypto.getRandomValues(new Uint8Array(16));b[6]=(b[6]&15)|64;b[8]=(b[8]&63)|128;return [...b].map((v,i)=>([4,6,8,10].includes(i)?'-':'')+v.toString(16).padStart(2,'0')).join('');};
 function refresh(){
  let cents=0,unknown=0,invalid=false;
  const rows=[...items.querySelectorAll('[data-treatment-item]')];
  rows.forEach((row,index)=>{
   row.querySelector('[data-item-number]').textContent=index+1;
   const remove=row.querySelector('[data-remove-item]');remove.hidden=false;remove.disabled=rows.length===1;
   if(row.querySelector('[data-status]').value==='cancelled')return;
   const price=row.querySelector('input[data-price]').value.trim(),quantity=Number(row.querySelector('[data-quantity]').value);
   if(!Number.isInteger(quantity)||quantity<1||quantity>100){invalid=true;return;}
   if(price===''){unknown++;return;}
   if(!/^\d{1,6}([.,]\d{1,2})?$/.test(price)){invalid=true;return;}
   const [whole,fraction='']=price.replace(',','.').split('.');cents+=(Number(whole)*100+Number(fraction.padEnd(2,'0')))*quantity;
  });
  document.getElementById('treatment-total').textContent=invalid?'Confira os valores':money.format(cents/100);
  document.getElementById('treatment-total-note').textContent=unknown?`Subtotal: ${unknown} item(ns) com valor a combinar. Itens cancelados não entram no total.`:'Itens cancelados não entram no total. Valores previstos, sem pagamentos.';
  add.disabled=rows.length>=50;
 }
 add.hidden=false;
 add.addEventListener('click',()=>{
  if(items.children.length>=50)return;
  const fragment=template.content.cloneNode(true),index=nextIndex++;
  fragment.querySelectorAll('[name]').forEach(el=>el.name=el.name.replace('__INDEX__',String(index)));
  fragment.querySelector('[data-item-id]').value=uuid();items.append(fragment);refresh();items.lastElementChild.querySelector('[data-service]').focus();
 });
 items.addEventListener('click',e=>{const button=e.target.closest('[data-remove-item]');if(button&&items.children.length>1){button.closest('[data-treatment-item]').remove();refresh();}});
 items.addEventListener('change',e=>{
  if(e.target.matches('[data-service]')){const option=e.target.selectedOptions[0],row=e.target.closest('[data-treatment-item]');if(option.value){row.querySelector('input[data-description]').value=option.dataset.description;row.querySelector('input[data-price]').value=option.dataset.price;}}
  refresh();
 });
 items.addEventListener('input',refresh);refresh();
})();
