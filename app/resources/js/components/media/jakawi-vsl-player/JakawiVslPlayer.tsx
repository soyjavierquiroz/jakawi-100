import { useEffect, useState, type CSSProperties } from 'react';
import { cn } from '@/lib/utils';
import styles from './JakawiVslPlayer.module.css';
import { Pause, Play, RotateCcw, Volume2 } from 'lucide-react';
import { applyYouTubeCrop, framingMetadata } from './youtube-crop';
import { resolveFraming, verticalFraming } from './marketing-presentation';
import { useDecorativeProgress } from './use-decorative-progress';
import type { JakawiVslPlayerProps } from './types';
import { PlaybackControls } from './PlaybackControls';
import { useVslPlayback } from './use-vsl-playback';
import { videoThemeColors } from './video-theme';
import { parseYoutubeId } from './youtube-engine';

export function JakawiVslPlayer(props: JakawiVslPlayerProps) {
    props = { ...props, framing: typeof props.framing === 'string' ? { kind: props.framing } : props.framing };
    const framingOptions = typeof props.framing === 'object' ? props.framing : undefined;
    const {
        poster,
        title = 'Video',
        fit = 'cover',
        focalPosition = 'center',
        className,
    } = props;
    const colors = videoThemeColors(props.colors);
    const { state, videoRef, youtubeRef, engineRef } = useVslPlayback(props);
    const course = props.mode === 'course';
    const youtube = props.source.type === 'youtube';
    const sourceKey = `${props.source.type}:${props.source.url}`;
    const [progressReset, setProgressReset] = useState(0);
    const progress = useDecorativeProgress(
        state,
        `${sourceKey}:${course ? 'course' : 'vsl'}:${progressReset}`,
        props.progressAcceleration,
        props.progressSeconds,
        !course,
    );
    const playing = state.status === 'playing';
    const audible = playing && !state.muted && state.volume > 0;
    const [audioRequest, setAudioRequest] = useState<string | null>(null);
    useEffect(() => {
        if (
            audible ||
            ['blocked', 'error', 'paused', 'ended'].includes(state.status)
        )
            setAudioRequest(null);
    }, [audible, state.status]);
    const awaitingAudio =
        audioRequest === sourceKey &&
        ['requesting', 'buffering'].includes(state.status);
    const needsSound = (playing && !audible) || awaitingAudio;
    const manual = ['ready', 'paused', 'ended', 'blocked', 'error'].includes(
        state.status,
    );
    const showPoster = !state.hasPlayed || state.status === 'ended';
    // Course playback starts from the bottom controls, not YouTube's large
    // initial play affordance. Keep its thumbnail until playback begins.
    let courseYoutubePoster: string | undefined;
    if (course && youtube && showPoster) {
        try {
            courseYoutubePoster =
                poster ||
                `https://i.ytimg.com/vi/${parseYoutubeId(props.source.url)}/hqdefault.jpg`;
        } catch {
            // The existing engine reports invalid sources; retain a clean cover.
        }
    }
    const replay = () => {
        setProgressReset((value) => value + 1);
        engineRef.current?.seek(0);
        engineRef.current?.play();
    };
    const action =
        state.status === 'ended'
            ? 'Volver a ver'
            : state.status === 'paused'
              ? 'Continuar'
              : state.status === 'error'
                ? 'Intentar de nuevo'
                : 'Ver video';
    const framing = resolveFraming(props.source.type, props.framing);
    const geometry =
        (props.framing || !focalPosition || focalPosition === 'center') &&
        !youtube &&
        framing.kind !== 'horizontal' &&
        fit === 'cover'
            ? verticalFraming({
                  sourceAspectRatio: framing.sourceAspectRatio,
                  contentAspectRatio: framing.contentAspectRatio,
                  focalX: framingOptions?.focalX,
                  focalY: framingOptions?.focalY,
              })
            : undefined;
    useEffect(() => {
        const iframe = youtubeRef.current?.querySelector('iframe');
        if (!iframe) return;
        iframe.removeAttribute('style');
        if (framing.kind === 'embedded-vertical') {
            applyYouTubeCrop(
                iframe,
                framingMetadata(
                    framing.kind,
                    framingOptions?.focalX,
                    framingOptions?.focalY,
                ),
            );
        } else if (framing.kind === 'native-vertical') {
            Object.assign(iframe.style, {
                width: '100%',
                height: 'auto',
                aspectRatio: '9 / 16',
                left: '50%',
                top: '50%',
                inset: 'auto',
                transform: 'translate(-50%, -50%)',
            });
        }
    }, [
        state.ready,
        framing.kind,
        framingOptions?.focalX,
        framingOptions?.focalY,
        youtubeRef,
    ]);
    const mediaStyle = geometry
        ? {
              width: `${geometry.widthPercent}%`,
              height: `${geometry.heightPercent}%`,
              left: `${geometry.leftPercent}%`,
              top: `${geometry.topPercent}%`,
              objectFit: fit,
          }
        : {
              objectFit:
                  framing.kind === 'horizontal' ? ('contain' as const) : fit,
              objectPosition: focalPosition,
          };
    const soundAction = (
        <button
            type="button"
            className={styles.sound}
            onClick={() => {
                setAudioRequest(sourceKey);
                setProgressReset((value) => value + 1);
                engineRef.current?.activateAudio();
            }}
        >
            <span className={styles.soundCopy}>
                <strong>Enciende tus parlantes.</strong>
                <span className={styles.soundPrompt}>
                    <Volume2 size={16} strokeWidth={1.6} aria-hidden="true" />
                    <span>Haz clic para escuchar.</span>
                </span>
            </span>
        </button>
    );
    return (
        <div
            data-theme={props.theme ?? 'inherit'}
            style={{
                '--jakawi-video-accent': colors.accent,
                '--jakawi-video-text': colors.text,
                '--jakawi-video-overlay': colors.overlay,
            } as CSSProperties}
            role="region"
            aria-label={title}
            aria-busy={
                state.status === 'requesting' || state.status === 'buffering'
            }
            className={cn(
                styles.player,
                framing.kind === 'horizontal' && styles.horizontal,
                className,
            )}
        >
            <div
                className={styles.frame}
                style={{ aspectRatio: framing.viewportAspectRatio }}
            >
                {youtube ? (
                    <>
                        <div ref={youtubeRef} className={styles.youtube} />
                    </>
                ) : (
                    <>
                        <video
                            ref={videoRef}
                            poster={poster}
                            playsInline
                            muted
                            aria-label={title}
                            className={styles.media}
                            style={mediaStyle}
                        />
                    </>
                )}
                <>
                    {course && youtube && showPoster && (
                        <div className={styles.coursePoster} data-course-poster>
                            {courseYoutubePoster && (
                                <img src={courseYoutubePoster} alt="" />
                            )}
                        </div>
                    )}
                    {showPoster && poster && !(course && youtube) && (
                        <img
                            src={poster}
                            alt=""
                            className={styles.media}
                            style={{ objectFit: 'cover' }}
                        />
                    )}
                    {!course && manual && (
                        <div className={styles.overlay}>
                            <button
                                type="button"
                                className={styles.start}
                                disabled={
                                    (!state.ready &&
                                        state.error?.kind !== 'media') ||
                                    state.error?.kind === 'source'
                                }
                                onClick={
                                    state.status === 'ended'
                                        ? replay
                                        : () => engineRef.current?.play()
                                }
                            >
                                {state.status === 'ended' ? (
                                    <RotateCcw size={26} aria-hidden="true" />
                                ) : (
                                    <Play size={28} aria-hidden="true" />
                                )}
                                {action}
                            </button>
                        </div>
                    )}
                    {!course && needsSound && (
                        <div className={styles.soundOverlay}>{soundAction}</div>
                    )}
                    {!course && playing && (
                        <button
                            type="button"
                            className={styles.pause}
                            aria-label="Pausar video"
                            onClick={() => engineRef.current?.pause()}
                        >
                            <Pause size={20} aria-hidden="true" />
                        </button>
                    )}
                </>
                {state.error && (
                    <p role="status" className={styles.error}>
                        {state.error.message}
                    </p>
                )}
                {(state.status === 'initializing' ||
                    state.status === 'requesting' ||
                    state.status === 'buffering') && (
                    <span className={styles.loading} role="status">
                        {state.status === 'initializing'
                            ? 'Preparando video…'
                            : 'Cargando…'}
                    </span>
                )}
                {course && (
                    <PlaybackControls
                        key={`${sourceKey}:course`}
                        state={state}
                        engine={engineRef.current}
                    />
                )}
                {!course && (
                    <div
                        className={styles.progress}
                        aria-hidden="true"
                        data-vsl-progress="decorative"
                    >
                        <div style={{ transform: `scaleX(${progress})` }} />
                    </div>
                )}
            </div>
            <span className={styles.srOnly} role="status" aria-live="polite">
                {audible
                    ? 'Reproduciendo con sonido'
                    : needsSound
                      ? 'Reproduciendo sin sonido'
                      : state.status === 'ended'
                        ? 'Video terminado'
                        : state.status === 'paused'
                          ? 'Video pausado'
                          : ''}
            </span>
        </div>
    );
}
