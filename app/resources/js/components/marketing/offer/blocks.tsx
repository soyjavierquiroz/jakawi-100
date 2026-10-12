import { LandingVideo } from '@/components/media/jakawi-vsl-player/LandingVideo';
import { JakawiImage } from '@/components/jakawi-image';
import {
    MarketingCTA,
    type MarketingAction,
} from '@/components/marketing/primitives';
import { Fragment, type ReactNode, type Ref } from 'react';
import type { OfferPresentationV1 } from './presentation';

export type OfferTreatment = 'compact' | 'editorial' | 'plain';
export const offerContainer = (compact: boolean) =>
    compact
        ? 'mx-auto max-w-6xl px-4 sm:px-6 lg:px-8'
        : 'mx-auto max-w-7xl px-5 sm:px-8 lg:px-12';

export function OptionalMarketingMedia({
    media,
    compact = false,
}: {
    media?: OfferPresentationV1['media'];
    compact?: boolean;
}) {
    if (!media) return null;
    return (
        <div
            data-landing-media
            className={
                compact
                    ? 'w-full'
                    : 'min-w-0 lg:col-start-2 lg:row-span-2 lg:self-center'
            }
        >
            {media.type === 'vsl' ? (
                <LandingVideo config={{ ...media.config, theme: 'inherit' }} />
            ) : (
                <JakawiImage
                    src={media.src}
                    alt={media.alt}
                    className={
                        compact
                            ? 'aspect-[4/3] w-full rounded-[var(--radius-card)] object-cover'
                            : 'aspect-[4/3] w-full object-cover lg:aspect-square'
                    }
                    loading="eager"
                    priority
                />
            )}
        </div>
    );
}

export function OfferHero({
    hero,
    treatment,
    hookClassName = 'text-[clamp(2rem,8.2vw,2.2rem)] leading-[0.98] tracking-[-0.055em]',
}: {
    hero: OfferPresentationV1['hero'];
    treatment: OfferTreatment;
    hookClassName?: string;
}) {
    const compact = treatment === 'compact';
    const editorial = treatment === 'editorial';
    // Emphasis must match text already present in the headline. Invalid spans fall back to plain text.
    const lines = hero.headline.split('\n');
    const content = lines.map((line, index) => {
        const offset = hero.emphasis ? line.indexOf(hero.emphasis) : -1;
        const text =
            offset < 0 ? (
                line
            ) : (
                <>
                    {line.slice(0, offset)}
                    <span className="text-[var(--marketing-emphasis)]">
                        {hero.emphasis}
                    </span>
                    {line.slice(offset + hero.emphasis!.length)}
                </>
            );
        if (compact)
            return line === hero.emphasis ? (
                <span
                    key={index}
                    className="block text-[var(--marketing-emphasis)]"
                >
                    {line}
                </span>
            ) : (
                <Fragment key={index}>
                    {index > 0 && <br />}
                    {text}
                </Fragment>
            );
        return index ? (
            <span key={index} className={editorial ? 'mt-1 block' : 'block'}>
                {text}
            </span>
        ) : (
            <Fragment key={index}>{text}</Fragment>
        );
    });
    return (
        <>
            <p
                className={
                    compact
                        ? 'text-xs font-extrabold tracking-[0.18em] uppercase'
                        : editorial
                          ? 'text-sm font-extrabold tracking-[0.14em] uppercase'
                          : 'text-sm font-extrabold tracking-widest uppercase'
                }
            >
                {hero.eyebrow}
                {hero.context && (
                    <span className="mt-1 block text-[var(--marketing-text-secondary)]">
                        {hero.context}
                    </span>
                )}
            </p>
            <h1
                className={
                    compact
                        ? `${hookClassName} mt-3 max-w-2xl font-extrabold uppercase`
                        : editorial
                          ? 'mt-4 text-[clamp(2.65rem,9.5vw,5rem)] leading-[0.98] font-extrabold tracking-[-0.055em] uppercase lg:text-[4rem]'
                          : 'mt-4 text-[clamp(2.65rem,9.5vw,4rem)] leading-[0.98] font-extrabold tracking-[-0.05em] uppercase'
                }
            >
                {content}
            </h1>
        </>
    );
}

export function MarketingFactStrip({
    facts,
    treatment,
}: {
    facts: OfferPresentationV1['facts'];
    treatment: OfferTreatment;
}) {
    if (!facts.length) return null;
    if (treatment === 'compact')
        return (
            <dl className="offer-facts">
                {facts.map((fact, index) => (
                    <div
                        key={index}
                        className="mt-2 text-xs leading-5 text-[var(--cream)]/70"
                    >
                        <dt className="inline">{fact.label} </dt>
                        <dd className="inline">{fact.value}</dd>
                    </div>
                ))}
            </dl>
        );
    return (
        <dl
            aria-label="Datos esenciales"
            className={`${treatment === 'plain' ? 'mt-4 grid grid-cols-2 gap-4 border-y border-border py-3 text-sm' : 'grid grid-cols-[0.7fr_1.1fr_1.2fr] gap-3 border-y border-border py-3 text-sm leading-5'} offer-facts`}
        >
            {facts.map((fact, index) => (
                <div key={index} className="min-w-0">
                    <dt className="text-[var(--marketing-text-secondary)]">
                        {fact.label}
                    </dt>
                    <dd className="mt-1 font-bold">{fact.value}</dd>
                </div>
            ))}
        </dl>
    );
}

export function MarketingValueBlock({
    value,
    compact = false,
    supplement,
}: {
    value?: OfferPresentationV1['value'];
    compact?: boolean;
    supplement?: ReactNode;
}) {
    if (!value) return null;
    if (compact)
        return (
            <section
                className="offer-value py-8 sm:py-14"
                aria-label={value.label}
            >
                <div className="grid gap-4 lg:grid-cols-[1fr_2fr] lg:gap-12">
                    <div>
                        <p
                            className={`inline-flex rounded-full px-4 py-2 text-xs font-extrabold tracking-widest ${value.tone === 'reward' ? 'bg-[var(--marketing-reward)] text-[var(--marketing-reward-text)]' : 'bg-surface-muted text-foreground'}`}
                        >
                            {value.compactLabel ? (
                                <>
                                    <span className="lg:hidden">
                                        {value.compactLabel}
                                    </span>
                                    <span className="hidden lg:inline">
                                        {value.label}
                                    </span>
                                </>
                            ) : (
                                value.label
                            )}
                        </p>
                    </div>
                    <div>
                        <h2 className="max-w-3xl text-3xl leading-[1.08] font-extrabold tracking-tight sm:text-5xl">
                            {value.title}
                        </h2>
                        {value.description && (
                            <p className="mt-3 text-base leading-6 font-semibold">
                                {value.description}
                            </p>
                        )}
                        {value.detail && (
                            <p className="mt-4 max-w-2xl leading-6 text-[var(--marketing-text-secondary)]">
                                {value.detail}
                            </p>
                        )}
                        {value.note && (
                            <p className="mt-3 text-sm leading-5 text-[var(--marketing-text-muted)]">
                                {value.note}
                            </p>
                        )}
                        {supplement}
                    </div>
                </div>
            </section>
        );
    return (
        <div aria-label={value.label} className="border-t border-border pt-4">
            <p className="text-sm text-[var(--marketing-text-secondary)]">
                {value.label}
            </p>
            <p className="mt-1 text-2xl leading-tight font-extrabold">
                {value.title}
            </p>
            {value.note && (
                <p className="mt-2 text-base leading-6">{value.note}</p>
            )}
        </div>
    );
}

export function MarketingPrices({
    prices,
}: {
    prices?: OfferPresentationV1['prices'];
}) {
    if (!prices?.length) return null;
    return (
        <dl
            aria-label="Precios"
            className="mt-4 grid grid-cols-2 gap-x-5 gap-y-4"
        >
            {prices.map((price, index) => (
                <div
                    key={index}
                    className={
                        index % 2
                            ? 'min-w-0 border-l border-border pl-5'
                            : 'min-w-0'
                    }
                >
                    <dt className="text-sm leading-5 text-[var(--marketing-text-secondary)]">
                        {price.label}
                    </dt>
                    <dd className="mt-1 text-3xl leading-tight font-extrabold tracking-tight">
                        {price.formattedAmount}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

export const MarketingPrimaryCTA = MarketingCTA;

export function MarketingSteps({
    steps,
    compact = false,
    disclosure = false,
}: {
    steps?: OfferPresentationV1['steps'];
    compact?: boolean;
    disclosure?: boolean;
}) {
    if (steps && disclosure)
        return (
            <details className="border-y border-border py-5">
                <summary className="cursor-pointer text-base font-bold">
                    {steps.headline}
                </summary>
                <ol className="mt-4 space-y-5">
                    {steps.items.map((step, index) => (
                        <li key={index}>
                            <h4 className="font-bold">{step.title}</h4>
                            <p className="mt-1 text-base leading-7 text-[var(--marketing-text-secondary)]">
                                {step.description}
                            </p>
                        </li>
                    ))}
                </ol>
            </details>
        );
    if (!steps?.items.length) return null;
    return (
        <section
            data-offer-chapter="steps"
            aria-label={steps.eyebrow}
            className={
                compact
                    ? 'border-t border-border py-10 sm:py-16'
                    : 'border-t border-border py-12 sm:py-16'
            }
        >
            <p
                className={
                    compact
                        ? 'discovery-eyebrow text-foreground'
                        : 'text-sm font-bold tracking-widest'
                }
            >
                {steps.eyebrow}
            </p>
            <h2
                className={
                    compact
                        ? 'mt-3 text-4xl leading-none font-extrabold tracking-tight uppercase sm:text-6xl'
                        : 'mt-3 text-3xl font-extrabold tracking-tight uppercase sm:text-5xl'
                }
            >
                {steps.headline}
            </h2>
            <div
                className={
                    compact
                        ? 'mt-8 grid gap-8 md:grid-cols-3 md:gap-10'
                        : 'mt-8 grid gap-7 md:grid-cols-3'
                }
            >
                {steps.items.map((step, index) => (
                    <article
                        key={index}
                        className={
                            compact ? 'pt-2' : 'border-t border-border pt-5'
                        }
                    >
                        <div
                            className={
                                compact
                                    ? 'offer-step-header'
                                    : 'offer-step-header-simple'
                            }
                        >
                            <span
                                className={
                                    compact
                                        ? 'inline-flex h-14 min-w-18 items-center justify-center rounded-[var(--radius-control)] bg-[var(--marketing-action)] px-3 text-3xl font-extrabold tracking-tight text-[var(--marketing-action-text)]'
                                        : 'text-sm font-bold'
                                }
                            >
                                {String(index + 1).padStart(2, '0')}
                            </span>
                            <h3
                                className={
                                    compact
                                        ? 'mt-4 text-3xl font-extrabold tracking-tight uppercase'
                                        : 'mt-3 text-xl font-extrabold'
                                }
                            >
                                {step.title}
                            </h3>
                        </div>
                        <p
                            className={
                                compact
                                    ? 'mt-3 max-w-sm leading-6 text-[var(--marketing-text-secondary)]'
                                    : 'mt-3 text-base leading-7 text-[var(--marketing-text-secondary)]'
                            }
                        >
                            {step.description}
                        </p>
                    </article>
                ))}
            </div>
        </section>
    );
}

export function MarketingFinalCTA({
    finalCta,
    action,
    treatment,
    sectionRef,
}: {
    finalCta: OfferPresentationV1['finalCta'];
    action: MarketingAction;
    treatment: OfferTreatment;
    sectionRef?: Ref<HTMLElement>;
}) {
    const compact = treatment === 'compact';
    return (
        <section
            data-marketing-surface="dark"
            ref={sectionRef}
            data-offer-chapter="final"
            aria-label="CTA final"
            className={
                compact
                    ? 'bg-[var(--ink)] py-12 text-[var(--cream)] sm:py-16'
                    : 'bg-[var(--ink)] py-14 text-[var(--cream)] sm:py-20'
            }
        >
            <div className={offerContainer(compact)}>
                {finalCta.eyebrow && (
                    <p className="discovery-eyebrow text-[var(--cream)]/70">
                        {finalCta.eyebrow}
                    </p>
                )}
                <h2
                    className={
                        compact
                            ? 'mt-4 max-w-4xl text-5xl leading-[0.95] font-extrabold tracking-tight uppercase sm:text-7xl'
                            : `max-w-4xl text-4xl ${treatment === 'editorial' ? 'leading-[1.02]' : 'leading-tight'} font-extrabold tracking-tight uppercase sm:text-6xl`
                    }
                >
                    {finalCta.headline}
                </h2>
                {finalCta.description && (
                    <p
                        className={
                            compact
                                ? 'mt-5 max-w-2xl text-[var(--cream)]/75'
                                : 'mt-5 max-w-xl text-base leading-7 text-[var(--marketing-text-secondary)]'
                        }
                    >
                        {finalCta.description}
                    </p>
                )}
                <div className="mt-8">
                    <MarketingPrimaryCTA
                        action={action}
                        className={compact ? 'offer-action' : 'text-base'}
                    />
                </div>
            </div>
        </section>
    );
}
