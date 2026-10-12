import { useGrowthLandingClicks } from '@/lib/growth-landing-analytics';
import {
    MarketingFAQ,
    type MarketingAction,
} from '@/components/marketing/primitives';
import type { ReactNode, Ref } from 'react';
import type { OfferPresentationV1 } from './presentation';
import { distinctOfferValue } from './composition';
import {
    MarketingFactStrip,
    MarketingFinalCTA,
    MarketingPrices,
    MarketingPrimaryCTA,
    MarketingSteps,
    MarketingValueBlock,
    OfferHero,
    OptionalMarketingMedia,
    offerContainer,
    type OfferTreatment,
} from './blocks';

/** Frontend composition slots are bound by the caller; none are persisted in presentation. */
type Props = {
    presentation: OfferPresentationV1;
    action: MarketingAction;
    treatment: OfferTreatment;
    hero?: 'OFFER_FIRST' | 'MEDIA_LED';
    density?: 'STANDARD' | 'COMPACT';
    theme?: 'DARK_CONVERSION' | 'LIGHT_CONVERSION';
    className?: string;
    hookClassName?: string;
    faqClassName?: string;
    stepsPlacement?: 'content' | 'operational';
    renderOperationalContent: () => ReactNode;
    renderNotice?: () => ReactNode;
    renderHeroAnnotation?: () => ReactNode;
    renderHeroSupport?: () => ReactNode;
    renderValueSupplement?: () => ReactNode;
    renderAfterFinal?: () => ReactNode;
    heroActionRef?: Ref<HTMLDivElement>;
    finalActionRef?: Ref<HTMLElement>;
};

export function OfferConversionTemplate({
    presentation: p,
    action,
    treatment: legacyTreatment,
    hero: heroLayout,
    density,
    theme,
    className = '',
    hookClassName,
    faqClassName = '',
    stepsPlacement = 'content',
    renderOperationalContent,
    renderNotice,
    renderHeroAnnotation,
    renderHeroSupport,
    renderValueSupplement,
    renderAfterFinal,
    heroActionRef,
    finalActionRef,
}: Props) {
    const treatment = density
        ? density === 'COMPACT'
            ? 'compact'
            : heroLayout === 'MEDIA_LED'
              ? 'editorial'
              : 'plain'
        : legacyTreatment;
    const compact = treatment === 'compact';
    const mediaLed = heroLayout ? heroLayout === 'MEDIA_LED' : true;
    const editorial = treatment === 'editorial';
    const trackGrowthClick = useGrowthLandingClicks();
    const offerDetails = (
        <>
            <MarketingValueBlock
                value={distinctOfferValue(p)}
                supplement={renderValueSupplement?.()}
            />
            <MarketingFactStrip facts={p.facts} />
            <MarketingPrices prices={p.prices} />
            {p.essentialConditions.map((condition, index) => (
                <p
                    key={index}
                    className="mt-3 text-base leading-6 font-semibold"
                >
                    {condition}
                </p>
            ))}
            <div ref={heroActionRef} className="mt-4">
                <MarketingPrimaryCTA
                    action={action}
                    location="hero"
                    className={
                        compact
                            ? 'offer-action sm:w-full'
                            : 'text-base sm:w-full'
                    }
                />
            </div>
            {p.actionExplanation &&
                (editorial ? (
                    <div
                        data-action-explanation
                        className="mt-3 space-y-1 text-sm leading-5 text-[var(--marketing-text-secondary)]"
                    >
                        <p>{p.actionExplanation.description}</p>
                        {p.actionExplanation.clarification && (
                            <p className="font-semibold text-foreground">
                                {p.actionExplanation.clarification}
                            </p>
                        )}
                    </div>
                ) : (
                    <p className="mt-3 text-sm leading-5 text-[var(--marketing-text-secondary)]">
                        {p.actionExplanation.description}
                    </p>
                ))}
        </>
    );
    const intro = (
        <>
            <OfferHero
                hero={p.hero}
                treatment={treatment}
                hookClassName={hookClassName}
            />
            {renderHeroAnnotation?.()}
            {renderHeroSupport ? (
                renderHeroSupport()
            ) : (
                <p
                    className={
                        compact
                            ? 'mt-3 max-w-xl text-sm leading-5 text-[var(--marketing-text-secondary)] sm:text-base sm:leading-6'
                            : editorial
                              ? 'mt-5 max-w-lg text-base leading-6 text-[var(--marketing-text-secondary)] lg:text-lg lg:leading-7'
                              : 'mt-5 text-base leading-6 text-[var(--marketing-text-secondary)]'
                    }
                >
                    {p.hero.description}
                </p>
            )}
        </>
    );
    const hero = (
        <section
            data-offer-chapter="hero"
            aria-label={p.hero.eyebrow}
            className={`offer-hero-v3 ${mediaLed ? 'offer-media-led' : 'offer-first'} ${p.media ? 'offer-with-media' : ''}`}
        >
            <div className="offer-intro min-w-0">{intro}</div>
            {p.media && (
                <div className="offer-media min-w-0">
                    <OptionalMarketingMedia media={p.media} compact={compact} />
                </div>
            )}
            <div className="offer-details min-w-0">{offerDetails}</div>
        </section>
    );
    const faq = p.faq.length > 0 && (
        <div className={faqClassName}>
            <MarketingFAQ
                items={p.faq}
                treatment={compact ? 'compact' : 'standard'}
            />
        </div>
    );
    return (
        <main
            data-offer-hero={mediaLed ? 'MEDIA_LED' : 'OFFER_FIRST'}
            data-offer-density={compact ? 'COMPACT' : 'STANDARD'}
            onClickCapture={trackGrowthClick}
            className={className}
            data-marketing-surface={
                compact
                    ? undefined
                    : theme === 'DARK_CONVERSION'
                      ? 'dark'
                      : 'light'
            }
        >
            <div className={offerContainer(compact)}>
                {renderNotice?.()}
                {hero}
                {stepsPlacement === 'content' && (
                    <MarketingSteps steps={p.steps} compact={compact} />
                )}
                <div data-offer-operational>{renderOperationalContent()}</div>
                {faq}
            </div>
            <MarketingFinalCTA
                finalCta={p.finalCta}
                action={action}
                treatment={treatment}
                sectionRef={finalActionRef}
            />
            {renderAfterFinal?.()}
        </main>
    );
}
