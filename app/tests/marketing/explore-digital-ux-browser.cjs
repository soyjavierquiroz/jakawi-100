const { chromium } = require(process.env.QA_PLAYWRIGHT_MODULE);
const fs = require('node:fs'), assert = require('node:assert/strict');
const origin = process.env.QA_ORIGIN, out = process.env.QA_EVIDENCE;
assert.match(origin, /^http:\/\/10\.245\.96\.[2-9]:8000$/);
const checks = [], errors = [], contrasts = [], resultFile = 'browser';
let page;
const dark = p => p.evaluate(() => document.documentElement.classList.contains('dark'));
async function ready(p) {
 await p.getByRole('button', { name: /^Apariencia:/ }).filter({visible:true}).first().waitFor();
 await p.waitForLoadState('networkidle');
 const icon=await p.getByRole('button',{name:/^Apariencia:/}).filter({visible:true}).first().locator('svg').boundingBox();
 assert.equal(icon.width,20,'Appearance icon width');assert.equal(icon.height,20,'Appearance icon height');
}

const trigger = p => p.getByRole('button', {name:/^Apariencia:/}).filter({visible:true}).first();
const labels = {light:'Claro',dark:'Oscuro',system:'Sistema'};
async function choose(p, mode, account=false) {
 const response = account ? p.waitForResponse(r => r.url()===origin+'/settings/appearance' && r.request().method()==='PATCH') : null;
 await p.getByRole('menu').waitFor({state:'hidden'});
 await trigger(p).click();
 await p.getByRole('menuitemradio', {name:labels[mode],exact:true}).click();
 await p.getByRole('menu').waitFor({state:'hidden'});
 if(response) assert.equal((await response).status(),204);
 await p.waitForFunction(m => document.documentElement.dataset.appearance===m, mode);
}
async function login(p,role) {
 await p.goto(origin+'/login'); await ready(p);
 await p.locator('[name=email]').fill('ux-'+role+'@qa.invalid');
 await p.locator('[name=password]').fill('Isolated-Ux-QA-2026');
 await p.locator('[data-test=login-button]').click();
 await p.waitForURL(u=>!u.pathname.includes('/login')); await ready(p);
}
async function inspect(p,label,width,mode){
 await p.evaluate(()=>window.scrollTo(0,0));
 await p.waitForTimeout(700);
 assert.equal(await dark(p),mode==='dark',label+' resolved theme');
 assert(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),label+' horizontal overflow');
 const colors=await p.evaluate(()=>{
  const canvas=document.createElement('canvas');canvas.width=canvas.height=1;const ctx=canvas.getContext('2d',{willReadFrequently:true});
  const rgb=(color)=>{ctx.clearRect(0,0,1,1);ctx.fillStyle=color;ctx.fillRect(0,0,1,1);return [...ctx.getImageData(0,0,1,1).data];};
  const lum=c=>c.slice(0,3).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4}).reduce((s,v,i)=>s+v*[.2126,.7152,.0722][i],0);
  const ratio=(a,b)=>{const [x,y]=[lum(a),lum(b)].sort((x,y)=>x-y);return (y+.05)/(x+.05)};
  const failures=[];
  for(const el of document.querySelectorAll('p,a,span,h1,h2,h3,button,label,select,input,th,td,summary,small')){
   const rect=el.getBoundingClientRect(),s=getComputedStyle(el);
   if(el.closest('[aria-hidden=true], [inert]')||!rect.width||!rect.height||s.visibility==='hidden'||el.disabled||!([...el.childNodes].some(n=>n.nodeType===3&&n.textContent.trim())||el.matches('input,select')))continue;
   if(el.closest('[data-vsl-player], [data-landing-media]'))continue;
   let bg=[255,255,255,255],layers=[],skip=false;
   for(let n=el;n;n=n.parentElement){const cs=getComputedStyle(n);if(Number(cs.opacity)<1||cs.backgroundImage!=='none'){skip=true;break;}layers.push(rgb(cs.backgroundColor));}
   if(skip)continue;
   for(const c of layers.reverse()){const a=c[3]/255;bg=bg.map((v,i)=>i<3?c[i]*a+v*(1-a):255);}
   const fg=rgb(s.color),a=fg[3]/255,ink=fg.map((v,i)=>i<3?v*a+bg[i]*(1-a):255),contrast=ratio(ink,bg);
   const large=parseFloat(s.fontSize)>=24||(parseFloat(s.fontSize)>=18.66&&Number(s.fontWeight)>=700),required=large?3:4.5;
   if(contrast+.03<required)failures.push({text:(el.innerText||el.value||el.placeholder||'').slice(0,65),tag:el.tagName,classes:el.className,fg:s.color,bg,contrast:+contrast.toFixed(2),required});
  }
  const root=getComputedStyle(document.documentElement);return {background:root.getPropertyValue('--background').trim(),foreground:root.getPropertyValue('--foreground').trim(),failures};
 });
 const prior=contrasts.findIndex(c=>c.label===label&&c.width===width&&c.mode===mode);if(prior>=0)contrasts[prior]={label,width,mode,...colors};else contrasts.push({label,width,mode,...colors});
 await p.screenshot({path:`${out}/${label}-${mode}-${width}.png`,fullPage:false});
 if(!checks.some(c=>c.label===label&&c.width===width&&c.mode===mode))checks.push({label,width,mode,noOverflow:true,theme:true});
 fs.writeFileSync(out+'/'+resultFile+'-progress.json',JSON.stringify({checks,errors,contrasts},null,2));
}

(async()=>{
 const browser=await chromium.launch({executablePath:process.env.QA_CHROMIUM_PATH,args:['--no-sandbox','--disable-dev-shm-usage','--renderer-process-limit=1']});
 async function context(width) {
  const c=await browser.newContext({viewport:{width,height:width===390?844:1000},colorScheme:'light',serviceWorkers:'block'});
  c.setDefaultTimeout(30000);
  await c.route('**/*',r=>new URL(r.request().url()).origin===origin?r.continue():r.abort());
  await c.route('https://media.ux.example.test/test.mp4',r=>r.fulfill({status:200,contentType:'video/webm',body:fs.readFileSync(process.env.QA_VIDEO_FIXTURE)}));
  c.on('page',p=>p.on('pageerror',e=>errors.push(e.message)));
  c.on('response',r=>{if(/\.(js|css)(\?|$)/.test(r.url())&&r.status()>=400)errors.push('Asset '+r.status()+' '+r.url());});
  return c;
 }
 try {
 for(const width of [390,1440]) {
  const c=await context(width); page=await c.newPage();
  await page.goto(origin+'/explorar'); await ready(page);
  assert.equal(await page.locator('[data-mobile-bottom-nav] a').count(),4);
  const original=await page.getByRole('heading',{name:'EXPLORAR',exact:true}).evaluate(e=>({font:getComputedStyle(e).fontFamily,size:getComputedStyle(e).fontSize,weight:getComputedStyle(e).fontWeight,left:e.getBoundingClientRect().left}));
  for(const mode of ['light','dark']){await choose(page,mode);await inspect(page,'explore',width,mode);}
  await page.getByRole('button',{name:'PRODUCTOS DIGITALES',exact:true}).click();
  await page.waitForURL(/type=digital/);await ready(page);
  assert.equal(await page.getByRole('button',{name:'PRODUCTOS DIGITALES',exact:true}).getAttribute('aria-pressed'),'true');
  assert.equal(await page.locator('.explorer-card').count(),18);
  assert.equal(await page.getByLabel('Categorías').count(),0);
  await page.getByText('20 resultados para descubrir',{exact:true}).waitFor();
  assert.deepEqual(await page.getByRole('heading',{name:'EXPLORAR',exact:true}).evaluate(e=>({font:getComputedStyle(e).fontFamily,size:getComputedStyle(e).fontSize,weight:getComputedStyle(e).fontWeight,left:e.getBoundingClientRect().left})),original);
  for(const mode of ['light','dark']){await choose(page,mode);await inspect(page,'digital-filter',width,mode);}
  await page.getByRole('button',{name:'CARGAR MÁS',exact:true}).click();
  await page.waitForFunction(()=>document.querySelectorAll('.explorer-card').length===20);await ready(page);
  assert.equal(new Set(await page.locator('.explorer-card').evaluateAll(es=>es.map(e=>e.href))).size,20);
  assert.equal(await page.getByRole('button',{name:'CARGAR MÁS',exact:true}).count(),0);
  await page.getByLabel('Ordenar oportunidades').selectOption('name');await page.waitForURL(/sort=name/);await ready(page);
  assert.match(await page.locator('.explorer-card').first().innerText(),/Guía creativa QA 01/);
  assert.equal(await page.locator('.explorer-card').count(),18);
  await page.getByLabel('Buscar en Explorar').fill('brújula');
  await page.waitForURL(/q=br/);await ready(page);
  assert.equal(await page.locator('.explorer-card').count(),1);
  assert.match(await page.locator('.explorer-card').innerText(),/QA 07/);
  await page.getByLabel('Buscar en Explorar').fill('ausente');await page.waitForURL(/q=ausente/);await ready(page);
  await page.getByText('0 resultados',{exact:false}).first().waitFor();
  await page.getByRole('button',{name:'LIMPIAR BÚSQUEDA',exact:true}).click();await ready(page);
  await page.getByText('20 resultados para descubrir',{exact:true}).waitFor();
  await page.getByRole('button',{name:'BENEFICIOS',exact:true}).click();await page.waitForURL(/type=benefits/);await ready(page);
  assert(await page.getByLabel('Categorías').count());
  assert.equal(await page.locator('.explorer-card').count(),3);
  assert.equal(await page.getByText('Digital · disponible online',{exact:true}).count(),0);
  await page.goto(origin+'/productos-digitales');await ready(page);
  assert.equal(await page.locator('.explorer-card').count(),18);
  assert.equal(await page.getByRole('button',{name:'PRODUCTOS DIGITALES',exact:true}).getAttribute('aria-pressed'),'true');
  for(const mode of ['light','dark']){await choose(page,mode);await inspect(page,'catalog-direct',width,mode);}
  await page.getByLabel('Ordenar oportunidades').selectOption('name');await page.waitForURL(/sort=name/);await ready(page);
  assert(new URL(page.url()).pathname==='/productos-digitales');
  assert.match(await page.locator('.explorer-card').first().innerText(),/QA 01/);
  await page.getByLabel('Buscar en Explorar').fill('QA 02');await page.waitForURL(/q=QA/);await ready(page);
  assert.equal(await page.locator('.explorer-card').count(),1);
  await c.addCookies([{name:'selected_city',value:'la-paz',url:origin}]);
  await page.goto(origin+'/productos-digitales');await ready(page);
  assert.equal(await page.locator('.explorer-card').count(),18);
  assert.equal(await page.getByText('La Paz',{exact:true}).count(),0);
  await page.goto(origin+'/productos-digitales/guia-ux-qa-1');await ready(page);
  assert.equal(await page.getByRole('button',{name:/Comprar/}).count(),0);
  for(const mode of ['light','dark']){await choose(page,mode);await inspect(page,'digital-detail',width,mode);}
  checks.push({width,digitalFilter:true,directCatalog:true,sameTypographyAndContainer:true,globalCityIndependent:true,searchNameAndContent:true,sort:true,paginationWithoutDuplicates:true,physicalFiltersPreserved:true,prelaunchPurchasesBlocked:true});

  await page.goto(origin+'/login');await ready(page);
  await page.locator('[name=email]').fill('unsaved@qa.invalid');
  for(const mode of ['light','dark','system']){
   await choose(page,mode);assert.equal(await page.locator('[name=email]').inputValue(),'unsaved@qa.invalid');
   assert(await trigger(page).locator('svg.lucide-'+{light:'sun',dark:'moon',system:'monitor'}[mode]).count());
   await trigger(page).focus();await page.keyboard.press('Enter');
   await page.getByRole('menuitemradio',{name:labels[mode],exact:true}).waitFor();
   assert.equal(await page.getByRole('menuitemradio',{name:labels[mode],exact:true}).getAttribute('aria-checked'),'true');
   await inspect(page,'appearance-'+mode,width,mode==='dark'?'dark':'light');
   await page.keyboard.press('Escape');await page.getByRole('menu').waitFor({state:'hidden'});await page.waitForFunction(()=>document.activeElement?.getAttribute('aria-label')?.startsWith('Apariencia:'));assert(await trigger(page).evaluate(e=>e===document.activeElement));
  }
  await trigger(page).focus();await page.keyboard.press('Enter');await page.keyboard.press('Home');await page.keyboard.press('Enter');
  await page.waitForFunction(()=>document.documentElement.dataset.appearance==='light');
  await page.reload();await ready(page);assert.equal(await dark(page),false);
  await choose(page,'system');await page.emulateMedia({colorScheme:'dark'});await page.waitForFunction(()=>document.documentElement.classList.contains('dark'));
  await page.emulateMedia({colorScheme:'light'});await page.waitForFunction(()=>!document.documentElement.classList.contains('dark'));
  const tab=await c.newPage();await tab.goto(origin+'/explorar');await ready(tab);
  await choose(page,'dark');await tab.waitForFunction(()=>document.documentElement.dataset.appearance==='dark');await tab.close();
  assert.equal(await page.locator('select[aria-label="Apariencia"]').count(),0);
  const size=await trigger(page).boundingBox();assert(size.width>=44&&size.height>=44);
  checks.push({width,guestPersistence:true,systemMedia:true,crossTab:true,keyboard:true,escapeReturnsFocus:true,activeOption:true,formPreserved:true,touchTarget44:true});
  await c.close();

  for(const role of ['admin','owner','member']) {
   const account=await context(width);page=await account.newPage();await login(page,role);
   const paths=role==='admin'?[['admin-react','/admin'],['admin-blade','/admin/conciliacion-membresias'],['admin-form','/admin/productos-digitales/guia-ux-qa-1/editar']]:role==='owner'?[['partner','/partner/ux-qa']]:[['mi-jakawi','/mi-jakawi']];
   for(const [label,path] of paths) {
    assert.equal((await page.goto(origin+path)).status(),200);await ready(page);
    for(const mode of ['light','dark','system']) {
     let field;
     if(label==='admin-form'){field=page.locator('main input[name=name], main input[type=text], main input:not([type])').first();await field.fill('Borrador local QA');}
     await choose(page,mode,true);if(field)assert.equal(await field.inputValue(),'Borrador local QA');
     await inspect(page,label+'-'+mode,width,mode==='dark'?'dark':'light');
     await page.reload();await ready(page);assert.equal(await trigger(page).getAttribute('aria-label'),'Apariencia: '+labels[mode]);
    }
    assert.equal(await page.locator('select[aria-label="Apariencia"]').count(),0);
   }
   const sibling=await account.newPage();await sibling.goto(origin+paths[0][1]);await ready(sibling);
   await choose(page,'dark',true);await sibling.waitForFunction(()=>document.documentElement.dataset.appearance==='dark');assert.equal(await trigger(sibling).getAttribute('aria-label'),'Apariencia: Oscuro');
   await choose(page,'light',true);await sibling.waitForFunction(()=>document.documentElement.dataset.appearance==='light');await sibling.close();
   checks.push({width,role,accountPersistence:true,accountCrossTab:true,sharedMenu:true});await account.close();
  }
  // Video is synthetic and intercepted within the isolated browser, never a supplier asset.
  const videoContext=await context(width);page=await videoContext.newPage();await page.goto(origin+'/l/ux-video');await ready(page);
  await page.waitForFunction(()=>document.querySelector('video')?.readyState>=2);
  await page.locator('video').evaluate(v=>v.pause());
  const position=await page.locator('video').evaluate(v=>({time:v.currentTime,node:v.dataset.qa='same'}));
  for(const mode of ['dark','light','system']){await choose(page,mode);assert(await page.locator('video').evaluate(v=>v.paused&&v.dataset.qa==='same'));}
  assert(Math.abs(await page.locator('video').evaluate(v=>v.currentTime)-position.time)<0.1);
  await page.locator('video').evaluate(async v=>{v.loop=true;await v.play();});
  for(const mode of ['dark','light','system']){await choose(page,mode);assert(await page.locator('video').evaluate(v=>!v.paused&&v.dataset.qa==='same'));}
  checks.push({width,videoNodeAndPausedStatePreserved:true,videoPlayingStatePreserved:true});await videoContext.close();
 }
 assert.deepEqual(errors,[]);
 const failures=contrasts.flatMap(c=>c.failures.map(f=>({label:c.label,width:c.width,mode:c.mode,...f})));
 fs.writeFileSync(out+'/browser-contrast.json',JSON.stringify({failures},null,2));
 fs.writeFileSync(out+'/browser.json',JSON.stringify({result:failures.length?'REVIEW':'PASS',isolated:true,checks,errors,contrastFailures:failures.length},null,2));
 assert.equal(failures.length,0,'Contrast failures');console.log('PASS',checks.length,'checks',contrasts.length,'visual states');
 }catch(e){if(page&&!page.isClosed())await page.screenshot({path:out+'/failure.png',fullPage:true}).catch(()=>{});throw e;}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
