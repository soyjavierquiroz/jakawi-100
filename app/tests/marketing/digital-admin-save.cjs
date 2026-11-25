// Admin save smoke. Mutations are restricted to the isolated QA host.
const {chromium}=require('/tools/node_modules/playwright');
const fs=require('node:fs'),assert=require('node:assert/strict'),crypto=require('node:crypto');
const origin='http://admin-save-qa:8082',editor='/admin/productos-digitales/gluteo-perfecto/editar';
(async()=>{
 const browser=await chromium.launch({executablePath:'/browser/chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--renderer-process-limit=1']});const errors=[];
 try{
  const ctx=await browser.newContext({viewport:{width:1440,height:900}});const p=await ctx.newPage();p.on('pageerror',e=>errors.push(e.message));
  await p.goto(origin+'/login');await p.locator('input[name=email]').fill('admin@save.qa.invalid');await p.locator('input[name=password]').fill('AdminSaveQa!123456');await p.locator('button[type=submit]').click();await p.waitForURL('**/admin');
  await p.goto(origin+editor);await p.getByLabel('Nombre',{exact:true}).fill('Glúteo Perfecto QA guardado');
  const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jPmQAAAAASUVORK5CYII=','base64');
  const file={name:'QA-NO-PAGAR.png',mimeType:'image/png',buffer:png};
  await p.locator('form input[type=file]').nth(1).setInputFiles(file);await p.locator('form input[type=file]').nth(2).setInputFiles(file);
  await p.getByRole('checkbox',{name:/Confirmo que el QR público pertenece/}).check();await p.getByRole('button',{name:'Guardar producto',exact:true}).click();
  await p.locator('[data-field-error]').filter({hasText:'Primero configura y autoriza la cuenta exclusiva'}).first().waitFor();assert(p.url().endsWith(editor));assert.equal(await p.getByLabel('Nombre',{exact:true}).inputValue(),'Glúteo Perfecto QA guardado');assert.equal(await p.locator('form input[type=file]').nth(1).evaluate(e=>e.files.length),1);assert.equal(await p.getByText('Oops! An Error Occurred',{exact:true}).count(),0);
  await p.screenshot({path:'/evidence/editor-error.png'});
  await p.getByRole('checkbox',{name:/Confirmo que el QR público pertenece/}).uncheck();await p.getByRole('button',{name:'Guardar producto',exact:true}).click();await p.waitForURL('**/admin/productos-digitales');
  await p.goto(origin+editor);assert.equal(await p.getByLabel('Nombre',{exact:true}).inputValue(),'Glúteo Perfecto QA guardado');assert.equal(await p.getByRole('link',{name:'Revisar QR público actual',exact:true}).count(),1);assert.equal(await p.getByRole('link',{name:'Revisar QR miembro actual',exact:true}).count(),1);assert.equal(await p.getByRole('checkbox',{name:/Confirmo que el QR público pertenece/}).isChecked(),false);
  await p.getByRole('button',{name:'Habilitar venta',exact:true}).click();await p.locator('[data-field-error]').filter({hasText:'PDF comercial definitivo'}).first().waitFor();await p.getByText('Disponibilidad comercial: Venta desactivada',{exact:true}).waitFor();
  assert.deepEqual(errors,[]);fs.writeFileSync('/evidence/browser.json',JSON.stringify({result:'PASS',checks:['field error stays in editor','other values and selected QR retained','both QR stored unverified','editorial save preserves courtesy','incomplete sale blocked'],errors,manifest_sha256:crypto.createHash('sha256').update(fs.readFileSync('/source/public/build/manifest.json')).digest('hex')},null,2));console.log('Admin digital save browser PASS');await ctx.close();
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
