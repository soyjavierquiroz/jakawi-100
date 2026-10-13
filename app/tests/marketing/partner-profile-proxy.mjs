// Serves final local assets and relays to the isolated Docker PHP HTTP kernel.
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
const root = process.cwd();
const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="960" height="480"><rect width="960" height="480" fill="#f6ebd9"/><circle cx="480" cy="240" r="130" fill="#603f2c"/><circle cx="480" cy="240" r="90" fill="#dbb58b"/><text x="480" y="420" text-anchor="middle" font-size="30" fill="#231f20">DOSIS 43 — QA</text></svg>';
http.createServer((req, res) => {
    const pathname = new URL(req.url, 'http://127.0.0.1').pathname;
    if (pathname.startsWith('/build/')) {
        const file = path.resolve(root, 'app/public', '.' + pathname);
        if (!file.startsWith(root + '/app/public/build/') || !fs.existsSync(file)) { res.writeHead(404); res.end(); return; }
        res.setHeader('Content-Type', file.endsWith('.js') ? 'text/javascript' : file.endsWith('.css') ? 'text/css' : 'application/octet-stream');
        res.end(fs.readFileSync(file)); return;
    }
    if (pathname.startsWith('/storage/') && pathname.includes('partner-profile-qa-')) { res.setHeader('Content-Type', 'image/svg+xml'); res.end(svg); return; }
    if (['/jakawi-logo.svg', '/jakawi-mark.svg'].includes(pathname)) { res.setHeader('Content-Type', 'image/svg+xml'); res.end(fs.readFileSync(root + '/app/public' + pathname)); return; }
    const initial = req.method === 'GET' && !req.headers['x-inertia'] && req.headers.accept?.includes('text/html');
    const headers = { ...req.headers, host: '127.0.0.1:8098' }; if (initial) headers['x-inertia'] = 'true';
    const upstream = http.request({ host: process.env.PARTNER_TEST_HOST, port: 8099, path: req.url, method: req.method, headers }, response => {
        let body = ''; response.on('data', data => body += data); response.on('end', () => {
            const h = { ...response.headers }; delete h['content-length']; delete h['transfer-encoding'];
            if (initial && response.statusCode === 200 && body.startsWith('{')) {
                const manifest = JSON.parse(fs.readFileSync(root + '/app/public/build/manifest.json'));
                h['content-type'] = 'text/html'; delete h['x-inertia'];
                body = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/build/${manifest['resources/css/app.css'].file}"><script type="module" src="/build/${manifest['resources/js/app.tsx'].file}"></script></head><body><div id="app"></div><script data-page="app" type="application/json">${body.replaceAll('<', '\\u003c')}</script></body></html>`;
            }
            res.writeHead(response.statusCode, h); res.end(body);
        });
    });
    upstream.on('error', error => { res.writeHead(502); res.end(String(error)); }); req.pipe(upstream);
}).listen(8098, '127.0.0.1');
