import { useLandingAction } from '@/components/marketing/landing-action-context';
import { useAcquisition } from '@/components/marketing/acquisition-context';
import { distinctOfferFaq } from '@/components/marketing/offer/composition';
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
    canonical,
    noindex,
    ...challengeProps
}: ChallengeOfferProps) {
    const { auth } = usePage().props as {
        auth: { user?: { id: number } | null };
    };
    const acquire = useAcquisition();
    const continueAction = useLandingAction();
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
                  label: 'VER MI PREMIO',
              }
            : membershipRequired
              ? {
                    href: `/membresia?journey=SOCIAL_CHALLENGE&action=PARTICIPATE&resource_id=${challengeProps.challenge.id}&landing_presentation_id=${presentation.id}`,
                    label: 'ACTIVAR MEMBRESÍA',
                }
              : participating
                ? { href: '#participar', label: 'VER MI PARTICIPACIÓN' }
                : closed
                  ? { href: '#mecanica', label: 'VER RESULTADOS' }
                  : {
                        href: '#participar',
                        label: auth.user
                            ? 'ENTRAR AL RETO'
                            : 'QUIERO PARTICIPAR',
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
            if (challengeProps.preview) {
                event.preventDefault();
                return;
            }
            if (!auth.user && !closed) {
                event.preventDefault();
                acquire();
            } else if (auth.user) { event.preventDefault(); continueAction(); }
        },
        ...action,
        kind: membershipRequired
            ? 'membership'
            : !participating && !closed
              ? 'participate'
              : 'other',
    };
    const socialPost = challengeProps.challenge.evidence_type === 'SOCIAL_POST';
    const supportCopy = copy.mechanism;
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
        : [
              {
                  title: 'PARTICIPA',
                  description: copy.steps[0]?.description || copy.mechanism,
              },
              {
                  title: 'CONFIRMA EL RESULTADO',
                  description: `${copy.qualification} ${copy.selection}`,
              },
          ];
    const offer: OfferPresentationV1 = {
        hero: {
            eyebrow: copy.eyebrow,
            headline: copy.headline,
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
            label: 'PREMIO DEL RETO',
            title: copy.reward,
            detail: copy.rewardDetail || undefined,
            note: copy.rewardNote || undefined,
        },
        essentialConditions: [copy.qualification],
        actionExplanation: { description: copy.membership },
        steps: {
            eyebrow: 'CÓMO FUNCIONA',
            headline: `${steps.length} PASOS PARA PARTICIPAR`,
            items: steps,
        },
        faq: distinctOfferFaq(copy.faq, [
            copy.membership,
            copy.selection,
            copy.qualification,
            copy.review,
            'Personas con una cuenta JAKAWI.',
            ...steps.map((step) => step.description),
            ...copy.rules.map((rule) => rule.description),
        ]),
        finalCta: {
            eyebrow: 'JAKAWI',
            headline: participating
                ? 'SIGUE TU PARTICIPACIÓN'
                : copy.finalHeadline,
            description: `${copy.reward} · ${copy.selection}`,
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
                action={primaryAction}
                treatment="compact"
                className="challenge-marketing-v3 challenge-mobile-polish pb-[calc(5rem+env(safe-area-inset-bottom))] lg:pb-0"
                hookClassName="challenge-hook"
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
                renderOperationalContent={() => (
                    <>
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
                                            href="#participar"
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
                                <MarketingCTA action={primaryAction} location="body" className="offer-action mt-5" />
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
