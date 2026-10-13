// Real isolated Laravel Admin via publisher-workflow-router.php + publisher-workflow-proxy.mjs.
import fs from 'node:fs';
import assert from 'node:assert/strict';
const {default:puppeteer}=await import(process.env.PUBLISHER_BROWSER_MODULE);
const out=process.env.EDITORIAL_ARTIFACTS??'artifacts/editorial-validation-fix';
const json=JSON.parse(fs.readFileSync(out+'/arcilla.json'));
const browser=await puppeteer.launch({headless:true,executablePath:process.env.PUBLISHER_CHROMIUM,args:['--no-sandbox','--disable-dev-shm-usage']});
const page=await browser.newPage();await page.setViewport({width:1440,height:1000});
const report={scope:'Final built assets + actual authenticated isolated Laravel HTTP save/validation/Preview + jakawi_test DB. Exact three supplied human-review strings. One successful save and one safe slug collision. No production requests or record changes.',checks:[],requests:[],responses:[],errors:[]};
page.on('pageerror',e=>report.errors.push(e.message));page.on('request',r=>{if(['PUT','POST'].includes(r.method()))report.requests.push({method:r.method(),url:r.url(),data:JSON.parse(r.postData())});});page.on('response',r=>{if(['PUT','POST'].includes(r.request().method()))report.responses.push({status:r.status(),method:r.request().method()});});
async function click(t){const h=await page.evaluateHandle(t=>[...(document.querySelector('[role="dialog"]')??document).querySelectorAll('button')].find(b=>b.textContent.trim()===t),t);assert(h.asElement(),t);await h.asElement().click();}
const state=()=>page.evaluate(()=>Object.fromEntries([...document.querySelector('form').querySelectorAll('input,textarea,select')].filter(e=>e.name&&(!['radio','checkbox'].includes(e.type)||e.checked)).map(e=>[e.name,e.value])));
const db=()=>page.evaluate(async()=>await(await fetch('/__qa/persisted',{headers:{Accept:'application/json','X-Inertia':'true'}})).json());
const text=()=>page.evaluate(()=>document.body.innerText);
async function apply(data){await click('IMPORTAR JSON');await page.waitForSelector('[role="dialog"] textarea');await page.type('[role="dialog"] textarea',JSON.stringify(data));await click('VALIDAR Y APLICAR');await page.waitForFunction(()=>!document.querySelector('[role="dialog"]'));}
try{
 await page.goto((process.env.PUBLISHER_ORIGIN??'http://127.0.0.1:8098')+'/admin/experiencias/publisher-arcilla-qa/landings',{waitUntil:'networkidle2'});await click('Editar');await page.waitForSelector('[name="headline"]');
 const initial=await db();await click('PREVIEW GUARDADO');await page.waitForSelector('iframe');await page.waitForNetworkIdle();
 await apply(json);const imported=await state();assert((await text()).includes('● CAMBIOS SIN GUARDAR'));assert((await text()).includes('✓ JSON APLICADO'));assert.deepEqual(await db(),initial);assert.equal(report.requests.length,0);
 assert.equal(imported.subheadline,json.hero.supporting_copy);assert.equal(imported.reward_display_override,json.value.supporting_copy);assert.equal(imported.final_cta_description,json.closing.supporting_copy);
 report.checks.push('Exact Arcilla prose imports; dirty indicator; import does not persist');
 await click('GUARDAR Y PREVISUALIZAR');await page.waitForFunction(()=>document.body.innerText.includes('✓ LANDING GUARDADA'));await page.waitForNetworkIdle();
 assert.equal(await page.$('#landing-save-errors'),null);assert(!(await text()).includes('Los importes, cantidades y condiciones de acceso'));assert(!(await text()).includes('● CAMBIOS SIN GUARDAR'));assert.deepEqual(await state(),imported);
 const saved=await db();assert.equal(saved.subheadline,json.hero.supporting_copy);assert.equal(saved.reward_display_override,json.value.supporting_copy);assert.equal(saved.final_cta_description,json.closing.supporting_copy);
 const previewFrame=await(await page.$('iframe')).contentFrame();const previewText=await previewFrame.evaluate(()=>document.body.innerText);
 for(const copy of [json.hero.headline,json.hero.supporting_copy,json.value.supporting_copy,json.closing.supporting_copy])assert(previewText.includes(copy),copy);
 report.checks.push('Save succeeds with all three formerly rejected fields; success confirmed; summary absent; form and DB exact; automatically refreshed saved Preview contains headline/description/value/closing');
 await page.screenshot({path:out+'/saved-desktop.png',fullPage:true});
 await apply({...json,slug:'arcilla-y-manos-demo-collision'});const rejected=await state();const previewSource=await page.$eval('iframe',e=>e.src);
 await click('GUARDAR Y PREVISUALIZAR');await page.waitForSelector('#landing-save-errors');await page.waitForNetworkIdle();
 const summary=await page.$eval('#landing-save-errors',e=>e.textContent);assert(summary.includes('NO SE PUDO GUARDAR'));assert(summary.includes('Slug único'));assert(summary.includes('El slug "arcilla-y-manos-demo-collision" ya está en uso.'));assert(!summary.includes('reward_display_override'));assert.deepEqual(await state(),rejected);assert.deepEqual(await db(),saved);assert.equal(await page.$eval('iframe',e=>e.src),previewSource);
 const help=await page.$eval('[data-editorial-help]',e=>({role:e.getAttribute('role'),color:getComputedStyle(e).color,text:e.textContent}));assert.equal(help.role,null);assert(help.text.includes('Los datos comerciales'));assert.notEqual(help.color,await page.$eval('#landing-save-errors',e=>getComputedStyle(e).color));assert.equal(await page.evaluate(()=>document.activeElement.name),'slug');
 report.checks.push('One real slug collision: human label + specific red error, first-field focus, imported prose and Preview preserved; shared neutral help differs from validation error');
 await page.screenshot({path:out+'/failed-save-desktop.png',fullPage:true});
 assert.deepEqual(report.errors,[]);assert.equal(report.requests.length,2);assert(report.requests.every(r=>r.method==='PUT'));assert(report.responses.every(r=>r.status===303));report.result='PASS';
}catch(e){report.result='FAIL';report.failure=e.stack;process.exitCode=1;}finally{fs.writeFileSync(out+'/browser-validation.json',JSON.stringify(report,null,2)+'\n');console.log(JSON.stringify(report,null,2));await browser.close();}
