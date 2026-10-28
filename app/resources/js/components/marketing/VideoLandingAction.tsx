import { useEffect, useRef } from 'react';
import { usePage } from '@inertiajs/react';
import { MarketingCTA, type MarketingAction } from './primitives';

/** Preserve the authorized handler; private/non-operational previews only navigate to details. */
export function useVideoLandingAction(action: MarketingAction, details = '#landing-details') {
    const { props } = usePage();
    const status = (props.presentation as { status?: string } | undefined)?.status;
    const informational = props.preview === true || (status !== undefined && status !== 'PUBLISHED') || action.disabled === true || action.kind === 'unavailable';
    return {
        informational,
        action: informational ? { href: details, label: 'VER DETALLES', kind: 'informational' } : action,
    };
}

export function VideoLandingAction({ action, informational }: { action: MarketingAction; informational: boolean }) {
    const bar = useRef<HTMLDivElement>(null);
    useEffect(() => {
        const element = bar.current;
        const page = element?.closest<HTMLElement>('[data-video-marketing]');
        if (!element || !page) return;
        const resize = new ResizeObserver(sync);
        const observed = new Set<Element>();
        function sync() {
            if (!element || !page) return;
            let offset = 0;
            document.querySelectorAll('[data-mobile-bottom-nav], [data-pwa-install-panel]').forEach(panel => {
                if (!observed.has(panel)) { observed.add(panel); resize.observe(panel); }
                const rect = panel.getBoundingClientRect();
                if (rect.height > 0) offset = Math.max(offset, window.innerHeight - rect.top);
            });
            element.style.setProperty('--marketing-bar-offset', `${offset}px`);
            page.style.setProperty('--marketing-sticky-clearance', `${element.getBoundingClientRect().height > 0 ? element.getBoundingClientRect().height + offset : 0}px`);
        }
        resize.observe(element);
        const mutation = new MutationObserver(sync);
        mutation.observe(document.body, { childList: true, subtree: true });
        window.addEventListener('resize', sync);
        sync();
        return () => { resize.disconnect(); mutation.disconnect(); window.removeEventListener('resize', sync); page.style.removeProperty('--marketing-sticky-clearance'); };
    }, []);
    return <div ref={bar} data-video-sticky className="video-landing-action">
        <div className="mx-auto max-w-lg">
            {informational && <p className="mb-2 text-center text-xs font-extrabold tracking-widest">EN PREPARACIÓN</p>}
            <MarketingCTA action={action} location="body" className="w-full sm:w-full" />
        </div>
    </div>;
}
