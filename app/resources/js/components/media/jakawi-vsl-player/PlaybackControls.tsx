import { useEffect, useRef, useState } from 'react';
import {
    Pause,
    Play,
    RotateCcw,
    RotateCw,
    Volume2,
    VolumeX,
} from 'lucide-react';
import type { Html5Engine } from './html5-engine';
import type { PlaybackState } from './types';
import { courseProgress, courseTime } from './course-presentation';
import styles from './JakawiVslPlayer.module.css';

/** One course presentation shared by all engines; no provider clock/store of its own. */
export function PlaybackControls({
    state,
    engine,
}: {
    state: PlaybackState;
    engine: Html5Engine | null;
}) {
    const [active, setActive] = useState(true);
    const [draft, setDraft] = useState<number | null>(null);
    const timer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
    const seekGesture = useRef<{ x: number; y: number } | null>(null);
    const dragging = useRef(false);
    const adjusting = useRef(false);
    const tapWasVisible = useRef(true);
    const pointerFocus = useRef(false);
    const focused = useRef(false);
    const playing = state.status === 'playing';
    const visible = !playing || active || draft !== null;
    const capabilities = engine?.getCapabilities();
    const seekable = capabilities?.seek === true;
    const duration = Number.isFinite(state.duration)
        ? Math.max(0, state.duration)
        : 0;
    const time = draft ?? state.currentTime;
    const busy = ['requesting', 'buffering'].includes(state.status);
    const unavailable =
        state.error?.kind === 'source' ||
        (!state.ready && state.error?.kind !== 'media');
    function arm() {
        clearTimeout(timer.current);
        if (
            playing &&
            !dragging.current &&
            !adjusting.current &&
            !focused.current
        )
            timer.current = setTimeout(() => setActive(false), 2500);
    }
    function wake() {
        setActive(true);
        arm();
    }
    useEffect(() => {
        const keyboard = () => {
            pointerFocus.current = false;
        };
        document.addEventListener('keydown', keyboard);
        return () => document.removeEventListener('keydown', keyboard);
    }, []);
    useEffect(() => {
        wake();
        return () => clearTimeout(timer.current);
    }, [playing]);
    function previewSeek(
        event: import('react').PointerEvent<HTMLInputElement>,
    ) {
        const bounds = event.currentTarget.getBoundingClientRect();
        const target =
            Math.max(
                0,
                Math.min(1, (event.clientX - bounds.left) / bounds.width),
            ) * duration;
        setDraft(target);
        return target;
    }
    function toggle() {
        if (busy || playing) engine?.pause();
        else {
            // Manual first playback should be audible; later mute preferences persist.
            if (!state.hasPlayed && !state.error) engine?.setMuted(false);
            engine?.play();
        }
        wake();
    }
    return (
        <div
            className={styles.courseSurface}
            data-course-visible={visible}
            onPointerDownCapture={(event) => {
                if (event.target === event.currentTarget)
                    tapWasVisible.current = visible;
                pointerFocus.current = true;
                focused.current = false;
                wake();
            }}
            tabIndex={0}
            role="group"
            aria-label="Video del curso"
            onKeyDown={(event) => {
                focused.current = true;
                pointerFocus.current = false;
                wake();
                if (event.target !== event.currentTarget) return;
                if (event.key === ' ' || event.key === 'Enter') {
                    event.preventDefault();
                    if (!unavailable) toggle();
                }
            }}
            onPointerMove={(event) => {
                if (event.pointerType === 'mouse') wake();
            }}
            onPointerEnter={(event) => {
                if (event.pointerType === 'mouse') wake();
            }}
            onFocusCapture={(event) => {
                focused.current =
                    !pointerFocus.current &&
                    event.target instanceof Element &&
                    event.target.matches(':focus-visible');
                wake();
            }}
            onBlurCapture={(event) => {
                if (!event.currentTarget.contains(event.relatedTarget)) {
                    focused.current = false;
                    arm();
                }
            }}
            onClick={(event) => {
                if (event.target !== event.currentTarget) return;
                if (
                    tapWasVisible.current &&
                    window.matchMedia('(pointer: coarse)').matches &&
                    !unavailable
                )
                    toggle();
                else wake();
            }}
        >
            <div
                className={styles.courseControls}
                role="group"
                aria-label="Controles del curso"
                inert={!visible}
            >
                <button
                    type="button"
                    disabled={unavailable}
                    onClick={toggle}
                    aria-label={
                        state.status === 'ended'
                            ? 'Volver a ver'
                            : playing || busy
                              ? 'Pausar video'
                              : state.error
                                ? 'Intentar de nuevo'
                                : 'Reproducir video'
                    }
                >
                    {state.status === 'ended' ? (
                        <RotateCcw size={20} />
                    ) : playing || busy ? (
                        <Pause size={20} />
                    ) : (
                        <Play size={20} />
                    )}
                </button>
                <button
                    type="button"
                    disabled={!seekable}
                    aria-label="Retroceder 10 segundos"
                    onClick={() => {
                        engine?.seek(state.currentTime - 10);
                        wake();
                    }}
                >
                    <RotateCcw size={19} />
                    <span>10</span>
                </button>
                <button
                    type="button"
                    disabled={!seekable}
                    aria-label="Avanzar 10 segundos"
                    onClick={() => {
                        engine?.seek(state.currentTime + 10);
                        wake();
                    }}
                >
                    <RotateCw size={19} />
                    <span>10</span>
                </button>
                <span
                    className={styles.courseTime}
                    aria-label="Tiempo transcurrido y duración"
                >
                    {courseTime(time)} / {courseTime(duration)}
                </span>
                <button
                    type="button"
                    disabled={!capabilities?.mute}
                    onClick={() => {
                        engine?.setMuted(!state.muted);
                        wake();
                    }}
                    aria-label={
                        state.muted ? 'Activar sonido' : 'Silenciar video'
                    }
                >
                    {state.muted ? (
                        <VolumeX size={20} />
                    ) : (
                        <Volume2 size={20} />
                    )}
                </button>
                <input
                    className={styles.courseVolume}
                    type="range"
                    min="0"
                    max="1"
                    step="0.01"
                    value={state.muted ? 0 : state.volume}
                    disabled={!capabilities?.volume}
                    aria-label="Volumen"
                    onPointerDown={(event) => {
                        event.currentTarget.setPointerCapture(event.pointerId);
                        adjusting.current = true;
                        wake();
                    }}
                    onPointerUp={() => {
                        adjusting.current = false;
                        wake();
                    }}
                    onPointerCancel={() => {
                        adjusting.current = false;
                        wake();
                    }}
                    onChange={(event) => {
                        engine?.setVolume(Number(event.target.value));
                        engine?.setMuted(Number(event.target.value) === 0);
                        wake();
                    }}
                />
            </div>
            <div
                className={styles.courseSeek}
                style={
                    {
                        '--course-progress': `${courseProgress(time, duration) * 100}%`,
                    } as import('react').CSSProperties
                }
            >
                <input
                    type="range"
                    min="0"
                    max={duration || 1}
                    step="0.1"
                    value={Math.max(0, Math.min(duration, time))}
                    disabled={!seekable}
                    aria-label="Posición del video"
                    aria-valuetext={`${courseTime(time)} de ${courseTime(duration)}`}
                    onPointerDown={(event) => {
                        event.preventDefault();
                        event.currentTarget.setPointerCapture(event.pointerId);
                        event.currentTarget.focus({ preventScroll: true });
                        seekGesture.current = {
                            x: event.clientX,
                            y: event.clientY,
                        };
                        dragging.current = true;
                        previewSeek(event);
                        wake();
                    }}
                    onPointerMove={(event) => {
                        if (!dragging.current) return;
                        previewSeek(event);
                        wake();
                    }}
                    onChange={(event) => {
                        if (!dragging.current)
                            engine?.seek(Number(event.target.value));
                        wake();
                    }}
                    onPointerUp={(event) => {
                        const start = seekGesture.current;
                        const verticalScroll =
                            start &&
                            Math.abs(event.clientY - start.y) >
                                Math.max(8, Math.abs(event.clientX - start.x));
                        if (dragging.current && seekable && !verticalScroll)
                            engine?.seek(previewSeek(event));
                        dragging.current = false;
                        seekGesture.current = null;
                        setDraft(null);
                        wake();
                    }}
                    onPointerCancel={() => {
                        dragging.current = false;
                        seekGesture.current = null;
                        setDraft(null);
                        wake();
                    }}
                    onLostPointerCapture={() => {
                        if (dragging.current) {
                            dragging.current = false;
                            seekGesture.current = null;
                            setDraft(null);
                            wake();
                        }
                    }}
                    onKeyDown={wake}
                />
            </div>
        </div>
    );
}
