import assert from 'node:assert/strict';
import { test } from 'node:test';
import { pathToFileURL } from 'node:url';

const { createHtml5Engine, shouldAutoplay, resolveSource } = await import(
    pathToFileURL(process.env.VSL_ENGINE_MODULE).href
);

class Video extends EventTarget {
    src = '';
    currentSrc = '';
    readyState = 0;
    duration = 12;
    muted = true;
    defaultMuted = true;
    volume = 1;
    paused = true;
    ended = false;
    error = null;
    time = 0;
    playCalls = [];
    loadCalls = 0;
    handlers = new Map();
    result = () => Promise.resolve();
    get currentTime() {
        return this.time;
    }
    set currentTime(value) {
        this.time = value;
        this.ended = false;
    }
    addEventListener(name, callback) {
        this.handlers.set(name, callback);
        super.addEventListener(name, callback);
    }
    removeEventListener(name, callback) {
        this.handlers.delete(name);
        super.removeEventListener(name, callback);
    }
    load() {
        this.loadCalls++;
    }
    removeAttribute(name) {
        if (name === 'src') this.src = '';
    }
    play() {
        this.playCalls.push({
            time: this.currentTime,
            muted: this.muted,
            volume: this.volume,
        });
        return this.result();
    }
    pause() {
        this.paused = true;
    }
    fire(name) {
        this.dispatchEvent(new Event(name));
    }
    ready() {
        this.readyState = 1;
        this.currentSrc = this.src;
        this.fire('loadedmetadata');
    }
    confirm() {
        this.paused = false;
        this.ended = false;
        this.fire('playing');
    }
}
function setup(extra = {}, video = new Video()) {
    const events = [];
    const states = [];
    const engine = createHtml5Engine(video, {
        source: { type: 'mp4', url: 'https://example.test/alpha.mp4' },
        origin: 'http://127.0.0.1:8094',
        development: false,
        autoplay: true,
        onState: (state) => states.push(state),
        onEvent: (event) => events.push(event),
        ...extra,
    });
    return { video, engine, events, states };
}
const settle = () => new Promise((resolve) => setImmediate(resolve));

void test('initial state and readiness are distinct from requested and actual playback', async () => {
    const { video, engine, events } = setup();
    assert.equal(engine.getState().status, 'initializing');
    assert.equal(video.muted, true);
    assert.equal(video.loop, false);
    video.ready();
    assert.equal(engine.getState().status, 'requesting');
    assert.equal(events.filter((event) => event.type === 'ready').length, 1);
    video.fire('canplay');
    assert.equal(video.playCalls.length, 1);
    await settle();
    assert.equal(engine.getState().hasPlayed, false);
    assert.equal(
        events.some((event) => event.type === 'playing'),
        false,
    );
    video.confirm();
    assert.equal(engine.getState().status, 'playing');
    assert.equal(engine.getState().hasPlayed, true);
});

void test('autoplay rejection is recoverable and distinct from media failure', async () => {
    const { video, engine, events } = setup();
    video.result = () =>
        Promise.reject(new DOMException('blocked', 'NotAllowedError'));
    video.ready();
    await settle();
    assert.equal(engine.getState().status, 'blocked');
    assert.equal(engine.getState().error.kind, 'autoplay-blocked');
    assert.equal(engine.getState().hasPlayed, false);
    video.result = () => Promise.resolve();
    engine.play();
    video.confirm();
    assert.equal(engine.getState().error, null);
    video.error = { code: 2 };
    video.fire('error');
    assert.equal(events.at(-1).error.kind, 'media');
    assert.equal(events.at(-1).error.code, 2);
});

void test('click-to-sound seeks to zero and requests unmuted playback synchronously', () => {
    const { video, engine, events } = setup();
    video.ready();
    video.confirm();
    video.currentTime = 5;
    engine.activateAudio();
    assert.deepEqual(video.playCalls.at(-1), {
        time: 0,
        muted: false,
        volume: 1,
    });
    assert.equal(engine.getState().status, 'requesting');
    assert.equal(
        events.some((event) => event.type === 'audio-activated'),
        false,
    );
    video.confirm();
    assert.equal(events.at(-1).type, 'audio-activated');
});

void test('failed audio activation remutes and leaves a manual retry', async () => {
    const { video, engine } = setup();
    video.ready();
    video.confirm();
    video.result = () =>
        Promise.reject(new DOMException('blocked', 'NotAllowedError'));
    engine.activateAudio();
    await settle();
    assert.equal(engine.getState().error.kind, 'playback-blocked');
    assert.equal(engine.getState().muted, true);
    video.result = () => Promise.resolve();
    engine.activateAudio();
    video.confirm();
    assert.equal(engine.getState().muted, false);
    assert.equal(engine.getState().status, 'playing');
});

void test('pause and resume preserve node, position, audio preference and source load count', () => {
    const { video, engine } = setup();
    video.ready();
    engine.activateAudio();
    video.confirm();
    video.currentTime = 4;
    engine.pause();
    video.fire('pause');
    assert.equal(engine.getState().currentTime, 4);
    assert.equal(engine.getState().status, 'paused');
    const loads = video.loadCalls;
    const src = video.src;
    engine.play();
    video.confirm();
    assert.equal(video.loadCalls, loads);
    assert.equal(video.src, src);
    assert.deepEqual(video.playCalls.at(-1), {
        time: 4,
        muted: false,
        volume: 1,
    });
});

void test('end resets to zero, stops and replay retains audio without another autoplay', () => {
    const { video, engine, events } = setup();
    video.ready();
    engine.activateAudio();
    video.confirm();
    video.time = 12;
    video.ended = true;
    video.paused = true;
    video.fire('ended');
    assert.equal(engine.getState().status, 'ended');
    assert.equal(video.currentTime, 0);
    assert.equal(video.paused, true);
    assert.equal(events.at(-1).type, 'ended');
    const count = video.playCalls.length;
    video.fire('canplay');
    video.fire('pause');
    assert.equal(engine.getState().status, 'ended');
    assert.equal(video.playCalls.length, count);
    engine.play();
    video.confirm();
    assert.deepEqual(video.playCalls.at(-1), {
        time: 0,
        muted: false,
        volume: 1,
    });
});

void test('source change removes old listeners and ignores stale callbacks and promises', async () => {
    const { video, engine, events } = setup();
    let reject;
    video.result = () =>
        new Promise((_, no) => {
            reject = no;
        });
    video.ready();
    const stale = video.handlers.get('playing');
    const oldSrc = video.src;
    video.volume = 0.3;
    engine.dispose();
    const count = events.length;
    const next = setup(
        { source: { type: 'mp4', url: 'https://example.test/next.mp4' } },
        video,
    );
    stale();
    reject(new DOMException('blocked', 'NotAllowedError'));
    await settle();
    assert.equal(events.length, count);
    assert.equal(next.engine.getState().status, 'initializing');
    assert.equal(next.engine.getState().volume, 0.3);
    video.currentSrc = oldSrc;
    video.fire('loadedmetadata');
    assert.equal(next.engine.getState().ready, false);
    video.result = () => Promise.resolve();
    video.ready();
    video.confirm();
    assert.equal(next.engine.getState().status, 'playing');
    assert.equal(video.handlers.size, 10);
});

void test('cleanup is idempotent, releases resource and makes pending work inert', async () => {
    const { video, engine, events, states } = setup();
    let reject;
    video.result = () =>
        new Promise((_, no) => {
            reject = no;
        });
    video.ready();
    engine.dispose();
    const count = events.length;
    const stateCount = states.length;
    const loads = video.loadCalls;
    engine.dispose();
    engine.play();
    engine.activateAudio();
    engine.seek(2);
    engine.setVolume(0.5);
    reject(new Error('network'));
    video.confirm();
    await settle();
    assert.equal(video.src, '');
    assert.equal(video.handlers.size, 0);
    assert.equal(video.loadCalls, loads);
    assert.equal(events.length, count);
    assert.equal(states.length, stateCount);
});

void test('reduced motion, Save-Data and explicit autoplay opt-out require manual play', () => {
    for (const preference of [
        { autoplay: true, reducedMotion: true, saveData: false },
        { autoplay: true, reducedMotion: false, saveData: true },
        { autoplay: false, reducedMotion: false, saveData: false },
    ]) {
        const { video, engine } = setup({
            autoplay: shouldAutoplay(preference),
        });
        video.ready();
        assert.equal(video.playCalls.length, 0);
        assert.equal(engine.getState().status, 'ready');
        engine.play();
        assert.equal(video.playCalls.length, 1);
    }
});

void test('manual play before metadata prevents a later autoplay request', () => {
    const { video, engine } = setup();
    engine.play();
    video.ready();
    assert.equal(video.playCalls.length, 1);
});

void test('seek, mute, volume and progress report native values', () => {
    const { video, engine, events } = setup({ autoplay: false });
    video.ready();
    engine.seek(100);
    assert.equal(video.currentTime, 12);
    engine.seek(-1);
    assert.equal(video.currentTime, 0);
    engine.setMuted(false);
    engine.setVolume(0.4);
    video.fire('volumechange');
    video.currentTime = 3;
    video.fire('timeupdate');
    assert.equal(events.at(-1).type, 'progress');
    assert.equal(events.at(-1).state.currentTime, 3);
    assert.equal(engine.getState().volume, 0.4);
    assert.equal(engine.getState().muted, false);
});

void test('source policy refuses insecure URLs, embedded credentials and production fixtures', () => {
    assert.throws(() =>
        resolveSource(
            { type: 'mp4', url: 'http://example.test/a.mp4' },
            'http://localhost',
            true,
        ),
    );
    assert.throws(() =>
        resolveSource(
            { type: 'mp4', url: 'https://user:secret@example.test/a.mp4' },
            'http://localhost',
            true,
        ),
    );
    const fixture = {
        type: 'development-fixture',
        url: '/__vsl-fixtures__/alpha.mp4',
    };
    assert.throws(() => resolveSource(fixture, 'http://localhost', false));
    assert.equal(
        resolveSource(fixture, 'http://localhost', true),
        'http://localhost/__vsl-fixtures__/alpha.mp4',
    );
    const { engine, video } = setup({
        source: { type: 'mp4', url: 'http://example.test/a.mp4' },
    });
    assert.equal(engine.getState().error.kind, 'source');
    engine.play();
    assert.equal(video.playCalls.length, 0);
});

void test('pause invalidates pending rejection and queued playing cannot resume', async () => {
    const { video, engine } = setup();
    let reject;
    video.result = () =>
        new Promise((_, no) => {
            reject = no;
        });
    video.ready();
    engine.pause();
    video.confirm();
    reject(new Error('network'));
    await settle();
    assert.equal(engine.getState().status, 'paused');
    assert.equal(engine.getState().error, null);
});

void test('preference pause before ready cancels deferred autoplay', () => {
    const { video, engine } = setup();
    engine.pause();
    video.ready();
    assert.equal(video.playCalls.length, 0);
    engine.play();
    video.confirm();
    assert.equal(engine.getState().status, 'playing');
});

void test('native pause following error retains recoverable media error', () => {
    const { video, engine } = setup();
    video.ready();
    video.confirm();
    video.error = { code: 3 };
    video.fire('error');
    video.pause();
    video.fire('pause');
    assert.equal(engine.getState().status, 'error');
    const loads = video.loadCalls;
    engine.play();
    assert.equal(video.loadCalls, loads + 1);
});

void test('synchronous play rejection is handled without an uncaught exception', () => {
    const { video, engine } = setup();
    video.result = () => {
        throw new DOMException('blocked', 'NotAllowedError');
    };
    assert.doesNotThrow(() => video.ready());
    assert.equal(engine.getState().error.kind, 'autoplay-blocked');
});

void test('aborted play is recoverable without misclassifying a network failure', async () => {
    const { video, engine } = setup();
    video.result = () =>
        Promise.reject(new DOMException('aborted', 'AbortError'));
    video.ready();
    await settle();
    assert.equal(engine.getState().status, 'paused');
    assert.equal(engine.getState().error, null);
    video.result = () => Promise.resolve();
    engine.play();
    video.confirm();
    assert.equal(engine.getState().status, 'playing');
});

void test('play on confirmed playback is idempotent without waiting for another playing event', () => {
    const { video, engine } = setup();
    video.ready();
    video.confirm();
    const requests = video.playCalls.length;
    engine.play();
    assert.equal(engine.getState().status, 'playing');
    assert.equal(video.playCalls.length, requests);
});

void test('native waiting reports buffering and playing confirms recovery', () => {
    const { video, engine, events } = setup();
    video.ready();
    video.confirm();
    video.currentTime = 3;
    video.fire('waiting');
    assert.equal(engine.getState().status, 'buffering');
    assert.equal(engine.getState().currentTime, 3);
    assert.equal(video.paused, false);
    const count = video.playCalls.length;
    video.confirm();
    assert.equal(engine.getState().status, 'playing');
    assert.equal(events.at(-1).type, 'playing');
    assert.equal(video.playCalls.length, count);
    video.fire('waiting');
    engine.pause();
    assert.equal(engine.getState().status, 'paused');
    assert.equal(video.currentTime, 3);
});

void test('preference pause after media error retains explicit reload recovery', () => {
    const { video, engine } = setup();
    video.ready();
    video.confirm();
    video.error = { code: 2 };
    video.fire('error');
    engine.pause();
    assert.equal(engine.getState().status, 'error');
    const loads = video.loadCalls;
    engine.play();
    assert.equal(video.loadCalls, loads + 1);
});
