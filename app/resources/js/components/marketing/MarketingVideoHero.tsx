import type { ReactNode } from 'react';

/** One media/purchase instance: mobile keeps its approved flow; desktop uses commercial rows. */
export function MarketingVideoHero({
    intro,
    media,
    description,
    context,
    purchase,
    framing,
    label,
    detailsClassName = '',
}: {
    intro: ReactNode;
    media: ReactNode;
    description: string;
    context?: ReactNode;
    purchase?: ReactNode;
    framing?: string;
    label?: string;
    detailsClassName?: string;
}) {
    return (
        <div className="offer-video-layout">
            <section
                data-offer-chapter="hero"
                aria-label={label}
                className="offer-hero-v3 offer-media-led offer-with-media offer-video-first"
            >
                <div className="offer-intro min-w-0">{intro}</div>
                <div className="offer-media min-w-0" data-video-framing={framing}>
                    {media}
                </div>
                <p data-video-subtitle className="offer-video-subtitle min-w-0 text-lg leading-7 text-[var(--marketing-text-secondary)]">
                    {description}
                </p>
            </section>
            <section id="landing-details" tabIndex={-1} aria-label="Estado y detalles de la propuesta" className={`offer-video-details scroll-mt-6 ${detailsClassName}`}>
                <div className="offer-video-context">{context}</div>
                <div className="offer-video-purchase">{purchase}</div>
            </section>
        </div>
    );
}
