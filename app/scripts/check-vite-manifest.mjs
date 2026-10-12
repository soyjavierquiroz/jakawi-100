import { existsSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { inertiaPages } from './inertia-pages.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const manifestPath = resolve(process.argv[2] ?? `${root}/public/build/manifest.json`);
try {
    if (existsSync(resolve(dirname(manifestPath), '../hot'))) throw new Error('Production artifact FAIL: public/hot must not exist');
    const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
    const errors = [];
    for (const source of ['resources/css/app.css', 'resources/js/app.tsx', ...inertiaPages(root)]) {
        if (!manifest[source]?.isEntry) errors.push(`Missing source entry: ${source}`);
    }
    for (const [source, entry] of Object.entries(manifest)) {
        for (const asset of [entry.file, ...(entry.css ?? []), ...(entry.assets ?? [])]) {
            if (!asset || !existsSync(resolve(dirname(manifestPath), asset))) {
                errors.push(`Missing asset: ${source} -> ${asset}`);
            }
        }
        for (const dependency of [...(entry.imports ?? []), ...(entry.dynamicImports ?? [])]) {
            if (!manifest[dependency]) errors.push(`Missing manifest dependency: ${source} -> ${dependency}`);
        }
    }
    if (errors.length) throw new Error(errors.join('\n'));
    console.log(`Vite manifest PASS: ${inertiaPages(root).length} Inertia pages; all assets and dependencies exist.`);
} catch (error) {
    console.error(error.message);
    process.exitCode = 1;
}
