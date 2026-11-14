document.addEventListener('DOMContentLoaded',()=>{
 const controls=[];
 const help=document.querySelector('.schedule-help');
 const badge=document.createElement('span');badge.className='duration-badge';badge.setAttribute('role','status');
 if(help)help.prepend(badge);
 const duration=()=>{
  const start=document.getElementById('starts_at')?.value,end=document.getElementById('ends_at')?.value;
  const minutes=start&&end?Math.round((new Date(end)-new Date(start))/60000):0;
  badge.textContent=minutes>0?`Duração: ${minutes>=60?Math.floor(minutes/60)+'h ':''}${minutes%60||minutes<60?(minutes%60)+'min':''}`.trim():'';
 };
 for(const name of ['starts_at','ends_at']){
  const original=document.getElementById(name);if(!original)continue;
  const group=document.createElement('div');group.className='split-datetime';
  const date=document.createElement('input'),time=document.createElement('input');
  date.type='date';time.type='time';date.required=time.required=true;time.step=60;
  date.setAttribute('aria-label',name==='starts_at'?'Data de início':'Data de término');
  time.setAttribute('aria-label',name==='starts_at'?'Horário de início':'Horário de término');
  date.id=name+'-date';date.min='1900-01-01';date.max='9999-12-31';
  const label=document.querySelector('label[for="'+name+'"]');if(label)label.htmlFor=date.id;
  let writing=false;
  const read=()=>{if(writing)return;const value=original.value;date.value=value.slice(0,10);time.value=value.slice(11,16);duration();};
  const write=()=>{writing=true;try{original.value=date.value&&time.value?date.value+'T'+time.value:'';original.dispatchEvent(new Event('change',{bubbles:true}));}finally{writing=false;}duration();};
  date.addEventListener('change',write);time.addEventListener('change',write);
  original.addEventListener('change',read);read();
  for(const [input,title,cls] of [[date,'Data','date-part'],[time,'Horário','time-part']]){
   const wrap=document.createElement('label');wrap.className=cls;const text=document.createElement('span');text.textContent=title;wrap.append(text,input);group.append(wrap);
  }
  original.after(group);original.hidden=true;original.required=false;controls.push({original,date,time,read});
 }
 duration();document.addEventListener('agenda:time-suggested',()=>controls.forEach(c=>c.read()));
});
