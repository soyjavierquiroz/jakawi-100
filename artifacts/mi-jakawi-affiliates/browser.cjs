const { chromium } = require('/tmp/jakawi-phase11b-browser/node_modules/playwright');
const fs = require('fs');
const origin=process.env.MI_QA_ORIGIN, out=__dirname;
if(!origin?.startsWith('http://10.245.96.'))throw Error('Isolated origin required');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'/root/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome',args:['--no-sandbox','--disable-dev-shm-usage','--renderer-process-limit=1']});
 const checks=[], errors=[];
 for(const type of ['free','pioneer','standard']) {
  const context=await browser.newContext({viewport:{width:390,height:844},serviceWorkers:'block'});
  await context.route('**/*',r=>new URL(r.request().url()).origin===origin?r.continue():r.abort());
  const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
  await page.goto(origin+'/login');await page.locator('#email').fill(type+'@example.test');await page.locator('#password').fill('MiJakawi-test-only-2026!');await page.locator('[data-test="login-button"]').click();await page.waitForURL(u=>!u.pathname.startsWith('/login'));
  for(const width of [390,1440])for(const theme of ['light','dark']) {
   await page.emulateMedia({colorScheme:theme});
   await page.setViewportSize({width,height:width===390?844:1000});
   await page.evaluate(theme=>localStorage.setItem('appearance',theme),theme);
   await page.goto(origin+'/mi-jakawi');await page.locator('.mi-membership').waitFor();await page.evaluate(()=>document.fonts.ready);
   const card=page.getByRole('region',{name:'Tu membresía',exact:true});
   await card.getByRole('heading',{name:type==='free'?'Cuenta gratuita':type==='pioneer'?'Pionero · Activa':type==='standard'?'Estándar · Activa':'Estándar · Vencida',exact:true}).waitFor();
   const activate=page.getByRole('link',{name:'Activar mi membresía'});
   if(['pioneer','standard'].includes(type)){if(await activate.count())throw Error('Repurchase offered '+type)}
   else {if(await activate.count()!==2)throw Error('Activation missing');for(const a of await activate.all())if(await a.getAttribute('href')!=='https://jakawi.club/membresia')throw Error('Wrong CLUB CTA');}
   for(const a of await page.getByRole('link',{name:'Explorar beneficios',exact:true}).all())if(await a.getAttribute('href')!=='/beneficios')throw Error('Wrong catalog CTA');
   if(type==='pioneer')await card.getByText(/15 meses después del lanzamiento/).waitFor();
   if(type==='standard'){await page.getByRole('link',{name:'Descubre tu ciudad'}).waitFor();await page.getByRole('navigation',{name:'Paginación',exact:true}).waitFor();}
   else {if(await page.getByRole('navigation',{name:'Paginación',exact:true}).count())throw Error('Empty pagination');if(await page.getByText('No hay resultados.',{exact:true}).count())throw Error('Duplicate empty');await page.getByRole('link',{name:'Explorar retos',exact:true}).waitFor();}
   const affiliate=page.getByRole('region',{name:'Invita amigos. Gana comisiones.',exact:true});
   await affiliate.waitFor();
   const affiliateLink=affiliate.getByRole('link',{name:'Mis referidos y ganancias'});
   if(await affiliateLink.getAttribute('href')!=='https://jakawi.club/afiliados')throw Error('Wrong affiliate destination');
   if(await affiliateLink.getAttribute('target'))throw Error('Expected normal navigation');
   const text=await affiliate.innerText();
   if(!text.includes('pago elegibles y verificadas')||!text.includes('sin comprar una membresía'))throw Error('Affiliate terms missing');
   for(const img of await page.locator('.mi-benefit img').all()){await img.scrollIntoViewIfNeeded();await img.evaluate(i=>i.decode());}await page.evaluate(()=>scrollTo(0,0));
   const colors=await page.evaluate(()=>{const m=document.querySelector('.mi-jakawi'),c=document.querySelector('.mi-membership');return {background:getComputedStyle(m).backgroundColor,card:getComputedStyle(c).backgroundColor,overflow:document.documentElement.scrollWidth>innerWidth+1,images:[...document.querySelectorAll('.mi-benefit img')].every(i=>i.complete&&i.naturalWidth>0)}});
   if(colors.overflow)throw Error('Overflow '+type+width+theme);
   if(colors.background!==(theme==='light'?'rgb(255, 248, 242)':'rgb(17, 17, 17)'))throw Error('Background '+JSON.stringify(colors));
   if(colors.card!==(theme==='light'?'rgb(255, 255, 255)':'rgb(27, 27, 27)'))throw Error('Card surface '+JSON.stringify(colors));
   if(!colors.images)throw Error('Image unavailable');
   await page.screenshot({path:`${out}/${type}-${theme}-${width}.png`,fullPage:true});
   checks.push({type,width,theme,...colors,result:'PASS'});
  }
  const detail=await context.request.get(new URL(await page.locator('.mi-benefit').first().getAttribute('href'),origin).href);if(detail.status()!==200)throw Error('Benefit detail');
  await page.goto(origin+'/perfil');
  await page.getByRole('navigation',{name:'Navegación personal'}).waitFor({timeout:60000});
  const personal=page.getByRole('navigation',{name:'Navegación personal'});
  if(await personal.getByRole('link',{name:'Ganar con JAKAWI'}).getAttribute('href')!=='https://jakawi.club/afiliados')throw Error('Personal nav destination');
  console.log(type,'PASS; errors:',JSON.stringify(errors));
  await context.close();
 }
 await browser.close();if(errors.length)throw Error(JSON.stringify(errors));
 fs.writeFileSync(out+'/browser.json',JSON.stringify({result:'PASS',isolated:true,checks,errors},null,2));
})().catch(e=>{console.error(e);process.exit(1)});
