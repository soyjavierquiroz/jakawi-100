import { Button } from '@/components/ui/button';
import type { PlaybackState } from './types';

function clock(seconds: number) {
    return `${Math.floor(seconds / 60)}:${Math.floor(seconds % 60)
        .toString()
        .padStart(2, '0')}`;
}

export function PlaybackControls({
    state,
    onPlay,
    onPause,
    onMute,
    onSeek,
    onVolume,
    onFullscreen,
    fullscreenSupported,
}: {
    state: PlaybackState;
    onPlay: () => void;
    onPause: () => void;
    onMute: () => void;
    onSeek: (time: number) => void;
    onVolume: (volume: number) => void;
    onFullscreen: () => void;
    fullscreenSupported: boolean;
}) {
    const playing =
        state.status === 'playing' ||
        state.status === 'requesting' ||
        state.status === 'buffering';
    return (
        <div className="space-y-3 border-t border-border bg-surface p-3 text-surface-foreground">
            <div className="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    onClick={playing ? onPause : onPlay}
                    disabled={state.error?.kind === 'source'}
                    aria-label={
                        playing
                            ? 'Pausar video'
                            : state.status === 'ended'
                              ? 'Volver a reproducir'
                              : 'Reproducir video'
                    }
                >
                    {playing
                        ? 'Pausar'
                        : state.status === 'ended'
                          ? 'Repetir'
                          : 'Reproducir'}
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    onClick={onMute}
                    disabled={!state.ready || state.error?.kind === 'source'}
                    aria-label={
                        state.muted
                            ? 'Activar sonido desde el inicio'
                            : 'Silenciar video'
                    }
                >
                    {state.muted ? 'Activar sonido' : 'Silenciar'}
                </Button>
                {fullscreenSupported && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onFullscreen}
                    >
                        Pantalla completa
                    </Button>
                )}
            </div>
            <label className="flex items-center gap-3 text-sm">
                <span>Posición</span>
                <input
                    className="min-w-0 flex-1 accent-primary focus-visible:outline-2 focus-visible:outline-ring"
                    type="range"
                    min={0}
                    max={state.duration || 0}
                    step={0.1}
                    value={state.currentTime}
                    disabled={!state.ready || !state.duration}
                    onChange={(event) => onSeek(Number(event.target.value))}
                    aria-valuetext={`${clock(state.currentTime)} de ${clock(state.duration)}`}
                />
                <span className="tabular-nums">
                    {clock(state.currentTime)} / {clock(state.duration)}
                </span>
            </label>
            <label className="flex items-center gap-3 text-sm">
                <span>Volumen</span>
                <input
                    className="min-w-0 flex-1 accent-primary focus-visible:outline-2 focus-visible:outline-ring"
                    type="range"
                    min={0}
                    max={1}
                    step={0.05}
                    value={state.volume}
                    onChange={(event) => onVolume(Number(event.target.value))}
                    aria-valuetext={`${Math.round(state.volume * 100)}%`}
                />
            </label>
        </div>
    );
}
