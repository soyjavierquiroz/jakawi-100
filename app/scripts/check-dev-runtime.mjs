import { readFileSync, lstatSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

/** Semantic URL guard: no filename/module exceptions and no lexical localhost ban. */
export function checkRuntimeText(text, label = 'runtime') {
    // Decode ordinary JS/HTML URL escapes and literal string concatenation.
    const decoded = text
        .replace(/\\u([\da-f]{4})/gi, (_, hex) =>
            String.fromCharCode(parseInt(hex, 16)),
        )
        .replace(/\\x([\da-f]{2})/gi, (_, hex) =>
            String.fromCharCode(parseInt(hex, 16)),
        )
        .replaceAll('\\/', '/')
        .replace(/&(?:colon|#58|#x3a);/gi, ':')
        .replace(/(["'`])\s*\+\s*(["'`])/g, '');
    if (decoded.includes('@vite/client'))
        throw new Error(`${label}: @vite/client is forbidden`);
    // Includes protocol-relative URLs, HTTP(S)/WS(S), credentials, ports and IPv6 loopback.
    if (
        /(?:\b(?:https?|wss?):)?\/\/(?:[^\s/"'`<>@]*@)?(?:(?:[a-z\d-]+\.)*localhost\.?|127(?:\.\d{1,3}){3}|\[::1\])(?=[:/\s"'`<>?#)\]},;]|$)/i.test(
            decoded,
        )
    )
        throw new Error(`${label}: loopback runtime URL is forbidden`);
}
export function checkRuntimeArtifacts(publicDir, htmlFiles = []) {
    try {
        lstatSync(resolve(publicDir, 'hot'));
        throw new Error(
            'public/hot must not exist (including a broken symlink)',
        );
    } catch (error) {
        if (error.code !== 'ENOENT') throw error;
    }
    const build = resolve(publicDir, 'build');
    const raw = readFileSync(resolve(build, 'manifest.json'), 'utf8');
    checkRuntimeText(raw, 'manifest');
    const manifest = JSON.parse(raw);
    const files = new Set(
        Object.values(manifest).flatMap((entry) => [
            entry.file,
            ...(entry.css ?? []),
            ...(entry.assets ?? []),
        ]),
    );
    for (const file of files) {
        const path = resolve(build, file);
        if (!path.startsWith(build + '/'))
            throw new Error(`Invalid manifest path: ${file}`);
        if (/\.(?:js|css|html|json)$/.test(path))
            checkRuntimeText(readFileSync(path, 'utf8'), file);
    }
    for (const html of htmlFiles)
        checkRuntimeText(readFileSync(html, 'utf8'), html);
    return { assets: files.size, html: htmlFiles.length };
}
if (
    process.argv[1] &&
    resolve(process.argv[1]) === fileURLToPath(import.meta.url)
) {
    try {
        const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
        const result = checkRuntimeArtifacts(
            process.argv[2] ?? resolve(root, 'public'),
            process.argv.slice(3),
        );
        console.log(
            `Dev runtime guard PASS: ${result.assets} manifest assets; ${result.html} HTML files; hot absent`,
        );
    } catch (error) {
        console.error(`Dev runtime guard FAIL: ${error.message}`);
        process.exitCode = 1;
    }
}
