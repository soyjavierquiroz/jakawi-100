// Read-only mobile smoke against the existing isolated QA database.
const {chromium}=require('/tools/node_modules/playwright');
const fs=require('node:fs'),assert=require('node:assert/strict'),crypto=require('node:crypto');
(async()=>{const browser=await chromium.launch({executablePath:'/browser/chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--renderer-process-limit=1']});try{
const page=await browser.newPage({viewport:{width:390,height:844}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
await page.goto('http://remove-prelaunch-qa:8082/');await page.getByRole('heading',{name:'¿QUÉ HACEMOS HOY?'}).waitFor();
assert(!/prelaunch|prelanzamiento/i.test(await page.locator('body').innerText()));
assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
await page.goto('http://remove-prelaunch-qa:8082/membresia/checkout');await page.waitForURL('**/login');await page.locator('input[name=email]').waitFor();
assert.deepEqual(errors,[]);fs.writeFileSync('/evidence/browser.json',JSON.stringify({result:'PASS',checks:['390px operational homepage','no global prelaunch notice','membership checkout preserves authentication'],errors,manifest_sha256:crypto.createHash('sha256').update(fs.readFileSync('/source/public/build/manifest.json')).digest('hex')},null,2));console.log('Mobile smoke PASS; no transactions');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});
