'use strict';
const $ = (s) => document.querySelector(s);
const esc = s => String(s ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let parsed, jobId, promotion, timer, currentJob;
async function api(route, data, multipart=false) {
  const headers={'X-CSRF-Token':window.ttwCsrf};
  if(data && !multipart) headers['Content-Type']='application/json';
  const response=await fetch('api/index.php?route='+encodeURIComponent(route),{method:data?'POST':'GET',headers,body:data?(multipart?data:JSON.stringify(data)):undefined,credentials:'same-origin'});
  const result=await response.json();
  if(!response.ok) {const e=new Error(result.error||'Request failed');e.existingId=result.existing_id;throw e;}
  return result;
}
function message(value='') {$('#message').hidden=!value;$('#message').textContent=value;}
function stage(n){document.querySelectorAll('[data-stage]').forEach(s=>s.hidden=Number(s.dataset.stage)!==n);document.querySelectorAll('.auto-steps li').forEach((li,i)=>li.classList.toggle('active',i===n));message();}
async function action(button, fn){button.disabled=true;message();try{await fn();}catch(e){message(e.message);}finally{button.disabled=false;}}
function inputText(){
  let text='';let last='';
  for(const a of parsed.articles){const key=a.section==='category'?a.category_name:({header:'header',pick:'Pick News',more:'More News'})[a.section];if(key!==last){text+='\n'+(a.section==='category'?'Categories:\n':'')+key+':\n';last=key;}text+=a.url+'\n';}
  return text;
}
function review(){
  $('#parseSummary').textContent=`${parsed.total} articles found · ${new Set(parsed.articles.filter(a=>a.category_id).map(a=>a.category_id)).size} categories`;
  $('#parsedArticles').innerHTML=parsed.articles.map((a,i)=>`<article class="auto-article"><header><strong>${esc(a.category_name||({header:'Header Story',pick:'Pick News',more:'More News'})[a.section])}</strong><div><button type="button" data-up="${i}" aria-label="Move up">↑</button> <button type="button" data-down="${i}" aria-label="Move down">↓</button> <button type="button" data-remove="${i}">Remove</button></div></header><input aria-label="Article URL" data-url="${i}" value="${esc(a.url)}"></article>`).join('');
  message([...parsed.errors,...parsed.warnings].join('\n'));$('#toDetails').disabled=parsed.errors.length>0;
}
$('#automationForm').addEventListener('submit',e=>{e.preventDefault();action($('#parse'),async()=>{parsed=await api('/automation/parse',{text:$('#automationForm').elements.text.value});stage(1);review();});});
$('#parsedArticles').addEventListener('click',e=>{
  const b=e.target.closest('button');if(!b)return;
  if(b.dataset.remove!==undefined)parsed.articles.splice(Number(b.dataset.remove),1);
  else {const i=Number(b.dataset.up??b.dataset.down);const j=b.dataset.up!==undefined?i-1:i+1;if(j>=0&&j<parsed.articles.length&&parsed.articles[i].section===parsed.articles[j].section&&parsed.articles[i].category_id===parsed.articles[j].category_id)[parsed.articles[i],parsed.articles[j]]=[parsed.articles[j],parsed.articles[i]];}
  parsed.total=parsed.articles.length;review();
});
$('#parsedArticles').addEventListener('input',e=>{if(e.target.dataset.url!==undefined)parsed.articles[Number(e.target.dataset.url)].url=e.target.value;});
$('#toDetails').onclick=()=>action($('#toDetails'),async()=>{parsed=await api('/automation/parse',{text:inputText()});if(parsed.errors.length){review();return;}stage(2);});
document.querySelectorAll('[data-back]').forEach(b=>b.onclick=()=>stage(Number(b.dataset.back)));
$('#template').onclick=()=>{$('#automationForm').elements.text.value='header:\n\nPick News:\n\nMore News:\n\nCategories:\nTOURISM NEWS:\n\nMEETING AND EVENT INDUSTRY NEWS:\n';};
$('#banner').onchange=()=>action($('#startJob'),async()=>{if(!$('#banner').files[0])return;const data=new FormData();data.append('banner',$('#banner').files[0]);promotion=await api('/uploads/promotion',data,true);$('#bannerPreview').src=promotion.url;$('#bannerPreview').hidden=false;});
$('#removeBanner').onclick=()=>{promotion=null;$('#banner').value='';$('#bannerPreview').hidden=true;};
$('#startJob').onclick=()=>action($('#startJob'),async()=>{
  const data=Object.fromEntries(new FormData($('#automationForm')));data.text=inputText();if(promotion){data.promotion_image=promotion.path;data.promotion_signature=promotion.signature;}
  const result=await api('/automation/jobs',data);jobId=result.id;$('#preview').hidden=true;stage(3);await poll();await refresh();
});
async function poll(){clearTimeout(timer);try{
  currentJob=await api('/automation/jobs/'+jobId);const j=currentJob;$('#jobTitle').textContent=j.newsletter_id?'Newsletter Draft Created':j.status==='failed'?'Review article failures':'Creating Newsletter…';$('#jobStatus').textContent=`${j.completed}/${j.total} articles · ${j.status.replaceAll('_',' ')}`;$('#jobProgress').value=j.progress;
  $('#articleResults').innerHTML=j.articles.map(a=>`<article class="auto-article"><strong>${esc(a.result?.title||a.url)}</strong><small>${esc(a.section)} · ${esc(a.status)}</small>${a.error_message?`<p>${esc(a.error_message)}</p>`:''}<details><summary>Article details</summary><p>${esc(a.url)}</p><p>${esc(a.result?.description||'Description not available yet')}</p><p>Title: ${a.result?.title?'found':'pending / missing'} · Image: ${a.result?.featured_image?'found':'missing / not required'}</p></details></article>`).join('');
  const done=['completed','completed_with_warnings','failed'].includes(j.status);$('#generate').hidden=!done||j.status==='failed'||!!j.newsletter_id;$('#retry').hidden=!done||j.status==='completed'||!!j.newsletter_id;$('#warningConsent').hidden=j.status!=='completed_with_warnings'||!!j.newsletter_id;
  if(j.newsletter_id)openPreview(j.newsletter_id);if(!done)timer=setTimeout(poll,2500);
}catch(e){message(e.message);}}
function openPreview(id){$('#preview').hidden=false;$('#newsletterPreview').src='api/index.php?route='+encodeURIComponent(`/newsletters/${id}/preview`);$('#editDraft').href='editor.php?id='+id;$('#approve').dataset.id=id;}
$('#retry').onclick=()=>action($('#retry'),async()=>{await api(`/automation/jobs/${jobId}/retry`,{});await poll();});
$('#generate').onclick=()=>action($('#generate'),async()=>{
  const options={accept_warnings:$('#acceptWarnings').checked};
  try{const r=await api(`/automation/jobs/${jobId}/generate`,options);openPreview(r.newsletter_id);await refresh();}
  catch(e){if(!e.existingId)throw e;message(e.message);const box=document.createElement('div');box.className='auto-actions';box.innerHTML=`<a class="button secondary" href="view.php?id=${Number(e.existingId)}">Open existing</a><button type="button" class="button secondary">Replace draft</button><button type="button" class="button secondary">Cancel</button>`;$('#message').append(box);const buttons=box.querySelectorAll('button');buttons[0].onclick=()=>action(buttons[0],async()=>{const r=await api(`/automation/jobs/${jobId}/generate`,{...options,replace_draft:true});openPreview(r.newsletter_id);message();await refresh();});buttons[1].onclick=()=>message();}
});
$('#approve').onclick=()=>action($('#approve'),async()=>{await api(`/newsletters/${$('#approve').dataset.id}/approve`,{});message('Newsletter approved.');await refresh();});
async function refresh(){
  const [n,j]=await Promise.all([api('/newsletters'),api('/automation/jobs')]);
  $('#today').innerHTML=['M','E'].map(e=>{const row=n.today.find(x=>x.edition===e);return `<div class="edition-card"><h3>${e==='M'?'☀ Morning':'☾ Evening'} Edition</h3>${row?(row.approved_at?'✓ Approved':'✓ Draft created'):'○ Not created'}<small> · ${esc(n.server_date)}</small></div>`;}).join('');
  $('#recentNews').innerHTML=n.newsletters.slice(0,5).map(r=>`<div class="auto-recent"><strong>${esc(r.newsletter_date)} · ${r.edition==='M'?'Morning':'Evening'}</strong><small>${esc(r.subject_title)}</small><a href="view.php?id=${Number(r.id)}">${r.approved_at?'Approved':'Draft'} · Preview →</a></div>`).join('')||'<p>No newsletters yet.</p>';
  $('#recentJobs').innerHTML=j.jobs.map(r=>`<div class="auto-recent"><button type="button" data-job="${Number(r.id)}">Job #${Number(r.id)} · ${esc(r.status)}<small>${r.completed}/${r.total} processed</small></button></div>`).join('')||'<p>No jobs yet.</p>';
}
$('#recentJobs').onclick=e=>{const b=e.target.closest('[data-job]');if(b){jobId=b.dataset.job;stage(3);$('#preview').hidden=true;poll();}};
refresh().catch(e=>message(e.message));
