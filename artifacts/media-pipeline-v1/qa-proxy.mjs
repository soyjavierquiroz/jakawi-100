import http from 'node:http';
import fs from 'node:fs';
const root=process.cwd();
http.createServer((req,res)=>{
 if(req.url.startsWith('/build/') || req.url === '/jakawi-wordmark.svg') {const p=root+'/app/public'+req.url.split('?')[0];res.setHeader('content-type',p.endsWith('.js')?'text/javascript':p.endsWith('.css')?'text/css':p.endsWith('.svg')?'image/svg+xml':'application/octet-stream');res.end(fs.readFileSync(p));return;}

 const initial=req.method==='GET'&&!req.headers['x-inertia']&&req.headers.accept?.includes('text/html');
 const headers={...req.headers,host:'127.0.0.1:8108'}; if(initial) headers['x-inertia']='true';
 const upstream=http.request({host:process.env.MEDIA_TEST_HOST,port:8109,path:req.url,method:req.method,headers},r=>{
 const chunks=[];r.on('data',b=>chunks.push(b));r.on('end',()=>{
 let body=Buffer.concat(chunks);
 const h={...r.headers};delete h['content-length'];delete h['transfer-encoding'];
 if(initial&&r.statusCode===200&&body.toString('utf8').startsWith('{')) {const m=JSON.parse(fs.readFileSync(root+'/app/public/build/manifest.json'));h['content-type']='text/html';delete h['x-inertia'];body=`<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/build/${m['resources/css/app.css'].file}"><script type="module" src="/build/${m['resources/js/app.tsx'].file}"></script></head><body><div id="app"></div><script data-page="app" type="application/json">${body.toString('utf8').replaceAll('<','\\u003c')}</script></body></html>`;}
 res.writeHead(r.statusCode,h);res.end(body);
 });});upstream.on('error',e=>{res.writeHead(502);res.end(String(e));});req.pipe(upstream);
}).listen(8108,'127.0.0.1');
