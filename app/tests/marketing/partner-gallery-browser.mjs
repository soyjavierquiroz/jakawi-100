// Run against a read-only Partner preview. Fixture preview also supports
// /baseline (the pre-change page) and ?count=1|2|4, without database writes.
import assert from 'node:assert/strict';
import fs from 'node:fs';
const { default: puppeteer } = await import(process.env.PARTNER_BROWSER_MODULE);
const base = process.env.PARTNER_GALLERY_URL || 'http://127.0.0.1:8120';
const out = process.env.PARTNER_GALLERY_OUT || 'artifacts/partner-gallery-carousel';
fs.mkdirSync(out, { recursive: true });
const browser = await puppeteer.launch({ executablePath: process.env.PARTNER_CHROMIUM, headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] });
const report = { checks: [], responsive: [], errors: [], mode: 'Local source preview, real existing Partner response fixtures and image URLs; no application/database writes.' };
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
const page = await browser.newPage();
page.on('pageerror', error => report.errors.push(error.message));
const index = () => page.$eval('[data-partner-gallery]', n => Number(n.dataset.galleryIndex));
const scrollGallery = () => page.$eval('[data-partner-editorial]', n => { n.scrollIntoView({ block: 'start', behavior: 'instant' }); window.scrollBy(0, -120); });
async function go(path = '/dossis43') {
    await page.goto(base + path, { waitUntil: 'networkidle0' });
    await scrollGallery();
    await page.evaluate(() => document.fonts.ready);
    await page.waitForFunction(() => [...document.querySelectorAll('[data-partner-editorial] figure')].filter(f => { const r=f.getBoundingClientRect(); return getComputedStyle(f).display !== 'none' && r.right > 0 && r.left < innerWidth; }).every(f => { const i=f.querySelector('img'); return i && i.complete && i.naturalWidth > 0; }));
}
async function geometry() {
    return page.$eval('[data-partner-gallery]', n => {
        const r = n.getBoundingClientRect();
        const frames = [...n.querySelectorAll('figure')].filter(f => getComputedStyle(f).display !== 'none').map(f => {
            const b = f.getBoundingClientRect(), i = f.querySelector('img');
            return { x: b.x - r.x, width: b.width, height: b.height, alt: i.alt, src: i.src, fit: getComputedStyle(i).objectFit };
        });
        const visible = frames.filter(f => f.x + f.width > 0.5 && f.x < r.width - 0.5);
        return { width: r.width, height: r.height, visible, overflow: document.documentElement.scrollWidth > innerWidth, notice: document.body.innerText.includes('MODO PRE-LANZAMIENTO') };
    });
}
async function swipe(direction, vertical = false) {
    const r = await page.$eval('[data-partner-gallery]', n => { const b = n.getBoundingClientRect(); return { x: b.x + b.width / 2, y: b.y + Math.min(b.height / 2, 200) }; });
    const client = await page.createCDPSession();
    await client.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [r] });
    for (let i = 1; i <= 8; i++) {
        await client.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: r.x + (vertical ? 0 : direction * i * 15), y: r.y + (vertical ? -i * 15 : 0) }] });
        await sleep(16);
    }
    await client.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
    await client.detach();
    await sleep(450);
}
try {
    await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
    for (const width of [320, 375, 390, 430]) {
        await page.setViewport({ width, height: 844, hasTouch: true });
        await go();
        const g = await geometry();
        assert(!g.overflow); assert(g.notice); assert.equal(g.visible.length, 2);
        assert(Math.abs(g.visible[0].width / g.width - .84) < .002);
        assert(g.width - g.visible[1].x > 30);
        assert(g.visible.every(f => f.fit === 'cover' && Math.abs(f.width / f.height - .8) < .002));
        await page.screenshot({ path: `${out}/mobile-${width}.png`, fullPage: true });
        await page.screenshot({ path: `${out}/gallery-${width}.png` });
        report.responsive.push({ viewport: width, ...g });
    }
    report.checks.push('320/375/390/430: 84% main image, next photo peek >30px, 4:5 cover, stable height, no page overflow, prelaunch notice retained');
    await page.setViewport({ width: 1440, height: 1000 });
    await go('/baseline');
    const before = await geometryBaseline();
    const baselineScreenshot = await page.screenshot({ path: `${out}/desktop-before-1440.png`, fullPage: true });
    await go();
    const after = await geometryBaseline();
    assert.deepEqual(after, before);
    const desktopScreenshot = await page.screenshot({ path: `${out}/desktop-1440.png`, fullPage: true });
    assert(Buffer.from(desktopScreenshot).equals(Buffer.from(baselineScreenshot)), 'desktop screenshots must be pixel-identical');
    report.checks.push('1440: desktop full-page screenshot pixel-identical to pre-change page; original grid, image URLs, alts and dimensions unchanged');
    await page.setViewport({ width: 390, height: 844, hasTouch: true });
    await go();
    await swipe(-1); assert.equal(await index(), 1);
    await swipe(1); assert.equal(await index(), 0);
    await swipe(1); assert.equal(await index(), 2);
    await swipe(-1); assert.equal(await index(), 0);
    await page.focus('[data-partner-gallery]');
    for (let i = 0; i < 7; i++) {
        const previous = await index();
        await page.keyboard.press('ArrowRight'); await sleep(450);
        assert.equal(await index(), (previous + 1) % 3);
        const g = await geometry(); assert.equal(g.visible.length, 2); assert(Math.abs(g.visible[0].x) < 1);
    }
    report.checks.push('Real CDP touch swipes left/right, both loop boundaries and repeated keyboard loops preserve alignment and peek');
    const reducedIndex = await index(); await sleep(4500); assert.equal(await index(), reducedIndex);
    report.checks.push('Reduced motion: no autoplay after 4.5s, manual swipe/keyboard remain available');
    const y = await page.evaluate(() => scrollY); await swipe(-1, true);
    assert((await page.evaluate(() => scrollY)) > y + 30);
    report.checks.push('Vertical touch gesture scrolls the page normally');
    for (const count of [1, 2, 4]) {
        await go(`/dossis43?count=${count}`);
        const g = await geometry(); assert(!g.overflow);
        await page.focus('[data-partner-gallery]'); await page.keyboard.press('ArrowLeft'); await sleep(450);
        assert.equal(await index(), count === 1 ? 0 : count - 1);
        await page.keyboard.press('ArrowRight'); await sleep(450); assert.equal(await index(), 0);
        assert.equal(g.visible.length, count === 1 ? 1 : 2);
    }
    report.checks.push('1, 2 and 4 photos: stable dimensions and manual boundary loop; single photo has no clones or movement');
    await go('/elrepollo'); assert.equal((await geometry()).visible.length, 2);
    report.checks.push('Second Partner uses the same mobile gallery');
    await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'no-preference' }]);
    await go();
    await page.evaluate(() => {
        window.galleryChanges = [];
        const n = document.querySelector('[data-partner-gallery]');
        new MutationObserver(() => window.galleryChanges.push({ time: performance.now(), index: Number(n.dataset.galleryIndex) })).observe(n, { attributes: true, attributeFilter: ['data-gallery-index'] });
    });
    await sleep(6400);
    const changes = await page.evaluate(() => window.galleryChanges.filter((v, i, a) => !i || v.index !== a[i - 1].index));
    assert(changes.length >= 3, JSON.stringify(changes));
    for (let i = 1; i < changes.length; i++) {
        assert.equal(changes[i].index, (changes[i - 1].index + 1) % 3);
        assert(Math.abs(changes[i].time - changes[i - 1].time - 2000) < 120, JSON.stringify(changes));
    }
    // Boundary resets disable transitions; trigger a normal keyboard slide to inspect duration.
    await page.focus('[data-partner-gallery]'); await page.keyboard.press('ArrowRight');
    assert.equal(await page.$eval('.partner-gallery-track', n => getComputedStyle(n).transitionDuration), '0.4s');
    const paused = await index(); await sleep(3500); assert.equal(await index(), paused);
    await sleep(1000); assert.equal(await index(), (paused + 1) % 3);
    report.autoplay = changes;
    report.checks.push('Autoplay advances exactly one image every 2000ms; slide transition 400ms; focus/keyboard pause and resume after 4s');
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' })); await sleep(200);
    const offscreen = await index(); await sleep(4500); assert.equal(await index(), offscreen);
    await scrollGallery(); await sleep(2500); assert.equal(await index(), (offscreen + 1) % 3);
    report.checks.push('Offscreen: autoplay paused; entering viewport resumes');
    await page.$eval('[data-partner-gallery]', n => n.blur());
    const other = await browser.newPage(); await other.goto('about:blank'); await other.bringToFront(); await sleep(200);
    assert.equal(await page.evaluate(() => document.hidden), true);
    const hidden = await index(); await sleep(4500); assert.equal(await index(), hidden);
    await page.bringToFront(); await sleep(2500); assert.equal(await index(), (hidden + 1) % 3); await other.close();
    report.checks.push('Hidden tab: autoplay paused; visible tab resumes');
    // A touch hold must remain paused beyond the inactivity timeout.
    const client = await page.createCDPSession();
    const point = await page.$eval('[data-partner-gallery]', n => {const r=n.getBoundingClientRect();return {x:r.x+50,y:r.y+100};});
    await client.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [point] });
    const held = await index(); await sleep(4500); assert.equal(await index(), held);
    await client.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
    await sleep(3500); assert.equal(await index(), held); await sleep(1000); assert.equal(await index(), (held + 1) % 3); await client.detach();
    report.checks.push('Touch hold pauses beyond 4s; touch release restarts full 4s inactivity delay');
    await go('/dossis43?count=1'); const single = await index(); await sleep(4500); assert.equal(await index(), single);
    report.checks.push('Single photo: no autoplay or looping errors');
    assert.deepEqual(report.errors, []); report.status = 'PASS';
} catch (error) {
    report.status = 'FAIL'; report.failure = error.stack; await page.screenshot({ path: `${out}/failure.png` }); process.exitCode = 1;
} finally {
    fs.writeFileSync(`${out}/validation.json`, JSON.stringify(report, null, 2) + '\n');
    console.log(JSON.stringify(report, null, 2)); await browser.close();
}
async function geometryBaseline() {
    return page.$eval('[data-partner-editorial]', n => [...n.querySelectorAll('figure')].filter(f => getComputedStyle(f).display !== 'none').map(f => {
        const r = f.getBoundingClientRect(), i = f.querySelector('img');
        return { x: r.x, y: r.y, width: r.width, height: r.height, src: i.src, alt: i.alt, fit: getComputedStyle(i).objectFit };
    }));
}
