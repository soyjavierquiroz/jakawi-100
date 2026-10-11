import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import styles from './JakawiVslPlayer.module.css';
import { PlaybackControls } from './PlaybackControls';
import type { JakawiVslPlayerProps } from './types';
import { useVslPlayback } from './use-vsl-playback';

type WebkitVideo = HTMLVideoElement & { webkitEnterFullscreen?: () => void };

export function JakawiVslPlayer(props: JakawiVslPlayerProps) {
    const {
        poster,
        title = 'Video',
        fit = 'cover',
        focalPosition = 'center',
        className,
    } = props;
    const { state, videoRef, engineRef } = useVslPlayback(props);
    const containerRef = useRef<HTMLDivElement>(null);
    const [fullscreenSupported, setFullscreenSupported] = useState(false);
    const [fullscreenError, setFullscreenError] = useState('');
    const fullscreenRequestRef = useRef(0);
    useEffect(() => {
        setFullscreenSupported(
            Boolean(
                document.fullscreenEnabled ||
                (videoRef.current as WebkitVideo | null)?.webkitEnterFullscreen,
            ),
        );
        return () => {
            ++fullscreenRequestRef.current;
        };
    }, [videoRef]);
    const fullscreen = () => {
        setFullscreenError('');
        const request = ++fullscreenRequestRef.current;
        const reportError = (message: string) => {
            if (request === fullscreenRequestRef.current)
                setFullscreenError(message);
        };
        try {
            if (document.fullscreenElement) {
                void document
                    .exitFullscreen()
                    .catch(() =>
                        reportError('No se pudo salir de pantalla completa.'),
                    );
            } else if (document.fullscreenEnabled && containerRef.current) {
                void containerRef.current
                    .requestFullscreen()
                    .catch(() =>
                        reportError('Pantalla completa no disponible.'),
                    );
            } else {
                (
                    videoRef.current as WebkitVideo | null
                )?.webkitEnterFullscreen?.();
            }
        } catch {
            reportError('Pantalla completa no disponible.');
        }
    };
    const manual =
        state.status !== 'playing' &&
        state.status !== 'requesting' &&
        state.status !== 'buffering';
    const showPoster = !state.hasPlayed || state.status === 'ended';
    return (
        <div
            ref={containerRef}
            role="region"
            aria-label={title}
            aria-busy={
                state.status === 'requesting' || state.status === 'buffering'
            }
            className={cn(
                styles.player,
                'mx-auto w-full max-w-md overflow-hidden rounded-xl border border-border bg-surface text-surface-foreground',
                className,
            )}
        >
            <div
                className={cn(
                    styles.frame,
                    'relative aspect-[3/4] bg-surface-muted',
                )}
            >
                <video
                    ref={videoRef}
                    poster={poster}
                    playsInline
                    muted
                    aria-label={title}
                    className="absolute inset-0 size-full"
                    style={{ objectFit: fit, objectPosition: focalPosition }}
                />
                {showPoster && poster && (
                    <img
                        src={poster}
                        alt=""
                        className="pointer-events-none absolute inset-0 size-full"
                        style={{
                            objectFit: fit,
                            objectPosition: focalPosition,
                        }}
                    />
                )}
                {manual && (
                    <div className="absolute inset-0 flex items-center justify-center">
                        <Button
                            type="button"
                            size="lg"
                            disabled={state.error?.kind === 'source'}
                            onClick={() => engineRef.current?.play()}
                        >
                            {state.status === 'ended'
                                ? 'Volver a reproducir'
                                : state.status === 'error'
                                  ? 'Intentar de nuevo'
                                  : 'Reproducir video'}
                        </Button>
                    </div>
                )}
                {state.status === 'playing' && state.muted && (
                    <div className="absolute inset-x-0 bottom-4 flex justify-center">
                        <Button
                            type="button"
                            size="lg"
                            onClick={() => engineRef.current?.activateAudio()}
                        >
                            ACTIVAR SONIDO
                        </Button>
                    </div>
                )}
            </div>
            <p className="px-3 py-2 text-sm" role="status" aria-live="polite">
                {state.error?.message ??
                    (state.status === 'requesting'
                        ? 'Iniciando video…'
                        : state.status === 'buffering'
                          ? 'Cargando video…'
                          : state.status === 'playing'
                            ? state.muted || state.volume === 0
                                ? 'Reproduciendo sin sonido.'
                                : 'Reproduciendo con sonido.'
                            : state.status === 'ended'
                              ? 'Video terminado. Puedes volver a reproducirlo.'
                              : 'Pulsa reproducir para ver el video.')}
            </p>
            {fullscreenError && (
                <p role="status" className="px-3 text-sm">
                    {fullscreenError}
                </p>
            )}
            <PlaybackControls
                state={state}
                onPlay={() => engineRef.current?.play()}
                onPause={() => engineRef.current?.pause()}
                onMute={() => {
                    if (state.muted) engineRef.current?.activateAudio();
                    else engineRef.current?.setMuted(true);
                }}
                onSeek={(time) => engineRef.current?.seek(time)}
                onVolume={(volume) => engineRef.current?.setVolume(volume)}
                onFullscreen={fullscreen}
                fullscreenSupported={fullscreenSupported}
            />
        </div>
    );
}
