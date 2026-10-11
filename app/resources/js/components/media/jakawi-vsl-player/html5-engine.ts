import type {
    PlaybackError,
    PlaybackState,
    VslEvent,
    VslSource,
} from './types';

export const initialPlaybackState: PlaybackState = {
    status: 'initializing',
    ready: false,
    hasPlayed: false,
    currentTime: 0,
    duration: 0,
    muted: true,
    volume: 1,
    error: null,
};

export function shouldAutoplay(options: {
    autoplay: boolean;
    reducedMotion: boolean;
    saveData: boolean;
}): boolean {
    return options.autoplay && !options.reducedMotion && !options.saveData;
}

export function resolveSource(
    source: VslSource,
    origin: string,
    development: boolean,
): string {
    const url = new URL(source.url, origin);
    if (url.username || url.password)
        throw new Error('Video credentials are not supported.');
    if (source.type === 'mp4' && url.protocol === 'https:') return url.href;
    if (
        source.type === 'development-fixture' &&
        development &&
        source.url === '/__vsl-fixtures__/alpha.mp4' &&
        url.origin === origin
    )
        return url.href;
    throw new Error(
        'Use an HTTPS MP4 URL or the isolated development fixture.',
    );
}

/** One controller per source session. No DOM replacement or provider/global store. */
export function createHtml5Engine(
    video: HTMLVideoElement,
    options: {
        source: VslSource;
        origin: string;
        development: boolean;
        autoplay: boolean;
        onState: (state: PlaybackState) => void;
        onEvent?: (event: VslEvent) => void;
    },
) {
    let state = { ...initialPlaybackState };
    let alive = true;
    let request = 0;
    let intent: 'autoplay' | 'manual' | 'audio' | null = null;
    let audioPending = false;
    let autoplayAttempted = false;
    let expectedSource = '';
    const listeners: Array<[string, EventListener]> = [];

    function update(patch: Partial<PlaybackState>) {
        if (!alive) return;
        state = { ...state, ...patch };
        options.onState(state);
    }
    function emit(type: Exclude<VslEvent['type'], 'error'>) {
        if (alive) options.onEvent?.({ type, state });
    }
    function fail(error: PlaybackError) {
        autoplayAttempted = true;
        intent = null;
        audioPending = false;
        update({
            status: error.kind.endsWith('blocked') ? 'blocked' : 'error',
            error,
        });
        if (alive) options.onEvent?.({ type: 'error', state, error });
    }
    function listen(name: string, handler: () => void) {
        const listener: EventListener = () => {
            // Listeners from a disposed session and events for an old resource are inert.
            if (
                !alive ||
                video.src !== expectedSource ||
                (video.currentSrc && video.currentSrc !== expectedSource)
            )
                return;
            handler();
        };
        listeners.push([name, listener]);
        video.addEventListener(name, listener);
    }
    function readProgress() {
        update({
            currentTime: video.currentTime,
            duration: Number.isFinite(video.duration) ? video.duration : 0,
        });
    }
    function seek(time: number) {
        if (!alive || !state.ready || !Number.isFinite(time)) return false;
        try {
            video.currentTime = Math.min(
                Math.max(time, 0),
                state.duration || 0,
            );
            readProgress();
            return true;
        } catch {
            fail({
                kind: 'media',
                message: 'No se pudo cambiar la posición. Intenta de nuevo.',
            });
            return false;
        }
    }
    function play(kind: 'autoplay' | 'manual' | 'audio' = 'manual') {
        if (!alive || state.error?.kind === 'source') return;
        // Calling play() on an already playing element need not emit `playing` again.
        if (
            kind !== 'audio' &&
            state.status === 'playing' &&
            !video.paused &&
            !video.ended
        )
            return;
        if (kind !== 'autoplay') autoplayAttempted = true;
        const token = ++request;
        intent = kind;
        audioPending = kind === 'audio';
        update({ status: 'requesting', error: null });
        const rejected = (error: unknown) => {
            if (!alive || token !== request) return;
            const name = error instanceof Error ? error.name : '';
            if (name === 'AbortError') {
                intent = null;
                audioPending = false;
                update({ status: video.paused ? 'paused' : 'ready' });
                return;
            }
            if (kind === 'audio') video.muted = true;
            update({ muted: video.muted });
            fail({
                kind:
                    name === 'NotAllowedError'
                        ? kind === 'autoplay'
                            ? 'autoplay-blocked'
                            : 'playback-blocked'
                        : 'media',
                message:
                    name === 'NotAllowedError'
                        ? 'Pulsa reproducir para iniciar el video.'
                        : 'No se pudo reproducir el video. Intenta de nuevo.',
            });
        };
        try {
            // A resolved promise is NOT playback confirmation: only `playing` is.
            void video.play().catch(rejected);
        } catch (error) {
            rejected(error);
        }
    }
    function ready() {
        if (video.readyState < 1) return;
        const first = !state.ready;
        readProgress();
        update({
            ready: true,
            ...(state.status === 'initializing'
                ? { status: 'ready' as const }
                : {}),
        });
        if (first) emit('ready');
        if (options.autoplay && !autoplayAttempted) {
            autoplayAttempted = true;
            play('autoplay');
        }
    }
    listen('loadedmetadata', ready);
    listen('canplay', ready);
    listen('durationchange', readProgress);
    listen('timeupdate', () => {
        readProgress();
        emit('progress');
    });
    listen('volumechange', () => {
        update({ muted: video.muted, volume: video.volume });
    });
    listen('playing', () => {
        if (video.paused || video.ended || !intent) return;
        update({
            status: 'playing',
            hasPlayed: true,
            muted: video.muted,
            error: null,
        });
        emit('playing');
        if (audioPending && !video.muted && video.volume > 0) {
            audioPending = false;
            emit('audio-activated');
        }
    });
    listen('waiting', () => {
        if (video.paused || video.ended || !intent) return;
        readProgress();
        update({ status: 'buffering' });
    });
    listen('pause', () => {
        if (
            !video.paused ||
            video.ended ||
            state.status === 'ended' ||
            state.status === 'error' ||
            state.status === 'blocked'
        )
            return;
        ++request;
        intent = null;
        audioPending = false;
        readProgress();
        update({ status: 'paused' });
        emit('paused');
    });
    listen('ended', () => {
        if (!video.ended) return;
        ++request;
        intent = null;
        audioPending = false;
        video.pause();
        seek(0);
        update({ status: 'ended', currentTime: 0 });
        emit('ended');
    });
    listen('error', () => {
        if (!video.error) return;
        ++request;
        fail({
            kind: 'media',
            code: video.error.code,
            message:
                'No se pudo cargar el video. Revisa tu conexión e intenta de nuevo.',
        });
    });
    update({ ...initialPlaybackState, volume: video.volume });
    try {
        expectedSource = resolveSource(
            options.source,
            options.origin,
            options.development,
        );
        video.muted = true;
        video.defaultMuted = true;
        video.loop = false;
        video.src = expectedSource;
        video.load();
    } catch {
        fail({
            kind: 'source',
            message: 'La fuente del video no está permitida.',
        });
    }

    return {
        getState: () => state,
        play: () => {
            if (!alive) return;
            if (state.status === 'error' && state.error?.kind === 'media') {
                video.load();
            }
            if (state.status === 'ended') seek(0);
            play();
        },
        pause: () => {
            if (!alive) return;
            autoplayAttempted = true;
            ++request;
            intent = null;
            audioPending = false;
            video.pause();
            if (
                state.status !== 'ended' &&
                state.status !== 'error' &&
                state.status !== 'blocked'
            )
                update({ status: 'paused', currentTime: video.currentTime });
        },
        seek,
        activateAudio: () => {
            if (!alive || !state.ready) return;
            video.pause();
            if (!seek(0)) return;
            // Synchronous inside the button gesture; no effect/promise before play().
            video.muted = false;
            if (video.volume === 0) video.volume = 1;
            update({ muted: false, volume: video.volume });
            play('audio');
        },
        setMuted: (muted: boolean) => {
            if (!alive) return;
            video.muted = muted;
            update({ muted });
        },
        setVolume: (volume: number) => {
            if (!alive || !Number.isFinite(volume)) return;
            video.volume = Math.max(0, Math.min(1, volume));
            update({ volume: video.volume });
        },
        dispose: () => {
            if (!alive) return;
            alive = false;
            ++request;
            for (const [name, listener] of listeners)
                video.removeEventListener(name, listener);
            video.pause();
            video.removeAttribute('src');
            video.load();
        },
    };
}

export type Html5Engine = ReturnType<typeof createHtml5Engine>;
