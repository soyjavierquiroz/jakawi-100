const { chromium } = require('/tmp/jakawi-phase11b-browser/node_modules/playwright');
const fs = require('fs');
const out = __dirname, origin = process.env.SUPPORT_QA_ORIGIN;
if (!origin || !origin.startsWith('http://10.245.95.')) throw Error('Isolated origin required');
const fixtures = JSON.parse(fs.readFileSync(`${out}/fixtures.json`, 'utf8'));
(async () => {
 const browser = await chromium.launch({headless: true, executablePath:'/root/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome', args:['--no-sandbox','--disable-dev-shm-usage']});
 const errors = [], checks = [];
 for (const width of [1440,390]) {
  const context = await browser.newContext({viewport:{width,height:width===390?844:1000}, serviceWorkers:'block'});
  await context.route('**/*', r => new URL(r.request().url()).origin === origin ? r.continue() : r.abort());
  const page = await context.newPage();
  page.on('pageerror', e => errors.push(e.message));
  async function shot(name) {
   if (await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)) throw Error('Horizontal overflow: '+name+' '+width);
   await page.screenshot({path:`${out}/${name}-${width}.png`,fullPage:true});
  }
  await page.goto(`${origin}/login`);
  await page.locator('#email').fill('support-admin@example.test');
  await page.locator('#password').fill('Support-test-only-2026!');
  await page.locator('[data-test="login-button"]').click();
  await page.waitForURL(u=>!u.pathname.startsWith('/login'));
  async function enter(params) {
   await page.goto(`${origin}/admin/soporte?${params}`);
   await page.getByLabel('Tu contraseña',{exact:true}).fill('Support-test-only-2026!');
   await page.getByRole('button',{name:'Reautenticar',exact:true}).click();
   await page.getByRole('button',{name:'Reautenticar',exact:true}).waitFor({state:'hidden'});
   await page.getByLabel('Motivo obligatorio').fill('Validación aislada de experiencia COM');
   await page.getByRole('button',{name:params.startsWith('partner=')?'Ver como Partner':'Ver como usuario',exact:true}).click();
   await page.getByRole('button',{name:'Volver a mi administración',exact:true}).waitFor();
  }
  async function leave() {
   await page.getByRole('button',{name:'Volver a mi administración',exact:true}).click();
   await page.waitForURL(u=>u.pathname==='/admin');
   if(await page.getByRole('button',{name:'Volver a mi administración',exact:true}).count()) throw Error('Support did not end');
  }
  await enter(`user=${fixtures.free}`);
  await page.getByText('Gratuito',{exact:true}).waitFor();
  await shot('free');
  await page.goto(`${origin}/perfil`);
  await page.getByText('MODO SOPORTE — Estás viendo la cuenta de Usuario Gratuito QA',{exact:true}).waitFor();
  await shot('profile');
  const denied = await context.request.put(`${origin}/mi-jakawi/perfil`,{data:{name:'MUTATED'}});
  if(denied.status()!==403) throw Error('Mutation not blocked');
  await page.goto(`${origin}/membresia/checkout`);
  await page.getByRole('heading',{name:'Modo de sólo lectura',exact:true}).waitFor();
  await shot('limited');
  await leave();
  await enter(`user=${fixtures.pioneer}`);
  await page.getByText('Pionero',{exact:true}).waitFor();
  await shot('pioneer');
  await leave();
  await enter(`partner=${fixtures.partner}`);
  await page.getByText('MODO SOPORTE — Estás viendo la cuenta de Representante QA / Partner QA',{exact:true}).waitFor();
  await shot('partner');
  const isolated = await context.request.get(`${origin}/partner/${fixtures.other_partner}`);
  if(isolated.status()!==403)throw Error('Partner isolation failed');
  await leave();
  await page.goto(`${origin}/admin/soporte?partner=${fixtures.partner}`);
  await shot('tool');
  checks.push({width,result:'PASS',free:true,pioneer:true,partner:true,mutationBlocked:true,partnerIsolation:true,adminRestored:true});
  await context.close();
 }
 if(errors.length)throw Error(JSON.stringify(errors));
 fs.writeFileSync(`${out}/browser.json`,JSON.stringify({result:'PASS',database:'isolated PostgreSQL; synthetic accounts only',checks,errors},null,2));
 await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
