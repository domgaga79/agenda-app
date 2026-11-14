document.addEventListener('DOMContentLoaded',()=>{
 const customer=document.getElementById('customer_id'),service=document.getElementById('service_id');
 const field=n=>document.querySelector('[name="'+n+'"]');
 if(!customer||!service)return;
 const lock=()=>{['client_name','client_phone','client_email'].forEach(n=>{field(n).readOnly=!!customer.value;});field('service').readOnly=!!service.value;};
 const suggest=()=>{const o=service.selectedOptions[0],start=field('starts_at').value;if(!service.value||!start)return;const d=new Date(start);d.setMinutes(d.getMinutes()+Number(o.dataset.duration));if(!Number.isNaN(d.getTime())){const p=n=>String(n).padStart(2,'0');field('ends_at').value=d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate())+'T'+p(d.getHours())+':'+p(d.getMinutes());document.dispatchEvent(new Event('agenda:time-suggested'));}};
 customer.addEventListener('change',()=>{const o=customer.selectedOptions[0];if(customer.value){field('client_name').value=o.dataset.name;field('client_phone').value=o.dataset.phone;field('client_email').value=o.dataset.email;}lock();});
 service.addEventListener('change',()=>{if(service.value){field('service').value=service.selectedOptions[0].dataset.name;suggest();}lock();});
 field('starts_at').addEventListener('change',suggest);lock();
});
