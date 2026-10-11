import type { PlaybackError } from './types';
import type Hls from 'hls.js';

/** Owns only the HLS transport; playback and controls stay in the HTML5 engine. */
export function attachHlsSource(
    video: HTMLVideoElement,
    url: string,
    callbacks: {
        onAttached: (mediaUrl: string) => void;
        onError: (error: PlaybackError) => void;
    },
    loadHls: () => Promise<{ default: typeof Hls }> = () => import('hls.js'),
) {
    let alive = true;
    let hls: Hls | undefined;
    const fail = (message: string, code?: number) => {
        if (!alive) return;
        hls?.destroy();
        hls = undefined;
        callbacks.onError({ kind: 'media', message, code });
    };
    if (video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = url;
        callbacks.onAttached(video.src);
        video.load();
    } else {
        void loadHls()
            .then(({ default: HlsClass }) => {
                if (!alive) return;
                if (!HlsClass.isSupported()) {
                    fail(
                        'Este navegador no soporta HLS. Usa un navegador compatible e intenta de nuevo.',
                    );
                    return;
                }
                // Default ABR: automatic start/level selection, without a resolution cap.
                hls = new HlsClass({ debug: false });
                hls.on(HlsClass.Events.MEDIA_ATTACHED, () => {
                    if (alive) callbacks.onAttached(video.src);
                });
                hls.on(HlsClass.Events.ERROR, (_event, data) => {
                    if (!alive || !data.fatal) return;
                    // HLS.js retries transient network/segment failures internally.
                    // Exhausted network or decoding failures expose the existing retry control.
                    const code = data.response?.code;
                    const message =
                        data.type === HlsClass.ErrorTypes.NETWORK_ERROR
                            ? code
                                ? `No se pudo cargar HLS (HTTP ${code}). Revisa el acceso al video e intenta de nuevo.`
                                : 'No se pudo cargar HLS. Revisa tu conexión y el acceso CORS e intenta de nuevo.'
                            : data.type === HlsClass.ErrorTypes.MEDIA_ERROR
                              ? 'No se pudo decodificar el video HLS. Intenta de nuevo.'
                              : 'No se pudo reproducir HLS. Intenta de nuevo.';
                    fail(message, code);
                });
                hls.loadSource(url);
                hls.attachMedia(video);
            })
            .catch(() =>
                fail(
                    'No se pudo preparar HLS. Revisa tu conexión e intenta de nuevo.',
                ),
            );
    }
    return {
        dispose() {
            if (!alive) return;
            alive = false;
            hls?.destroy();
            hls = undefined;
        },
    };
}
