/** Course values come only from the provider clock, never from the VSL curve. */
export function courseProgress(time: number, duration: number): number {
    if (!Number.isFinite(time) || !Number.isFinite(duration) || duration <= 0)
        return 0;
    return Math.max(0, Math.min(1, time / duration));
}
export function courseTime(time: number): string {
    const seconds = Number.isFinite(time) ? Math.max(0, Math.floor(time)) : 0;
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor(seconds / 60) % 60;
    return `${hours ? `${hours}:` : ''}${String(hours ? minutes : Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
}
