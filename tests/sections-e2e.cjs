const {chromium}=require('./.runtime/node_modules/playwright');
const {spawnSync}=require('node:child_process');
const path=require('node:path');
if(process.env.TTW_DB_NAME!=='ttw_automation_test')throw Error('Use isolated fixture database only.');
(async()=>{
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 const page=await browser.newPage({viewport:{width:1440,height:1050}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://127.0.0.1:8088/login.php');await page.locator('[name=username]').fill('test_admin');await page.locator('[name=password]').fill('ttw_test_password');
 await Promise.all([page.waitForURL('**/index.php'),page.locator('button[type=submit]').click()]);
 const summary=await page.request.get('http://127.0.0.1:8088/api/index.php?route=/newsletters').then(r=>r.json());let date='2090-11-09';
 while(summary.newsletters.some(n=>n.newsletter_date===date)){const d=new Date(date+'T00:00:00Z');d.setUTCDate(d.getUTCDate()+1);date=d.toISOString().slice(0,10);}
 await page.goto('http://127.0.0.1:8088/editor.php');await page.locator('[name=newsletter_date]').fill(date);
 await page.locator('.step').nth(3).click();await page.getByText('Draft saved.',{exact:true}).waitFor();
 const id=new URL(page.url()).searchParams.get('id');if(!id)throw Error('Draft was not saved before importing.');
 async function read(){const r=await page.request.get(`http://127.0.0.1:8088/api/index.php?route=/newsletters/${id}`);return (await r.json()).newsletter;}
 async function importSection(section,step,text){
  await page.locator('.step').nth(step).click();const box=page.locator(`[data-import-section=${section}]`);await box.locator('summary').click();await box.locator('[data-import-links]').fill(text);await box.locator('[data-import-start]').click();
  await page.waitForFunction(s=>document.querySelector(`[data-import-section=${s}] [data-import-status]`).textContent.includes('pending'),section);
  const workerRun=spawnSync('php',['automation/cron.php'],{cwd:path.resolve(__dirname,'..'),env:process.env,encoding:'utf8'});if(workerRun.status!==0)throw Error('Cron failed: '+workerRun.stderr);
  await page.waitForURL(`**/editor.php?id=${id}&step=${step}`,{timeout:45000});
  if(await box.locator('[data-import-warning]').isVisible())throw Error('Unexpected fixture article warning.');
 }
 const header='https://www.travelandtourworld.com/news/article/2d2gcmvmefhd/';
 const pick='https://www.travelandtourworld.com/news/article/ml4qnd4tb8kf/';
 await importSection('more',3,header+'\n'+pick);let n=await read();
 if(n.more_news.length!==2||n.header_title!==''||n.subject_title!=='')throw Error('More-first draft did not preserve the optional title/header.');
 await importSection('category',4,'TOURISM NEWS:\n'+pick);n=await read();if(n.categories.length!==1||n.more_news.length!==2)throw Error('Category import lost previous content.');
 await importSection('header',1,header);n=await read();
 if(n.header_title.endsWith(' - Travel And Tour World')||n.header_description.split(/\s+/).length!==35||n.hidden_text.split(/\s+/).length!==16)throw Error('Header body text/heading rules failed.');
 await importSection('pick',2,pick);n=await read();
 if(n.pick_news.length!==1||n.pick_news[0].description.split(/\s+/).length!==16||n.more_news.length!==2||n.categories.length!==1)throw Error('Pick import lost earlier sections or body word count.');
 await page.locator('.step').nth(0).click();await page.locator('[name=subject_title]').fill('Manual Email Title added last');await page.locator('section.section.active').getByRole('button',{name:'Save Draft',exact:true}).click();await page.waitForURL(`**/editor.php?id=${id}`);
 n=await read();if(n.subject_title!=='Manual Email Title added last'||n.pick_news.length!==1)throw Error('Late Email Title save lost content.');
 await page.screenshot({path:'docs/web-section-workflow.png',fullPage:true});
 await page.locator('.step').nth(3).click();const returnBox=page.locator('[data-import-section=more]');await returnBox.locator('summary').click();await returnBox.locator('[data-import-links]').fill(pick);await returnBox.locator('[data-import-start]').click();
 await page.waitForFunction(()=>document.querySelector('[data-import-section=more] [data-import-status]').textContent.includes('pending'));
 await returnBox.locator('[data-import-stop]').click();
 if(!await page.locator('[name=subject_title]').isEnabled())throw Error('Returning to editing did not unlock the form.');
 const stoppedRun=spawnSync('php',['automation/cron.php'],{cwd:path.resolve(__dirname,'..'),env:process.env,encoding:'utf8'});if(stoppedRun.status!==0)throw Error('Stopped-import cron failed');
 n=await read();if(n.more_news.length!==2)throw Error('Stopped polling unexpectedly replaced the section.');
 await page.setViewportSize({width:390,height:844});await page.locator('.step').nth(3).click();await page.screenshot({path:'docs/web-section-mobile.png',fullPage:true});
 if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1))throw Error('Section editor overflows mobile viewport.');
 if(errors.length)throw Error(errors.join('; '));await browser.close();console.log('PASS: browser More→Categories→Header→Pick imports, late optional title, body snippets, return-to-editing and responsive layout.');
})().catch(e=>{console.error(e);process.exit(1);});
