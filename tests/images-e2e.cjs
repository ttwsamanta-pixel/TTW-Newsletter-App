const {chromium}=require('./.runtime/node_modules/playwright');
const fs=require('node:fs');
const path=require('node:path');
(async()=>{
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 const context=await browser.newContext({javaScriptEnabled:false});
 const page=await context.newPage();
 await page.route('**/*',route=>new URL(route.request().url()).hostname==='127.0.0.1'?route.continue():route.abort());
 const responses=[];page.on('response',r=>{if(r.url().includes('/image.php?'))responses.push(r);});
 await page.goto('http://127.0.0.1:8089/New/preview.php');
 const images=page.locator('img[src*="image.php?"]');
 if(await images.count()!==4)throw Error('Newsletter, promotion and profile previews do not all use fallback.');
 for(let i=0;i<4;i++){if(!await images.nth(i).evaluate(img=>img.complete&&img.naturalWidth>0))throw Error('Broken image with JavaScript disabled.');}
 const sources=await Promise.all(responses.map(async r=>({kind:new URL(r.url()).searchParams.get('kind'),source:await r.headerValue('X-TTW-Image-Source')})));
 for(const [kind,source] of [['header','primary'],['pick','legacy'],['promotion','placeholder'],['profile','legacy']]){
  if(!sources.some(r=>r.kind===kind&&r.source===source))throw Error(`Wrong ${kind} fallback source: ${JSON.stringify(sources)}`);
 }
 const root=path.resolve(__dirname,'.runtime/image-web');const primary=path.join(root,'New/uploads/2026/Nov/shared.png');const legacy=path.join(root,'uploads/2026/Nov/shared.png');
 const url='http://127.0.0.1:8089/New/image.php?path=uploads%2F2026%2FNov%2Fshared.png&kind=header';
 const first=await context.request.get(url);if(first.headers()['x-ttw-image-source']!=='primary')throw Error('Primary did not win.');
 const original=fs.readFileSync(primary);fs.unlinkSync(primary);
 const second=await context.request.get(url,{headers:{'If-None-Match':first.headers().etag}});
 if(second.status()!==200||second.headers()['x-ttw-image-source']!=='legacy')throw Error('Legacy did not replace a cached missing primary.');
 const unchanged=await context.request.get(url,{headers:{'If-None-Match':second.headers().etag}});if(unchanged.status()!==304)throw Error('Unchanged selected image was not revalidated.');
 const old=fs.readFileSync(legacy);fs.unlinkSync(legacy);
 const third=await context.request.get(url,{headers:{'If-None-Match':second.headers().etag}});if(third.headers()['x-ttw-image-source']!=='placeholder')throw Error('Final placeholder missing.');
 fs.writeFileSync(primary,original);fs.writeFileSync(legacy,old);
 const restored=await context.request.get(url,{headers:{'If-None-Match':third.headers().etag}});if(restored.status()!==200||restored.headers()['x-ttw-image-source']!=='primary')throw Error('New upload did not replace cached placeholder.');
 for(const invalid of ['uploads/../config.php','uploads/%2e%2e/config.php','https://evil.example/uploads/test.png']){
  const r=await context.request.get('http://127.0.0.1:8089/New/image.php?path='+encodeURIComponent(invalid));if(r.status()!==400)throw Error('Unsafe image path accepted.');
 }
 const head=await context.request.head(url);if(head.status()!==200||head.headers()['content-type']!=='image/png')throw Error('HEAD image response invalid.');
 await browser.close();console.log('PASS: New→original→placeholder images with JavaScript disabled, newsletter/profile/promotion rendering, cache recovery, HEAD and unsafe-path rejection.');
})().catch(e=>{console.error(e);process.exit(1);});
