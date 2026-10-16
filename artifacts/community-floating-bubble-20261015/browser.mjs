import fs from 'node:fs';
import assert from 'node:assert/strict';
import puppeteer from '/tmp/jakawi-brand-release/browser/node_modules/puppeteer-core/lib/puppeteer/puppeteer-core.js';
const p='/home/jakawi.com/backups/community-floating-bubble-20261015', stage=process.env.QA_STAGE??'candidate';
const routes=JSON.parse(fs.readFileSync(`${p}/${stage}-smoke.json`));
const result={stage,status:'FAIL',views:[],errors:[],requests:[],timers:[],externalRequests:0};
const browser=await puppeteer.launch({executablePath:'/root/.cache/puppeteer/chrome/linux-155.0.8059.39/chrome-linux64/chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
let current,fixture=false,opened=false;
try {
 const page=await browser.newPage();await page.setCacheEnabled(false);await page.setBypassServiceWorker(true);
 page.on('pageerror',e=>result.errors.push(e.message));
 await page.evaluateOnNewDocument(()=>{const original=window.setTimeout;window.__communityTimers=[];window.setTimeout=(fn,delay,...args)=>{if(delay>=3000&&delay<=10000)window.__communityTimers.push(delay);return original(fn,delay,...args);};});
 await page.setRequestInterception(true);
 page.on('request',request=>{
  try {
   const u=new URL(request.url());
   if(request.method()==='POST') {
    if (!u.pathname.startsWith('/comunidades/')) return request.respond({status:200,contentType:'application/json',body:'{}'});
    const data=JSON.parse(request.postData());result.requests.push({route:current.name,...data});
    return request.respond({status:200,contentType:'application/json',body:JSON.stringify({accepted:true,status:data.action==='opened'?'opened':'invited',...(data.action==='opened'?{url:'https://chat.whatsapp.com/'+ 'A'.repeat(22)}:{})})});
   }
   if(u.hostname==='chat.whatsapp.com') {opened=true;return request.respond({status:204});}
   if(request.isNavigationRequest()&&request.frame()===page.mainFrame()) {
    let body=fs.readFileSync(`${p}/${stage}-${current.name}.html`,'utf8');
    if(fixture)body=body.replace(/(<script data-page="app" type="application\/json">)([\s\S]*?)(<\/script>)/,(_,a,s,b)=>{const d=JSON.parse(s);d.props.communityBubble={city_slug:'cochabamba',city_name:'Cochabamba',name:'Comunidad JAKAWI Cochabamba',status:'not_invited',automatic:true,source:'floating'};if(d.props.community)d.props.community.automatic=false;return a+JSON.stringify(d).replace(/</g,'\\u003c')+b;});
    return request.respond({status:200,contentType:'text/html',body});
   }
   if(u.hostname==='jakawi.com'&&u.pathname.startsWith('/build/')) {
    if(stage==='live')return request.continue();
    const file=`${p}/app-public${u.pathname}`;
    return request.respond({status:200,contentType:file.endsWith('.css')?'text/css':'application/javascript',body:fs.readFileSync(file)});
   }
   return request.respond({status:204});
  }catch(e){result.errors.push(e.message);return request.abort();}
 });
 for(const width of [390,1440]) {
  await page.setViewport({width,height:900});
  for(const route of routes) {
   current=route;fixture=false;
   await page.goto('https://jakawi.com'+route.path,{waitUntil:'networkidle0'});await page.waitForSelector('#app > *');
   const data=await page.evaluate(()=>JSON.parse(document.querySelector('script[data-page]').textContent));
   if(['root','admin-cities'].includes(route.name)){assert.equal(data.props.communityBubble,null);assert.equal(await page.$('.community-bubble'),null);}
   if(['explore','mi-jakawi','partner'].includes(route.name)) {
    assert(data.props.prelaunchMode);assert(await page.$('[data-prelaunch-notice]'));
    assert.equal(await page.$eval('[data-prelaunch-notice]',el=>el.querySelectorAll('p')[1].textContent.trim()),'JAKAWI está en modo DEMO. Las experiencias, beneficios, retos o premios son ilustrativos y no corresponden a items reales.');
    assert(data.props.communityBubble?.source==='floating');
   }
   result.views.push({name:route.name,width,realProps:true,automatic:data.props.communityBubble?.automatic??false,status:'PASS'});
  }
  for(const name of ['explore','partner','mi-jakawi']) {
   current=routes.find(r=>r.name===name);fixture=stage==='candidate';opened=false;
   await page.evaluate(()=>sessionStorage.clear());
   const before=result.requests.length,start=Date.now();
   await page.goto('https://jakawi.com'+current.path,{waitUntil:'domcontentloaded'});
   assert.equal(await page.$('.community-bubble'),null);
   await page.waitForSelector('.community-bubble',{timeout:13000});
   const elapsed=Date.now()-start;assert(elapsed>=2900&&elapsed<=13000);
   await new Promise(r=>setTimeout(r,400));
   const inspect=()=>{const el=document.querySelector('.community-bubble-action'),r=el.getBoundingClientRect(),n=document.querySelector('[data-mobile-bottom-nav]'),s=getComputedStyle(el);return {width:r.width,height:r.height,right:r.right,bottom:r.bottom,navTop:n.getBoundingClientRect().top,navVisible:getComputedStyle(n).display!=='none',overflow:document.documentElement.scrollWidth>innerWidth,bg:s.backgroundColor,floatAnimation:getComputedStyle(document.querySelector('.community-bubble-float')).animationName};};
   const info=await page.evaluate(inspect);assert.equal(info.width,width===390?52:56);assert.equal(info.height,info.width);assert(info.right<=width);assert(!info.overflow);if(info.navVisible)assert(info.bottom<info.navTop);
   assert.equal(await page.$$eval('.community-bubble',els=>els.length),1);
   const timers=await page.evaluate(()=>window.__communityTimers);assert(timers.some(t=>t>=3000&&t<=10000));result.timers.push(...timers);
   await page.focus('.community-bubble-action');assert.equal(await page.$eval('.community-bubble-action',el=>getComputedStyle(el).outlineStyle),'solid');
   await page.screenshot({path:`${p}/${stage}-${name}-${width}.png`,fullPage:true});
   await page.emulateMediaFeatures([{name:'prefers-reduced-motion',value:'reduce'}]);assert.equal((await page.evaluate(inspect)).floatAnimation,'none');await page.emulateMediaFeatures([{name:'prefers-reduced-motion',value:'no-preference'}]);
   assert.equal(result.requests.slice(before).filter(r=>r.action==='presented').length,1);
   if(name==='explore') {
    await page.click('.community-bubble-dismiss');assert.equal(await page.$('.community-bubble'),null);
    await page.reload({waitUntil:'networkidle0'});await new Promise(r=>setTimeout(r,10500));assert.equal(await page.$('.community-bubble'),null);assert.equal(result.requests.length,before+1);
   } else if(name==='partner') {
    await page.click('.community-bubble-action');await new Promise(r=>setTimeout(r,700));assert(opened);assert.equal(result.requests.at(-1).action,'opened');assert.equal(result.requests.at(-1).source,'floating');
   } else {
    await page.evaluate(()=>window.dispatchEvent(new CustomEvent('jakawi:community-interaction',{detail:'cochabamba'})));assert.equal(await page.$('.community-bubble'),null);
   }
   result.views.push({name,width,fixture:fixture?'browser memory only; writes intercepted':'real backend props; writes intercepted',elapsed,info,status:'PASS'});console.log(stage,name,width,'PASS');
  }
 }
 assert.deepEqual(result.errors,[]);result.status='PASS';
} finally {await browser.close();fs.writeFileSync(`${p}/${stage}-browser.json`,JSON.stringify(result,null,2));}
console.log(JSON.stringify({stage,status:result.status,views:result.views.length,errors:result.errors}));
