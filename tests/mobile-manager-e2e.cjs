const {chromium}=require('./.runtime/node_modules/playwright');
(async()=>{
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 const page=await browser.newPage({viewport:{width:390,height:844},deviceScaleFactor:2});
 await page.route('https://newsletter.travelandtourworld.com/**',async route=>{
  const req=route.request();
  if(req.method()==='OPTIONS'){await route.fulfill({status:204,headers:{'access-control-allow-origin':'*','access-control-allow-headers':'authorization,content-type','access-control-allow-methods':'GET,POST,OPTIONS'}});return;}
  const u=new URL(req.url());const r=await route.fetch({url:'http://127.0.0.1:8090'+u.pathname+u.search});
  await route.fulfill({response:r,headers:{...r.headers(),'access-control-allow-origin':'*'}});
 });
 await page.goto('http://127.0.0.1:8092');await page.waitForTimeout(2500);
 await page.locator('flt-semantics-placeholder').evaluate(e=>e.click());await page.waitForTimeout(300);
 await page.screenshot({path:'tests/.runtime/mobile-manager-login.png'});
 await page.locator('input').nth(0).click();await page.keyboard.type('manager_admin');
 await page.locator('input').nth(1).click();await page.waitForTimeout(300);await page.keyboard.type('fixture_password_123',{delay:30});
 await page.getByRole('button',{name:/Login/}).click();await page.getByRole('tab',{name:'Home',exact:true}).waitFor();await page.waitForTimeout(800);
 await page.screenshot({path:'tests/.runtime/mobile-manager-dashboard.png'});
 await page.getByRole('tab',{name:'Newsletters',exact:true}).click();await page.waitForTimeout(700);
 await page.screenshot({path:'tests/.runtime/mobile-manager-library.png'});
 await page.getByRole('tab',{name:'Reports',exact:true}).click();await page.waitForTimeout(700);
 await page.screenshot({path:'tests/.runtime/mobile-manager-reports.png'});
 await page.getByRole('tab',{name:'More',exact:true}).click();await page.waitForTimeout(500);
 if(!(await page.locator('body').innerText()).includes('Employees & Permissions'))throw Error('Admin manager modules missing.');
 await page.screenshot({path:'tests/.runtime/mobile-manager-settings.png'});
 await browser.close();console.log('PASS: rendered Flutter login, authenticated dashboard, library, reports and admin settings at 390px. All API traffic used local fixtures.');
})().catch(e=>{console.error(e);process.exit(1)});
