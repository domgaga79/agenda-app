document.querySelectorAll('.help-root').forEach(root=>{
 const search=root.querySelector('#help-search'), role=root.querySelector('#help-role');
 const articles=[...root.querySelectorAll('[data-help-roles]')];
 const normalize=value=>value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
 function filter(){const terms=normalize(search.value).trim().split(/\s+/).filter(Boolean);let count=0;
 articles.forEach(article=>{const text=normalize(article.textContent);const match=(!role.value||article.dataset.helpRoles.split(' ').includes(role.value))&&terms.every(term=>text.includes(term));article.hidden=!match;root.querySelector('[data-help-link="'+article.id+'"]').hidden=!match;if(match)count++;});
 root.querySelector('[data-help-count]').textContent=count+' assunto'+(count===1?'':'s')+' encontrado'+(count===1?'':'s');root.querySelector('[data-help-empty]').hidden=count!==0;}
 search.addEventListener('input',filter);role.addEventListener('change',filter);root.querySelector('[data-help-print]').addEventListener('click',()=>window.print());filter();
});
