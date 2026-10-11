import assert from 'node:assert/strict';
import { test } from 'node:test';
import { pathToFileURL } from 'node:url';
const { attachHlsSource } = await import(
    pathToFileURL(process.env.VSL_HLS_MODULE).href
);
const settle = () => new Promise((resolve) => setImmediate(resolve));
const url = 'https://example.test/video/playlist.m3u8';
function setup(native = false, loader) {
    const attached = [],
        errors = [];
    const video = {
        src: '',
        canPlayType: () => (native ? 'maybe' : ''),
        load() {
            this.loads = (this.loads ?? 0) + 1;
        },
    };
    const session = attachHlsSource(
        video,
        url,
        {
            onAttached: (src) => attached.push(src),
            onError: (error) => errors.push(error),
        },
        loader,
    );
    return { video, attached, errors, session };
}
class FakeHls {
    static Events = { MEDIA_ATTACHED: 'attached', ERROR: 'error' };
    static ErrorTypes = { NETWORK_ERROR: 'network', MEDIA_ERROR: 'media' };
    static isSupported = () => true;
    static instances = [];
    handlers = new Map();
    destroyed = 0;
    constructor(config) {
        this.config = config;
        FakeHls.instances.push(this);
    }
    on(event, callback) {
        this.handlers.set(event, callback);
    }
    loadSource(value) {
        this.source = value;
    }
    attachMedia(video) {
        video.src = 'blob:test';
        this.handlers.get('attached')();
    }
    destroy() {
        this.destroyed++;
    }
}
const loader = async () => ({ default: FakeHls });
test('native HLS does not import hls.js', () => {
    const result = setup(true, () => {
        throw Error('must not load');
    });
    assert.equal(result.video.src, url);
    assert.deepEqual(result.attached, [url]);
    result.session.dispose();
});
test('MSE attaches existing video with uncapped automatic quality and destroys once', async () => {
    const result = setup(false, loader);
    await settle();
    const hls = FakeHls.instances.at(-1);
    assert.equal(hls.source, url);
    assert.deepEqual(hls.config, { debug: false });
    assert.deepEqual(result.attached, ['blob:test']);
    result.session.dispose();
    result.session.dispose();
    assert.equal(hls.destroyed, 1);
    hls.handlers.get('error')('', { fatal: true, type: 'network' });
    assert.equal(result.errors.length, 0);
});
test('disposing during deferred import never creates an orphan instance', async () => {
    let resolve;
    const before = FakeHls.instances.length;
    const result = setup(
        false,
        () =>
            new Promise((done) => {
                resolve = done;
            }),
    );
    result.session.dispose();
    resolve({ default: FakeHls });
    await settle();
    assert.equal(FakeHls.instances.length, before);
    assert.equal(result.attached.length, 0);
});
test('unsupported MSE and failed import return recoverable media errors', async () => {
    class Unsupported extends FakeHls {
        static isSupported = () => false;
    }
    for (const load of [
        async () => ({ default: Unsupported }),
        async () => {
            throw Error('offline');
        },
    ]) {
        const result = setup(false, load);
        await settle();
        assert.equal(result.errors[0].kind, 'media');
        result.session.dispose();
    }
});
test('transient errors use library retries; fatal network and decoder errors release transport', async () => {
    for (const type of ['network', 'media']) {
        const result = setup(false, loader);
        await settle();
        const hls = FakeHls.instances.at(-1);
        hls.handlers.get('error')('', { fatal: false, type });
        assert.equal(result.errors.length, 0);
        hls.handlers.get('error')('', {
            fatal: true,
            type,
            response: { code: 403 },
        });
        assert.equal(result.errors[0].kind, 'media');
        assert.match(
            result.errors[0].message,
            type === 'network' ? /HTTP 403/ : /decodificar/,
        );
        assert.equal(hls.destroyed, 1);
        result.session.dispose();
        assert.equal(hls.destroyed, 1);
    }
});
