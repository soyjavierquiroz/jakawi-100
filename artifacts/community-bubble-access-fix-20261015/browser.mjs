import fs from 'node:fs';
import assert from 'node:assert/strict';
import puppeteer from '/tmp/jakawi-brand-release/browser/node_modules/puppeteer-core/lib/puppeteer/puppeteer-core.js';
const p='/home/jakawi.com/backups/community-bubble-access-fix-20261015',stage=process.env.QA_STAGE??'candidate';
const result={stage,status:'FAIL',checks:[],errors:[],communityRequests:[],timers:[],productionWrites:0};
const browser=await puppeteer.launch({executablePath:'/root/.cache/puppeteer/chrome/linux-155.0.8059.39/chrome-linux64/chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
let route='explore',scenario='normal',opened=false;
const wait=ms=>new Promise(r=>setTimeout(r,ms));
try {
 const page=await browser.newPage();await page.setCacheEnabled(false);await page.setBypassServiceWorker(true);
 page.on('pageerror',e=>result.errors.push(e.message));
 await page.evaluateOnNewDocument(()=>{window.__bubbleTimers=[];const original=window.setTimeout;window.setTimeout=(fn,delay,...args)=>{const community=delay>=3000&&delay<=10000;if(community)window.__bubbleTimers.push(delay);return original(fn,community&&location.search.includes('qa-fast=1')?delay/100:delay,...args);};});
 await page.setRequestInterception(true);
 page.on('request',request=>{
  try {
   const u=new URL(request.url());
   if(!['GET','HEAD'].includes(request.method())) {
    if(u.pathname.startsWith('/comunidades/')) {
     const data=JSON.parse(request.postData());result.communityRequests.push(data);
     return request.respond({status:200,contentType:'application/json',body:JSON.stringify({accepted:true,status:scenario==='joined'?'self_reported_joined':'opened',url:'https://chat.whatsapp.com/'+'A'.repeat(22)})});
    }
    return request.respond({status:200,contentType:'application/json',body:'{}'});
   }
   if(u.hostname==='chat.whatsapp.com'){opened=true;return request.respond({status:204});}
   if(request.isNavigationRequest()&&request.frame()===page.mainFrame()) {
    let body=fs.readFileSync(`${p}/${stage==='before'?'before-explore':`${stage}-${route}`}.html`,'utf8');
    body=body.replace(/(<script data-page="app" type="application\/json">)([\s\S]*?)(<\/script>)/,(_,a,s,b)=>{
     const d=JSON.parse(s);
     if(scenario==='joined')d.props.communityBubble.status='self_reported_joined';
     if(scenario==='off')d.props.communityBubble=null;
     if(scenario==='declined')d.props.communityBubble={...d.props.communityBubble,access:false,status:'declined'};
     return a+JSON.stringify(d).replace(/</g,'\\u003c')+b;
    });
    return request.respond({status:200,contentType:'text/html',body});
   }
   if(u.hostname==='jakawi.com'&&u.pathname.startsWith('/build/')) {
    if(stage!=='candidate')return request.continue();
    const file=`${p}/app-public${u.pathname}`;return request.respond({status:200,contentType:file.endsWith('.css')?'text/css':'application/javascript',body:fs.readFileSync(file)});
   }
   return request.respond({status:204});
  }catch(e){result.errors.push(e.message);return request.abort();}
 });
 const goto=async(fast=false)=>{const path=stage==='before'?'/explorar':JSON.parse(fs.readFileSync(`${p}/${stage}-smoke.json`)).find(r=>r.name===route).path;await page.goto('https://jakawi.com'+path+(fast?'?qa-fast=1':''),{waitUntil:'networkidle0'});await page.waitForSelector('#app > *');};
 if(stage==='before') {
  await page.setViewport({width:390,height:900});await goto();await wait(10500);
  const c=await page.evaluate(()=>JSON.parse(document.querySelector('script[data-page]').textContent).props.communityBubble);
  assert.equal(c.status,'opened');assert.equal(c.automatic,false);assert.equal(await page.$('.community-bubble'),null);
  result.checks.push({before:true,status:c.status,automatic:c.automatic,visible:false,waitMs:10500});
 } else for(const width of [390,1440]) {
  await page.setViewport({width,height:900});
  for(const name of ['root','admin','guest']) {
   route=name;scenario='normal';await goto(true);await wait(150);
   assert.equal(await page.$('.community-bubble'),null);const c=await page.evaluate(()=>JSON.parse(document.querySelector('script[data-page]').textContent).props.communityBubble);assert.equal(c,null);
   result.checks.push({name,width,visible:false,status:'PASS'});
  }
  for(const name of ['explore','partner','mi-jakawi']) {
   route=name;scenario='normal';await page.evaluate(()=>sessionStorage.clear());
   const count=result.communityRequests.length,start=Date.now();await goto();
   const c=await page.evaluate(()=>JSON.parse(document.querySelector('script[data-page]').textContent).props.communityBubble);
   assert.equal(c.access,true);assert.equal(c.automatic,false);assert.equal(c.status,'opened');
   await page.waitForSelector('.community-bubble',{timeout:12500});const elapsed=Date.now()-start;assert(elapsed>=2900);
   await wait(400);
   const info=await page.evaluate(()=>{const el=document.querySelector('.community-bubble-action'),r=el.getBoundingClientRect(),nav=document.querySelector('[data-mobile-bottom-nav]'),n=nav.getBoundingClientRect(),hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);return {size:r.width,height:r.height,bottom:r.bottom,right:r.right,navTop:n.top,navVisible:getComputedStyle(nav).display!=='none',overflow:document.documentElement.scrollWidth>innerWidth,hit:el.contains(hit),zIndex:getComputedStyle(document.querySelector('.community-bubble')).zIndex,opacity:getComputedStyle(document.querySelector('.community-bubble')).opacity};});
   assert.equal(info.size,width===390?52:56);assert.equal(info.size,info.height);assert(!info.overflow&&info.hit);assert.equal(info.opacity,'1');assert(info.right<=width);if(info.navVisible)assert(info.bottom<info.navTop);
   assert.equal(await page.$$eval('.community-bubble',els=>els.length),1);assert.equal(result.communityRequests.length,count);
   result.timers.push(...await page.evaluate(()=>window.__bubbleTimers));
   assert.equal(await page.$eval('[data-prelaunch-notice]',el=>el.querySelectorAll('p')[1].textContent.trim()),'JAKAWI está en modo DEMO. Las experiencias, beneficios, retos o premios son ilustrativos y no corresponden a items reales.');
   await page.focus('.community-bubble-action');assert.equal(await page.$eval('.community-bubble-action',el=>getComputedStyle(el).outlineStyle),'solid');
   await page.screenshot({path:`${p}/${stage}-${name}-${width}.png`,fullPage:true});
   await page.emulateMediaFeatures([{name:'prefers-reduced-motion',value:'reduce'}]);assert.equal(await page.$eval('.community-bubble-float',el=>getComputedStyle(el).animationName),'none');await page.emulateMediaFeatures([{name:'prefers-reduced-motion',value:'no-preference'}]);
   if(name==='explore') {
    await page.click('.community-bubble-dismiss');assert.equal(await page.$('.community-bubble'),null);await goto(true);await wait(200);assert.equal(await page.$('.community-bubble'),null);
   }
   result.checks.push({name,width,realAffectedUserProps:true,elapsed,info,status:'PASS'});console.log(stage,name,width,'PASS');
  }
  route='explore';await page.evaluate(()=>sessionStorage.clear());
  for(const state of ['joined','off','declined']) {
   scenario=state;await goto(true);await wait(250);
   if(state==='joined') {
    await page.waitForSelector('.community-bubble');opened=false;await page.click('.community-bubble-action');await wait(500);assert(opened);assert.equal(result.communityRequests.at(-1).action,'opened');assert.equal(result.communityRequests.at(-1).source,'floating');assert(await page.$('.community-bubble'));
    await page.evaluate(()=>window.dispatchEvent(new CustomEvent('jakawi:community-interaction',{detail:{city_slug:'cochabamba',status:'declined'}})));await wait(50);assert.equal(await page.$('.community-bubble'),null);
   } else assert.equal(await page.$('.community-bubble'),null);
   result.checks.push({scenario:state,width,propsFixture:true,status:'PASS'});
  }
 }
 assert(result.communityRequests.every(r=>r.action==='opened'));assert(result.timers.every(t=>t>=3000&&t<=10000));assert.deepEqual(result.errors,[]);result.status='PASS';
}finally{await browser.close();fs.writeFileSync(`${p}/${stage}-browser.json`,JSON.stringify(result,null,2));}
console.log(JSON.stringify({stage,status:result.status,checks:result.checks.length,errors:result.errors}));
