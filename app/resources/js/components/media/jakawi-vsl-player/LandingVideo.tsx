import { JakawiVslPlayer } from './JakawiVslPlayer';
import type { JakawiVslPlayerProps } from './types';

export type LandingVideoConfig = Pick<JakawiVslPlayerProps, 'poster' | 'theme' | 'colors'> & {
    source: Exclude<JakawiVslPlayerProps['source'], { type: 'development-fixture' }>;
    autoplay?: 'muted' | 'off';
    mode?: 'vsl';
    framing?: 'embedded-vertical' | 'native-vertical' | 'horizontal';
};

export function LandingVideo({ config }: { config: LandingVideoConfig }) {
    return <JakawiVslPlayer {...config} mode="vsl" />;
}
