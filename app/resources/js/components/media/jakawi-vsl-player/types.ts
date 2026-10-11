export type VslSource =
    | { type: 'mp4'; url: string }
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

export interface PlaybackState {
    status: PlaybackStatus;
    ready: boolean;
    hasPlayed: boolean;
    currentTime: number;
    duration: number;
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
    poster?: string;
    title?: string;
    autoplay?: boolean;
    fit?: 'cover' | 'contain';
    focalPosition?: string;
    className?: string;
    onEvent?: (event: VslEvent) => void;
}
