const {chromium}=require('/tools/node_modules/playwright');
const fs=require('node:fs'), assert=require('node:assert/strict'),crypto=require('node:crypto');
(async()=>{const browser=await chromium.launch({executablePath:'/browser/chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--renderer-process-limit=1']});
try{
 const results=[];
 for(const width of [390,1440]){
  const context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage(),errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://hosted-app-qa:8082/mi-jakawi/productos-digitales');
  await page.waitForURL('**/login');
  await page.locator('[name=email]').fill('hosted-app@qa.invalid');await page.locator('[name=password]').fill('password');
  await page.locator('button[type=submit]').click();
  await page.waitForURL(url=>!url.pathname.endsWith('/login'));
  await page.goto('http://hosted-app-qa:8082/mi-jakawi/productos-digitales');
  await page.getByRole('link',{name:'ABRIR APLICACIÓN',exact:true}).waitFor();
  assert.equal(await page.getByRole('link',{name:'Descargar PDF',exact:true}).count(),1);
  assert(await page.locator('article img').first().evaluate(img=>img.complete&&img.naturalWidth>0));
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
  await page.screenshot({path:`/evidence/library-${width}.png`,fullPage:true});
  await page.goto('http://hosted-app-qa:8082/admin/productos-digitales/gluteo-perfecto/editar');
  await page.getByText('Método de entrega',{exact:false}).first().waitFor();
  const method=page.locator('select').filter({has:page.locator('option[value=hosted_app]')});
  assert.equal(await method.inputValue(),'hosted_app');
  await page.screenshot({path:`/evidence/admin-${width}.png`,fullPage:true});
  assert.deepEqual(errors,[]);results.push({width,library:'APP and PDF separated, cover visible',admin:'hosted_app selected',errors});await context.close();
 }
 fs.writeFileSync('/evidence/browser.json',JSON.stringify({result:'PASS',results,manifest_sha256:crypto.createHash('sha256').update(fs.readFileSync('/source/public/build/manifest.json')).digest('hex')},null,2));console.log('Mobile/desktop library and admin PASS');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});
