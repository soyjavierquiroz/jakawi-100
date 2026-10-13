// Run from repo root. Required env: AI_BRIEF_FIXTURE_DIR, AI_BRIEF_BROWSER_MODULE, AI_BRIEF_CHROMIUM, AI_BRIEF_ARTIFACTS.
import fs from 'node:fs';
import http from 'node:http';
import assert from 'node:assert/strict';
const {default: puppeteer} = await import(process.env.AI_BRIEF_BROWSER_MODULE);
const root=process.cwd(), dir=process.env.AI_BRIEF_FIXTURE_DIR, out=process.env.AI_BRIEF_ARTIFACTS;
fs.mkdirSync(out,{recursive:true});
const manifest=JSON.parse(fs.readFileSync(root+'/app/public/build/manifest.json'));
const inertia=JSON.parse(fs.readFileSync(dir+'/page.json'));
assert.equal(inertia.component,'admin/landing-presentations/index');
assert.equal(inertia.props.presentations.at(-1).status,'DRAFT');
const escape=s=>s.replaceAll('&','&amp;').replaceAll('"','&quot;').replaceAll('<','&lt;');
const html=`<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/build/${manifest['resources/css/app.css'].file}"><script type="module" src="/build/${manifest['resources/js/app.tsx'].file}"></script></head><body><div id="app"></div><script data-page="app" type="application/json">${JSON.stringify(inertia).replaceAll('<', '\\u003c')}</script></body></html>`;
const server=http.createServer((req,res)=>{
 if(req.url.startsWith('/build/')) {const path=root+'/app/public'+req.url.split('?')[0]; if(!fs.existsSync(path)){res.writeHead(404);res.end();return;} res.setHeader('content-type',path.endsWith('.js')?'text/javascript':path.endsWith('.css')?'text/css':'application/octet-stream');res.end(fs.readFileSync(path));return;}
 if(req.url.startsWith('/admin/')) {res.setHeader('content-type','text/html; charset=utf-8');res.end(html);return;}
 res.writeHead(404);res.end();
});
await new Promise(r=>server.listen(8098,'127.0.0.1',r));
const browser=await puppeteer.launch({headless:true,executablePath:process.env.AI_BRIEF_CHROMIUM,args:['--no-sandbox','--disable-dev-shm-usage']});
const page=await browser.newPage(); await page.setViewport({width:1440,height:1000});
const report={scope:'Final compiled frontend and isolated authenticated Laravel Admin props from current peinado DEMO public snapshot. Fixtures rolled back. No deploy. No external AI.',checks:[],mutationRequests:[],requestsDuringCopy:[],errors:[]};
page.on('pageerror',e=>report.errors.push(e.message));
await page.setRequestInterception(true);
let copying=false;
page.on('request',r=>{if(copying)report.requestsDuringCopy.push({method:r.method(),url:r.url()});if(r.method()!=='GET'){report.mutationRequests.push({method:r.method(),url:r.url()});r.abort();}else if(new URL(r.url()).hostname!=='127.0.0.1')r.abort();else r.continue();});
async function click(text){if(['IMPORTAR JSON','EXPORTAR JSON'].includes(text))await page.waitForFunction(()=>!document.querySelector('[role="dialog"]'));const handle=await page.evaluateHandle(t=>{const b=[...(document.querySelector('[role="dialog"]') ?? document).querySelectorAll('button')].find(e=>e.textContent.trim()===t);if(!b)throw Error('Button missing: '+t);return b;},text);await handle.asElement().click();await handle.dispose();}

const formState = () => page.evaluate(() => [...document.querySelector('form').querySelectorAll('input,select,textarea')].map(e => [e.name,e.value,e.checked]));
try {
 await browser.defaultBrowserContext().overridePermissions('http://127.0.0.1:8098',['clipboard-read','clipboard-sanitized-write']);
 await page.goto('http://127.0.0.1:8098'+inertia.props.baseUrl,{waitUntil:'networkidle2'});
 await click('Editar'); await page.waitForSelector('input[name="headline"]');
 const baseline=await formState(); const truth=await page.$eval('dl',e=>e.textContent);
 const buttons=await page.evaluate(()=>[...document.querySelectorAll('form button')].map(b=>b.textContent.trim()));
 assert(buttons.indexOf('COPIAR BRIEF PARA IA')<buttons.indexOf('IMPORTAR JSON'));
 assert(buttons.indexOf('IMPORTAR JSON')<buttons.indexOf('EXPORTAR JSON'));
 copying=true; await page.bringToFront(); await click('COPIAR BRIEF PARA IA');
 await page.waitForFunction(()=>document.querySelector('[role="status"]')?.textContent==='✓ BRIEF COPIADO');
 const copied=await page.evaluate(()=>navigator.clipboard.readText()); const brief=JSON.parse(copied);
 assert.deepEqual(brief,inertia.props.subject.aiBrief); assert.equal(copied,JSON.stringify(brief,null,2));
 assert.equal(brief.brief_version,'jakawi-product-brief-v1'); assert.equal(brief.domain,'Benefit');
 assert.equal(brief.title,'20% en peinado de evento — DEMO'); assert.equal(brief.partner,'Luna Lateral — DEMO');
 assert.equal(brief.commercial_truth.benefit_type,'percentage'); assert.match(brief.commercial_truth.value.description,/20%/);
 assert.equal(brief.commercial_truth.membership_required,true); assert.equal(brief.demo,true);
 assert(brief.commercial_truth.validity.starts_at); assert(brief.commercial_truth.validity.ends_at);
 assert.equal(brief.commercial_truth.available,false); assert(!brief.commercial_truth.locations);
 assert(brief.commercial_truth.requirements.length); assert(brief.commercial_truth.conditions.length);
 const walk=data=>{for(const [key,value] of Object.entries(data)){assert(!/(^id$|_id$|uuid|secret|url|path|email|phone|notes)/i.test(key));if(value&&typeof value==='object')walk(value);}};
 walk(brief); assert.deepEqual(await formState(),baseline); assert.equal(await page.$eval('dl',e=>e.textContent),truth);
 assert.equal(await page.$('[role="dialog"]'),null);
 report.checks.push('Button order; one-click real clipboard; valid 2-space UTF-8 JSON; current peinado authoritative title/value/access/validity/requirements/conditions/disclosure; archived availability and missing published locations respected; no identifiers');
 await page.evaluate(()=>Object.defineProperty(navigator.clipboard,'writeText',{configurable:true,value:async()=>{throw Error('Clipboard denied');}}));
 await click('COPIAR BRIEF PARA IA'); await page.waitForSelector('[role="dialog"] textarea');
 assert.equal(await page.$eval('[role="dialog"] textarea',e=>e.value),copied);
 assert.equal(await page.$eval('[role="dialog"] textarea',e=>e.readOnly),true);
 await click('CERRAR'); await page.waitForFunction(()=>!document.querySelector('[role="dialog"]'));
 assert.deepEqual(await formState(),baseline); assert.deepEqual(report.requestsDuringCopy,[]);
 assert.deepEqual(report.mutationRequests,[]); assert.deepEqual(report.errors,[]);
 report.checks.push('Clipboard-denied manual fallback; unchanged entire form including campaign and audience; zero requests during copy; no save/publish/AI calls');
 fs.writeFileSync(out+'/brief.json',copied+'\n');
 await page.screenshot({path:out+'/admin-brief-desktop.png',fullPage:true}); report.result='PASS';
} catch(e){report.result='FAIL';report.failure=e.stack;process.exitCode=1;} finally {
 fs.writeFileSync(out+'/browser-validation.json',JSON.stringify(report,null,2)+'\n');
 console.log(JSON.stringify(report,null,2)); await browser.close(); server.close();
}
