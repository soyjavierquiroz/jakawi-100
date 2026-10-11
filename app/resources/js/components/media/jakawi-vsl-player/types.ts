export type VslSource =
    | { type: 'mp4'; url: string }
    | { type: 'bunny'; url: string }
    | { type: 'youtube'; url: string }
    | { type: 'development-fixture'; url: '/__vsl-fixtures__/alpha.mp4' };

export type PlaybackStatus =
    | 'initializing'
    | 'ready'
    | 'requesting'
    | 'playing'
    | 'buffering'
    | 'paused'
    | 'ended'
    | 'blocked'
    | 'error';

export interface PlaybackError {
    kind: 'autoplay-blocked' | 'playback-blocked' | 'media' | 'source';
    message: string;
    code?: number;
}

export interface PlaybackCapabilities {
    seek: boolean;
    mute: boolean;
    volume: boolean;
}

export interface PlaybackState {
    status: PlaybackStatus;
    ready: boolean;
    hasPlayed: boolean;
    currentTime: number;
    duration: number;
    /** Native metadata, when the provider exposes it. */
    aspectRatio?: number;
    muted: boolean;
    volume: number;
    error: PlaybackError | null;
}

export type VslEvent =
    | {
          type:
              | 'ready'
              | 'playing'
              | 'paused'
              | 'progress'
              | 'ended'
              | 'audio-activated';
          state: PlaybackState;
      }
    | { type: 'error'; state: PlaybackState; error: PlaybackError };

export interface JakawiVslPlayerProps {
    source: VslSource;
    /** VSL marketing by default; course uses manual playback and real navigation. */
    mode?: 'vsl' | 'course';
    poster?: string;
    title?: string;
    autoplay?: 'muted' | 'off' | boolean;
    theme?: 'inherit' | 'custom';
    colors?: { accent?: string; text?: string; overlay?: string };
    fit?: 'cover' | 'contain';
    focalPosition?: string;
    /** Stable layout fixed before loading. Auto uses sourceAspectRatio or defaults
     * to horizontal (vertical for the development fixture), never media metadata.
     * Embedded content requires explicit framing.
     */
    framing?: 'embedded-vertical' | 'native-vertical' | 'horizontal' | {
        kind: 'auto' | 'native-vertical' | 'horizontal' | 'embedded-vertical';
        sourceAspectRatio?: number;
        contentAspectRatio?: number;
        focalX?: number;
        focalY?: number;
    };
    /** Decorative curve exponent (1–6); never reported as media progress. */
    progressAcceleration?: number;
    /** Independent decorative clock horizon in seconds (default 90). */
    progressSeconds?: number;
    className?: string;
    onEvent?: (event: VslEvent) => void;
}
