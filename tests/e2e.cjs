// npm.cmd install --prefix tests/.runtime playwright --no-audit --no-fund
// Requires isolated DB environment and local PHP test server on port 8088.
const {chromium}=require('./.runtime/node_modules/playwright');
const {spawnSync}=require('node:child_process');
const path=require('node:path');
if(process.env.TTW_DB_NAME!=='ttw_automation_test')throw Error('Use isolated test database only.');
(async()=>{
  const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
  const page=await browser.newPage({viewport:{width:1440,height:1050}});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://127.0.0.1:8088/login.php');
  await page.locator('[name=username]').fill('test_admin');await page.locator('[name=password]').fill('ttw_test_password');
  await Promise.all([page.waitForURL('**/index.php'),page.locator('button[type=submit]').click()]);
  await page.goto('http://127.0.0.1:8088/automation.php');
  await page.locator('#recentJobs .auto-recent').first().waitFor();
  await page.screenshot({path:'docs/web-dashboard.png',fullPage:true});
  const issues=await page.request.get('http://127.0.0.1:8088/api/index.php?route=/newsletters').then(r=>r.json());
  let issueDate='2096-11-06';
  while(issues.newsletters.some(n=>n.newsletter_date===issueDate)){const d=new Date(issueDate+'T00:00:00Z');d.setUTCDate(d.getUTCDate()+1);issueDate=d.toISOString().slice(0,10);}
  await page.locator('[name=newsletter_date]').fill(issueDate);
  await page.locator('[name=text]').fill('header:\nhttps://www.travelandtourworld.com/news/article/tbfqplg7jbyu/\nPick News:\nhttps://www.travelandtourworld.com/news/article/sy1fa7hprgfw/');
  await page.locator('#parse').click();await page.locator('#parseSummary').waitFor();
  if(!await page.locator('#parseSummary').innerText().then(t=>t.includes('2 articles')))throw Error('Parse count missing');
  await page.locator('#toDetails').click();await page.locator('[name=subject_title]').fill('Browser acceptance newsletter');
  await page.locator('#banner').setInputFiles('TTW-Logo.png');await page.locator('#bannerPreview').waitFor();
  await page.locator('[name=promotion_url]').fill('https://www.travelandtourworld.com/ttwapp/');
  await page.locator('#startJob').click();await page.locator('#jobStatus').waitFor();
  for(let i=0;i<2;i++){
    const result=spawnSync('php',['automation/worker.php','--once'],{cwd:path.resolve(__dirname,'..'),env:process.env,encoding:'utf8'});
    if(result.status!==0)throw Error('Worker failed');
  }
  await page.locator('#generate').waitFor({timeout:30000});
  if(await page.locator('#warningConsent').isVisible())await page.locator('#acceptWarnings').check();
  await page.locator('#generate').click();await page.locator('#preview').waitFor();
  const frame=page.frameLocator('#newsletterPreview');
  await frame.locator('h2').filter({hasText:'DAILY TRAVEL NEWS UPDATE'}).waitFor();
  await page.locator('#approve').click();
  await page.getByText('Newsletter approved.',{exact:true}).waitFor();
  await page.screenshot({path:'docs/web-preview.png',fullPage:true});
  await page.setViewportSize({width:390,height:844});
  await page.goto('http://127.0.0.1:8088/automation.php');await page.locator('#today .edition-card').first().waitFor();
  await page.screenshot({path:'docs/web-mobile.png',fullPage:true});
  const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth+1);
  if(overflow)throw Error('Mobile horizontal overflow');
  if(errors.length)throw Error('Browser errors: '+errors.join('; '));
  await browser.close();console.log('PASS: browser login, responsive layout, parse, upload, real worker, generate, renderer preview, approve');
})().catch(e=>{console.error(e.message);process.exit(1);});
