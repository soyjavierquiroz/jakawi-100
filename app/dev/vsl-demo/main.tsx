import { StrictMode, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { JakawiVslPlayer } from '@/components/media/jakawi-vsl-player';
import type {
    PlaybackState,
    VslEvent,
} from '@/components/media/jakawi-vsl-player';
import '../../resources/css/app.css';

function Demo() {
    const [events, setEvents] = useState<string[]>([]);
    const [fit, setFit] = useState<'cover' | 'contain'>('cover');
    const [mounted, setMounted] = useState(true);
    const [autoplay, setAutoplay] = useState(true);
    const [source, setSource] = useState<'fixture' | 'failed'>('fixture');
    const [focalPosition, setFocalPosition] = useState('center');
    const [snapshot, setSnapshot] = useState<PlaybackState | null>(null);
    const videoIdentity = useRef<{
        node: HTMLVideoElement | null;
        count: number;
    }>({ node: null, count: 0 });
    const [nodeId, setNodeId] = useState(0);
    const report = (event: VslEvent) => {
        setSnapshot(event.state);
        const node = document.querySelector('video');
        if (node && node !== videoIdentity.current.node) {
            videoIdentity.current = {
                node,
                count: videoIdentity.current.count + 1,
            };
            setNodeId(videoIdentity.current.count);
        }
        if (event.type !== 'progress')
            setEvents((previous) => [...previous.slice(-9), event.type]);
    };
    return (
        <main className="min-h-screen space-y-4 bg-background p-4 text-foreground">
            <h1 className="text-center text-xl font-bold">
                JAKAWI VSL Alpha — desarrollo aislado
            </h1>
            <p className="text-center text-sm">
                Fixture sintético 9:16 de seis segundos con tono de audio.
            </p>
            <div className="mx-auto flex max-w-md flex-wrap gap-4">
                <label>
                    Fuente{' '}
                    <select
                        value={source}
                        onChange={(event) =>
                            setSource(
                                event.target.value as 'fixture' | 'failed',
                            )
                        }
                    >
                        <option value="fixture">MP4 sintético 9:16</option>
                        <option value="failed">
                            URL HTTPS fallida (.invalid)
                        </option>
                    </select>
                </label>
                <label>
                    Posición focal{' '}
                    <select
                        value={focalPosition}
                        onChange={(event) =>
                            setFocalPosition(event.target.value)
                        }
                    >
                        <option value="center">Centro</option>
                        <option value="center top">Arriba</option>
                        <option value="center bottom">Abajo</option>
                    </select>
                </label>
                <label>
                    Encuadre{' '}
                    <select
                        value={fit}
                        onChange={(event) =>
                            setFit(event.target.value as 'cover' | 'contain')
                        }
                    >
                        <option value="cover">Cover</option>
                        <option value="contain">Contain</option>
                    </select>
                </label>
                <label>
                    <input
                        type="checkbox"
                        checked={autoplay}
                        onChange={(event) => setAutoplay(event.target.checked)}
                    />{' '}
                    Autoplay
                </label>
                <label>
                    <input
                        type="checkbox"
                        checked={mounted}
                        onChange={(event) => setMounted(event.target.checked)}
                    />{' '}
                    Montar reproductor
                </label>
            </div>
            {mounted && (
                <JakawiVslPlayer
                    source={
                        source === 'fixture'
                            ? {
                                  type: 'development-fixture',
                                  url: '/__vsl-fixtures__/alpha.mp4',
                              }
                            : {
                                  type: 'mp4',
                                  url: 'https://example.invalid/alpha.mp4',
                              }
                    }
                    poster="/poster.svg"
                    title="Prueba VSL JAKAWI"
                    fit={fit}
                    focalPosition={focalPosition}
                    autoplay={autoplay}
                    onEvent={report}
                />
            )}
            <p className="mx-auto max-w-md text-sm">
                Nodo video #{nodeId}; último estado emitido:{' '}
                {snapshot?.status ?? 'initializing'}; tiempo:{' '}
                {snapshot?.currentTime.toFixed(2) ?? '0.00'}; muted:{' '}
                {String(snapshot?.muted ?? true)}; volumen:{' '}
                {snapshot?.volume ?? 1}
            </p>
            <pre
                className="mx-auto max-w-md rounded-lg bg-surface p-3 text-sm whitespace-pre-wrap"
                aria-label="Eventos técnicos"
            >
                {events.join('\n')}
            </pre>
        </main>
    );
}

const root = document.getElementById('root');
if (import.meta.env.DEV && root)
    createRoot(root).render(
        <StrictMode>
            <Demo />
        </StrictMode>,
    );
