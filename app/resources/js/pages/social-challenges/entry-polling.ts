export type PollableEntry = { inspection_status: string; refresh_pending: boolean };

export function shouldPollEntry(entry: PollableEntry): boolean {
    return entry.refresh_pending;
}

export function pollDelay(elapsedMs: number): number {
    return elapsedMs < 15_000 ? 1_000 : 5_000;
}
