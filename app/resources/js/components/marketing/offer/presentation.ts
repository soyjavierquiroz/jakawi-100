import type { LandingVideoConfig } from '@/components/media/jakawi-vsl-player/LandingVideo';

/** Ephemeral projection of authoritative props, never an economic/rules record. */
export type OfferPresentationV1 = {
    hero: {
        eyebrow: string;
        headline: string;
        emphasis?: string;
        description: string;
        context?: string;
    };
    media?:
        | { type: 'vsl'; config: LandingVideoConfig }
        | { type: 'image'; src: string; alt: string; srcset?: {src: string; width: number}[]; desktopSrcset?: {src: string; width: number}[] };
    facts: Array<{ label: string; value: string; tone?: 'reward' }>;
    value?: {
        // An offer is informational; a reward is an opportunity, never a won prize.
        tone: 'offer' | 'reward';
        label: string;
        compactLabel?: string;
        title: string;
        description?: string;
        detail?: string;
        note?: string;
    };
    prices?: Array<{
        label: string;
        formattedAmount: string;
        memberAdvantage?: boolean;
    }>;
    essentialConditions: string[];
    actionExplanation?: { description: string; clarification?: string };
    steps?: {
        eyebrow: string;
        headline: string;
        items: Array<{ title: string; description: string }>;
    };
    faq: Array<{ question: string; answer: string }>;
    finalCta: { eyebrow?: string; headline: string; description?: string };
};

/** Validated editorial projection; separate from authoritative offer/action data. */
export type LandingOfferEditorial = {
    version: number;
    eyebrow: string;
    headline: string;
    subheadline: string;
    emphasis: string | null;
    finalHeadline: string;
    finalDescription: string | null;
};
