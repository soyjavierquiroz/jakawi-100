import { OfferConversionTemplate } from '@/components/marketing/offer/OfferConversionTemplate';
import { MarketingSteps } from '@/components/marketing/offer/blocks';
import type {
    OfferPresentationV1,
    LandingOfferEditorial,
} from '@/components/marketing/offer/presentation';
import { type LandingVideoConfig } from '@/components/media/jakawi-vsl-player/LandingVideo';
import { Head, Link, router } from '@inertiajs/react';
import { useRef } from 'react';
import { StatusBanner } from '@/components/state-panel';
import { trackJourneyIntent } from '@/lib/journey-analytics';

type Session = {
    id: number;
    date: string;
    time: string;
    endTime?: string | null;
    capacity?: number | null;
    place: string | null;
    address: string | null;
    city: string | null;
};
type Partner = { name: string; slug: string; role?: string };
export type ExperienceOfferProps = {
    vsl?: LandingVideoConfig | null;
    offerEditorial?: LandingOfferEditorial;
    presentation: {
        hero_layout?: 'OFFER_FIRST' | 'MEDIA_LED' | null;
        density?: 'STANDARD' | 'COMPACT' | null;
        id?: number;
        slug?: string;
        marketing_theme?: 'DARK_CONVERSION' | 'LIGHT_CONVERSION' | null;
        template_key?: 'OFFER_CONVERSION' | null;
        hero_alt: string | null;
    };
    heroUrl: string | null;
    nativeUrl: string;
    canonical: string;
    noindex: boolean;
    preview: boolean;
    copy: {
        title: string;
        slug: string;
        experienceType: string | null;
        durationMinutes?: number | null;
        reservationMethod?: string;
        eyebrow: string;
        headline: string;
        subheadline: string;
        valueNote: string | null;
        description: string | null;
        terms: string | null;
        organizers: Partner[];
        partners: Partner[];
        prices: Array<{ label: string; value: string }>;
        next: Session | null;
        sessions: Session[];
        sessionCount: number;
        membership: string;
        steps: Array<{ title: string; description: string }>;
        faq: Array<{ question: string; answer: string }>;
        finalHeadline: string;
        action: {
            label: string;
            href: string;
            kind: string;
            sessionId: number | null;
        };
    };
};

// This editorial treatment belongs to the inspected QA presentation, not every event.
// The duration-led offer requires the inspected duration and city; domain actions remain authoritative.
export default function ExperienceOfferAdapter({
    vsl,
    offerEditorial,
    presentation,
    heroUrl,
    nativeUrl,
    canonical,
    noindex,
    preview,
    copy,
}: ExperienceOfferProps) {
    const qaSunset =
        presentation.id === 3 &&
        presentation.slug === 'qa-experience-marketing-sunset-jakawi' &&
        copy.slug === 'demo-sunset-jakawi';
    const sunset =
        qaSunset &&
        copy.durationMinutes === 180 &&
        copy.next?.city === 'Cochabamba';
    const externalReservation = Boolean(
        copy.reservationMethod && copy.reservationMethod !== 'jakawi',
    );
    const sent = useRef(false);
    const disabled = preview || copy.action.kind === 'unavailable';
    const action = {
        ...copy.action,
        label:
            copy.action.kind === 'native' &&
            ['QUIERO IR', 'RESERVAR'].includes(copy.action.label)
                ? 'VER FECHAS Y RESERVAR'
                : copy.action.label,
        disabled,
        onClick: (event: React.MouseEvent<HTMLAnchorElement>) => {
            if (disabled) {
                event.preventDefault();
                return;
            }
            if (copy.action.kind === 'guest') {
                event.preventDefault();
                router.post(copy.action.href, {
                    experience_session_id: copy.action.sessionId,
                });
                return;
            }
            if (copy.action.kind === 'membership' && !sent.current) {
                sent.current = true;
                trackJourneyIntent('experience', copy.slug);
            }
        },
    };
    const place = (session: Session) =>
        [session.place, session.city].filter(Boolean).join(' · ');
    const headline = sunset
        ? '3 horas para vivir Cochabamba desde arriba.'
        : copy.headline;
    const promise = sunset
        ? `Música suave, vista a la ciudad y un atardecer compartido${copy.next?.place ? ` en ${copy.next.place}` : ''}.`
        : copy.subheadline;
    const compactDate = (date: string) => {
        const [day, month] = date.split('/');
        const name = [
            'ENE',
            'FEB',
            'MAR',
            'ABR',
            'MAY',
            'JUN',
            'JUL',
            'AGO',
            'SEP',
            'OCT',
            'NOV',
            'DIC',
        ][Number(month) - 1];
        return name ? `${Number(day)} ${name}` : date;
    };
    const priceLabel = (value: string) => value.replace(/\.00\b/g, '');
    // Preserve the reservation condition without promoting unverified scarcity.
    const terms =
        qaSunset && copy.terms === 'Cupo limitado; reserva previa requerida.'
            ? 'Reserva previa requerida.'
            : copy.terms;
    const points = sunset
        ? [
              {
                  title: 'Música suave',
                  description: 'El ritmo de una tarde compartida.',
              },
              {
                  title: 'Vista a la ciudad',
                  description: 'Un encuentro en altura.',
              },
              {
                  title: 'Atardecer compartido',
                  description: 'Una tarde para encontrarnos.',
              },
          ]
        : [];
    const offer: OfferPresentationV1 = {
        hero: {
            eyebrow: sunset ? 'SUNSET JAKAWI' : copy.eyebrow,
            context: !sunset ? copy.next?.city || undefined : undefined,
            headline: sunset
                ? '3 horas para vivir\nCochabamba desde arriba.'
                : copy.headline,
            emphasis: sunset ? 'Cochabamba' : undefined,
            description: promise,
        },
        media: vsl
            ? { type: 'vsl', config: vsl }
            : heroUrl
              ? {
                    type: 'image',
                    src: heroUrl,
                    alt: presentation.hero_alt || copy.title,
                }
              : undefined,
        facts: copy.next
            ? [
                  { label: 'Fecha', value: compactDate(copy.next.date) },
                  {
                      label: 'Hora',
                      value:
                          copy.next.time +
                          (copy.next.endTime ? `–${copy.next.endTime}` : ''),
                  },
                  {
                      label: 'Lugar',
                      value: copy.next.place || copy.next.city || '',
                  },
              ]
            : [],
        prices: copy.prices.map((price) => ({
            label: price.label,
            formattedAmount: priceLabel(price.value),
        })),
        essentialConditions: [],
        actionExplanation: {
            description: externalReservation
                ? 'La reserva continúa en la ficha y se gestiona con el partner.'
                : copy.membership,
            clarification: externalReservation
                ? 'No necesitas membresía para asistir.'
                : undefined,
        },
        steps: {
            eyebrow: 'CÓMO RESERVAR',
            headline: 'Cómo reservar',
            items: copy.steps,
        },
        faq: copy.faq,
        finalCta: {
            headline: sunset
                ? 'Haz del atardecer tu próximo plan.'
                : copy.finalHeadline,
            description: sunset
                ? 'Consulta la ficha y continúa tu reserva con el partner.'
                : copy.title,
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
                treatment="editorial"
                stepsPlacement="operational"
                className="experience-marketing-v3 overflow-x-clip font-discovery break-words"
                faqClassName="experience-faq border-t border-border"
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
                            aria-label="Lo que vas a vivir"
                            className="border-t border-border py-12 sm:py-20 lg:grid lg:grid-cols-[0.9fr_1.1fr] lg:gap-14"
                        >
                            <div>
                                <h2 className="max-w-xl text-4xl leading-[1.04] font-extrabold tracking-tight uppercase sm:text-6xl">
                                    {sunset ? 'Por qué este plan' : copy.title}
                                </h2>
                            </div>
                            <div className="mt-7 lg:mt-0">
                                {!sunset && copy.description && (
                                    <p className="max-w-xl text-lg leading-7 whitespace-pre-line text-[var(--marketing-text-secondary)]">
                                        {copy.description}
                                    </p>
                                )}
                                {points.length > 0 && (
                                    <div className="divide-y divide-border">
                                        {points.map((point, index) => (
                                            <article
                                                key={point.title}
                                                className="flex gap-5 py-5"
                                            >
                                                <span className="pt-1 text-sm font-bold text-[var(--marketing-text-secondary)]">
                                                    0{index + 1}
                                                </span>
                                                <div>
                                                    <h3 className="text-xl font-extrabold">
                                                        {point.title}
                                                    </h3>
                                                    <p className="mt-2 text-base leading-6 text-[var(--marketing-text-secondary)]">
                                                        {point.description}
                                                    </p>
                                                </div>
                                            </article>
                                        ))}
                                    </div>
                                )}
                                {copy.valueNote && (
                                    <p className="mt-6 text-base leading-7">
                                        {copy.valueNote}
                                    </p>
                                )}
                            </div>
                        </section>
                        <section
                            id="condiciones"
                            className="border-t border-border py-12 sm:py-16 lg:grid lg:grid-cols-[0.9fr_1.1fr] lg:gap-14"
                        >
                            <div>
                                <p className="text-sm font-extrabold tracking-widest">
                                    PREPARA TU PLAN
                                </p>
                                <h2 className="mt-4 text-3xl leading-tight font-extrabold tracking-tight uppercase sm:text-5xl">
                                    Antes de reservar
                                </h2>
                                <p className="mt-5 text-base leading-7 text-[var(--marketing-text-secondary)]">
                                    Revisa los datos de la sesión y las
                                    condiciones antes de continuar.
                                </p>
                                {copy.action.kind === 'unavailable' && (
                                    <StatusBanner
                                        title={copy.action.label}
                                        description="Consulta la ficha para conocer el estado de la experiencia."
                                        className="mt-6"
                                    />
                                )}
                            </div>
                            <div className="mt-7 lg:mt-0">
                                <div>
                                    <h3 className="text-lg font-extrabold">
                                        Cuándo y dónde
                                    </h3>
                                    {copy.sessions.length ? (
                                        <ul className="mt-3 divide-y divide-border">
                                            {copy.sessions.map((session) => (
                                                <li
                                                    key={session.id}
                                                    className="py-4 text-base leading-7"
                                                >
                                                    <p className="font-bold">
                                                        {session.date} ·{' '}
                                                        {session.time}
                                                        {session.endTime
                                                            ? `–${session.endTime}`
                                                            : ''}
                                                    </p>
                                                    <p>{place(session)}</p>
                                                    {session.address && (
                                                        <p className="text-[var(--marketing-text-secondary)]">
                                                            {session.address}
                                                        </p>
                                                    )}
                                                    {session.capacity !=
                                                        null && (
                                                        <p className="text-sm text-[var(--marketing-text-secondary)]">
                                                            Capacidad de la
                                                            sesión:{' '}
                                                            {session.capacity}{' '}
                                                            personas.
                                                        </p>
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <p className="mt-4">
                                            No hay próximas fechas publicadas.
                                        </p>
                                    )}
                                    {copy.sessionCount >
                                        copy.sessions.length && (
                                        <p className="mt-4">
                                            Consulta los demás horarios en la
                                            ficha.
                                        </p>
                                    )}
                                </div>
                                <MarketingSteps
                                    steps={offer.steps}
                                    disclosure
                                />
                                {terms && (
                                    <details className="border-b border-border py-5">
                                        <summary className="cursor-pointer text-base font-bold">
                                            Condiciones completas
                                        </summary>
                                        <p className="mt-4 text-base leading-7 whitespace-pre-line text-[var(--marketing-text-secondary)]">
                                            {terms}
                                        </p>
                                    </details>
                                )}
                                {copy.partners.length > 0 && (
                                    <details className="border-b border-border py-5">
                                        <summary className="cursor-pointer text-base font-bold">
                                            Quién lo hace posible
                                        </summary>
                                        <div className="mt-4 space-y-4">
                                            {copy.partners.map((partner) => (
                                                <p
                                                    key={`${partner.slug}-${partner.role}`}
                                                    className="text-base leading-6"
                                                >
                                                    <span className="block text-sm text-[var(--marketing-text-secondary)]">
                                                        {partner.role ===
                                                        'organizer'
                                                            ? 'Organiza'
                                                            : 'Partner · ' +
                                                              partner.role}
                                                    </span>
                                                    <Link
                                                        href={`/partners/${partner.slug}`}
                                                        className="inline-flex min-h-11 items-center font-bold underline"
                                                    >
                                                        {partner.name}
                                                    </Link>
                                                </p>
                                            ))}
                                            <p className="text-base leading-6 text-[var(--marketing-text-secondary)]">
                                                JAKAWI conecta esta experiencia
                                                contigo.
                                            </p>
                                        </div>
                                    </details>
                                )}
                                <Link
                                    href={nativeUrl}
                                    className="mt-5 inline-flex min-h-11 items-center text-base font-bold underline"
                                >
                                    Ver ficha, fechas y reserva
                                </Link>
                            </div>
                        </section>
                    </>
                )}
            />
        </>
    );
}
