import { useCallback, useEffect, useRef } from 'react';
import type { DiscoveryOpportunity } from '@/types/discovery';

type OpportunityAnalyticsContext = {
    opportunity: DiscoveryOpportunity;
    surface: 'HOME' | 'EXPLORE' | 'SEARCH';
    section: 'HERO' | 'FOR_YOU' | 'HAPPENING_NOW' | 'DISCOVER_MORE' | 'RESULTS';
    position: number;
};

function csrfToken() {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
}

function track(event: 'opportunity_impression' | 'opportunity_opened', context: OpportunityAnalyticsContext) {
    const category = context.opportunity.type === 'UNLOCK' ? null : context.opportunity.categories[0] ?? null;
    void fetch('/analytics/opportunities', {
        method: 'POST',
        keepalive: true,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrfToken() ? { 'X-CSRF-TOKEN': csrfToken() } : {}),
        },
        body: JSON.stringify({
            event,
            opportunity_type: context.opportunity.type,
            source_id: String(context.opportunity.source_id),
            surface: context.surface,
            section: context.section,
            position: context.position,
            ...(category ? { category } : {}),
        }),
    });
}

/** Tracks a Discovery placement once while it is visible, and records intentional opens without delaying navigation. */
export function useOpportunityAnalytics(context: OpportunityAnalyticsContext) {
    const elementRef = useRef<HTMLAnchorElement | null>(null);
    const impressionKey = `${context.opportunity.type}:${context.opportunity.source_id}:${context.surface}:${context.section}:${context.position}`;
    const emittedImpression = useRef<string | null>(null);

    useEffect(() => {
        const element = elementRef.current;
        if (!element || emittedImpression.current === impressionKey) return;

        const observer = new IntersectionObserver(([entry]) => {
            if (!entry.isIntersecting || emittedImpression.current === impressionKey) return;
            emittedImpression.current = impressionKey;
            track('opportunity_impression', context);
            observer.disconnect();
        });
        observer.observe(element);
        return () => observer.disconnect();
    }, [context, impressionKey]);

    const onOpen = useCallback(() => track('opportunity_opened', context), [context]);

    return { elementRef, onOpen };
}
