import { framingMetadata } from './youtube-crop';
import { useEffect, useRef, useState } from 'react';
import {
    createHtml5Engine,
    initialPlaybackState,
    shouldAutoplay,
} from './html5-engine';
import { createYoutubeEngine } from './youtube-engine';
import type { Html5Engine } from './html5-engine';
import type { JakawiVslPlayerProps } from './types';

export function useVslPlayback({
    source,
    autoplay,
    mode = 'vsl',
    onEvent,
    framing,
}: JakawiVslPlayerProps) {
    framing = typeof framing === 'string' ? { kind: framing } : framing;
    const videoRef = useRef<HTMLVideoElement>(null);
    const youtubeRef = useRef<HTMLDivElement>(null);
    const engineRef = useRef<Html5Engine | null>(null);
    const callbackRef = useRef(onEvent);
    const [state, setState] = useState(initialPlaybackState);

    useEffect(() => {
        callbackRef.current = onEvent;
    }, [onEvent]);
    useEffect(() => {
        const video = videoRef.current;
        const youtube = youtubeRef.current;
        if (source.type === 'youtube' ? !youtube : !video) return;
        const reducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );
        const connection = (
            navigator as Navigator & {
                connection?: EventTarget & { saveData?: boolean };
            }
        ).connection;
        const permitted = shouldAutoplay({
            autoplay: autoplay === 'muted' ? true : autoplay === 'off' ? false : autoplay ?? mode === 'vsl',
            reducedMotion: reducedMotion.matches,
            saveData: connection?.saveData === true,
        });
        const options = {
            origin: window.location.origin,
            autoplay: permitted,
            onState: setState,
            onEvent: (event: import('./types').VslEvent) =>
                callbackRef.current?.(event),
        };
        if (video)
            video.preload =
                mode === 'course' || permitted ? 'metadata' : 'none';
        const engine =
            source.type === 'youtube'
                ? createYoutubeEngine(youtube!, {
                      ...options,
                      source,
                      presentation: framingMetadata(
                          framing?.kind ?? 'horizontal',
                          framing?.focalX,
                          framing?.focalY,
                      ),
                  })
                : createHtml5Engine(video!, {
                      ...options,
                      source,
                      development: import.meta.env.DEV,
                      resetOnEnd: mode !== 'course',
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
    }, [source.type, source.url, autoplay, mode]);

    return { state, videoRef, youtubeRef, engineRef };
}
