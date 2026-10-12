import { useGrowthLandingClicks } from '@/lib/growth-landing-analytics';
import {
    MarketingFAQ,
    type MarketingAction,
} from '@/components/marketing/primitives';
import type { ReactNode, Ref } from 'react';
import type { OfferPresentationV1 } from './presentation';
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
            {!compact && <MarketingValueBlock value={p.value} />}
            {!compact && (
                <MarketingFactStrip facts={p.facts} treatment={treatment} />
            )}
            <MarketingPrices prices={p.prices} />
            {!compact &&
                p.essentialConditions.map((condition, index) => (
                    <p
                        key={index}
                        className="mt-3 text-base leading-6 font-semibold"
                    >
                        {condition}
                    </p>
                ))}
            <div
                ref={heroActionRef}
                className={compact ? (p.media ? 'mt-4' : '') : 'mt-4'}
            >
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
            {compact &&
                p.essentialConditions.map((condition, index) => (
                    <p
                        key={index}
                        className="mt-3 text-xs font-extrabold tracking-widest"
                    >
                        {condition}
                    </p>
                ))}
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
                    <p
                        className={
                            compact
                                ? 'mt-3 hidden text-sm leading-5 font-semibold lg:block'
                                : 'mt-3 text-sm leading-5 text-[var(--marketing-text-secondary)]'
                        }
                    >
                        {p.actionExplanation.description}
                    </p>
                ))}
            {compact && (
                <MarketingFactStrip facts={p.facts} treatment={treatment} />
            )}
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
    const hero = compact ? (
        <section
            data-marketing-surface={
                theme === 'LIGHT_CONVERSION' ? 'light' : 'dark'
            }
            data-offer-chapter="hero"
            className={
                theme === 'LIGHT_CONVERSION'
                    ? 'bg-background text-foreground'
                    : 'bg-[var(--ink)] text-[var(--cream)]'
            }
        >
            <div className="mx-auto grid max-w-6xl gap-4 px-4 py-5 sm:px-6 sm:py-10 lg:grid-cols-2 lg:items-start lg:gap-x-12 lg:px-8 lg:pt-14 lg:pb-12">
                <div className="min-w-0 lg:sticky lg:top-8">{intro}</div>
                <div className="min-w-0 lg:col-start-2">
                    {mediaLed && (
                        <OptionalMarketingMedia media={p.media} compact />
                    )}
                    {offerDetails}
                    {!mediaLed && (
                        <OptionalMarketingMedia media={p.media} compact />
                    )}
                </div>
            </div>
        </section>
    ) : (
        <section
            aria-label={p.hero.eyebrow}
            className={`offer-hero grid gap-y-4 pt-7 pb-12 ${editorial ? 'sm:pt-10 sm:pb-16 lg:gap-y-0' : ''} lg:gap-x-14 lg:pt-16 lg:pb-20 ${p.media ? 'lg:grid-cols-[0.9fr_1.1fr]' : editorial ? 'lg:max-w-4xl' : 'max-w-4xl'}`}
        >
            <div
                className={
                    editorial
                        ? 'min-w-0 lg:self-end lg:pb-8'
                        : 'min-w-0 lg:pb-5'
                }
            >
                {intro}
            </div>
            {mediaLed && <OptionalMarketingMedia media={p.media} />}
            <div className="min-w-0 lg:col-start-1 lg:row-start-2">
                {offerDetails}
            </div>
            {!mediaLed && p.media && (
                <div className="min-w-0 lg:col-start-2 lg:row-span-2 lg:row-start-1">
                    <OptionalMarketingMedia media={p.media} />
                </div>
            )}
        </section>
    );
    const faq = (
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
            {compact ? (
                <>
                    {renderNotice?.()}
                    {hero}
                    <div data-marketing-surface="light">
                        <div className={offerContainer(true)}>
                            <MarketingValueBlock
                                value={p.value}
                                compact
                                supplement={renderValueSupplement?.()}
                            />
                            <MarketingSteps steps={p.steps} compact />
                        </div>
                        {renderOperationalContent()}
                        <div className={offerContainer(true)}>{faq}</div>
                    </div>
                </>
            ) : (
                <div className={offerContainer(false)}>
                    {renderNotice?.()}
                    {hero}
                    {stepsPlacement === 'content' && (
                        <MarketingSteps steps={p.steps} />
                    )}
                    {renderOperationalContent()}
                    {faq}
                </div>
            )}
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
