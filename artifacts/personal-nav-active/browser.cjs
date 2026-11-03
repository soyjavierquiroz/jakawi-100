const {chromium}=require('/tmp/jakawi-phase11b-browser/node_modules/playwright');
const fs=require('fs');const origin=process.env.NAV_QA_ORIGIN,out=__dirname;
if(origin!=='http://10.245.96.4')throw Error('Isolated origin only');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'/root/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome',args:['--no-sandbox','--disable-dev-shm-usage','--renderer-process-limit=1']});
 const context=await browser.newContext({serviceWorkers:'block'});await context.route('**/*',r=>new URL(r.request().url()).origin===origin?r.continue():r.abort());
 const page=await context.newPage(),errors=[],checks=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(origin+'/login');await page.locator('#email').fill('navigation@example.test');await page.locator('#password').fill('Navigation-QA-only-2026!');await page.locator('[data-test="login-button"]').click();await page.waitForURL(u=>u.pathname!=='/login');
 const nav=()=>page.locator('nav[aria-label="Navegación principal"]:visible');
 async function check(label,theme){
  const active=nav().locator('[aria-current="page"]');await active.filter({hasText:label}).waitFor();
  if(await active.count()!==1||await active.innerText()!==label)throw Error('Multiple or wrong active links');
  await page.waitForTimeout(200);
  const style=await active.evaluate(a=>{const c=getComputedStyle(a);return{color:c.color,weight:c.fontWeight,line:c.textDecorationLine,thickness:c.textDecorationThickness,offset:c.textUnderlineOffset,coral:c.textDecorationColor}});
  if(style.color!==(theme==='dark'?'rgb(255, 255, 255)':'rgb(17, 17, 17)')||style.weight!=='600'||style.line!=='underline'||style.thickness!=='2px'||style.offset!=='8px'||style.coral!=='rgb(255, 90, 79)')throw Error(JSON.stringify(style));
  for(const link of await nav().locator('a:not([aria-current])').all())if(await link.evaluate(a=>getComputedStyle(a).textDecorationLine)!=='none')throw Error('Inactive underline');
  if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1))throw Error('Overflow');
  return style;
 }
 for(const width of [390,1440])for(const theme of ['light','dark']){
  await page.setViewportSize({width,height:width===390?844:1000});await page.emulateMedia({colorScheme:theme});await page.evaluate(t=>localStorage.setItem('appearance',t),theme);
  await page.goto(origin+'/beneficios');
  for(const [path,label,slug] of [['/beneficios','Explorar','explore'],['/mi-jakawi','Mi JAKAWI','account'],['/perfil','Perfil','profile']]){
   if(path!=='/beneficios'){
    await page.evaluate(()=>window.__navigationDocument='retained');await nav().getByRole('link',{name:label,exact:true}).click();await page.waitForURL(u=>u.pathname===path);
    if(await page.evaluate(()=>window.__navigationDocument)!=='retained')throw Error('Inertia replaced document');
   }
   const style=await check(label,theme);await page.evaluate(()=>document.fonts.ready);
   const inactive=nav().locator('a:not([aria-current])').first();await inactive.hover();await page.waitForTimeout(200);
   const hoverColor=await inactive.evaluate(a=>getComputedStyle(a).color);if(hoverColor!==style.color)throw Error('Hover color');
   await page.mouse.move(0,0);await page.waitForTimeout(200);await page.keyboard.press('Tab');await nav().locator('[aria-current]').focus();
   if(!await nav().locator('[aria-current]').evaluate(a=>a.matches(':focus-visible')&&getComputedStyle(a).outlineWidth==='2px'))throw Error('Focus missing');
   await page.evaluate(()=>document.activeElement.blur());
   await page.screenshot({path:`${out}/${slug}-${theme}-${width}.png`});
   await page.reload();await check(label,theme);checks.push({path,label,width,theme,style,inertia:true,refresh:true,result:'PASS'});
  }
  for(const [direction,label] of [['back','Mi JAKAWI'],['back','Explorar'],['forward','Mi JAKAWI'],['forward','Perfil']]){if(direction==='back')await page.goBack();else await page.goForward();await check(label,theme);}
  await page.goto(origin+'/perfil');await check('Perfil',theme);
  console.log(width,theme,'PASS: three states, Inertia, direct URL, refresh, back/forward, hover/focus');
 }
 await browser.close();if(errors.length)throw Error(JSON.stringify(errors));fs.writeFileSync(out+'/browser.json',JSON.stringify({result:'PASS',isolated:true,checks,errors,history:true,direct_link:true},null,2));
})().catch(e=>{console.error(e);process.exit(1)});
