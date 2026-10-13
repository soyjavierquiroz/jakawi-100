import { useLandingAction } from '@/components/marketing/landing-action-context';
import { useAcquisition } from '@/components/marketing/acquisition-context';
import { distinctOfferFaq } from '@/components/marketing/offer/composition';
import { OfferConversionTemplate } from '@/components/marketing/offer/OfferConversionTemplate';
import type {
    OfferPresentationV1,
    LandingOfferEditorial,
} from '@/components/marketing/offer/presentation';
import { type LandingVideoConfig } from '@/components/media/jakawi-vsl-player/LandingVideo';
import { Head, Link } from '@inertiajs/react';
import { useRef } from 'react';
import { StatusBanner } from '@/components/state-panel';
import { trackJourneyIntent } from '@/lib/journey-analytics';

export type BenefitOfferProps = {
    vsl?: LandingVideoConfig | null;
    offerEditorial?: LandingOfferEditorial;
    benefit: { slug: string; title: string; terms: string | null };
    presentation: {
        hero_layout?: 'OFFER_FIRST' | 'MEDIA_LED' | null;
        density?: 'STANDARD' | 'COMPACT' | null;
        id?: number;
        slug?: string;
        marketing_theme?: 'DARK_CONVERSION' | 'LIGHT_CONVERSION' | null;
        template_key?: 'OFFER_CONVERSION' | null;
        hero_alt: string | null;
    };
    copy: {
        eyebrow: string;
        headline: string;
        subheadline: string;
        value: string;
        valueNote: string | null;
        description: string | null;
        partner: string;
        deadline: string | null;
        membership: string;
        rules: string[];
        finalHeadline: string;
        places: Array<{
            id: number;
            name: string;
            city: string | null;
            address: string | null;
            zone: string | null;
        }>;
        benefits: Array<{ title: string; description: string }>;
        steps: Array<{ title: string; description: string }>;
        faq: Array<{ question: string; answer: string }>;
        action: { href: string; label: string; kind: string };
    };
    heroUrl: string | null;
    nativeUrl: string;
    canonical: string;
    noindex: boolean;
    preview: boolean;
};

export default function BenefitOfferAdapter({
    vsl,
    offerEditorial,
    benefit,
    presentation,
    copy,
    heroUrl,
    canonical,
    noindex,
    preview,
}: BenefitOfferProps) {
    const acquire = useAcquisition();
    const continueAction = useLandingAction();
    const sent = useRef(false);
    const disabled = preview || copy.action.kind === 'unavailable';
    const action = {
        ...copy.action,
        label:
            copy.action.kind === 'guest'
                ? 'QUIERO MI BENEFICIO'
                : copy.action.kind === 'native' &&
                    copy.action.label === 'USAR BENEFICIO'
                  ? 'USAR MI BENEFICIO'
                  : copy.action.label,
        disabled,
        href: disabled ? '#condiciones' : copy.action.href,
        onClick: (event: React.MouseEvent<HTMLAnchorElement>) => {
            if (disabled) {
                event.preventDefault();
                return;
            }
            if (copy.action.kind === 'guest') {
                event.preventDefault();
                acquire();
                return;
            }
            if (
                copy.action.kind === 'membership' ||
                (copy.action.kind === 'native' &&
                    copy.action.label === 'USAR BENEFICIO')
            ) {
                event.preventDefault();
                continueAction();
            }
            if (copy.action.kind === 'membership' && !sent.current) {
                sent.current = true;
                trackJourneyIntent('benefit', benefit.slug);
            }
        },
    };
    const cities = [
        ...new Set(copy.places.map((place) => place.city).filter(Boolean)),
    ].join(' · ');
    const facts = [
        cities && { label: 'Lugar', value: cities },
        copy.deadline && { label: 'Vigencia', value: copy.deadline, tone: 'reward' as const },
    ].filter((fact): fact is { label: string; value: string; tone?: 'reward' } => Boolean(fact));
    const offer: OfferPresentationV1 = {
        hero: {
            eyebrow: `Beneficio en ${copy.partner}`,
            headline: copy.headline,
            description: copy.subheadline,
        },
        media: vsl
            ? { type: 'vsl', config: vsl }
            : heroUrl
              ? {
                    type: 'image',
                    src: heroUrl,
                    alt: presentation.hero_alt || benefit.title,
                }
              : undefined,
        facts,
        value: {
            tone: 'offer',
            label: 'Qué obtienes',
            title: copy.value,
            note: copy.valueNote || undefined,
        },
        essentialConditions: [copy.membership],
        actionExplanation: {
            description:
                (copy.action.kind === 'guest'
                    ? 'Crea tu cuenta en menos de un minuto y continúa aquí mismo. El canje requiere membresía activa.'
                    : copy.action.kind === 'membership'
                      ? 'Activa tu membresía para continuar.'
                      : 'Continúa en la ficha para revisar cómo usarlo.') +
                ' El partner valida tu código.',
        },
        // A short domain-bound redemption explanation replaces the three generic introductions.
        faq: distinctOfferFaq(copy.faq, [
            copy.membership,
            ...copy.rules,
            ...copy.steps.map((step) => step.description),
            copy.places.map((place) => place.name).join(' · '),
        ]),
        finalCta: {
            headline: copy.finalHeadline,
            description: `${copy.value} en ${copy.partner}. ${copy.membership}`,
        },
    };
    if (offerEditorial?.version === 1) {
        offer.hero = {
            eyebrow: offerEditorial.eyebrow,
            headline: offerEditorial.headline,
            emphasis: offerEditorial.emphasis || undefined,
            description: offerEditorial.subheadline,
        };
        offer.finalCta.headline = offerEditorial.finalHeadline;
    }
    if (offerEditorial?.version === 1)
        offer.finalCta.description =
            offerEditorial.finalDescription ?? undefined;
    return (
        <>
            <Head title={offer.hero.headline}>
                <link rel="canonical" href={canonical} />
                {noindex && <meta name="robots" content="noindex,follow" />}
            </Head>
            <OfferConversionTemplate
                hero={presentation.hero_layout ?? undefined}
                density={presentation.density ?? undefined}
                presentation={offer}
                theme={presentation.marketing_theme || undefined}
                action={action}
                treatment="plain"
                className="benefit-discovery-v1 overflow-x-clip font-discovery break-words"
                faqClassName="benefit-faq border-t border-border"
                renderNotice={() =>
                    preview && (
                        <StatusBanner
                            title="VISTA PREVIA"
                            description="Las acciones están desactivadas en esta vista."
                            className="mt-5"
                        />
                    )
                }
                renderOperationalContent={() => (
                    <>
                        <section
                            id="condiciones"
                            className="border-t border-border py-12 sm:py-16 lg:grid lg:grid-cols-[0.9fr_1.1fr] lg:gap-14"
                        >
                            <div>
                                <h2 className="text-3xl font-extrabold tracking-tight uppercase sm:text-5xl">
                                    Antes de usarlo
                                </h2>
                                {copy.description &&
                                    (presentation.density === 'COMPACT' ? (
                                        <details className="mt-4 border-y border-border py-4">
                                            <summary className="cursor-pointer text-base font-bold">
                                                Sobre el beneficio
                                            </summary>
                                            <p className="mt-3 text-base leading-7 text-[var(--marketing-text-secondary)]">
                                                {copy.description}
                                            </p>
                                        </details>
                                    ) : (
                                        <p className="mt-5 text-base leading-7 text-[var(--marketing-text-secondary)]">
                                            {copy.description}
                                        </p>
                                    ))}
                                {copy.action.kind === 'unavailable' && (
                                    <StatusBanner
                                        title={copy.action.label}
                                        description="Revisa el estado en la ficha del beneficio."
                                        className="mt-6"
                                    />
                                )}
                            </div>
                            <div className="mt-7 lg:mt-0">
                                <p className="mb-5 text-base leading-6 text-[var(--marketing-text-secondary)]">
                                    {copy.steps.at(-1)?.description}
                                </p>
                                <h3 className="text-xl font-extrabold">
                                    {copy.partner}
                                </h3>
                                {copy.places.length ? (
                                    <ul className="mt-4 divide-y divide-border">
                                        {copy.places.map((place) => (
                                            <li
                                                key={place.id}
                                                className="py-4 text-base leading-6"
                                            >
                                                <p className="font-bold">
                                                    {place.name}
                                                    {place.city
                                                        ? ` · ${place.city}`
                                                        : ''}
                                                </p>
                                                <p className="mt-1 text-[var(--marketing-text-secondary)]">
                                                    {[place.zone, place.address]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </p>
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="mt-4">
                                        No hay sucursales aplicables publicadas.
                                    </p>
                                )}
                                <details className="border-y border-border py-5">
                                    <summary className="cursor-pointer text-base font-bold">
                                        Vigencia y requisitos
                                    </summary>
                                    <ul className="mt-4 space-y-3 text-base leading-7 text-[var(--marketing-text-secondary)]">
                                        {copy.rules.map((rule) => (
                                            <li
                                                key={rule}
                                                className="whitespace-pre-line"
                                            >
                                                {rule}
                                            </li>
                                        ))}
                                    </ul>
                                </details>
                                {benefit.terms && (
                                    <details className="border-b border-border py-5">
                                        <summary className="cursor-pointer text-base font-bold">
                                            Condiciones completas
                                        </summary>
                                        <p className="mt-4 text-base leading-7 whitespace-pre-line text-[var(--marketing-text-secondary)]">
                                            {benefit.terms}
                                        </p>
                                    </details>
                                )}
                                <Link
                                    href="#condiciones"
                                    className="mt-4 inline-flex min-h-11 items-center text-base font-bold underline"
                                >
                                    Ver ficha del beneficio
                                </Link>
                            </div>
                        </section>
                    </>
                )}
            />
        </>
    );
}
