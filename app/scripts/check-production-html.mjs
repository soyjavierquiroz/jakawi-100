import { readFileSync } from 'node:fs';

try {
    const [htmlPath, manifestPath] = process.argv.slice(2);
    if (!htmlPath || !manifestPath) throw new Error('Usage: node check-production-html.mjs HTML MANIFEST');
    const html = readFileSync(htmlPath, 'utf8').replace(/\\\//g, '/');
    const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
    if (/127\.0\.0\.1:|localhost:|@vite\/client|\/(?:resources\/(?:js|css)|@react-refresh|@fs|@id)\//i.test(html)) {
        throw new Error('Development server/source reference in candidate HTML');
    }
    if (/Unable to locate file in Vite manifest|Vite manifest not found/i.test(html)) {
        throw new Error('Vite manifest exception in candidate HTML');
    }
    const references = [...html.matchAll(/<(?:script|link)\b[^>]*\b(?:src|href)=["']([^"']+)["']/gi)]
        .map(match => new URL(match[1], 'https://release.invalid').pathname)
        .filter(path => path.startsWith('/build/'));
    const files = new Set(Object.values(manifest).flatMap(entry => [entry.file, ...(entry.css ?? []), ...(entry.assets ?? [])]).map(file => '/build/' + file));
    if (!references.includes('/build/' + manifest['resources/js/app.tsx']?.file)) throw new Error('Compiled app entry absent from candidate HTML');
    if (!Object.entries(manifest).some(([source, entry]) => source.startsWith('resources/js/pages/') && entry.isEntry && references.includes('/build/' + entry.file))) {
        throw new Error('Compiled Inertia page entry absent from candidate HTML');
    }
    if (references.some(path => !files.has(path))) throw new Error('HTML references assets outside candidate manifest');
    console.log('Production HTML PASS: compiled app/page assets; no development references');
} catch (error) {
    console.error(error.message);
    process.exitCode = 1;
}
