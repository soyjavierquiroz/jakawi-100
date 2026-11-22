const { chromium } = require(process.env.QA_PLAYWRIGHT_MODULE);
const fs=require('node:fs'),assert=require('node:assert/strict');
const origin=process.env.QA_ORIGIN,out=process.env.QA_EVIDENCE;assert.match(origin,/^http:\/\/10\.245\.96\.[2-9]:8001$/);
const checks=[],errors=[],contrasts=[],preview='/admin/productos-digitales/gluteo-perfecto/landings/gluteo-perfecto/preview';
async function login(p){await p.goto(origin+'/login');await p.locator('[name=email]').fill('gluteo-editor@qa.invalid');await p.locator('[name=password]').fill('Isolated-Gluteo-QA-2026');await p.locator('[data-test=login-button]').click();await p.waitForURL(u=>!u.pathname.includes('/login'));}
async function appearance(p,mode){await p.getByRole('button',{name:/^Apariencia:/}).filter({visible:true}).first().click();await p.getByRole('menuitemradio',{name:{light:'Claro',dark:'Oscuro',system:'Sistema'}[mode],exact:true}).click();await p.waitForFunction(mode=>document.documentElement.dataset.appearance===mode,mode);}
async function inspect(p,label,width){await p.waitForLoadState('networkidle');assert(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),label+' overflow'); const colors=await p.evaluate(()=>{
  const canvas=document.createElement('canvas');canvas.width=canvas.height=1;const ctx=canvas.getContext('2d',{willReadFrequently:true});
  const rgb=(color)=>{ctx.clearRect(0,0,1,1);ctx.fillStyle=color;ctx.fillRect(0,0,1,1);return [...ctx.getImageData(0,0,1,1).data];};
  const lum=c=>c.slice(0,3).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4}).reduce((s,v,i)=>s+v*[.2126,.7152,.0722][i],0);
  const ratio=(a,b)=>{const [x,y]=[lum(a),lum(b)].sort((x,y)=>x-y);return (y+.05)/(x+.05)};
  const failures=[];
  for(const el of document.querySelectorAll('p,a,span,h1,h2,h3,button,label,select,input,th,td,summary,small')){
   const rect=el.getBoundingClientRect(),s=getComputedStyle(el);
   if(el.closest('[aria-hidden=true], [inert]')||!rect.width||!rect.height||s.visibility==='hidden'||el.disabled||!([...el.childNodes].some(n=>n.nodeType===3&&n.textContent.trim())||el.matches('input,select')))continue;
   if(el.closest('[data-vsl-player], [data-landing-media]'))continue;
   let bg=[255,255,255,255],layers=[],skip=false;
   for(let n=el;n;n=n.parentElement){const cs=getComputedStyle(n);if(Number(cs.opacity)<1||cs.backgroundImage!=='none'){skip=true;break;}layers.push(rgb(cs.backgroundColor));}
   if(skip)continue;
   for(const c of layers.reverse()){const a=c[3]/255;bg=bg.map((v,i)=>i<3?c[i]*a+v*(1-a):255);}
   const fg=rgb(s.color),a=fg[3]/255,ink=fg.map((v,i)=>i<3?v*a+bg[i]*(1-a):255),contrast=ratio(ink,bg);
   const large=parseFloat(s.fontSize)>=24||(parseFloat(s.fontSize)>=18.66&&Number(s.fontWeight)>=700),required=large?3:4.5;
   if(contrast+.03<required)failures.push({text:(el.innerText||el.value||el.placeholder||'').slice(0,65),tag:el.tagName,classes:el.className,fg:s.color,bg,contrast:+contrast.toFixed(2),required});
  }
  const root=getComputedStyle(document.documentElement);return {background:root.getPropertyValue('--background').trim(),foreground:root.getPropertyValue('--foreground').trim(),failures};
 });
contrasts.push({label,width,...colors});assert.deepEqual(colors.failures,[],label+' contrast');await p.screenshot({path:`${out}/${label}-${width}.png`,fullPage:true});await p.screenshot({path:`${out}/${label}-viewport-${width}.png`,fullPage:false});checks.push({label,width,noOverflow:true});console.log('PASS visual',label,width);}
(async()=>{const browser=await chromium.launch({executablePath:process.env.QA_CHROMIUM_PATH,args:['--no-sandbox','--disable-dev-shm-usage','--renderer-process-limit=1']});
for(const width of [390,1440]){console.log('QA width',width);
 const c=await browser.newContext({viewport:{width,height:width===390?844:1000},serviceWorkers:'block'});c.setDefaultTimeout(30000);await c.route('**/*',r=>new URL(r.request().url()).origin===origin?r.continue():r.abort());const p=await c.newPage();p.on('pageerror',e=>errors.push(e.message));p.on('response',r=>{if(r.status()>=500)errors.push(r.status()+' '+r.url());});await login(p);
 for(const mode of ['light','dark','system']){await p.goto(origin+preview);await appearance(p,mode);await p.getByRole('heading',{name:'Glúteo Perfecto',exact:true,level:1}).waitFor();assert.equal(await p.locator('[data-editorial-section]').count(),9);assert.equal(await p.locator('blockquote').count(),3);assert.equal(await p.locator('details').count(),5);assert.equal(await p.locator('meta[name=robots]').getAttribute('content'),'noindex,nofollow');await inspect(p,'landing-'+mode,width);}
 await p.goto(origin+preview);await p.locator('details summary').first().click();assert(await p.locator('details').first().getAttribute('open')!==null);await p.locator('#oferta').scrollIntoViewIfNeeded();if(width===390){await p.getByRole('complementary',{name:'Acceso al producto'}).waitFor();await p.getByRole('complementary',{name:'Acceso al producto'}).locator('a').click();assert(p.url().endsWith('#oferta'));}
 await p.goto(origin+'/admin/productos-digitales/gluteo-perfecto/preview');await inspect(p,'product-preview',width);assert.equal(await p.getByRole('button',{name:/Comprar/}).count(),0);
 await p.goto(origin+'/admin/landings/gluteo-perfecto/edit');await p.getByRole('heading',{name:'Secciones editoriales',exact:true}).waitFor();assert.equal(await p.getByText('6 · Testimonios',{exact:false}).count(),1);await inspect(p,'editor',width);
 await p.locator('summary').filter({hasText:'1 · Hero'}).first().click();await p.locator('summary').filter({hasText:'1 · Hero'}).first().locator('..').locator('textarea').first().fill('Glúteo Perfecto QA');await p.getByRole('button',{name:'GUARDAR Y PREVISUALIZAR',exact:true}).first().click();await p.getByText('✓ PREVIEW ACTUALIZADO',{exact:false}).waitFor();await p.goto(origin+preview);await p.getByRole('heading',{name:'Glúteo Perfecto QA',exact:true,level:1}).waitFor();
 await p.goto(origin+'/admin/landings/gluteo-perfecto/edit');await p.getByRole('heading',{name:'Secciones editoriales',exact:true}).waitFor();await p.locator('summary').filter({hasText:'1 · Hero'}).first().click();await p.locator('summary').filter({hasText:'1 · Hero'}).first().locator('..').locator('textarea').first().fill('Glúteo Perfecto');await p.getByRole('button',{name:'GUARDAR Y PREVISUALIZAR',exact:true}).first().click();await p.getByText('✓ PREVIEW ACTUALIZADO',{exact:false}).waitFor();
 await p.goto(origin+'/explorar?type=digital&q=Glúteo');assert.equal(await p.getByRole('heading',{name:'Glúteo Perfecto',exact:true}).count(),0);assert.equal((await c.request.get(origin+'/l/gluteo-perfecto')).status(),404);checks.push({width,editorSave:true,draftHidden:true,faq:true,mobileCta:true});await c.close();
}
assert.deepEqual(errors,[]);fs.writeFileSync(out+'/browser.json',JSON.stringify({result:'PASS',checks,errors,contrasts,isolated:true},null,2));await browser.close();console.log('PASS '+checks.length+' browser checks');
})().catch(e=>{fs.writeFileSync(out+'/browser.json',JSON.stringify({result:'FAIL',error:String(e),checks,errors},null,2));console.error(e);process.exit(1)});
