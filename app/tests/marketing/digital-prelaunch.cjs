const {chromium}=require(process.env.QA_PLAYWRIGHT_MODULE);
const fs=require('fs'),assert=require('node:assert/strict');
const origin=process.env.QA_ORIGIN,out=process.env.QA_EVIDENCE;
assert.match(origin,/^http:\/\/10\.245\.96\.[2-9]:8001$/);
(async()=>{const browser=await chromium.launch({executablePath:process.env.QA_CHROMIUM_PATH,args:['--no-sandbox','--disable-dev-shm-usage']});
const ctx=await browser.newContext({viewport:{width:390,height:844}}),p=await ctx.newPage();
await p.goto(origin+'/productos-digitales/guia-digital-qa-1');await p.waitForLoadState('networkidle');
assert(await p.getByText('Estamos en prelanzamiento. Las compras todavía no están habilitadas.').isVisible());assert.equal(await p.getByRole('button',{name:/Comprar ·/}).count(),0);
await p.screenshot({path:out+'/prelaunch-390.png',fullPage:true});
await p.goto(origin+'/login');await p.locator('input[name=email]').fill('digital-admin@qa.invalid');await p.locator('input[name=password]').fill('DigitalQa!123456');await p.locator('[data-test=login-button]').click();await p.waitForURL(u=>!u.pathname.includes('/login'));await p.waitForLoadState('networkidle');
const cookie=(await ctx.cookies()).find(c=>c.name==='XSRF-TOKEN');assert(cookie);
const headers={'X-XSRF-TOKEN':decodeURIComponent(cookie.value),Accept:'application/json'};
const checks=[];const payment=process.env.QA_PAYMENT_ID,access=process.env.QA_ACCESS_ID;assert(payment&&access);
for(const path of ['/productos-digitales/guia-digital-qa-1/comprar',`/admin/digital/pagos/${payment}/confirmar`,`/admin/digital/accesos/${access}/reintentar`]){const r=await p.request.post(origin+path,{headers});assert.equal(r.status(),423,path);checks.push({path,status:r.status()});}
const download=await p.request.get(origin+`/mi-jakawi/productos-digitales/accesos/${access}/pdf`,{headers});assert.equal(download.status(),423);checks.push({path:'download',status:423});
fs.writeFileSync(out+'/prelaunch-browser.json',JSON.stringify({result:'PASS',checks},null,2));await browser.close();})().catch(e=>{console.error(e);process.exit(1)});
