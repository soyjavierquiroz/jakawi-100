import assert from 'node:assert/strict';
import { test } from 'node:test';
import { pathToFileURL } from 'node:url';
const { createYoutubeEngine, parseYoutubeId } = await import(
    pathToFileURL(process.env.VSL_YOUTUBE_MODULE).href
);
globalThis.document = { createElement: () => ({}) };
const id = 'M7lc1UVf-VE';
function setup(autoplay = true) {
    let callbacks;
    let instances = 0;
    let actualPlayer;
    const events = [];
    class Player {
        muted = false;
        volume = 100;
        time = 8;
        destroyed = false;
        plays = 0;
        constructor(_, options) {
            assert.equal(options.playerVars.controls, 0);
            assert.equal(options.host, 'https://www.youtube-nocookie.com');
            callbacks = options.events;
            instances++;
            actualPlayer = this;
        }
        mute() {
            this.muted = true;
        }
        unMute() {
            this.muted = false;
        }
        isMuted() {
            return this.muted;
        }
        getVolume() {
            return this.volume;
        }
        setVolume(value) {
            this.volume = value;
        }
        getCurrentTime() {
            return this.time;
        }
        getDuration() {
            return 20;
        }
        seekTo(time) {
            this.time = time;
        }
        playVideo() {
            this.plays++;
        }
        pauseVideo() {}
        destroy() {
            this.destroyed = true;
        }
    }
    const host = {
        append() {},
        querySelector() {
            return null;
        },
        replaceChildren() {},
    };
    const engine = createYoutubeEngine(host, {
        source: { type: 'youtube', url: id },
        origin: 'http://localhost:8094',
        autoplay,
        onState() {},
        onEvent: (e) => events.push(e),
        loadApi: async () => ({ Player }),
    });
    return {
        engine,
        events,
        async ready() {
            await Promise.resolve();
            const player = actualPlayer;
            callbacks.onReady({ target: player });
            return { player, callbacks };
        },
        get instances() {
            return instances;
        },
    };
}
test('YouTube IDs and common URLs', () => {
    for (const url of [
        id,
        `https://youtu.be/${id}?t=2`,
        `https://www.youtube.com/watch?v=${id}`,
        `https://m.youtube.com/shorts/${id}`,
        `https://youtube.com/live/${id}`,
        `https://www.youtube-nocookie.com/embed/${id}`,
    ])
        assert.equal(parseYoutubeId(url), id);
    for (const url of [
        'invalid',
        `https://evil.com/watch?v=${id}`,
        `https://youtube.com.evil.com/embed/${id}`,
        `https://u:p@youtube.com/watch?v=${id}`,
        'javascript:alert(1)',
    ])
        assert.throws(() => parseYoutubeId(url));
});
test('muted autoplay is confirmed by provider events', async () => {
    const f = setup();
    const { player, callbacks } = await f.ready();
    assert.equal(player.muted, true);
    assert.equal(player.plays, 1);
    assert.equal(f.engine.getState().status, 'requesting');
    callbacks.onStateChange({ data: 1 });
    assert.equal(f.engine.getState().status, 'playing');
    f.engine.dispose();
});
test('audio activation resets time, unmutes and confirms audio event', async () => {
    const f = setup(false);
    const { player, callbacks } = await f.ready();
    player.volume = 0;
    f.engine.activateAudio();
    assert.equal(player.time, 0);
    assert.equal(player.muted, false);
    assert.equal(player.volume, 100);
    assert.equal(
        f.events.some((e) => e.type === 'audio-activated'),
        false,
    );
    callbacks.onStateChange({ data: 1 });
    assert.equal(f.events.at(-1).type, 'audio-activated');
    f.engine.dispose();
});
test('pause/resume and replay reuse the same instance', async () => {
    const f = setup(false);
    const { player, callbacks } = await f.ready();
    const count = f.instances;
    f.engine.play();
    callbacks.onStateChange({ data: 1 });
    f.engine.pause();
    assert.equal(f.engine.getState().status, 'paused');
    f.engine.play();
    callbacks.onStateChange({ data: 0 });
    f.engine.play();
    assert.equal(player.time, 0);
    assert.equal(f.instances, count);
    f.engine.dispose();
});
test('autoplay blocked allows manual retry', async () => {
    const f = setup();
    const { callbacks } = await f.ready();
    callbacks.onAutoplayBlocked();
    assert.equal(f.engine.getState().error.kind, 'autoplay-blocked');
    f.engine.play();
    callbacks.onStateChange({ data: 1 });
    assert.equal(f.engine.getState().error, null);
    f.engine.dispose();
});
test('provider errors and disposed callbacks are inert', async () => {
    const f = setup(false);
    const { player, callbacks } = await f.ready();
    callbacks.onError({ data: 150 });
    assert.equal(f.engine.getState().error.code, 150);
    f.engine.dispose();
    const count = f.events.length;
    callbacks.onStateChange({ data: 1 });
    callbacks.onError({ data: 100 });
    assert.equal(f.events.length, count);
    assert.equal(player.destroyed, true);
});
test('disposal before API load never constructs an iframe', async () => {
    let resolve;
    let count = 0;
    const engine = createYoutubeEngine(
        { replaceChildren() {} },
        {
            source: { type: 'youtube', url: id },
            origin: 'http://localhost',
            autoplay: true,
            onState() {},
            loadApi: () =>
                new Promise((r) => {
                    resolve = r;
                }),
        },
    );
    engine.dispose();
    resolve({
        Player: class {
            constructor() {
                count++;
            }
        },
    });
    await Promise.resolve();
    assert.equal(count, 0);
});
test('API rejection is reported and invalid source never loads API', async () => {
    const engine = createYoutubeEngine(
        { replaceChildren() {} },
        {
            source: { type: 'youtube', url: id },
            origin: 'http://localhost',
            autoplay: true,
            onState() {},
            loadApi: async () => {
                throw new Error();
            },
        },
    );
    await new Promise((r) => setImmediate(r));
    assert.equal(engine.getState().error.kind, 'media');
    engine.dispose();
    const invalid = createYoutubeEngine(
        { replaceChildren() {} },
        {
            source: { type: 'youtube', url: 'bad' },
            origin: 'http://localhost',
            autoplay: true,
            onState() {},
            loadApi: () => {
                assert.fail();
            },
        },
    );
    assert.equal(invalid.getState().error.kind, 'source');
    invalid.dispose();
});

test('preference pause before ready cancels deferred autoplay', async () => {
    const f = setup();
    f.engine.pause();
    const { player } = await f.ready();
    assert.equal(player.plays, 0);
    f.engine.dispose();
});

test('blocked audio remutes and late pause preserves the error', async () => {
    const f = setup(false);
    const { player, callbacks } = await f.ready();
    f.engine.activateAudio();
    callbacks.onAutoplayBlocked();
    assert.equal(player.muted, true);
    assert.equal(f.engine.getState().error.kind, 'playback-blocked');
    callbacks.onStateChange({ data: 2 });
    assert.equal(f.engine.getState().status, 'blocked');
    f.engine.dispose();
});

// Owner-provided fixture: a valid ID must not be mistaken for provider rejection.
test('owner video ID and watch URL pass internal source validation', () => {
    assert.equal(parseYoutubeId('hI-Ecy2D8pg'), 'hI-Ecy2D8pg');
    assert.equal(
        parseYoutubeId('https://www.youtube.com/watch?v=hI-Ecy2D8pg'),
        'hI-Ecy2D8pg',
    );
});
test('provider rejection preserves code and does not report invalid source', async () => {
    const f = setup();
    const { callbacks } = await f.ready();
    callbacks.onError({ data: 150 });
    assert.equal(f.engine.getState().error.kind, 'media');
    assert.equal(f.engine.getState().error.code, 150);
    assert.match(f.engine.getState().error.message, /código 150/);
    f.engine.dispose();
});

test('course API navigation, mute and volume preserve position and readiness gates capabilities', async () => {
    const f = setup(false);
    assert.equal(f.engine.getCapabilities().seek, false);
    const { player } = await f.ready();
    assert.equal(player.plays, 0);
    assert.deepEqual(f.engine.getCapabilities(), {
        seek: true,
        mute: true,
        volume: true,
    });
    f.engine.seek(14);
    f.engine.setMuted(false);
    f.engine.setVolume(0.35);
    assert.equal(player.time, 14);
    assert.equal(player.volume, 35);
    f.engine.seek(100);
    assert.equal(player.time, 20);
    f.engine.seek(-1);
    assert.equal(player.time, 0);
    f.engine.dispose();
    assert.deepEqual(f.engine.getCapabilities(), {
        seek: false,
        mute: false,
        volume: false,
    });
});
