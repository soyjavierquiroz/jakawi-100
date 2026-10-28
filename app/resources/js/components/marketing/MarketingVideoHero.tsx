import type { ReactNode } from 'react';

/** One media/purchase instance: mobile keeps its approved flow; desktop centers the grouped copy beside its media. */
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
                <div className="offer-video-copy min-w-0">
                    <div className="offer-intro min-w-0">{intro}</div>
                    <p data-video-subtitle className="offer-video-subtitle min-w-0 text-lg leading-7 text-[var(--marketing-text-secondary)]">
                        {description}
                    </p>
                    <div className={`offer-video-purchase ${detailsClassName}`}>{purchase}</div>
                </div>
                <div className="offer-media min-w-0" data-video-framing={framing}>
                    {media}
                </div>
            </section>
            <section aria-label="Estado y detalles de la propuesta" className="offer-video-details scroll-mt-6">
                <div id="landing-details" tabIndex={-1} className="offer-video-context scroll-mt-6">{context}</div>
            </section>
        </div>
    );
}
