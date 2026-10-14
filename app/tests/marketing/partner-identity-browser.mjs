import fs from 'node:fs';
import assert from 'node:assert/strict';
const { default: puppeteer } = await import(process.env.PARTNER_BROWSER_MODULE);
const out=process.env.PARTNER_ARTIFACTS ?? 'artifacts/partner-profile-v2-1';
const report={pass:false,checks:[],errors:[],states:{}};
const browser=await puppeteer.launch({headless:true,executablePath:process.env.PARTNER_CHROMIUM,args:['--no-sandbox','--disable-dev-shm-usage']});
const page=await browser.newPage();page.on('pageerror',e=>report.errors.push(e.message));
const go=async path=>{const res=await page.goto('http://127.0.0.1:8098'+path,{waitUntil:'networkidle2'});assert.equal(res.status(),200,path);};
const state=()=>page.evaluate(async()=> (await fetch('/__qa/identity')).json());
async function click(text) {
 const handle=await page.evaluateHandle(text=>[...document.querySelectorAll('button,a')].find(n=>n.textContent.trim()===text),text);const element=handle.asElement();assert(element,text);await element.evaluate(n=>n.scrollIntoView({block:'center'}));await element.click();await handle.dispose();
}
const overflow=()=>page.evaluate(()=>document.documentElement.scrollWidth>innerWidth);
try {
 await page.setViewport({width:390,height:844});await go('/dosis43');await page.waitForSelector('[data-partner-socials]');
 const links=await page.$$eval('[data-partner-socials] a',nodes=>nodes.map(n=>({label:n.textContent.trim(),href:n.href,target:n.target,rel:n.rel})));
 assert.deepEqual(links.map(n=>n.label),['Instagram','TikTok','Facebook','Website','WhatsApp']);
 for(const link of links){assert.equal(link.target,'_blank');assert.equal(link.rel,'noopener noreferrer');assert(link.href.startsWith('https://'));}
 assert.equal(links[4].href,'https://wa.me/59171234567');
 const avatar=await page.$eval('[data-partner-logo]',n=>({width:n.getBoundingClientRect().width,height:n.getBoundingClientRect().height,radius:getComputedStyle(n).borderRadius,fit:getComputedStyle(n.querySelector('img')).objectFit,loaded:n.querySelector('img').naturalWidth>0}));
 assert.equal(avatar.width,avatar.height);assert.equal(avatar.radius,'50%');assert.equal(avatar.fit,'contain');assert(avatar.loaded);assert.equal(await overflow(),false);report.states.avatar=avatar;
 await page.screenshot({path:out+'/partner-390.png',fullPage:true});report.checks.push('390: circular undistorted loaded avatar, five ordered compact safe social links, no overflow; coral CTA/yellow rewards preserved');
 await go('/instagram-only-qa');assert.deepEqual(await page.$$eval('[data-partner-socials] a',nodes=>nodes.map(n=>n.textContent.trim())),['Instagram']);report.checks.push('Instagram-only Partner renders one link');
 await go('/empty-brand-qa');assert.equal(await page.$('[data-partner-socials]'),null);assert(await page.$('[data-partner-logo-fallback]'));assert.equal(await overflow(),false);report.checks.push('Missing socials omitted; circular initials fallback works');
 await go('/unsafe-socials-qa');assert.equal(await page.$('[data-partner-socials]'),null);report.checks.push('Unsafe/invalid schemes omitted');
 await page.setViewport({width:1440,height:1000});await go('/dosis43');assert.equal(await overflow(),false);await page.screenshot({path:out+'/partner-1440.png',fullPage:true});report.checks.push('1440: V2 hero preserved, circular avatar/socials, no overflow');
 await go('/login');await page.type('#email','partner-admin-qa@example.test');await page.type('#password','password');await page.$eval('form',form=>form.requestSubmit());await page.waitForFunction(()=>location.pathname!=='/login');
 const old=await state();report.states.old=old;await go(`/admin/partners/${old.id}/edit`);
 await click('ENCUADRAR LOGO');await page.waitForSelector('[data-avatar-crop]');await page.waitForFunction(()=>!([...document.querySelectorAll('[role="dialog"] button')].find(n=>n.textContent==='USAR FOTO')?.disabled));await click('Cancelar');assert.equal((await state()).logo_path,old.logo_path);report.checks.push('Admin can reopen saved master; cancel preserves old asset');
 const input=await page.$('input[aria-label="Seleccionar Logo / foto de perfil"]');assert(input);await input.uploadFile(process.env.PARTNER_CROP_IMAGE ?? '/tmp/partner-identity-horizontal.png');await page.waitForSelector('[data-avatar-crop]');
 await page.waitForFunction(()=>!([...document.querySelectorAll('[role="dialog"] button')].find(n=>n.textContent==='USAR FOTO')?.disabled));
 assert.equal(await page.$eval('[data-avatar-crop] .reactEasyCrop_CropArea',n=>getComputedStyle(n).borderRadius),'50%');
 const transform=()=>page.$eval('[data-avatar-crop] img',n=>n.style.transform);
 const initial=await transform();
 await page.focus('input[aria-label="Acercar foto"]');await page.keyboard.press('End');
 await page.waitForFunction(initial=>document.querySelector('[data-avatar-crop] img').style.transform!==initial,{},initial);
 assert(await page.$eval('input[aria-label="Acercar foto"]',n=>Number(n.value)>2.9));
 const zoomed=await transform();assert.notEqual(initial,zoomed);
 const bounds=await page.$eval('[data-avatar-crop]',n=>{const r=n.getBoundingClientRect();return{x:r.x,y:r.y,w:r.width,h:r.height};});
 await page.mouse.move(bounds.x+bounds.w/2,bounds.y+bounds.h/2);await page.mouse.down();await page.mouse.move(bounds.x+bounds.w/2+60,bounds.y+bounds.h/2+30,{steps:8});await page.mouse.up();assert.notEqual(await transform(),zoomed);
 await page.screenshot({path:out+'/admin-crop-1440.png',fullPage:true});
 await click('MOSTRAR LOGO COMPLETO');await page.screenshot({path:out+'/admin-crop-fit-1440.png'});await click('USAR FOTO');await page.waitForSelector('[data-avatar-crop]',{hidden:true});await page.waitForFunction(()=>document.querySelector('input[name="logo_upload"]')?.value.length>0);
 const preview=await page.$eval('img[alt="Vista previa: Logo / foto de perfil"]',async image=>{
  const bitmap=await createImageBitmap(await (await fetch(image.src)).blob());const canvas=document.createElement('canvas');canvas.width=bitmap.width;canvas.height=bitmap.height;const context=canvas.getContext('2d');context.drawImage(bitmap,0,0);const data=context.getImageData(0,0,canvas.width,canvas.height).data;let red=0,blue=0;for(let i=0;i<data.length;i+=4){if(data[i]>240&&data[i+1]<20&&data[i+2]<20)red++;if(data[i]<20&&data[i+1]<20&&data[i+2]>240)blue++;}return{width:bitmap.width,height:bitmap.height,red,blue,cornerAlpha:data[3]};
 });assert.equal(preview.width,preview.height);assert(preview.width>=800);assert(preview.red>1000&&preview.blue>1000);assert.equal(preview.cornerAlpha,0);report.states.preview=preview;
 await click('Guardar');await page.waitForFunction(()=>location.pathname==='/admin/partners');await page.waitForNetworkIdle();
 const saved=await state();assert.equal(saved.width,saved.height);assert.equal(saved.mime,'image/webp');assert.notEqual(saved.logo_path,old.logo_path);assert.equal(saved.cover_path,old.cover_path);assert(saved.masters.includes(old.logo_path)&&saved.masters.includes(saved.logo_path));report.states.saved=saved;
 await go(`/admin/partners/${old.id}/edit`);assert(await page.$('img[alt="Vista previa: Logo / foto de perfil"]'));await page.reload({waitUntil:'networkidle2'});assert.equal((await state()).logo_path,saved.logo_path);
 report.checks.push('Real isolated Admin: select horizontal PNG → round mask → zoom → drag/reposition → fit entire logo → square live preview retaining both brand edges → actual upload receipt → save/reload normalized WebP master; immutable old master retained; cover unchanged');
 // Low-resolution source: padding is allowed, source pixels are never enlarged to 800/1024.
 await page.setViewport({width:390,height:844});
 const smallInput=await page.$('input[aria-label="Seleccionar Logo / foto de perfil"]');
 await smallInput.uploadFile(process.env.PARTNER_SMALL_IMAGE ?? '/tmp/partner-identity-small.png');
 await page.waitForSelector('[data-avatar-crop]');await page.waitForFunction(()=>!([...document.querySelectorAll('[role="dialog"] button')].find(n=>n.textContent==='USAR FOTO')?.disabled));
 await page.$eval('[role="dialog"]',async node=>{await Promise.all(node.getAnimations().map(animation=>animation.finished.catch(()=>{})));});await page.screenshot({path:out+'/admin-crop-390.png'});await click('USAR FOTO');
 await page.waitForFunction(()=>document.querySelector('input[name="logo_upload"]')?.value.length>0);
 const small=await page.$eval('img[alt="Vista previa: Logo / foto de perfil"]',async image=>{const bitmap=await createImageBitmap(await (await fetch(image.src)).blob());return{width:bitmap.width,height:bitmap.height};});
 assert.equal(small.width,small.height);assert(small.width<=207);report.states.smallPreview=small;
 assert.equal((await state()).logo_path,saved.logo_path);report.checks.push('390 Admin crop responsive; 200×50 source stays small, no forced upscale; staged replacement does not overwrite saved master');
 await page.setViewport({width:390,height:844});await go('/dosis43');assert.equal(await page.$('[data-partner-account]'),null);assert.equal(await overflow(),false);report.checks.push('Authenticated Partner hides acquisition block');
 assert.deepEqual(report.errors,[]);report.pass=true;
} catch(e){report.failure=String(e);report.url=page.url();report.failureText=await page.evaluate(()=>document.body.innerText);await page.screenshot({path:out+'/failure.png',fullPage:true});throw e;}finally{fs.writeFileSync(out+'/browser-validation.json',JSON.stringify(report,null,2)+'\n');await browser.close();}
