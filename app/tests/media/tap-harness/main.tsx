import { createRoot } from 'react-dom/client';
import { useState } from 'react';
import { JakawiVslPlayer } from '../../../resources/js/components/media/jakawi-vsl-player/JakawiVslPlayer';
import type { VslSource, VslEvent } from '../../../resources/js/components/media/jakawi-vsl-player/types';
import '../../../resources/css/theme.css';
const params = new URLSearchParams(location.search);
const provider = params.get('provider') ?? 'mp4';
const events: VslEvent[] = [];
Object.assign(window, { tapEvents: events });
function Harness() {
    const [revision, changeSource] = useState(0);
    const source: VslSource = provider === 'youtube'
        ? { type: 'youtube', url: revision ? 'hI-Ecy2D8pg' : 'M7lc1UVf-VE' }
        : { type: provider === 'bunny' ? 'bunny' : 'mp4', url: `https://media.test/${provider === 'bunny' ? 'playlist.m3u8' : 'alpha.mp4'}?revision=${revision}` };
    return <><JakawiVslPlayer source={source} mode={params.get('mode') === 'course' ? 'course' : 'vsl'} framing={provider === 'youtube' ? 'embedded-vertical' : 'native-vertical'} onEvent={event => events.push(event)} /><button id="source" onClick={() => changeSource(value => value + 1)}>Change source</button><div style={{height: 1500}} /></>;
}
createRoot(document.getElementById('root')!).render(<Harness />);
