import { OfferConversionTemplate } from '@/components/marketing/offer/OfferConversionTemplate';
import type {
    OfferPresentationV1,
    LandingOfferEditorial,
} from '@/components/marketing/offer/presentation';
import { type LandingVideoConfig } from '@/components/media/jakawi-vsl-player/LandingVideo';
import { Head, Link, usePage } from '@inertiajs/react';
import { MarketingCTA } from '@/components/marketing/primitives';
import { StatusBanner } from '@/components/state-panel';
import Show from '@/pages/social-challenges/show';
import { useEffect, useRef, useState, type ComponentProps } from 'react';

export type ChallengeOfferProps = ComponentProps<typeof Show> & {
    vsl?: LandingVideoConfig | null;
    offerEditorial?: LandingOfferEditorial;
    presentation: {
        hero_layout?: 'OFFER_FIRST' | 'MEDIA_LED' | null;
        density?: 'STANDARD' | 'COMPACT' | null;
        id: number;
        name: string;
        slug: string;
        default_scope: 'NONE' | 'GUESTS' | 'ALL';
        marketing_theme?: 'DARK_CONVERSION' | 'LIGHT_CONVERSION' | null;
        template_key?: 'OFFER_CONVERSION' | null;
        hero_alt: string | null;
    };
    copy: {
        eyebrow: string;
        headline: string;
        subheadline: string;
        mechanism: string;
        confidence: string;
        reward: string;
        rewardType: string;
        rewardNote: string | null;
        rewardDetail: string | null;
        deadline: string | null;
        qualification: string;
        selection: string;
        membership: string;
        review: string;
        benefits: Array<{ title: string; description: string }>;
        steps: Array<{ title: string; description: string }>;
        rules: Array<{ title: string; description: string }>;
        faq: Array<{ question: string; answer: string }>;
        finalHeadline: string;
    };
    nativeUrl: string;
    canonical: string;
    noindex: boolean;
};

function formattedDate(value: string | null) {
    return value
        ? new Intl.DateTimeFormat('es-BO', {
              dateStyle: 'long',
              timeStyle: 'short',
              timeZone: 'America/La_Paz',
          }).format(new Date(value))
        : null;
}

export default function ChallengeOfferAdapter({
    vsl,
    offerEditorial,
    presentation,
    copy,
    nativeUrl,
    canonical,
    noindex,
    ...challengeProps
}: ChallengeOfferProps) {
    const { auth } = usePage().props as {
        auth: { user?: { id: number } | null };
    };
    // Editorial demo only: never replace the configured domain reward or rules.
    const commercialDemo =
        presentation.slug === 'qa-bafabo-marketing' &&
        challengeProps.challenge.id === 1 &&
        challengeProps.challenge.slug === 'reto-de-prueba-jakawi-bafabo';
    const demoDisclosure = 'Demo visual — premio ilustrativo, no canjeable.';
    const displayedReward = commercialDemo ? 'Cena para dos' : copy.reward;
    const confidence = commercialDemo
        ? 'Si cumples las reglas, calificas. Premio QA sin valor comercial.'
        : copy.confidence;
    const participating = Boolean(challengeProps.participation);
    const grant = challengeProps.grant;
    const qualified =
        challengeProps.participation?.qualification_status === 'qualified';
    const membershipRequired =
        qualified &&
        challengeProps.challenge.reward_eligibility === 'ACTIVE_MEMBERS' &&
        !challengeProps.isMember &&
        !grant;
    const closed = !challengeProps.canParticipate;
    const action =
        grant && ['granted', 'fulfilled'].includes(grant.status)
            ? {
                  href: '#participar',
                  label: commercialDemo
                      ? 'VER MI PARTICIPACIÓN'
                      : 'VER MI PREMIO',
              }
            : membershipRequired
              ? {
                    href: `/membresia?journey=SOCIAL_CHALLENGE&action=PARTICIPATE&resource_id=${challengeProps.challenge.id}`,
                    label: 'ACTIVAR MEMBRESÍA',
                }
              : participating
                ? { href: '#participar', label: 'VER MI PARTICIPACIÓN' }
                : closed
                  ? { href: '#mecanica', label: 'VER RESULTADOS' }
                  : {
                        href: '#participar',
                        label: auth.user
                            ? 'PARTICIPAR EN EL RETO'
                            : 'CREAR CUENTA Y PARTICIPAR',
                    };
    const heroCTA = useRef<HTMLDivElement>(null);
    const finalCTA = useRef<HTMLElement>(null);
    const [stickyVisible, setStickyVisible] = useState(false);
    useEffect(() => {
        const update = () => {
            const hero = heroCTA.current?.getBoundingClientRect();
            const final = finalCTA.current?.getBoundingClientRect();
            setStickyVisible(
                Boolean(
                    hero &&
                    final &&
                    hero.bottom < 0 &&
                    final.top >= window.innerHeight,
                ),
            );
        };
        const observer = new IntersectionObserver(update);
        if (heroCTA.current) observer.observe(heroCTA.current);
        if (finalCTA.current) observer.observe(finalCTA.current);
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        update();
        return () => {
            observer.disconnect();
            window.removeEventListener('scroll', update);
            window.removeEventListener('resize', update);
        };
    }, []);
    const primaryAction = {
        disabled: challengeProps.preview,
        onClick: (event: React.MouseEvent<HTMLAnchorElement>) => {
            if (challengeProps.preview) event.preventDefault();
        },
        ...action,
        kind: membershipRequired
            ? 'membership'
            : !participating && !closed
              ? 'participate'
              : 'other',
    };
    const allQualified =
        challengeProps.challenge.selection_type === 'ALL_QUALIFIED';
    const socialPost = challengeProps.challenge.evidence_type === 'SOCIAL_POST';
    const supportCopy = commercialDemo
        ? 'Publica un Reel mostrando un plan que valga la pena vivir en la ciudad. Usa #bafabo y envía tu enlace.'
        : socialPost
          ? `${copy.mechanism.split('. ')[0]}. JAKAWI verifica si cumpliste el reto.`
          : copy.mechanism;
    const steps = socialPost
        ? [
              {
                  title: 'PUBLICA',
                  description: copy.mechanism.split('. ')[0] + '.',
              },
              {
                  title: 'ENVÍA',
                  description:
                      'Con tu cuenta y correo verificado, envía el enlace de tu publicación.',
              },
              { title: 'CALIFICA', description: copy.qualification },
          ]
        : copy.steps;
    const offer: OfferPresentationV1 = {
        hero: {
            eyebrow: commercialDemo ? 'RETO JAKAWI · #BAFABO' : copy.eyebrow,
            headline: commercialDemo
                ? 'MUÉSTRANOS\nTU MEJOR PLAN\nEN COCHABAMBA'
                : copy.headline.endsWith('LA ROMPA?')
                  ? copy.headline.slice(0, -9) + '\nLA ROMPA?'
                  : copy.headline,
            emphasis: commercialDemo
                ? 'EN COCHABAMBA'
                : copy.headline.endsWith('LA ROMPA?')
                  ? 'LA ROMPA?'
                  : undefined,
            description: supportCopy,
        },
        media: vsl
            ? { type: 'vsl', config: vsl }
            : challengeProps.heroUrl
              ? {
                    type: 'image',
                    src: challengeProps.heroUrl,
                    alt:
                        presentation.hero_alt || challengeProps.challenge.title,
                }
              : undefined,
        facts: copy.deadline
            ? [{ label: 'Cierra el', value: formattedDate(copy.deadline)! }]
            : [],
        value: {
            tone: 'reward',
            label: commercialDemo ? 'PREMIO DEMO' : 'PREMIO DEL RETO',
            compactLabel: commercialDemo ? 'TU PREMIO' : undefined,
            title: displayedReward,
            description: commercialDemo ? demoDisclosure : undefined,
            detail: copy.rewardDetail || undefined,
            note: copy.rewardNote || undefined,
        },
        essentialConditions: ['PUBLICA · ENVÍA · CALIFICA'],
        actionExplanation: { description: confidence },
        steps: {
            eyebrow: 'CÓMO FUNCIONA',
            headline: `${steps.length} PASOS PARA PARTICIPAR`,
            items: steps,
        },
        faq: commercialDemo
            ? [
                  {
                      question: '¿La cena se puede canjear?',
                      answer:
                          demoDisclosure +
                          ' Este reto QA no ofrece una cena real ni un premio comercial.',
                  },
                  ...copy.faq,
              ]
            : copy.faq,
        finalCta: {
            eyebrow: 'JAKAWI',
            headline: participating
                ? 'SIGUE TU PARTICIPACIÓN'
                : copy.finalHeadline,
            description: commercialDemo
                ? demoDisclosure
                : `${copy.reward} · ${copy.selection}`,
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
            <Head
                title={
                    offerEditorial?.version === 1
                        ? offer.hero.headline
                        : commercialDemo
                          ? 'Muéstranos tu mejor plan en Cochabamba · Demo'
                          : copy.headline
                }
            >
                <link rel="canonical" href={canonical} />
                {noindex && <meta name="robots" content="noindex,follow" />}
            </Head>
            <OfferConversionTemplate
                hero={presentation.hero_layout ?? undefined}
                density={presentation.density ?? undefined}
                presentation={offer}
                theme={presentation.marketing_theme || undefined}
                action={primaryAction}
                treatment="compact"
                className="challenge-marketing-v3 challenge-mobile-polish pb-[calc(5rem+env(safe-area-inset-bottom))] lg:pb-0"
                hookClassName={`challenge-hook ${commercialDemo ? 'challenge-demo-hook' : ''}`}
                heroActionRef={heroCTA}
                finalActionRef={finalCTA}
                renderNotice={() =>
                    challengeProps.preview && (
                        <div className="mx-auto max-w-6xl px-4 pt-5">
                            <StatusBanner
                                title="VISTA PREVIA"
                                description="Las acciones están desactivadas en esta vista. El estado del reto corresponde al dominio."
                            />
                        </div>
                    )
                }
                renderHeroAnnotation={() => (
                    <>
                        {commercialDemo && (
                            <div
                                data-demo-reward
                                className="mt-5 border-l-4 border-[var(--marketing-reward)] pl-4"
                            >
                                <p className="challenge-compact-teaser hidden text-sm font-extrabold text-[var(--marketing-reward)]">
                                    PREMIO DEMO · CENA PARA DOS
                                </p>
                                <p className="challenge-desktop-teaser text-xs font-extrabold tracking-widest text-[var(--marketing-reward)]">
                                    PREMIO DEMO
                                </p>
                                <p className="challenge-desktop-teaser mt-1 text-2xl font-extrabold uppercase">
                                    CENA PARA DOS
                                </p>
                                <p
                                    className={`mt-2 text-sm leading-5 ${presentation.marketing_theme === 'LIGHT_CONVERSION' ? 'text-[var(--marketing-text-secondary)]' : 'text-[var(--cream)]'}`}
                                >
                                    <span className="lg:hidden">
                                        Demo visual · no canjeable.
                                    </span>
                                    <span className="hidden lg:inline">
                                        {demoDisclosure}
                                    </span>
                                </p>
                            </div>
                        )}
                    </>
                )}
                renderHeroSupport={
                    offerEditorial?.version === 1
                        ? undefined
                        : () => (
                              <>
                                  <p className="mt-3 max-w-xl text-sm leading-5 text-[var(--marketing-text-secondary)] sm:text-base sm:leading-6">
                                      {commercialDemo ? (
                                          <>
                                              <span className="lg:hidden">
                                                  Publica un Reel con #bafabo
                                                  <br />y envíanos tu enlace.
                                              </span>
                                              <span className="hidden lg:inline">
                                                  {supportCopy}
                                              </span>
                                          </>
                                      ) : (
                                          supportCopy
                                      )}
                                  </p>
                              </>
                          )
                }
                renderValueSupplement={() => (
                    <p className="mt-5 hidden border-t border-border pt-4 text-sm leading-5 font-semibold lg:block">
                        {confidence}
                    </p>
                )}
                renderOperationalContent={() => (
                    <>
                        <section
                            data-marketing-surface="dark"
                            id="mecanica"
                            className="challenge-result bg-[var(--marketing-bg-dark)] py-12 text-[var(--cream)] sm:py-14"
                        >
                            <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                                <p className="discovery-eyebrow">
                                    RESULTADO DEL RETO
                                </p>
                                <h2 className="mt-4 text-5xl leading-none font-extrabold tracking-tight text-foreground uppercase sm:text-7xl">
                                    {allQualified
                                        ? 'NO ES SORTEO'
                                        : challengeProps.challenge
                                                .selection_type === 'TOP_N'
                                          ? 'RANKING Y RESULTADO'
                                          : challengeProps.challenge
                                                  .selection_type === 'FIRST_N'
                                            ? 'PRIMEROS EN CUMPLIR'
                                            : 'CÓMO SE DEFINE EL RESULTADO'}
                                </h2>
                                {allQualified && (
                                    <p className="mt-6 max-w-3xl text-3xl leading-tight font-extrabold tracking-tight sm:text-5xl">
                                        Si cumples las reglas, calificas.
                                    </p>
                                )}
                                <p className="mt-5 max-w-3xl text-lg leading-7 text-[var(--marketing-text-secondary)]">
                                    {allQualified ? (
                                        commercialDemo ? (
                                            <>
                                                <span className="lg:hidden">
                                                    Todos los que califican
                                                    reciben el premio QA, sin
                                                    valor comercial.
                                                </span>
                                                <span className="hidden lg:inline">
                                                    Todos los participantes que
                                                    califican reciben el premio
                                                    QA configurado, sin valor
                                                    comercial. La cena es
                                                    ilustrativa y no canjeable.
                                                </span>
                                            </>
                                        ) : (
                                            'Todos los participantes que califican reciben el premio.'
                                        )
                                    ) : (
                                        copy.selection
                                    )}
                                </p>
                                <p className="mt-4 hidden max-w-3xl text-sm leading-6 text-[var(--cream)]/75 lg:block">
                                    {copy.qualification} {copy.membership}
                                </p>
                                {challengeProps.slots && (
                                    <p className="mt-4 font-bold">
                                        {challengeProps.slots.awarded} premios
                                        otorgados ·{' '}
                                        {Math.max(
                                            0,
                                            challengeProps.slots.limit -
                                                challengeProps.slots.reserved,
                                        )}{' '}
                                        disponibles
                                    </p>
                                )}
                            </div>
                        </section>
                        <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                            <section
                                data-offer-chapter="rules"
                                className="py-12 sm:py-20"
                            >
                                <p className="discovery-eyebrow text-foreground">
                                    REGLAS ESENCIALES
                                </p>
                                <h2 className="mt-3 max-w-3xl text-4xl leading-none font-extrabold tracking-tight uppercase sm:text-6xl">
                                    <span className="lg:hidden">
                                        LO ESENCIAL
                                    </span>
                                    <span className="hidden lg:inline">
                                        LO QUE DEBES SABER
                                    </span>
                                </h2>
                                <dl className="mt-8 grid gap-x-12 sm:grid-cols-2">
                                    {copy.rules.map((rule, index) => (
                                        <div
                                            key={rule.title}
                                            className={`${index > 2 ? 'hidden lg:block ' : ''}border-t border-border py-6`}
                                        >
                                            <dt className="font-extrabold">
                                                {rule.title}
                                            </dt>
                                            <dd className="mt-2 max-w-xl leading-7 text-[var(--marketing-text-secondary)]">
                                                {rule.title === 'Cierre' &&
                                                copy.deadline
                                                    ? formattedDate(
                                                          copy.deadline,
                                                      )
                                                    : rule.description}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                                <details className="mt-7 border-t border-b border-border py-5">
                                    <summary className="cursor-pointer font-extrabold">
                                        <span className="lg:hidden">
                                            VER REGLAS COMPLETAS
                                        </span>
                                        <span className="hidden lg:inline">
                                            REGLAS COMPLETAS
                                        </span>
                                    </summary>
                                    <div className="mt-4 space-y-3 text-sm leading-7">
                                        <dl className="lg:hidden">
                                            {copy.rules.slice(3).map((rule) => (
                                                <div
                                                    key={rule.title}
                                                    className="py-3"
                                                >
                                                    <dt className="font-extrabold">
                                                        {rule.title}
                                                    </dt>
                                                    <dd>
                                                        {rule.title ===
                                                            'Cierre' &&
                                                        copy.deadline
                                                            ? formattedDate(
                                                                  copy.deadline,
                                                              )
                                                            : rule.description}
                                                    </dd>
                                                </div>
                                            ))}
                                        </dl>
                                        <p>
                                            {
                                                challengeProps.challenge
                                                    .description
                                            }
                                        </p>
                                        <p>
                                            {
                                                challengeProps.challenge
                                                    .instructions
                                            }
                                        </p>
                                        <p>
                                            {copy.qualification}{' '}
                                            {copy.selection} {copy.membership}{' '}
                                            {copy.review}
                                        </p>
                                        {challengeProps.challenge
                                            .allowed_platforms?.length > 0 && (
                                            <p>
                                                Plataformas:{' '}
                                                {challengeProps.challenge.allowed_platforms.join(
                                                    ', ',
                                                )}
                                            </p>
                                        )}
                                        {challengeProps.challenge
                                            .required_hashtags?.length > 0 && (
                                            <p>
                                                Etiquetas:{' '}
                                                {challengeProps.challenge.required_hashtags.join(
                                                    ' ',
                                                )}
                                            </p>
                                        )}
                                        {challengeProps.challenge
                                            .required_mentions?.length > 0 && (
                                            <p>
                                                Menciones:{' '}
                                                {challengeProps.challenge.required_mentions.join(
                                                    ' ',
                                                )}
                                            </p>
                                        )}
                                        <Link
                                            href={nativeUrl}
                                            className="font-bold text-foreground underline"
                                        >
                                            Ver ficha y reglas del producto
                                        </Link>
                                    </div>
                                </details>
                            </section>
                            <section
                                className={`${!auth.user ? 'challenge-guest-participation ' : ''}border-t border-border py-10 sm:py-14`}
                                aria-label="Tu participación"
                            >
                                <p className="discovery-eyebrow text-foreground">
                                    TU PARTICIPACIÓN
                                </p>
                                <h2 className="mt-3 text-3xl font-extrabold uppercase">
                                    {participating
                                        ? 'SIGUE TU RETO'
                                        : 'PARTICIPA EN ESTE RETO'}
                                </h2>
                                <Show
                                    {...challengeProps}
                                    embedded
                                    landingSlug={presentation.slug}
                                />
                            </section>
                        </div>
                    </>
                )}
                renderAfterFinal={() => (
                    <>
                        {stickyVisible && (
                            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-background/95 px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom)+var(--pwa-install-clearance,0px))] shadow-featured backdrop-blur lg:hidden">
                                <MarketingCTA
                                    action={primaryAction}
                                    location="body"
                                    className="offer-action"
                                />
                            </div>
                        )}
                    </>
                )}
            />
        </>
    );
}
