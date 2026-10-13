// Real guest cookies + Laravel signup/login, final compiled assets; isolated fixture only.
import fs from 'node:fs';
import assert from 'node:assert/strict';
const { default: puppeteer } = await import(process.env.PARTNER_BROWSER_MODULE);
const origin = process.env.PARTNER_ORIGIN ?? 'http://127.0.0.1:8098';
const out = process.env.PARTNER_ARTIFACTS ?? 'artifacts/partner-public-profile-v1';
const browser = await puppeteer.launch({ headless: true, executablePath: process.env.PARTNER_CHROMIUM, args: ['--no-sandbox', '--disable-dev-shm-usage'] });
const report = { checks: [], errors: [], mutations: [], responses: [], states: {} };
async function session() {
    const context = await browser.createBrowserContext(); const page = await context.newPage();
    await page.setViewport({ width: 390, height: 844 });
    page.on('pageerror', error => report.errors.push(error.message));
    page.on('request', req => { if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(req.method())) report.mutations.push({ method: req.method(), path: new URL(req.url()).pathname }); });
    page.on('response', res => { if (['POST', 'PUT'].includes(res.request().method())) report.responses.push({ status: res.status(), path: new URL(res.url()).pathname }); });
    return { context, page };
}
async function go(page, path) { const res = await page.goto(origin + path, { waitUntil: 'networkidle2' }); assert.equal(res.status(), 200); }
const overflow = page => page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
const state = page => page.evaluate(async () => (await fetch('/__qa/state', { headers: { Accept: 'application/json' } })).json());
const text = page => page.evaluate(() => document.body.innerText);
async function click(page, label, selector = 'a,button') {
    const handle = await page.evaluateHandle((label, selector) => [...document.querySelectorAll(selector)].find(node => node.textContent.trim() === label), label, selector);
    const element = handle.asElement(); assert(element, label); await element.evaluate(node => node.scrollIntoView({ block: 'center' })); await element.click(); await handle.dispose();
}
try {
    const primary = await session(); const page = primary.page;
    await go(page, '/dosis43'); await page.waitForSelector('[data-partner-account]');
    const copy = await text(page);
    for (const value of ['DOSIS 43 — QA', 'Café para compartir en Cochabamba', 'DOSIS Centro — QA', 'Café para compartir — QA', 'Cata de café — QA', 'Comparte tu café — QA', 'CREAR MI CUENTA', 'INICIA SESIÓN', 'MODO PRE-LANZAMIENTO']) assert(copy.includes(value), value);
    assert.equal(await overflow(page), false);
    assert.deepEqual(await page.$$eval('meta[name="robots"]', nodes => nodes.map(node => node.content)), ['noindex,nofollow']);
    assert.equal(await page.$eval('link[rel="canonical"]', node => node.href), origin + '/dosis43');
    assert.equal(await page.$eval('a[href="/beneficios/dosis-benefit-qa"]', node => node.textContent), 'VER BENEFICIO');
    await page.screenshot({ path: out + '/partner-390.png', fullPage: true });
    report.states.guest = await state(page); assert(report.states.guest.pending.length > 0); assert.equal(report.states.guest.rewards, 0);
    report.checks.push('390 guest identity/location/all three inventory types, account/login visible, native Benefit URL, notice/noindex/root canonical, no overflow, context only/no rewards');

    // Primary path explicitly never clicks the Partner-page signup CTA.
    await click(page, 'VER BENEFICIO'); await page.waitForFunction(() => location.pathname === '/l/dosis-benefit-landing-qa'); await page.waitForNetworkIdle();
    await click(page, 'QUIERO MI BENEFICIO'); await page.waitForSelector('#acquisition-name');
    await page.type('#acquisition-name', 'Nueva persona Partner QA'); await click(page, 'CONTINUAR', '[role="dialog"] button');
    await page.waitForSelector('#acquisition-email'); await page.type('#acquisition-email', 'partner-browser-new@example.test'); await click(page, 'CONTINUAR', '[role="dialog"] button');
    await page.waitForSelector('#acquisition-whatsapp'); await page.type('#acquisition-whatsapp', '71234561');
    await page.screenshot({ path: out + '/micro-opt-in-390.png', fullPage: true });
    await page.$eval('[role="dialog"] form', form => form.requestSubmit());
    await page.waitForFunction(() => !document.querySelector('[role="dialog"]'), { timeout: 15000 }); await page.waitForNetworkIdle();
    report.states.newSignup = await state(page);
    const acquired = report.states.newSignup.users.find(user => user.email === 'partner-browser-new@example.test'); assert(acquired);
    assert.equal(report.states.newSignup.user_id, acquired.id); assert.equal(acquired.relationship.acquisition_partner_id, report.states.newSignup.partner_id); assert.equal(report.states.newSignup.rewards, 0);
    for (const domain of ['redemptions', 'reservations', 'participations']) assert.equal(report.states.newSignup[domain], 0);
    report.checks.push('PRIMARY: Partner → native Benefit → applicable Marketing Landing → three-step Micro Opt-in → real NEW account; DOSIS 43 referral ownership, no Partner signup click, zero commissions/domain actions');
    await go(page, '/dosis43'); assert.equal(await page.$('[data-partner-account]'), null); assert((await text(page)).includes('VER BENEFICIO')); assert.equal(await overflow(page), false);
    report.states.authenticatedNew = await state(page); assert.equal(report.states.authenticatedNew.users.find(user => user.id === acquired.id).relationship.acquisition_partner_id, report.states.newSignup.partner_id);
    await page.screenshot({ path: out + '/authenticated-390.png', fullPage: true }); report.checks.push('390 authenticated inventory visible; signup/login block hidden; owner unchanged');
    await primary.context.close();

    const existing = await session(); await go(existing.page, '/dosis43'); await click(existing.page, 'INICIA SESIÓN'); await existing.page.waitForSelector('#email');
    await existing.page.type('#email', 'partner-existing-qa@example.test'); await existing.page.type('#password', 'password');
    await existing.page.$eval('form', form => form.requestSubmit()); await existing.page.waitForFunction(() => location.pathname === '/dosis43'); await existing.page.waitForNetworkIdle();
    report.states.existingLogin = await state(existing.page); const known = report.states.existingLogin.users.find(user => user.email === 'partner-existing-qa@example.test');
    assert.equal(report.states.existingLogin.user_id, known.id); assert.equal(known.relationship, null); assert.deepEqual(report.states.existingLogin.pending, []); assert.equal(report.states.existingLogin.rewards, 0);
    assert.equal(await existing.page.$('[data-partner-account]'), null); assert((await text(existing.page)).includes('VER EXPERIENCIA')); assert.equal(await overflow(existing.page), false);
    await existing.page.screenshot({ path: out + '/existing-login-390.png', fullPage: true }); report.checks.push('Real existing-account login returns to profile; no acquisition/referral/reward; pending candidate consumed; authenticated inventory and no account block');
    await existing.context.close();

    const direct = await session(); await go(direct.page, '/dosis43'); await click(direct.page, 'CREAR MI CUENTA'); await direct.page.waitForSelector('#name');
    await direct.page.type('#name', 'Registro directo QA'); await direct.page.type('#email', 'partner-browser-direct@example.test'); await direct.page.type('#whatsapp', '71234562');
    await direct.page.$eval('form', form => form.requestSubmit()); await direct.page.waitForFunction(() => location.pathname === '/dosis43'); await direct.page.waitForNetworkIdle();
    report.states.directSignup = await state(direct.page); const directUser = report.states.directSignup.users.find(user => user.email === 'partner-browser-direct@example.test'); assert.equal(directUser.relationship.acquisition_partner_id, report.states.directSignup.partner_id); assert.equal(report.states.directSignup.rewards, 0);
    report.checks.push('Direct profile signup uses existing register form, credits Partner only for NEW account, returns to root profile'); await direct.context.close();

    const desktop = await session(); await desktop.page.setViewport({ width: 1440, height: 1000 }); await go(desktop.page, '/dosis43'); assert.equal(await overflow(desktop.page), false); assert.equal(await desktop.page.$$('article').then(nodes => nodes.length), 3); await desktop.page.screenshot({ path: out + '/partner-1440.png', fullPage: true });
    report.checks.push('1440 guest profile regression: three truthful inventory cards, no overflow');
    await go(desktop.page, '/'); assert.equal(await desktop.page.$('a[href="/dosis43"]'), null); assert(await desktop.page.$('[data-coming-soon]')); report.checks.push('Coming Soon remains isolated with no Partner links'); await desktop.context.close();
    assert.deepEqual(report.errors, []);
    assert(report.responses.some(response => response.path === '/register' && [302, 303].includes(response.status)));
    report.pass = true;
} catch (error) { report.pass = false; report.failure = String(error); throw error; }
finally { fs.writeFileSync(out + '/browser-validation.json', JSON.stringify(report, null, 2)); await browser.close(); }
