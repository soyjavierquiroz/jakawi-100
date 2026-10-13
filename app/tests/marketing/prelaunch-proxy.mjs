import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
const root=path.resolve('app/public');
http.createServer((req,res)=>{
 const urlPath=new URL(req.url,'http://qa.invalid').pathname;
 const p=path.resolve(root,'.'+urlPath);
 if(p.startsWith(root+'/')&&fs.existsSync(p)&&fs.statSync(p).isFile()) {
  res.setHeader('content-type',p.endsWith('.js')?'text/javascript':p.endsWith('.css')?'text/css':p.endsWith('.svg')?'image/svg+xml':p.endsWith('.woff2')?'font/woff2':'application/octet-stream');res.end(fs.readFileSync(p));return;
 }
 const headers={...req.headers,host:'127.0.0.1:8098'};
 const up=http.request({host:process.env.PRELAUNCH_TEST_HOST,port:8099,path:req.url,method:req.method,headers},r=>{const h={...r.headers};delete h['transfer-encoding'];res.writeHead(r.statusCode,h);r.pipe(res)});
 up.on('error',e=>{res.writeHead(502);res.end(String(e));});req.pipe(up);
}).listen(8098,'127.0.0.1');
