import { usePage } from '@inertiajs/react';
import type { MouseEvent } from 'react';

/** One delegated listener per rendered landing; never tracks views or domain outcomes. */
export function useGrowthLandingClicks() {
    const { presentation, preview } = usePage().props as unknown as { presentation?: { slug: string }; preview?: boolean };
    return (event: MouseEvent<HTMLElement>) => {
        if (preview || !presentation || !(event.target instanceof Element)) return;
        const target = event.target.closest<HTMLAnchorElement | HTMLButtonElement>('a[href], button[data-growth-kind]');
        if (!target || target.getAttribute('aria-disabled') === 'true' || target.hasAttribute('disabled')) return;
        const href = target.getAttribute('href') ?? target.dataset.growthDestination ?? '';
        let url: URL;
        try { url = new URL(href, window.location.href); } catch { return; }
        const external = url.origin !== window.location.origin;
        const actionKind = target.dataset.growthKind;
        const kind = actionKind === 'guest' || actionKind === 'signup' ? 'signup' : actionKind === 'membership' ? 'membership'
            : external ? 'external' : actionKind === 'participate' ? 'participate'
            : url.pathname.startsWith('/membresia') ? 'membership'
            : url.pathname.startsWith('/register') ? 'signup'
            : href.startsWith('#') ? 'other' : 'product_detail';
        const location = target.dataset.growthLocation ?? 'body';
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrf) return;
        // Strip query/fragment before sending. External paths can contain phone numbers/tokens.
        const destination = external ? 'external' : url.pathname;
        void fetch(`/analytics/landing-presentations/${encodeURIComponent(presentation.slug)}/cta`, {
            method: 'POST', credentials: 'same-origin', keepalive: true,
            headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ cta_kind: kind, cta_location: location, destination }),
        }).catch(() => undefined);
    };
}
