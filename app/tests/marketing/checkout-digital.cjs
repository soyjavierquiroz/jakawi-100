// Focused browser QA. Only the isolated checkout-qa host accepts mutations.
const {chromium}=require('/tools/node_modules/playwright');
const fs=require('node:fs'), assert=require('node:assert/strict'), crypto=require('node:crypto');
const origin='http://checkout-qa:8082',approved=process.argv.includes('--approved');
(async()=>{
 const browser=await chromium.launch({executablePath:'/browser/chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--renderer-process-limit=1']});
 const errors=[],checks=[];
 async function login(kind,width=390){
  const ctx=await browser.newContext({viewport:{width,height:844}});const p=await ctx.newPage();p.on('pageerror',e=>errors.push(e.message));
  await p.goto(origin+'/productos-digitales/gluteo-perfecto/comprar');await p.locator('input[name=email]').fill(kind+'@checkout.qa.invalid');await p.locator('input[name=password]').fill('CheckoutQa!123456');await p.locator('button[type=submit]').click();await p.waitForURL('**/productos-digitales/gluteo-perfecto/comprar');await p.locator('h1').waitFor();return {ctx,p};
 }
 async function fits(p){const overflow=await p.evaluate(()=>({width:innerWidth,scroll:document.documentElement.scrollWidth,elements:[...document.querySelectorAll('main *')].filter(e=>e.getBoundingClientRect().right>innerWidth).map(e=>({tag:e.tagName,cls:e.className,text:e.textContent?.slice(0,80),right:e.getBoundingClientRect().right})).slice(0,12)}));if(overflow.scroll>overflow.width){await p.screenshot({path:'/evidence/overflow.png'});console.log(overflow);}assert(overflow.scroll<=overflow.width,'horizontal overflow');}
 try{
 if(!approved){
  const {ctx,p}=await login('member');
  await p.getByRole('button',{name:'ACTIVAR MI REGALO',exact:true}).waitFor();await p.getByRole('button',{name:/^COMPRAR ·/}).waitFor();await fits(p);
  await p.goto(origin+'/l/checkout-qa');await p.getByRole('link',{name:'ACTIVAR MI REGALO',exact:true}).first().waitFor();await p.getByRole('button',{name:/^COMPRAR ·/}).waitFor();await fits(p);await p.getByRole('button',{name:/^COMPRAR ·/}).click();await p.waitForURL('**/pedidos/**');await p.getByText('Descuento de membresía',{exact:true}).waitFor();assert.match(await p.locator('main').innerText(),/29/);assert.match(await p.locator('main').innerText(),/20/);
  assert.equal(await p.locator('img[alt="QR de cobro JAKAWI Digital"]').count(),1);assert(await p.locator('img[alt="Glúteo Perfecto QA"]').evaluate(x=>x.complete&&x.naturalWidth>0));await fits(p);await p.screenshot({path:'/evidence/checkout-390.png'});checks.push('member 29, discount 20, pending gift and purchase, image and single QR');await ctx.close();
  const free=await login('free',1440);await free.p.getByRole('button',{name:/^COMPRAR AHORA/}).click();await free.p.waitForURL('**/pedidos/**');assert.match(await free.p.locator('main').innerText(),/49/);assert.equal(await free.p.getByText('Descuento de membresía',{exact:true}).count(),0);await fits(free.p);
  const reference=free.p.url().split('/').pop();fs.writeFileSync('/evidence/order.json',JSON.stringify({reference}));
  if(await free.p.locator('input[type=file]').count()) { await free.p.locator('input[type=file]').setInputFiles({name:'receipt.pdf',mimeType:'application/pdf',buffer:Buffer.from('%PDF-1.4\n% FICTICIO QA receipt\n%%EOF')});await free.p.getByRole('button',{name:'Enviar a verificación'}).click(); } await free.p.getByText('Recibimos tu comprobante. Estamos verificando tu pago.',{exact:true}).waitFor();assert.equal(await free.p.locator('img[alt="QR de cobro JAKAWI Digital"]').count(),0);assert.match(await free.p.locator('main').innerText(),/49/);await free.ctx.close();checks.push('free 49, receipt under review, QR hidden and summary retained');
  const gift=await login('gift');await gift.p.getByRole('button',{name:'ABRIR MI PRODUCTO',exact:true}).waitFor();await gift.p.goto(origin+'/mi-jakawi/productos-digitales');const link=gift.p.getByRole('link',{name:'Descargar PDF',exact:true});await link.waitFor();const response=await gift.ctx.request.get(new URL(await link.getAttribute('href'),origin).href);assert.equal(response.status(),200);assert.match(response.headers()['content-type'],/application\/pdf/);await fits(gift.p);await gift.ctx.close();checks.push('authorized gift visible and downloadable in PRELAUNCH');
 }else{
  const {ctx,p}=await login('free');const {reference}=JSON.parse(fs.readFileSync('/evidence/order.json'));await p.goto(origin+'/mi-jakawi/productos-digitales/pedidos/'+reference);await p.getByText('¡Tu producto está listo!',{exact:true}).waitFor();await p.getByRole('link',{name:'ABRIR MI BIBLIOTECA',exact:true}).click();await p.waitForURL('**/mi-jakawi/productos-digitales');const link=p.getByRole('link',{name:'Descargar PDF',exact:true});await link.waitFor();const response=await ctx.request.get(new URL(await link.getAttribute('href'),origin).href);assert.equal(response.status(),200);await fits(p);await ctx.close();checks.push('approved, library CTA, authorized private PDF');
 }
 assert.deepEqual(errors,[]);fs.writeFileSync('/evidence/'+(approved?'browser':'browser-pending')+'.json',JSON.stringify({result:'PASS',checks,errors,manifest_sha256:crypto.createHash('sha256').update(fs.readFileSync('/source/public/build/manifest.json')).digest('hex')},null,2));console.log(checks.join('\n'));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
