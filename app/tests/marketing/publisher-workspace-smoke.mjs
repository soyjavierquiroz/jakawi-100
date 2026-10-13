// Final compiled-asset smoke after restoring the saved Preview record label.
import fs from 'node:fs';
import assert from 'node:assert/strict';
const {default:puppeteer}=await import(process.env.PUBLISHER_BROWSER_MODULE);
const out=process.env.PUBLISHER_ARTIFACTS??'artifacts/publisher-workspace-ux-v2';
const browser=await puppeteer.launch({headless:true,executablePath:process.env.PUBLISHER_CHROMIUM,args:['--no-sandbox','--disable-dev-shm-usage']});
const page=await browser.newPage();const report={checks:[],mutations:[],errors:[]};
page.on('request',r=>{if(['PUT','POST'].includes(r.method()))report.mutations.push(r.url())});page.on('pageerror',e=>report.errors.push(e.message));
async function click(text){const h=await page.evaluateHandle(t=>[...document.querySelectorAll('button')].find(b=>b.textContent.trim()===t),text);assert(h.asElement(),text);await h.asElement().click();await h.dispose();}
const overflow=()=>page.evaluate(()=>document.documentElement.scrollWidth>innerWidth);
try{
 await page.setViewport({width:1440,height:1000});await page.goto((process.env.PUBLISHER_ORIGIN??'http://127.0.0.1:8098')+'/admin/experiencias/publisher-arcilla-qa/landings',{waitUntil:'networkidle2'});await click('Editar');await page.waitForSelector('iframe');await page.waitForNetworkIdle();
 const name=await page.$eval('[name="name"]',e=>e.value);assert((await page.$eval('[data-workspace-preview] h2',e=>e.innerText)).includes(name));assert.equal(await page.$eval('iframe',e=>e.offsetWidth),390);assert.equal(await overflow(),false);
 assert.equal(await page.$eval('[data-product-truth]',e=>e.open),false);assert.equal(await page.$eval('[data-workspace-toolbar]',e=>getComputedStyle(e).position),'sticky');
 await page.$eval('#landing-workspace',e=>e.scrollIntoView({block:'start'}));await page.screenshot({path:out+'/saved-desktop.png'});
 await page.$eval('figure',e=>e.scrollIntoView({block:'center'}));assert.equal(await page.$eval('figure img',e=>e.complete&&e.naturalWidth>0),true);await page.screenshot({path:out+'/media-desktop.png'});
 report.checks.push('Final compiled desktop workspace, named saved Preview, actual 390 canvas, collapsed truth, sticky toolbar and visible current image; no overflow');
 await page.setViewport({width:390,height:844});assert.equal(await page.$eval('[data-workspace-toolbar]',e=>getComputedStyle(e).position),'static');await page.$eval('#landing-workspace',e=>e.scrollIntoView({block:'start'}));await page.screenshot({path:out+'/mobile-editor.png'});
 await page.$eval('[data-workspace-preview]',e=>e.scrollIntoView({block:'end'}));await page.screenshot({path:out+'/mobile-preview.png'});
 for(const size of ['430','Desktop','390']){await click(size);assert.equal(await overflow(),false)}
 assert.equal(report.mutations.length,0);assert.deepEqual(report.errors,[]);report.checks.push('Final compiled 390 Admin stacks; toolbar does not cover controls; all Preview sizes fit without overflow or save requests');report.result='PASS';
}catch(e){report.result='FAIL';report.failure=e.stack;process.exitCode=1;}finally{fs.writeFileSync(out+'/final-smoke.json',JSON.stringify(report,null,2)+'\n');console.log(JSON.stringify(report,null,2));await browser.close();}
