import { JakawiVslPlayer } from './JakawiVslPlayer';
import type { JakawiVslPlayerProps } from './types';

export type LandingVideoConfig = Pick<JakawiVslPlayerProps, 'poster' | 'theme' | 'colors'> & {
    source: Exclude<JakawiVslPlayerProps['source'], { type: 'development-fixture' }>;
    autoplay?: 'muted' | 'off';
    mode?: 'vsl';
    framing?: 'embedded-vertical' | 'native-vertical' | 'horizontal';
};

export function LandingVideo({ config, fullFrame = false }: { config: LandingVideoConfig; fullFrame?: boolean }) {
    const kind = config.framing ?? 'horizontal';
    return <JakawiVslPlayer {...config} mode="vsl" {...(fullFrame ? { fit: kind === 'embedded-vertical' ? 'cover' : 'contain', framing: { kind, viewportAspectRatio: kind === 'horizontal' ? 16 / 9 : 9 / 16 } } : {})} />;
}
