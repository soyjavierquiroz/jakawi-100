import test from 'node:test';
import assert from 'node:assert/strict';
import {
    readFileSync,
    mkdtempSync,
    mkdirSync,
    writeFileSync,
    symlinkSync,
    rmSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import {
    checkRuntimeText,
    checkRuntimeArtifacts,
} from '../../scripts/check-dev-runtime.mjs';
import { replaceInertInertiaBase } from '../../scripts/inert-inertia-url-base.mjs';

test('rejects dev HTML, loopback assets, websocket and vite client', () => {
    for (const input of [
        '<script src="http://localhost:5173/@vite/client">',
        'http://127.0.0.1:5173/assets/app.js',
        '@vite/client',
        '<link href="//localhost:5173/app.css">',
        'wss://localhost:5173',
        'https://127.0.0.1/app.js',
        'fetch("http://" + "localhost:8000/api")',
        'http:\\/\\/localhost:5173',
        'http://[::1]:5173/app.js',
    ])
        assert.throws(() => checkRuntimeText(input), undefined, input);
});
test('allows non-network hostname comparisons, WebAuthn validation and ordinary text', () => {
    for (const input of [
        'hostname === "localhost"',
        'For local development, use localhost.',
        readFileSync(
            'node_modules/@simplewebauthn/browser/esm/helpers/isValidDomain.js',
            'utf8',
        ),
    ])
        assert.doesNotThrow(() => checkRuntimeText(input));
});
test('allows canonical production HTML but rejects embedded module imports', () => {
    assert.doesNotThrow(() =>
        checkRuntimeText(
            '<script src="https://jakawi.com/build/app.js"></script><link href="/build/app.css">',
        ),
    );
    assert.throws(() =>
        checkRuntimeText(
            '<script type="module">import "http://localhost:5173/app.js"</script>',
        ),
    );
});
test('retains hot-file and broken-symlink protection; scans referenced assets and HTML', () => {
    const dir = mkdtempSync(join(tmpdir(), 'jakawi-guard-'));
    try {
        mkdirSync(join(dir, 'build'));
        writeFileSync(
            join(dir, 'build/manifest.json'),
            JSON.stringify({ app: { file: 'app.js' } }),
        );
        writeFileSync(join(dir, 'build/app.js'), 'hostname === "localhost"');
        assert.doesNotThrow(() => checkRuntimeArtifacts(dir));
        symlinkSync(join(dir, 'missing'), join(dir, 'hot'));
        assert.throws(() => checkRuntimeArtifacts(dir));
        rmSync(join(dir, 'hot'));
        writeFileSync(
            join(dir, 'build/app.js'),
            'fetch("http://127.0.0.1/api")',
        );
        assert.throws(() => checkRuntimeArtifacts(dir));
        writeFileSync(join(dir, 'build/app.js'), '');
        writeFileSync(
            join(dir, 'page.html'),
            '<script src="//localhost:5173/app.js">',
        );
        assert.throws(() =>
            checkRuntimeArtifacts(dir, [join(dir, 'page.html')]),
        );
    } finally {
        rmSync(dir, { recursive: true, force: true });
    }
});
test('targeted Inertia parsing base is fail-closed and never escapes into relative links', () => {
    const original = readFileSync(
        'node_modules/@inertiajs/core/dist/index.js',
        'utf8',
    );
    const transformed = replaceInertInertiaBase(original);
    assert.throws(() => replaceInertInertiaBase('changed source'));
    const start = transformed.indexOf('function mergeDataIntoQueryString(');
    const end = transformed.indexOf('\nfunction urlWithoutHash', start);
    const parse = new Function(
        'urlHasProtocol',
        'isFormData',
        'hasIndices',
        'stringify',
        'parse',
        transformed.slice(start, end) + ';return mergeDataIntoQueryString;',
    )(
        (href) => /^[a-z][a-z\d+.-]*:/i.test(href),
        () => false,
        () => false,
        (data) => new URLSearchParams(data).toString(),
        (text) => Object.fromEntries(new URLSearchParams(text)),
    );
    assert.equal(
        parse('get', '/admin/landings', { q: 'test' })[0],
        '/admin/landings?q=test',
    );
    assert.equal(parse('get', 'relative#part', {})[0], 'relative#part');
    assert.equal(
        parse('get', 'https://canonical.example/admin?x=y', {})[0],
        'https://canonical.example/admin?x=y',
    );
});
