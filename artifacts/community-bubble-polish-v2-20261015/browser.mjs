import fs from 'node:fs';
import assert from 'node:assert/strict';
import puppeteer from '/tmp/jakawi-brand-release/browser/node_modules/puppeteer-core/lib/puppeteer/puppeteer-core.js';
const p='/home/jakawi.com/backups/community-bubble-polish-v2-20261015',stage=process.env.QA_STAGE??'candidate';
const result={stage,status:'FAIL',checks:[],errors:[],communityRequests:0,productionWrites:0};
const browser=await puppeteer.launch({executablePath:'/root/.cache/puppeteer/chrome/linux-155.0.8059.39/chrome-linux64/chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
let route='explore';
const wait=ms=>new Promise(r=>setTimeout(r,ms));
try {
 const page=await browser.newPage();await page.setCacheEnabled(false);await page.setBypassServiceWorker(true);
 page.on('pageerror',e=>result.errors.push(e.message));
 await page.evaluateOnNewDocument(()=>{const original=window.setTimeout;window.setTimeout=(fn,delay,...args)=>original(fn,location.search.includes('qa-fast=1')&&delay>=3000&&delay<=10000?delay/100:delay,...args);});
 await page.setRequestInterception(true);
 page.on('request',request=>{
  try {
   const u=new URL(request.url());
   if(!['GET','HEAD'].includes(request.method())) {if(u.pathname.startsWith('/comunidades/'))result.communityRequests++;return request.respond({status:200,contentType:'application/json',body:'{}'});}
   if(request.isNavigationRequest()&&request.frame()===page.mainFrame())return request.respond({status:200,contentType:'text/html',body:fs.readFileSync(`${p}/${stage}-${route}.html`)});
   if(u.hostname==='jakawi.com'&&u.pathname.startsWith('/build/')) {
    if(stage==='live')return request.continue();
    const file=`${p}/app-public${u.pathname}`;return request.respond({status:200,contentType:file.endsWith('.css')?'text/css':'application/javascript',body:fs.readFileSync(file)});
   }
   return request.respond({status:204});
  }catch(e){result.errors.push(e.message);return request.abort();}
 });
 const goto=async(fast=false)=>{const path=route==='root'?'/':route==='admin'?'/admin/ciudades':'/explorar';await page.goto('https://jakawi.com'+path+(fast?'?qa-fast=1':''),{waitUntil:'networkidle0'});await page.waitForSelector('#app > *');};
 for(const width of [390,1440]) {
  await page.setViewport({width,height:900});
  for(const name of ['root','admin']) {route=name;await goto(true);await wait(150);assert.equal(await page.$('.community-bubble'),null);result.checks.push({route,width,hidden:true});}
  route='explore';await page.evaluate(()=>sessionStorage.clear());const start=Date.now();await goto();
  await page.waitForSelector('.community-bubble',{timeout:12500});assert(Date.now()-start>=2900);await wait(450);
  for(const dark of [false,true]) {
   await page.evaluate(dark=>document.documentElement.classList.toggle('dark',dark),dark);
   const info=await page.evaluate(()=>{
    const dismiss=document.querySelector('.community-bubble-dismiss'),d=dismiss.getBoundingClientRect(),s=getComputedStyle(dismiss),icon=dismiss.querySelector('svg').getBoundingClientRect(),a=document.querySelector('.community-bubble-action').getBoundingClientRect(),nav=document.querySelector('[data-mobile-bottom-nav]'),float=getComputedStyle(document.querySelector('.community-bubble-float'));
    return {dismiss:{width:d.width,height:d.height,icon:icon.width,shadow:s.boxShadow,radius:s.borderRadius,background:s.backgroundColor},action:a.width,right:Math.max(a.right,d.right),navVisible:getComputedStyle(nav).display!=='none',navTop:nav.getBoundingClientRect().top,bottom:a.bottom,overflow:document.documentElement.scrollWidth>innerWidth,duration:float.animationDuration,easing:float.animationTimingFunction};
   });
   assert.equal(info.dismiss.width,24);assert.equal(info.dismiss.height,24);assert.equal(info.dismiss.icon,12);assert.equal(info.dismiss.radius,'50%');assert.equal(info.dismiss.shadow,'none');assert.equal(info.action,width===390?52:56);assert(info.right<=width);assert(!info.overflow);if(info.navVisible)assert(info.bottom<info.navTop);assert.equal(info.duration,'3.2s');assert.equal(info.easing,'ease-in-out');
   await page.focus('.community-bubble-dismiss');assert.equal(await page.$eval('.community-bubble-dismiss',el=>getComputedStyle(el).outlineStyle),'solid');
   await page.hover('.community-bubble-dismiss');await wait(180);const hover=await page.$eval('.community-bubble-dismiss',el=>({background:getComputedStyle(el).backgroundColor,color:getComputedStyle(el).color}));assert.notEqual(hover.background,info.dismiss.background);await page.mouse.move(0,0);await page.$eval('.community-bubble-dismiss',el=>el.blur());await wait(180);
   await page.screenshot({path:`${p}/${stage}-${width}-${dark?'dark':'light'}.png`});
   result.checks.push({route,width,dark,info,hover,status:'PASS'});
  }
  const samples=[];
  for(let i=0;i<23;i++){samples.push(await page.$eval('.community-bubble-float',el=>new DOMMatrixReadOnly(getComputedStyle(el).transform).m42));await wait(150);}
  const range=Math.max(...samples)-Math.min(...samples);assert(range>3.5&&range<=4.05);result.checks.push({width,floatRange:range,samples,status:'PASS'});
  await page.emulateMediaFeatures([{name:'prefers-reduced-motion',value:'reduce'}]);const reduced=await page.evaluate(()=>({float:getComputedStyle(document.querySelector('.community-bubble-float')).animationName,dismissTransition:getComputedStyle(document.querySelector('.community-bubble-dismiss')).transitionDuration,appearance:getComputedStyle(document.querySelector('.community-bubble')).animationName}));assert.equal(reduced.float,'none');assert.equal(reduced.dismissTransition,'0s');assert.equal(reduced.appearance,'community-bubble-fade');await page.emulateMediaFeatures([{name:'prefers-reduced-motion',value:'no-preference'}]);
  assert.equal(await page.$eval('[data-prelaunch-notice]',el=>el.querySelectorAll('p')[1].textContent.trim()),'JAKAWI está en modo DEMO. Las experiencias, beneficios, retos o premios son ilustrativos y no corresponden a items reales.');
  await page.click('.community-bubble-dismiss');assert.equal(await page.$('.community-bubble'),null);await goto(true);await wait(200);assert.equal(await page.$('.community-bubble'),null);result.checks.push({width,reduced,dismissSession:'PASS'});console.log(stage,width,'PASS');
 }
 assert.equal(result.communityRequests,0);assert.deepEqual(result.errors,[]);result.status='PASS';
}finally{await browser.close();fs.writeFileSync(`${p}/${stage}-browser.json`,JSON.stringify(result,null,2));}
console.log(JSON.stringify({stage,status:result.status,checks:result.checks.length,errors:result.errors}));
