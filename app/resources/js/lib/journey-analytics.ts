export function trackJourneyIntent(journey: 'experience' | 'unlock' | 'benefit', slug: string): void {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!csrf) return;

    void fetch(`/analytics/journey-intent/${journey}/${encodeURIComponent(slug)}`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
        credentials: 'same-origin',
        keepalive: true,
    }).catch(() => undefined);
}
