const { chromium } = require('/tmp/jakawi-phase11b-browser/node_modules/playwright');
const fs = require('fs');
const out = __dirname, origin = process.env.PORTAL_QA_ORIGIN;
if (!origin || !origin.startsWith('http://10.245.95.')) throw Error('Isolated origin required');
const f = JSON.parse(fs.readFileSync(`${out}/fixtures.json`, 'utf8'));
(async () => {
 const errors=[], checks=[];
 const browser=await chromium.launch({headless:true,executablePath:'/root/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome',args:['--no-sandbox','--disable-dev-shm-usage']});
 for(const width of [390,1440]) {
  const context=await browser.newContext({viewport:{width,height:width===390?844:1000},serviceWorkers:'block'});
  await context.route('**/*',r=>new URL(r.request().url()).origin===origin?r.continue():r.abort());
  const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
  async function shot(name){if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1))throw Error(`Overflow ${name} ${width}`);await page.screenshot({path:`${out}/${name}-${width}.png`,fullPage:true});}
  async function login(email){await page.goto(`${origin}/login`);await page.locator('#email').fill(email);await page.locator('#password').fill('Portal-test-only-2026!');await page.locator('[data-test="login-button"]').click();await page.waitForURL(u=>!u.pathname.startsWith('/login'));}
  await login('portal-owner@example.test');
  await page.goto(`${origin}/partner/${f.slug}`);await page.getByRole('heading',{name:'Inicio',exact:true}).waitFor();
  await page.getByRole('link',{name:'Crear promoción',exact:true}).waitFor();
  await page.getByRole('link',{name:'Gestionar mis promociones',exact:true}).waitFor();
  await page.getByRole('link',{name:'Mis referidos y comisiones',exact:true}).waitFor();
  await shot('inicio-dossis');
  const metricLinks=await page.locator('a').filter({has:page.locator('p.text-2xl')}).evaluateAll(nodes=>nodes.map(n=>n.getAttribute('href')));
  if(metricLinks.length!==9)throw Error(`Expected 9 linked metrics, got ${metricLinks.length}`);
  for(const href of [...new Set(metricLinks)]) {const r=await context.request.get(`${origin}${href}`);if(r.status()!==200)throw Error(`Metric destination ${href}: ${r.status()}`);}
  await page.getByRole('link',{name:'Gestionar mis promociones',exact:true}).click();await page.getByRole('navigation',{name:'Filtrar promociones'}).waitFor();await shot('promociones-dossis');
  await page.goto(`${origin}/partner/${f.qa}/promociones`);
  for(const name of ['Publicada','En revisión','Borrador','Cambios solicitados','Vencida']) await page.getByText(name,{exact:true}).first().waitFor();
  await page.getByRole('link',{name:'Publicada QA',exact:true}).click();await page.getByText(/Esta promoción conserva su versión pública/).waitFor();
  if(await page.getByLabel('Título',{exact:true}).isEnabled())throw Error('Published editor must be locked');await shot('publicada-protegida');
  await page.goto(`${origin}/partner/${f.qa}/promociones/crear`);
  const title=`Promoción editorial QA ${width}`;
  await page.getByLabel('Título',{exact:true}).fill(title);
  await page.getByLabel('Condiciones comerciales',{exact:true}).fill('Sólo lunes. Consumo mínimo Bs 50.');
  await page.getByLabel('Vigente desde (hora Bolivia)',{exact:true}).fill('2026-11-04T12:00');
  await page.getByLabel('Vigente hasta (hora Bolivia)',{exact:true}).fill('2026-12-04T12:00');
  await page.getByLabel('Alcance',{exact:true}).selectOption('all');
  await page.getByLabel('Límite de canjes por miembro',{exact:true}).fill('2');
  await shot('editor-promocion');
  await page.getByRole('button',{name:'Guardar borrador',exact:true}).click();await page.getByRole('link',{name:title,exact:true}).click();
  if(await page.getByLabel('Vigente desde (hora Bolivia)',{exact:true}).inputValue()!=='2026-11-04T12:00')throw Error('Date roundtrip');
  await page.getByLabel('Condiciones comerciales',{exact:true}).fill('Sólo martes. Consumo mínimo Bs 60.');
  await page.getByRole('button',{name:'Guardar borrador',exact:true}).click();await page.getByRole('link',{name:title,exact:true}).click();
  await page.getByRole('button',{name:'Enviar a revisión',exact:true}).click();await page.getByRole('button',{name:'Guardar borrador',exact:true}).waitFor({state:'hidden'});
  await page.goto(`${origin}/partner/${f.qa}/promociones?estado=submitted`);await page.getByRole('link',{name:title,exact:true}).waitFor();await shot('en-revision');
  await page.goto(`${origin}/partner/${f.other}`);if(await page.locator('body').innerText().then(t=>!t.includes('403')))throw Error('Partner isolation');
  await context.close();
  const support=await browser.newContext({viewport:{width,height:width===390?844:1000},serviceWorkers:'block'});
  await support.route('**/*',r=>new URL(r.request().url()).origin===origin?r.continue():r.abort());
  const sp=await support.newPage();sp.on('pageerror',e=>errors.push(e.message));
  await sp.goto(`${origin}/login`);await sp.locator('#email').fill('portal-admin@example.test');await sp.locator('#password').fill('Portal-test-only-2026!');await sp.locator('[data-test="login-button"]').click();await sp.waitForURL(u=>!u.pathname.startsWith('/login'));
  await sp.goto(`${origin}/admin/soporte?partner=${f.partner}`);await sp.getByLabel('Tu contraseña',{exact:true}).fill('Portal-test-only-2026!');await sp.getByRole('button',{name:'Reautenticar',exact:true}).click();await sp.getByRole('button',{name:'Reautenticar',exact:true}).waitFor({state:'hidden'});
  await sp.getByLabel('Motivo obligatorio').fill('Verificación operativa aislada del Portal Partner');await sp.getByRole('button',{name:'Gestionar Partner',exact:true}).click();await sp.getByRole('button',{name:'Terminar gestión',exact:true}).waitFor();
  await sp.getByRole('link',{name:'Ver rendimiento',exact:true}).click();await sp.getByRole('heading',{name:'Rendimiento',exact:true}).waitFor();
  await sp.getByText(/Las cifras económicas, referidos y comisiones se consultan desde soporte CLUB/).waitFor();
  if(await sp.getByRole('link',{name:'Mis referidos y comisiones',exact:true}).count())throw Error('Support must not expose economic shortcut');
  const payload=await support.request.get(`${origin}/partner/${f.slug}/rendimiento`,{headers:{'X-Inertia':'true','Accept':'text/html'}}).then(r=>r.json());
  if(!payload.props.operationalOnly || JSON.stringify(payload.props.kpis).includes('savings'))throw Error('Economic fields leaked');
  if(await sp.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1))throw Error('Support overflow');
  await sp.screenshot({path:`${out}/rendimiento-soporte-${width}.png`,fullPage:true});
  await sp.getByRole('button',{name:'Terminar gestión',exact:true}).click();await sp.waitForURL(u=>u.pathname==='/admin');
  await support.close();checks.push({width,result:'PASS',metricLinks:metricLinks.length,ownerEconomicsPreserved:true,termsAndDatesSaved:true,submittedForReview:true,publishedLocked:true,partnerIsolation:true,supportOperationalOnly:true,adminRestored:true});
 }
 await browser.close();if(errors.length)throw Error(JSON.stringify(errors));fs.writeFileSync(`${out}/browser.json`,JSON.stringify({result:'PASS',isolated:true,dossisSnapshotActivity:f.snapshotActivity,checks,errors},null,2));
})().catch(e=>{console.error(e);process.exit(1)});
