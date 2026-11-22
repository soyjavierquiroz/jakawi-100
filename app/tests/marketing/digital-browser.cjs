const { chromium } = require(process.env.QA_PLAYWRIGHT_MODULE);
const fs = require('fs');
const assert = require('node:assert/strict');
const origin = process.env.QA_ORIGIN, out = process.env.QA_EVIDENCE;
assert.match(origin, /^http:\/\/10\.245\.96\.[2-9]:8000$/);
const errors = [], checks = [], shots = [];
const runId = String(Date.now());
const pdf = Buffer.from('%PDF-1.4\n1 0 obj <</Type /Catalog>> endobj\n% QA only\n%%EOF');
async function ready(p) { await p.waitForLoadState('networkidle'); await p.locator('h1').first().waitFor(); }
async function login(p, role) {
 await p.goto(origin + '/login'); await p.locator('input[name=email]').fill(`digital-${role}@qa.invalid`);
 await p.locator('input[name=password]').fill('DigitalQa!123456');
 await p.locator('[data-test=login-button]').click(); await p.waitForURL(url=>!url.pathname.includes('/login')); await ready(p);
}
async function chooseTheme(p, mode) {
 await p.getByRole('button',{name:/^Apariencia:/}).filter({visible:true}).first().click();
 await p.getByRole('menuitemradio',{name:{light:'Claro',dark:'Oscuro',system:'Sistema'}[mode],exact:true}).click();
}
async function shot(p, label, width, mode) {
 await p.setViewportSize({width,height:width===390?844:1000});
 await chooseTheme(p,mode);
 await p.waitForFunction(m=>document.documentElement.dataset.appearance===m,mode);await p.waitForTimeout(200);
 assert.equal(await p.evaluate(()=>document.documentElement.classList.contains('dark')),mode==='dark');
 assert(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),label+' overflow');
 const file=`${label}-${mode}-${width}.png`;await p.screenshot({path:out+'/'+file,fullPage:true});shots.push(file);
}
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.QA_CHROMIUM_PATH,headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
 const context=await browser.newContext({viewport:{width:390,height:844},acceptDownloads:true});
 const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
 await page.goto(origin+'/explorar?q=creatividad');await ready(page);
 assert(await page.getByRole('heading',{name:'Productos digitales',exact:true}).count());
 for(const width of [390,1440])for(const mode of ['light','dark'])await shot(page,'explore',width,mode);
 await page.goto(origin+'/productos-digitales');await ready(page);
 assert.equal(await page.getByRole('heading',{level:3}).count(),3);
 await page.getByLabel('Buscar en Explorar').fill('creatividad');await page.waitForURL(/q=creatividad/);await ready(page);
 checks.push('global_catalog_search');
 await page.goto(origin+'/productos-digitales/guia-digital-qa-1');await ready(page);
 for(const width of [390,1440])for(const mode of ['light','dark'])await shot(page,'product',width,mode);
 await page.emulateMedia({colorScheme:'dark'});await chooseTheme(page,'system');await page.waitForFunction(()=>document.documentElement.classList.contains('dark'));checks.push('system_theme');
 await page.getByRole('link',{name:'Iniciar sesión para comprar'}).click();await page.waitForURL(/login/);
 await page.locator('input[name=email]').fill('digital-buyer@qa.invalid');await page.locator('input[name=password]').fill('DigitalQa!123456');await page.locator('[data-test=login-button]').click();await page.waitForURL(url=>!url.pathname.includes('/login'));await ready(page);
 if(!page.url().includes('/productos-digitales/')){await page.goto(origin+'/productos-digitales/guia-digital-qa-1');await ready(page);}
 await page.getByRole('button',{name:/Comprar ·/}).click();await page.waitForURL(/\/pedidos\//);await ready(page);
 const orderUrl=page.url(), ref=orderUrl.split('/').pop();
 await page.getByLabel('Adjuntar comprobante').setInputFiles({name:'receipt-qa.pdf',mimeType:'application/pdf',buffer:Buffer.concat([pdf,Buffer.from('browser receipt '+runId)])});
 await page.getByRole('button',{name:'Enviar a verificación'}).click();await page.getByText('Comprobante recibido. Pendiente de verificación bancaria.').waitFor();
 for(const width of [390,1440])for(const mode of ['light','dark'])await shot(page,'payment-review',width,mode);
 assert.equal(await page.getByRole('link',{name:/Descargar PDF privado/}).count(),0);checks.push('pending_no_access');
 const adminContext=await browser.newContext({viewport:{width:1440,height:1000}});const admin=await adminContext.newPage();admin.on('pageerror',e=>errors.push(e.message));
 await login(admin,'admin');await admin.goto(origin+'/admin/digital/pedidos/'+ref);await ready(admin);
 await admin.getByLabel('Referencia única de la operación bancaria').fill('BANK-QA-BROWSER-'+runId);
 const date=new Intl.DateTimeFormat('en-CA',{timeZone:'America/La_Paz',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
 await admin.getByLabel('Fecha del ingreso').fill(date);await admin.getByLabel(/Importe realmente ingresado/).fill('20.00');
 await admin.getByLabel(/Extracto\/evidencia bancaria PDF/).setInputFiles({name:'statement-qa.pdf',mimeType:'application/pdf',buffer:Buffer.concat([pdf,Buffer.from('browser bank evidence')])});
 await admin.getByLabel(/He contrastado el ingreso real/).check();
 for(const width of [390,1440])for(const mode of ['light','dark'])await shot(admin,'admin-review',width,mode);
 await admin.getByRole('button',{name:'Confirmar ingreso y conceder acceso'}).click();await admin.getByRole('heading',{name:'Acceso: Habilitado'}).waitFor();checks.push('authorized_bank_confirmation');
 await page.goto(orderUrl);await ready(page);
 const downloadPromise=page.waitForEvent('download');await page.getByRole('link',{name:'Descargar PDF privado'}).click();const download=await downloadPromise;await download.saveAs(out+'/download-qa.pdf');assert(fs.readFileSync(out+'/download-qa.pdf').subarray(0,5).equals(Buffer.from('%PDF-')));checks.push('authenticated_pdf');
 await page.goto(origin+'/mi-jakawi');await ready(page);await page.getByRole('link',{name:'Abrir mi biblioteca digital'}).click();await page.waitForURL(/\/mi-jakawi\/productos-digitales$/);await ready(page);await page.getByRole('heading',{name:'Mis productos digitales',exact:true}).waitFor();
 for(const width of [390,1440])for(const mode of ['light','dark'])await shot(page,'library',width,mode);
 await admin.goto(origin+'/admin/productos-digitales');await ready(admin);
 for(const width of [390,1440])for(const mode of ['light','dark'])await shot(admin,'admin-products',width,mode);
 await admin.goto(origin+'/admin/productos-digitales/guia-digital-qa-1/editar');await ready(admin);
 for(const width of [390,1440])for(const mode of ['light','dark'])await shot(admin,'admin-product-form',width,mode);
 await admin.goto(origin+'/admin/digital/cobro');await ready(admin);
 for(const width of [390,1440])for(const mode of ['light','dark'])await shot(admin,'admin-collection',width,mode);
 await admin.goto(origin+'/admin/digital/pedidos/'+ref);await ready(admin);
 await admin.getByLabel('Referencia de devolución').fill('REFUND-QA-'+runId);await admin.getByLabel('Motivo de devolución').fill('Prueba de revocación QA');await admin.getByLabel(/La devolución ya se realizó/).check();await admin.getByRole('button',{name:'Registrar y revocar acceso'}).click();await admin.getByRole('heading',{name:'Acceso: Revocado'}).waitFor();
 await page.goto(orderUrl);await ready(page);assert.equal(await page.getByRole('link',{name:'Descargar PDF privado'}).count(),0);checks.push('refund_revocation');
 const otherContext=await browser.newContext();const other=await otherContext.newPage();await login(other,'other');const denied=await other.goto(orderUrl);assert.equal(denied.status(),404);checks.push('ownership_denied');
 const memberContext=await browser.newContext();const member=await memberContext.newPage();await login(member,'member');await member.goto(origin+'/productos-digitales/guia-digital-qa-3');await ready(member);assert.match(await member.getByRole('button',{name:/Comprar ·/}).innerText(),/12[,.]50/);checks.push('member_price');
 assert.deepEqual(errors,[]);fs.writeFileSync(out+'/browser.json',JSON.stringify({result:'PASS',checks,errors,screenshots:shots,order:ref},null,2));await browser.close();
})().catch(e=>{fs.writeFileSync(out+'/browser.json',JSON.stringify({result:'FAIL',error:e.stack,checks,errors,screenshots:shots},null,2));console.error(e);process.exit(1)});
