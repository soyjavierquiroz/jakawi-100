import { OfferConversionTemplate } from '@/components/marketing/offer/OfferConversionTemplate';
import type {
    OfferPresentationV1,
    LandingOfferEditorial,
} from '@/components/marketing/offer/presentation';
import { type LandingVideoConfig } from '@/components/media/jakawi-vsl-player/LandingVideo';
import { Head, Link, router } from '@inertiajs/react';
import { useRef } from 'react';
import { StatusBanner } from '@/components/state-panel';
import { trackJourneyIntent } from '@/lib/journey-analytics';

export type BenefitOfferProps = {
    vsl?: LandingVideoConfig | null;
    offerEditorial?: LandingOfferEditorial;
    benefit: { slug: string; title: string; terms: string | null };
    presentation: {
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
    nativeUrl,
    canonical,
    noindex,
    preview,
}: BenefitOfferProps) {
    const sent = useRef(false);
    const disabled = preview || copy.action.kind === 'unavailable';
    const action = {
        ...copy.action,
        label:
            copy.action.kind === 'guest'
                ? 'CONTINUAR CON MI CUENTA'
                : copy.action.kind === 'native' &&
                    copy.action.label === 'USAR BENEFICIO'
                  ? 'VER CÓMO CANJEAR'
                  : copy.action.label,
        disabled,
        href: disabled ? '#condiciones' : copy.action.href,
        onClick: (event: React.MouseEvent<HTMLAnchorElement>) => {
            if (disabled) {
                event.preventDefault();
                return;
            }
            // TODO: Resolve guest redemption continuity (auth/verification + location_id) at domain flow level before real commercial launch.
            if (copy.action.kind === 'guest') {
                event.preventDefault();
                router.post(copy.action.href);
                return;
            }
            if (copy.action.kind === 'membership' && !sent.current) {
                sent.current = true;
                trackJourneyIntent('benefit', benefit.slug);
            }
        },
    };
    const qaCoffee =
        presentation.id === 2 &&
        presentation.slug === 'qa-benefit-marketing-demo-beneficio-01' &&
        benefit.slug === 'demo-beneficio-01' &&
        copy.value === 'Café de cortesía con tu brunch';
    const headline = qaCoffee
        ? 'Tu brunch, con café de cortesía.'
        : copy.headline;
    const cities = [
        ...new Set(copy.places.map((place) => place.city).filter(Boolean)),
    ].join(' · ');
    const facts = [
        cities && { label: 'Lugar', value: cities },
        copy.deadline && { label: 'Vigencia', value: copy.deadline },
    ].filter((fact): fact is { label: string; value: string } => Boolean(fact));
    const offer: OfferPresentationV1 = {
        hero: {
            eyebrow: `Beneficio en ${copy.partner}`,
            headline: qaCoffee ? 'Tu brunch,\ncon café de cortesía.' : headline,
            emphasis: qaCoffee ? 'café de cortesía.' : undefined,
            description: qaCoffee ? 'Para miembros JAKAWI.' : copy.subheadline,
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
            title: qaCoffee ? 'Café de cortesía' : copy.value,
            note: copy.valueNote || undefined,
        },
        essentialConditions: [
            qaCoffee
                ? 'Con tu brunch. Membresía activa requerida.'
                : copy.membership,
        ],
        actionExplanation: {
            description:
                (copy.action.kind === 'guest'
                    ? 'Continúa con tu cuenta JAKAWI. El canje requiere membresía activa.'
                    : copy.action.kind === 'membership'
                      ? 'Activa tu membresía para continuar.'
                      : 'Continúa en la ficha para revisar cómo usarlo.') +
                ' El partner valida tu código.',
        },
        steps: {
            eyebrow: 'CÓMO USARLO',
            headline: 'De tu cuenta al canje',
            items: copy.steps,
        },
        faq: copy.faq,
        finalCta: {
            headline: qaCoffee
                ? 'Tu brunch merece ese café.'
                : copy.finalHeadline,
            description: `${copy.value} en ${copy.partner}. ${qaCoffee ? 'Con tu brunch. Membresía activa requerida.' : copy.membership}`,
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
    if (offerEditorial?.finalDescription)
        offer.finalCta.description = offerEditorial.finalDescription;
    return (
        <>
            <Head title={offer.hero.headline}>
                <link rel="canonical" href={canonical} />
                {noindex && <meta name="robots" content="noindex,follow" />}
            </Head>
            <OfferConversionTemplate
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
                                {copy.description && (
                                    <p className="mt-5 text-base leading-7 text-[var(--marketing-text-secondary)]">
                                        {copy.description}
                                    </p>
                                )}
                                {copy.action.kind === 'unavailable' && (
                                    <StatusBanner
                                        title={copy.action.label}
                                        description="Revisa el estado en la ficha del beneficio."
                                        className="mt-6"
                                    />
                                )}
                            </div>
                            <div className="mt-7 lg:mt-0">
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
                                    href={nativeUrl}
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
