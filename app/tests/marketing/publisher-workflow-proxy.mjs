import http from 'node:http';
import fs from 'node:fs';
const root=process.cwd();
http.createServer((req,res)=>{
 if(req.url.startsWith('/build/')) {const p=root+'/app/public'+req.url.split('?')[0];res.setHeader('content-type',p.endsWith('.js')?'text/javascript':p.endsWith('.css')?'text/css':'application/octet-stream');res.end(fs.readFileSync(p));return;}
 if(req.url.startsWith('/storage/')) {res.setHeader('content-type','image/svg+xml');res.end('<svg xmlns="http://www.w3.org/2000/svg" width="240" height="160"><rect width="240" height="160" fill="tan"/></svg>');return;}
 const initial=req.method==='GET'&&!req.headers['x-inertia']&&req.headers.accept?.includes('text/html');
 const headers={...req.headers,host:'127.0.0.1:8098'}; if(initial) headers['x-inertia']='true';
 const upstream=http.request({host:process.env.PUBLISHER_TEST_HOST??'172.20.0.3',port:8099,path:req.url,method:req.method,headers},r=>{
 let body='';r.on('data',b=>body+=b);r.on('end',()=>{
 const h={...r.headers};delete h['content-length'];delete h['transfer-encoding'];
 if(initial&&r.statusCode===200&&body.startsWith('{')) {const m=JSON.parse(fs.readFileSync(root+'/app/public/build/manifest.json'));h['content-type']='text/html';delete h['x-inertia'];body=`<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/build/${m['resources/css/app.css'].file}"><script type="module" src="/build/${m['resources/js/app.tsx'].file}"></script></head><body><div id="app"></div><script data-page="app" type="application/json">${body.replaceAll('<','\\u003c')}</script></body></html>`;}
 res.writeHead(r.statusCode,h);res.end(body);
 });});upstream.on('error',e=>{res.writeHead(502);res.end(String(e));});req.pipe(upstream);
}).listen(8098,'127.0.0.1');
