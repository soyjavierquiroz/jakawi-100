import fs from 'node:fs';
import assert from 'node:assert/strict';
import puppeteer from '/tmp/jakawi-brand-release/browser/node_modules/puppeteer-core/lib/puppeteer/puppeteer-core.js';
const live=process.env.QA_LIVE==='true';
const publicOnly=process.env.QA_PUBLIC_ONLY==='true';
const origin=live?'https://jakawi.com':'http://127.0.0.1:8130';
const art='/home/jakawi.com/artifacts/experiences-v2';
const slug='toctoccafe-descubre-el-cafe-boliviano';
const edit='/admin/experiences/'+slug+'/edit';
const report={source:live?'LIVE · read only':'Production build · isolated jakawi_test replica',checks:[],errors:[]};
if(publicOnly)report.checks=JSON.parse(fs.readFileSync(art+'/candidate-browser.json')).checks.filter(check=>!check.public);
const browser=await puppeteer.launch({executablePath:'/root/.cache/puppeteer/chrome/linux-155.0.8059.39/chrome-linux64/chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
try {
 const context=await browser.createBrowserContext();
 const cookieFile=live?'/tmp/catalog-admin-cookie.json':'/tmp/experiences-v2-qa-cookie.json';
 const {session_id,...raw}=JSON.parse(fs.readFileSync(cookieFile));
 await context.setCookie({...raw,domain:live?'jakawi.com':'127.0.0.1',secure:live});
 let visitorCookie=null;if(live){const {visitor_id,...cookie}=JSON.parse(fs.readFileSync('/tmp/experiences-v2-live-visitor.json'));visitorCookie=cookie;await context.setCookie(cookie);}
 const page=await context.newPage();await page.emulateTimezone('America/La_Paz');await page.setBypassServiceWorker(true);await page.setExtraHTTPHeaders({'Purpose':'prefetch'});
 page.on('pageerror',error=>report.errors.push(error.message));
 if(!publicOnly) {
 for(const width of [375,390,1440]) {
  await page.setViewport({width,height:1000});const response=await page.goto(origin+edit,{waitUntil:'networkidle0'});assert.equal(response.status(),200);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  for(const [name,value] of [['regular_price','75.00'],['member_price','60.00'],['duration_minutes','90']]) assert.equal(await page.$eval(`[name=${name}]`,el=>el.value),value);
  assert.equal(await page.$eval('[aria-label="Partner 1"]',el=>el.selectedOptions[0].textContent),'Toc Toc Café Gourmet');
  assert(await page.$eval('#publicacion',el=>el.textContent.includes('No visible')&&el.textContent.includes('verifica membresía')));
  await page.screenshot({path:`${art}/${live?'live':'after'}-${width}.png`,fullPage:true});
  if(width!==375)for(const id of ['oferta','operacion','publicacion'])await (await page.$('#'+id)).screenshot({path:`${art}/${live?'live':'after'}-${id}-${width}.png`});
  report.checks.push({width,editor:'PASS',prices:'75/60',duration:90,partner:3,overflow:false});
 }
 await page.goto(origin+edit+'?q=Toc%20Toc',{waitUntil:'networkidle0'});
 await page.click('a[href="/admin/experiences?q=Toc%20Toc"]');await page.waitForNetworkIdle();assert(new URL(page.url()).searchParams.get('q')==='Toc Toc');
 report.checks.push({catalogNavigation:'PASS'});
 if(live){for(const path of ['/admin/experiencias/'+slug+'/landings/'+slug+'/preview','/admin/beneficios/dossis43-parada-estrategica/landings/dossis43-parada-estrategica/preview','/admin/beneficios/elrepollo-almuerzo/landings/elrepollo-almuerzo/preview']){const r=await page.goto(origin+path,{waitUntil:'networkidle0'});assert.equal(r.status(),200);report.checks.push({savedPreview:path,status:200});}}
 await page.goto(origin+'/admin/experiences/'+slug+'/preview',{waitUntil:'networkidle0'});assert(await page.$eval('body',el=>el.textContent.includes('PREVIEW ADMINISTRATIVO')));report.checks.push({adminPreview:'PASS'});
 const change=async(name,value)=>{const selector=`[name=${name}]`;await page.click(selector,{clickCount:3});await page.keyboard.down('Control');await page.keyboard.press('A');await page.keyboard.up('Control');if(value)await page.type(selector,value);else await page.keyboard.press('Backspace');};
 const save=async()=>{await Promise.all([page.waitForResponse(r=>r.request().method()==='POST'&&r.url().includes('/admin/experiences')),page.click('button[form="experience-editor"]')]);await page.waitForNetworkIdle({idleTime:350,timeout:30000});};
 if(!live) {
  await page.goto(origin+edit,{waitUntil:'networkidle0'});
  const originalTitle=await page.$eval('[name=title]',el=>el.value);
  await change('title','');await save();assert(await page.$('[name=title][aria-invalid="true"]'));assert.equal(await page.$eval('[name=member_price]',el=>el.value),'60.00');
  await change('title',originalTitle);await change('member_price','80');await save();assert(await page.$('[name=member_price][aria-invalid="true"]'));await change('member_price','60.00');
  await change('duration_minutes','91');
  let dirtyPrompt=false;page.once('dialog',async dialog=>{dirtyPrompt=true;await dialog.dismiss();});await page.click('a[href="/admin/experiences"]');await new Promise(r=>setTimeout(r,250));assert(dirtyPrompt);assert(new URL(page.url()).pathname===edit);
  await change('duration_minutes','90');
  await save();await page.reload({waitUntil:'networkidle0'});assert(await page.$eval('body',el=>el.textContent.includes('Sin cambios pendientes')));assert.equal(await page.$eval('[name=regular_price]',el=>el.value),'75.00');
  report.checks.push({validation:'field errors retain input',dirtyNavigation:'PASS',persistedSave:'PASS'});
  // Explicit QA-only copy correction, with no production database connection.
  await change('terms','Precio sin membresía: Bs 75 por persona.\nPrecio preferencial previsto para miembros: Bs 60; requiere futura activación y verificación.\nPago directo al Partner. No hay sesiones confirmadas. No admite reservas ni pagos ahora.');
  await page.select('[name=catalog_visibility]','upcoming');await save();await page.reload({waitUntil:'networkidle0'});assert.equal(await page.$eval('[name=catalog_visibility]',el=>el.value),'upcoming');
  await page.goto(origin+'/admin/experiencias/'+slug+'/landings',{waitUntil:'networkidle0'});
  await Promise.all([page.waitForResponse(r=>r.request().method()==='POST'&&r.url().endsWith('/publish')),page.evaluate(()=>[...document.querySelectorAll('button')].find(el=>el.textContent.trim()==='PUBLICAR').click())]);await page.waitForNetworkIdle();
  report.checks.push({upcomingAuthorization:'PASS',informationalLandingPublication:'PASS',qaCopyCorrection:'isolated replica only'});
  await page.goto(origin+'/admin/experiences/create',{waitUntil:'networkidle0'});await change('title','Plan de prueba sin configurar enlaces');await save();await page.reload({waitUntil:'networkidle0'});assert(new URL(page.url()).pathname.includes('/plan-de-prueba-sin-configurar-enlaces/edit'));assert.equal(await page.$eval('[name=catalog_visibility]',el=>el.value),'hidden');report.checks.push({creationWithoutSlug:'PASS',defaultPrivacy:'hidden'});
 }
 }
 const guest=await browser.createBrowserContext();if(visitorCookie)await guest.setCookie(visitorCookie);const publicPage=await guest.newPage();publicPage.on('pageerror',error=>report.errors.push(error.message));await publicPage.setBypassServiceWorker(true);await publicPage.setExtraHTTPHeaders({'Purpose':'prefetch'});
 if(!live)for(const width of [375,390,1440]) {
  await publicPage.setViewport({width,height:1000});
  for(const [name,path] of [['explore','/explorar?type=experiences'],['partner','/toctoccafe'],['landing','/l/'+slug]]) {
   const response=await publicPage.goto(origin+path,{waitUntil:'networkidle0'});assert.equal(response.status(),200);assert(await publicPage.$eval('body',(el,name)=>el.textContent.includes(name==='landing'?'El café boliviano':'Descubre el café boliviano'),name));assert.equal(await publicPage.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
   if(name==='landing'){assert(await publicPage.$eval('body',el=>el.textContent.includes('PRÓXIMAMENTE')&&el.textContent.includes('preferencial previsto')));assert.equal(await publicPage.$$eval('a[href*="/reservar"]',els=>els.length),0);}
   else assert(await publicPage.$eval('body',el=>/En preparación|Próximamente/.test(el.textContent)));
   await publicPage.screenshot({path:`${art}/public-${name}-${width}.png`,fullPage:true});report.checks.push({public:name,width,status:200,overflow:false});
  }
 }
 if(live){
  for(const path of ['/','/explorar','/toctoccafe','/experiencias/'+slug,'/experiencias','/beneficios','/retos']){const response=await publicPage.goto(origin+path,{waitUntil:'networkidle0'});assert.equal(response.status(),200);report.checks.push({path,status:200});}
  const response=await publicPage.goto(origin+'/l/'+slug,{waitUntil:'networkidle0'});assert.equal(response.status(),404);report.checks.push({draftLanding:404});
  await publicPage.setViewport({width:375,height:1000});await publicPage.goto(origin+'/dossis43',{waitUntil:'networkidle0'});const gallery=await publicPage.$('[data-partner-gallery]');assert(gallery);await gallery.evaluate(el=>el.scrollIntoView({block:'center'}));await gallery.focus();const before=await gallery.$eval('.partner-gallery-track',el=>getComputedStyle(el).transform);await publicPage.keyboard.press('ArrowRight');await new Promise(r=>setTimeout(r,650));assert.notEqual(await gallery.$eval('.partner-gallery-track',el=>getComputedStyle(el).transform),before);report.checks.push({existingMobileCarousel:'PASS'});
 }
 const r=await publicPage.goto(origin+'/admin/experiences/'+slug+'/preview',{waitUntil:'networkidle0'});assert(new URL(publicPage.url()).pathname==='/login');report.checks.push({guestPreviewProtection:'PASS'});
 if(live){fs.writeFileSync('/tmp/experiences-v2-live-cookies.json',JSON.stringify([...(await context.cookies()),...(await guest.cookies())]),{mode:0o600});}
 assert.deepEqual(report.errors,[]);report.status='PASS';
}catch(error){report.status='FAIL';report.failure=error.stack;process.exitCode=1;}finally{fs.writeFileSync(`${art}/${live?'live':'candidate'}-browser.json`,JSON.stringify(report,null,2));console.log(JSON.stringify(report,null,2));for(const p of await browser.pages())p.on('dialog',d=>d.accept());await browser.close();}
