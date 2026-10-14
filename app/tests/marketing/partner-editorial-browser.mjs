import fs from 'node:fs';
import assert from 'node:assert/strict';
const { default: puppeteer } = await import(process.env.PARTNER_BROWSER_MODULE);
const out = 'artifacts/partner-editorial-media-v1';
const report = { pass: false, checks: [], states: {}, errors: [] };
const browser = await puppeteer.launch({headless:true, executablePath:process.env.PARTNER_CHROMIUM, args:['--no-sandbox','--disable-dev-shm-usage']});
const page = await browser.newPage();
page.on('pageerror', e => report.errors.push(e.message));
const go = async path => {const response = await page.goto('http://127.0.0.1:8098'+path,{waitUntil:'networkidle2'});assert.equal(response.status(),200,path);};
const state = () => page.evaluate(async () => (await fetch('/__qa/identity')).json());
const overflow = () => page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
async function click(text, selector='button,a') {
    const handle = await page.evaluateHandle((text, selector) => [...document.querySelectorAll(selector)].find(n => n.textContent.trim() === text), text, selector);
    const node = handle.asElement();assert(node,text);await node.evaluate(n => n.scrollIntoView({block:'center'}));await node.click();await handle.dispose();
}
async function gallery(count, columns, before) {
    await page.waitForSelector('[data-partner-editorial]');
    await page.$eval('[data-partner-editorial]',n => n.scrollIntoView({block:'start'}));
    await page.waitForFunction(() => [...document.querySelectorAll('[data-partner-editorial] img')].every(n => n.complete && n.naturalWidth > 0));
    const data = await page.$eval('[data-partner-editorial]',(n,before) => ({
        title:n.querySelector('h2').textContent,
        columns:getComputedStyle(n.querySelector('.grid')).gridTemplateColumns.split(' ').length,
        frames:[...n.querySelectorAll('figure')].map(f => {const r=f.getBoundingClientRect();const i=f.querySelector('img');return {width:r.width,height:r.height,fit:getComputedStyle(i).objectFit,alt:i.alt,src:i.src};}),
        order:document.querySelector(before).compareDocumentPosition(n) & Node.DOCUMENT_POSITION_FOLLOWING,
        locationAfter:n.compareDocumentPosition(document.querySelector('#ubicaciones')) & Node.DOCUMENT_POSITION_FOLLOWING,
        accountAfter:n.compareDocumentPosition(document.querySelector('[data-partner-account]')) & Node.DOCUMENT_POSITION_FOLLOWING
    }),before);
    assert.equal(data.frames.length,count);assert.equal(data.columns,columns);assert(data.order && data.locationAfter && data.accountAfter);
    for (const [index, frame] of data.frames.entries()) {assert(Math.abs(frame.width/frame.height-.8)<.01);assert.equal(frame.fit,'cover');assert(frame.alt.endsWith(`imagen ${index+1}`));}
    assert.equal(await overflow(),false);return data;
}
async function edit(id) {await go(`/admin/partners/${id}/edit`);}
async function save() {await click('Guardar');await page.waitForFunction(() => location.pathname === '/admin/partners');}
async function remove(slot) {await click('QUITAR IMAGEN',`fieldset[aria-label="IMAGEN ${slot}"] button`);}
try {
    await page.setViewport({width:390,height:844});await go('/instagram-only-qa');assert.equal(await page.$('[data-partner-editorial]'),null);report.checks.push('Zero images: editorial section absent');
    await go('/empty-brand-qa');await gallery(1,1,'[data-partner-empty]');await page.screenshot({path:out+'/partner-zero-inventory-390.png',fullPage:true});report.checks.push('Zero inventory: compact state → editorial image → location → account');
    const old = await state();report.states.old=old;
    await go('/dosis43');report.states.mobile=await gallery(3,1,'#propuestas');
    assert(await page.$('[data-partner-logo]'));assert(await page.$('[data-partner-socials]'));assert(await page.$('[data-partner-account]'));
    await page.screenshot({path:out+'/partner-390.png',fullPage:true});report.checks.push('390: three ordered loaded 4:5 images; offers → editorial → location → guest acquisition; no overflow; V2.1 identity preserved');
    await page.setViewport({width:1440,height:1000});await go('/dosis43');report.states.desktop=await gallery(3,3,'#propuestas');await page.screenshot({path:out+'/partner-1440.png',fullPage:true});report.checks.push('1440: three-column editorial composition; no overflow');
    await go('/login');await page.type('#email','partner-admin-qa@example.test');await page.type('#password','password');await page.$eval('form',form=>form.requestSubmit());await page.waitForFunction(()=>location.pathname!=='/login');
    await page.setViewport({width:390,height:844});await edit(old.id);
    assert.equal(await page.$$eval('section[aria-label="Imágenes del Partner"] fieldset',nodes=>nodes.length),3);
    assert.equal(await overflow(),false);
    const select=await page.$('input[aria-label="Seleccionar IMAGEN 2"]');await select.uploadFile('/tmp/partner-editorial-1.png');
    await page.waitForFunction(()=>document.querySelector('input[name="gallery_2_upload"]')?.value.length>0);
    const preview=await page.$eval('img[alt="Vista previa: IMAGEN 2"]',n=>n.src);assert(preview.startsWith('blob:'));
    assert.deepEqual((await state()).gallery_paths,old.gallery_paths);await page.screenshot({path:out+'/admin-preview-390.png',fullPage:true});
    await save();const replaced=await state();assert.notEqual(replaced.gallery_paths[1],old.gallery_paths[1]);assert.equal(replaced.gallery_paths[0],old.gallery_paths[0]);assert.equal(replaced.gallery_paths[2],old.gallery_paths[2]);assert(replaced.gallery_masters.includes(old.gallery_paths[1]));assert.equal(replaced.logo_path,old.logo_path);assert.equal(replaced.cover_path,old.cover_path);
    await edit(old.id);await page.reload({waitUntil:'networkidle2'});assert(await page.$('img[alt="Vista previa: IMAGEN 2"]'));
    await remove(2);await save();assert.deepEqual((await state()).gallery_paths,[old.gallery_paths[0],old.gallery_paths[2]]);
    report.checks.push('Admin 390: exactly three shared upload slots; local selection/immediate preview; actual save/reload immutable replacement; remove compacts order and retains old asset; logo/cover untouched; no overflow');
    // Inspect public rendering as a guest after staged Admin changes.
    const guest=await browser.newPage();await guest.setViewport({width:1440,height:1000});await guest.goto('http://127.0.0.1:8098/dosis43',{waitUntil:'networkidle2'});
    assert.equal(await guest.$$eval('[data-partner-editorial] figure',n=>n.length),2);
    assert.equal(await guest.$eval('[data-partner-editorial] .grid',n=>getComputedStyle(n).gridTemplateColumns.split(' ').length),2);
    await guest.close();
    await edit(old.id);await remove(2);await save();assert.deepEqual((await state()).gallery_paths,[old.gallery_paths[0]]);
    const single=await browser.newPage();await single.setViewport({width:1440,height:1000});await single.goto('http://127.0.0.1:8098/dosis43',{waitUntil:'networkidle2'});
    assert.equal(await single.$$eval('[data-partner-editorial] figure',n=>n.length),1);assert(await single.$eval('[data-partner-editorial] figure',n=>n.getBoundingClientRect().width<=512));await single.screenshot({path:out+'/partner-single-1440.png',fullPage:true});await single.close();
    await edit(old.id);await remove(1);await save();assert.equal((await state()).gallery_paths,null);
    // Upload all three empty slots, including non-contiguous selection order.
    await edit(old.id);
    for(const slot of [3,1,2]) {const input=await page.$(`input[aria-label="Seleccionar IMAGEN ${slot}"]`);await input.uploadFile(`/tmp/partner-editorial-${slot}.png`);await page.waitForFunction(slot=>document.querySelector(`input[name="gallery_${slot}_upload"]`)?.value.length>0,{},slot);}
    await page.setViewport({width:1440,height:1000});assert.equal(await overflow(),false);await page.screenshot({path:out+'/admin-three-slots-1440.png',fullPage:true});await save();assert.equal((await state()).gallery_paths.length,3);
    report.checks.push('Admin: remove all → null; upload three empty slots in arbitrary selection order → three saved references in slot order; single image bounded to 512px; two-image balanced grid');
    await page.goto('http://127.0.0.1:8098/dosis43',{waitUntil:'networkidle2'});assert.equal(await page.$('[data-partner-account]'),null);report.checks.push('Authenticated acquisition block stays hidden');
    assert.deepEqual(report.errors,[]);report.pass=true;
} catch(e) {report.failure=String(e);report.url=page.url();report.failureText=await page.evaluate(()=>document.body.innerText);await page.screenshot({path:out+'/failure.png',fullPage:true});throw e;} finally {fs.writeFileSync(out+'/browser-validation.json',JSON.stringify(report,null,2)+'\n');await browser.close();}
