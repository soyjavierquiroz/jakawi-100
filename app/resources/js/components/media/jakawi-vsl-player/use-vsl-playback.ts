import { useEffect, useRef, useState } from 'react';
import {
    createHtml5Engine,
    initialPlaybackState,
    shouldAutoplay,
} from './html5-engine';
import type { Html5Engine } from './html5-engine';
import type { JakawiVslPlayerProps } from './types';

export function useVslPlayback({
    source,
    autoplay = true,
    onEvent,
}: JakawiVslPlayerProps) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const engineRef = useRef<Html5Engine | null>(null);
    const callbackRef = useRef(onEvent);
    const [state, setState] = useState(initialPlaybackState);

    useEffect(() => {
        callbackRef.current = onEvent;
    }, [onEvent]);
    useEffect(() => {
        const video = videoRef.current;
        if (!video) return;
        const reducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );
        const connection = (
            navigator as Navigator & {
                connection?: EventTarget & { saveData?: boolean };
            }
        ).connection;
        const permitted = shouldAutoplay({
            autoplay,
            reducedMotion: reducedMotion.matches,
            saveData: connection?.saveData === true,
        });
        video.preload = permitted ? 'metadata' : 'none';
        const engine = createHtml5Engine(video, {
            source:
                source.type === 'mp4'
                    ? { type: 'mp4', url: source.url }
                    : {
                          type: 'development-fixture',
                          url: '/__vsl-fixtures__/alpha.mp4',
                      },
            origin: window.location.origin,
            development: import.meta.env.DEV,
            autoplay: permitted,
            onState: setState,
            onEvent: (event) => callbackRef.current?.(event),
        });
        engineRef.current = engine;
        const honorPreference = () => {
            if (reducedMotion.matches || connection?.saveData) engine.pause();
        };
        reducedMotion.addEventListener('change', honorPreference);
        connection?.addEventListener('change', honorPreference);
        return () => {
            reducedMotion.removeEventListener('change', honorPreference);
            connection?.removeEventListener('change', honorPreference);
            engineRef.current = null;
            engine.dispose();
        };
    }, [source.type, source.url, autoplay]);

    return { state, videoRef, engineRef };
}
