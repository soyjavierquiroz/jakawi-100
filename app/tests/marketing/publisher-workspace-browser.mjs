// Requires the guarded isolated PHP router + local asset proxy; see validation.md.
import fs from 'node:fs';
import assert from 'node:assert/strict';
const {default:puppeteer}=await import(process.env.PUBLISHER_BROWSER_MODULE);
const origin=process.env.PUBLISHER_ORIGIN??'http://127.0.0.1:8098';
const out=process.env.PUBLISHER_ARTIFACTS??'artifacts/publisher-workspace-ux-v2';
const payload=JSON.parse(fs.readFileSync(out+'/import.json'));
const expected={name:payload.internal_name,slug:payload.slug,template_key:payload.template,marketing_theme:payload.presentation.theme,hero_layout:payload.presentation.hero_layout,density:payload.presentation.density,eyebrow:payload.hero.eyebrow,headline:payload.hero.headline,headline_emphasis:payload.hero.headline_emphasis,subheadline:payload.hero.supporting_copy,reward_display_override:payload.value.supporting_copy,acquisition_headline:payload.acquisition.headline,acquisition_supporting_copy:payload.acquisition.supporting_copy,final_cta_headline:payload.closing.headline,final_cta_description:payload.closing.supporting_copy,media_mode:payload.media.mode,hero_alt:payload.media.alt};
const browser=await puppeteer.launch({headless:true,executablePath:process.env.PUBLISHER_CHROMIUM,args:['--no-sandbox','--disable-dev-shm-usage']});
const page=await browser.newPage();await page.setViewport({width:1440,height:1000});
const report={checks:[],mutations:[],responses:[],errors:[]};
page.on('pageerror',e=>report.errors.push(e.message));
page.on('request',r=>{if(['POST','PUT'].includes(r.method()))report.mutations.push({method:r.method(),url:r.url(),data:JSON.parse(r.postData())});});
page.on('response',r=>{if(['POST','PUT'].includes(r.request().method()))report.responses.push({method:r.request().method(),status:r.status()});});
async function click(text){const h=await page.evaluateHandle(t=>[...(document.querySelector('[role="dialog"]')??document).querySelectorAll('button')].find(b=>b.textContent.trim()===t),text);assert(h.asElement(),text);await h.asElement().click();await h.dispose();}
const formState=()=>page.evaluate(()=>Object.fromEntries([...document.querySelector('form').querySelectorAll('input,textarea,select')].filter(e=>e.name&&(!['radio','checkbox'].includes(e.type)||e.checked)).map(e=>[e.name,e.type==='radio'?document.querySelector('input[name="media_mode"]:checked').parentElement.textContent.trim()==='Imagen'?'IMAGE':e.value:e.value])));
const persisted=()=>page.evaluate(async()=>await(await fetch('/__qa/persisted',{headers:{'X-Inertia':'true',Accept:'application/json'}})).json());
const text=()=>page.evaluate(()=>document.body.innerText);
async function importJson(json){await click('IMPORTAR JSON');await page.waitForSelector('[role="dialog"] textarea');await page.type('[role="dialog"] textarea',JSON.stringify(json));await click('VALIDAR Y APLICAR');await page.waitForFunction(()=>!document.querySelector('[role="dialog"]'));}
async function previewText(){const frame=await(await page.$('iframe')).contentFrame();return frame.evaluate(()=>document.body.innerText);}
try {
 await page.goto(origin+'/admin/experiencias/publisher-arcilla-qa/landings',{waitUntil:'networkidle2'});
 await click('Editar');await page.waitForSelector('[name="headline"]');
 const before=await persisted();
 const geometry=()=>page.evaluate(()=>{ const rect=s=>{const r=document.querySelector(s).getBoundingClientRect();return {x:r.x,y:r.y,width:r.width,height:r.height}};return {editor:rect('[data-workspace-editor]'),preview:rect('[data-workspace-preview]'),toolbar:rect('[data-workspace-toolbar]'),overflow:document.documentElement.scrollWidth>innerWidth};});
 const initialGeometry=await geometry();assert(initialGeometry.preview.x>initialGeometry.editor.x+initialGeometry.editor.width);assert(Math.abs(initialGeometry.editor.width/(initialGeometry.editor.width+initialGeometry.preview.width)-.45)<.02);assert.equal(initialGeometry.overflow,false);
 assert.equal(await page.$eval('[data-workspace-preview] button[aria-pressed="true"]',e=>e.textContent),'390');
 assert.equal(await page.$eval('iframe',e=>e.offsetWidth),390);
 assert.match(await page.$eval('[data-workspace-toolbar]',e=>e.innerText),/DRAFT.*Audience: GUESTS/s);
 assert.match(await text(),/CREAR CON IA/);for(const action of ['COPIAR BRIEF PARA IA','IMPORTAR JSON','EXPORTAR JSON'])assert((await text()).includes(action));
 assert.equal(await page.$eval('[data-product-truth]',e=>e.open),false);
 const facts=await page.$eval('[data-product-truth] dl',e=>e.textContent);assert(facts.trim().length>0);
 await page.click('[data-product-truth] summary');assert.equal(await page.$eval('[data-product-truth]',e=>e.open),true);assert.equal(await page.$eval('[data-product-truth] dl',e=>e.textContent),facts);await page.click('[data-product-truth] summary');
 await page.$eval('figure',e=>e.scrollIntoView({block:'center'}));await new Promise(r=>setTimeout(r,100));const sticky=await geometry();assert(sticky.preview.y>=sticky.toolbar.height);assert(sticky.preview.y<1000);assert.equal(await page.$eval('figure img',e=>e.complete&&e.naturalWidth>0),true);
 await page.$eval('#landing-workspace',e=>e.scrollIntoView({block:'start'}));
 report.checks.push('Desktop 45/55 split; selected record; sticky toolbar and Preview; default actual 390 canvas; AI workflow and actions; collapsed product facts preserved; prominent image; zero horizontal overflow');await page.waitForSelector('iframe');await page.waitForNetworkIdle();
 assert.match(await previewText(),/PREVIOUS SAVED COPY/);
 await importJson(payload);
 const imported=await formState();assert.equal(imported.default_scope,before.publish_scope);assert.equal(imported.campaign_key,String(before.campaign_key??''));assert.equal(imported.hero_path,String(before.hero_path??''));for(const [key,value] of Object.entries(expected))if(key!=='template_key')assert.equal(imported[key],value,key);
 assert.match(await text(),/✓ JSON APLICADO/);await click('EXPORTAR JSON');await page.waitForSelector('[role="dialog"] textarea');const exported=JSON.parse(await page.$eval('[role="dialog"] textarea',e=>e.value));assert.equal(exported.template,payload.template);await click('CERRAR');await page.waitForFunction(()=>!document.querySelector('[role="dialog"]'));
 assert.match(await text(),/● CAMBIOS SIN GUARDAR/);assert.match(await page.$eval('[data-workspace-toolbar]',e=>e.innerText),/● CAMBIOS SIN GUARDAR/); assert.match(await page.$eval('[data-workspace-preview]',e=>e.innerText),/● CAMBIOS SIN GUARDAR/);
 assert.deepEqual(await persisted(),before);assert.equal(report.mutations.length,0);assert(!(await previewText()).includes(expected.headline));
 report.checks.push('All 17 JSON mappings applied; dirty banner; import causes zero persistence or requests; saved-only preview clearly labeled');
 await page.$eval('[data-workspace-toolbar]',e=>[...e.querySelectorAll('button')].find(b=>b.textContent==='PUBLICAR').click());assert.match(await text(),/GUARDA TUS CAMBIOS ANTES DE PUBLICAR/);assert.equal(report.mutations.length,0);
 report.checks.push('Unsaved publication blocked without request or auto-publish');
 await click('GUARDAR Y ACTUALIZAR PREVIEW');await page.waitForFunction(()=>document.body.innerText.includes('✓ LANDING GUARDADA'));await page.waitForNetworkIdle();
 assert(!(await text()).includes('● CAMBIOS SIN GUARDAR'));assert.match(await text(),/✓ GUARDADO/);
 const db=await persisted();assert.equal(db.publish_scope,before.publish_scope);assert.equal(db.campaign_key,before.campaign_key);assert.equal(db.hero_path,before.hero_path);for(const [key,value] of Object.entries(expected))assert.equal(db[key],value,'DB '+key);
 const after=await formState();for(const [key,value] of Object.entries(expected))if(key!=='template_key')assert.equal(after[key],value,'form '+key);
 assert((await previewText()).includes(expected.headline));assert((await previewText()).includes(expected.final_cta_headline));
 assert.equal(await page.$eval('figure img',e=>e.complete&&e.naturalWidth>0),true);
 report.checks.push('Server-confirmed save keeps editor and all imported values; 17 DB fields exact; dirty cleared; iframe refreshed with editorial headline/closing; current image loaded');
 assert.match(await page.$eval('[data-workspace-preview]',e=>e.innerText),/✓ PREVIEW ACTUALIZADO/);
 await page.$eval('#landing-workspace',e=>e.scrollIntoView({block:'start'}));
 await page.screenshot({path:out+'/saved-desktop.png'});
 const requestsBeforeViewportChecks=report.mutations.length; await click('Desktop');await new Promise(r=>setTimeout(r,100));assert.equal((await geometry()).overflow,false);assert.equal(await page.$eval('iframe',e=>e.offsetWidth),1440);await click('430');assert.equal(await page.$eval('iframe',e=>e.offsetWidth),430);await click('390');
 await page.setViewport({width:390,height:844});await new Promise(r=>setTimeout(r,200));const mobile=await geometry();assert(mobile.preview.y>mobile.editor.y+mobile.editor.height-1);assert.equal(mobile.overflow,false);
 await page.$eval('[data-workspace-preview]',e=>e.scrollIntoView({block:'end'}));await page.screenshot({path:out+'/mobile-preview.png'});await click('Desktop');await new Promise(r=>setTimeout(r,100));assert.equal((await geometry()).overflow,false);await click('390');
 await page.$eval('#landing-workspace',e=>e.scrollIntoView({block:'start'}));await page.screenshot({path:out+'/mobile-editor.png'});
 assert.equal(report.mutations.length,requestsBeforeViewportChecks,'Viewport controls never save'); await page.setViewport({width:1440,height:1000});report.checks.push('Saved Preview confirmation; 430/Desktop fit without page overflow; 390 Admin stacks editor above Preview with zero overflow');
 // Same slug save must reload an already open iframe, too.
 await importJson({...payload,hero:{...payload.hero,headline:'MANOS EN LA ARCILLA. CREA ALGO PROPIO.',headline_emphasis:'CREA ALGO PROPIO.'}});
 await click('GUARDAR Y PREVISUALIZAR');await page.waitForFunction(()=>document.body.innerText.includes('✓ LANDING GUARDADA'));await page.waitForNetworkIdle();
 assert((await previewText()).includes('MANOS EN LA ARCILLA. CREA ALGO PROPIO.'));
 report.checks.push('Same-slug successful save refreshes open iframe');
 await page.reload({waitUntil:'networkidle2'});await click('Editar');await page.waitForSelector('[name="headline"]');
 const reloaded=await formState();for(const [key,value] of Object.entries(expected))if(!['template_key','headline','headline_emphasis'].includes(key))assert.equal(reloaded[key],value,'reload '+key);assert.equal(reloaded.headline_emphasis,'CREA ALGO PROPIO.');assert.equal(reloaded.name,expected.name);assert.equal(reloaded.slug,expected.slug);assert.equal(reloaded.headline,'MANOS EN LA ARCILLA. CREA ALGO PROPIO.');assert.equal(reloaded.acquisition_headline,expected.acquisition_headline);assert.equal(reloaded.final_cta_headline,expected.final_cta_headline);
 report.checks.push('Reload retains name, slug, editorial, acquisition and closing');
 await page.waitForNetworkIdle();
 const savedPreview=await page.$eval('iframe',e=>e.src), beforeFailure=await persisted();
 await importJson({...payload,slug:'arcilla-y-manos-demo-collision'});const failedForm=await formState();
 await click('GUARDAR Y PREVISUALIZAR');await page.waitForSelector('#landing-save-errors');await page.waitForNetworkIdle();
 assert.match(await text(),/NO SE PUDO GUARDAR/);assert.match(await text(),/El slug "arcilla-y-manos-demo-collision" ya está en uso/);
 assert.deepEqual(await formState(),failedForm);assert.deepEqual(await persisted(),beforeFailure);assert.equal(await page.$eval('iframe',e=>e.src),savedPreview);
 assert.equal(await page.$eval('[name="slug"]',e=>e.getAttribute('aria-invalid')),'true');assert.equal(await page.evaluate(()=>document.activeElement.name),'slug');
 assert.match(await text(),/● CAMBIOS SIN GUARDAR/);
 report.checks.push('Slug collision has explicit summary and invalid field; first-error focus; entire imported form intact; DB and Preview unchanged');
 await page.screenshot({path:out+'/failed-save-desktop.png',fullPage:true});
 // A real server length error uses a human label; neutral guidance is not an error.
 await page.click('[name="reward_display_override"]',{clickCount:3});await page.keyboard.press('Backspace');await page.type('[name="reward_display_override"]','a'.repeat(181));
 const rejected=await formState();await click('GUARDAR Y PREVISUALIZAR');await page.waitForSelector('#landing-save-errors');await page.waitForNetworkIdle();
 assert.deepEqual(await formState(),rejected);assert.match(await page.$eval('#landing-save-errors',e=>e.textContent),/Copy complementario del valor/);
 assert(!(await page.$eval('#landing-save-errors',e=>e.textContent)).includes('reward_display_override'));
 assert.equal(await page.$eval('[name="reward_display_override"]',e=>e.getAttribute('aria-invalid')),'true');
 const help=await page.$eval('[data-editorial-help]',e=>({role:e.getAttribute('role'),color:getComputedStyle(e).color}));const error=await page.$eval('#landing-save-errors',e=>getComputedStyle(e).color);assert.equal(help.role,null);assert.notEqual(help.color,error);
 report.checks.push('Real length error surfaced with human value-copy label; values preserved; neutral help differs semantically and visually from red error');
 // Create path must become an editable persisted record without prompting again.
 await page.$eval('[data-workspace-toolbar] details',e=>e.open=true);await click('CANCELAR');await click('CREAR LANDING');await importJson({...payload,internal_name:'Arcilla nueva — demo',slug:'arcilla-new-qa'});
 await click('GUARDAR Y PREVISUALIZAR');await page.waitForFunction(()=>document.body.innerText.includes('✓ LANDING GUARDADA'));await page.waitForNetworkIdle();
 assert.equal((await formState()).name,'Arcilla nueva — demo');assert.equal((await formState()).slug,'arcilla-new-qa');assert((await previewText()).includes(expected.headline));
 report.checks.push('New DRAFT POST also keeps name/slug, stays editable and opens current saved Preview');
 await page.$eval('[data-workspace-toolbar] details',e=>e.open=true);await click('CANCELAR');
 await click('DUPLICAR');await page.waitForSelector('[name="duplicate_name"]');await click('GUARDAR COPIA');await page.waitForFunction(()=>!document.querySelector('[name="duplicate_name"]'));await page.waitForNetworkIdle();
 const copy=await page.evaluate(()=>[...document.querySelectorAll('article')].find(e=>e.innerText.includes('— copia'))?.innerText);assert(copy?.includes('DRAFT'));
 await page.evaluate(()=>[...document.querySelectorAll('article')].find(e=>e.innerText.includes('— copia')).querySelectorAll('button').forEach(b=>{if(b.textContent.trim()==='ARCHIVAR')b.click()}));await page.waitForNetworkIdle();assert(await page.evaluate(()=>[...document.querySelectorAll('article')].find(e=>e.innerText.includes('— copia'))?.innerText.includes('ARCHIVED')));
 report.checks.push('Existing duplicate creates DRAFT; archive updates lifecycle; audience/campaign/media preserved through editorial saves');
 assert.deepEqual(report.errors,[]);assert(report.responses.every(r=>[302,303].includes(r.status)));assert(report.mutations.every(r=>!r.url.endsWith('/publish')));
 report.result='PASS';
} catch(e){await page.screenshot({path:out+'/failure.png'});report.result='FAIL';report.failure=e.stack;process.exitCode=1;}finally{fs.writeFileSync(out+'/browser-validation.json',JSON.stringify(report,null,2)+'\n');console.log(JSON.stringify(report,null,2));await browser.close();}
