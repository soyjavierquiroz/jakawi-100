const { chromium } = require('/tmp/jakawi-phase11b-browser/node_modules/playwright');
const fs = require('fs');
const out = __dirname, origin = process.env.SUPPORT_QA_ORIGIN;
if (!origin || !origin.startsWith('http://10.245.95.')) throw Error('Isolated origin required');
const fixtures = JSON.parse(fs.readFileSync(`${out}/fixtures.json`, 'utf8'));
(async () => {
 const browser = await chromium.launch({headless:true,executablePath:'/root/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome',args:['--no-sandbox','--disable-dev-shm-usage']});
 const errors=[],checks=[];
 for(const width of [390,1440]) {
  const context=await browser.newContext({viewport:{width,height:width===390?844:1000},serviceWorkers:'block'});
  await context.route('**/*',r=>new URL(r.request().url()).origin===origin?r.continue():r.abort());
  const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
  async function shot(name){if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1))throw Error(`Overflow ${name} ${width}`);await page.screenshot({path:`${out}/${name}-${width}.png`,fullPage:true});}
  await page.goto(`${origin}/login`);await page.locator('#email').fill('support-admin@example.test');await page.locator('#password').fill('Support-test-only-2026!');await page.locator('[data-test="login-button"]').click();await page.waitForURL(u=>!u.pathname.startsWith('/login'));
  await page.goto(`${origin}/admin/soporte?partner=${fixtures.partner}`);
  await page.getByLabel('Tu contraseña',{exact:true}).fill('Support-test-only-2026!');await page.getByRole('button',{name:'Reautenticar',exact:true}).click();await page.getByRole('button',{name:'Reautenticar',exact:true}).waitFor({state:'hidden'});
  await page.getByLabel('Motivo obligatorio').fill('Preparación editorial aislada solicitada');await shot('entry');
  await page.getByRole('button',{name:'Gestionar Partner',exact:true}).click();await page.getByRole('button',{name:'Terminar gestión',exact:true}).waitFor();
  await page.getByText('Gestionando Dossis 43 QA como Javier QA',{exact:true}).waitFor();await shot('portal');
  await page.getByRole('link',{name:'Perfil y sedes',exact:true}).click();await page.getByLabel('Nombre',{exact:true}).fill(`Dossis 43 QA ${width}`);
  await page.getByRole('button',{name:'Guardar cambios',exact:true}).first().click();await page.getByText('Partner guardado correctamente.',{exact:true}).waitFor();await shot('profile');
  await page.getByRole('link',{name:'Promociones',exact:true}).click();await page.getByRole('link',{name:'Crear promoción',exact:true}).click();
  await page.getByLabel('Título',{exact:true}).fill(`Promoción café QA ${width}`);await page.getByRole('button',{name:'Guardar borrador',exact:true}).click();await page.getByRole('link',{name:`Promoción café QA ${width}`,exact:true}).waitFor();await shot('promotion');
  await page.getByRole('link',{name:'Desbloqueos',exact:true}).click();await shot('campaigns');
  const denied=await context.request.put(`${origin}/partner/${fixtures.other_partner}/perfil`,{data:{name:'Forbidden'}});if(denied.status()!==403)throw Error('Partner isolation');
  await page.getByRole('button',{name:'Terminar gestión',exact:true}).click();await page.waitForURL(u=>u.pathname==='/admin');checks.push({width,result:'PASS',profileSaved:true,promotionDraftSaved:true,partnerIsolation:true,adminRestored:true});await context.close();
 }
 await browser.close();if(errors.length)throw Error(JSON.stringify(errors));fs.writeFileSync(`${out}/browser.json`,JSON.stringify({result:'PASS',isolated:true,checks,errors},null,2));
})().catch(e=>{console.error(e);process.exit(1)});
