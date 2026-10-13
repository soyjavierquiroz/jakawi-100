import { DeliveryLayout } from '@/components/marketing/delivery';
import { Head, Link, router, usePage } from '@inertiajs/react';
import BenefitShow from '@/pages/benefits/show';
import ExperienceShow from '@/pages/experiences/show';
import ChallengeShow from '@/pages/social-challenges/show';
import UnlockShow from '@/pages/unlocks/show';
import { useState, type ComponentProps } from 'react';
import type { AcquisitionContext } from '@/components/marketing/acquisition-context';

/** Operational full page. No marketing template, identity modal or dialog provider. */
export default function Conversion() {
    const { props } = usePage();
    const context = props.acquisition as AcquisitionContext;
    const result = props.conversionStage === 'result';
    const [verificationSent, setVerificationSent] = useState(false);
    const [sending, setSending] = useState(false);
    const experience = props.experience as
        | ComponentProps<typeof ExperienceShow>['experience']
        | undefined;
    const selectedSession = experience?.sessions?.find(
        (session) => session.id === props.restoredSessionId,
    );
    return (
        <DeliveryLayout delivered={result && !props.verificationRequired}>
            <Head title={result ? 'Tu resultado' : 'Continúa tu plan'}>
                <meta name="robots" content="noindex,nofollow" />
            </Head>
            {!result && (
                <Link
                    href={props.landingUrl as string}
                    className="mb-6 inline-flex min-h-11 items-center font-bold underline"
                >
                    ← Volver a la propuesta
                </Link>
            )}
            {props.verificationRequired ? (
                <section
                    className="space-y-4 rounded-2xl border border-border bg-surface p-5"
                    aria-label="Confirmación de correo"
                >
                    <p className="font-bold">{context.title}</p>
                    {selectedSession && (
                        <p className="text-sm">
                            {new Intl.DateTimeFormat('es-BO', {
                                dateStyle: 'long',
                                timeStyle: 'short',
                                timeZone: 'America/La_Paz',
                            }).format(new Date(selectedSession.starts_at))}
                            {selectedSession.location?.name
                                ? ` · ${selectedSession.location.name}`
                                : selectedSession.venue_label
                                  ? ` · ${selectedSession.venue_label}`
                                  : ''}
                        </p>
                    )}
                    <h1 className="text-2xl font-extrabold">
                        {context.type === 'EXPERIENCE'
                            ? 'CONFIRMA TU CORREO PARA RESERVAR'
                            : 'CONFIRMA TU CORREO PARA CONTINUAR'}
                    </h1>
                    <p className="text-muted-foreground">
                        {context.type === 'EXPERIENCE'
                            ? 'Estamos por guardar tu lugar. Confirma tu correo y volverás directamente aquí.'
                            : 'Confirma tu correo antes de continuar con tu compromiso. Volverás a este desbloqueo para decidir tu siguiente paso.'}
                    </p>
                    <button
                        disabled={sending}
                        className="funnel-primary-action min-h-12 w-full rounded-xl px-4 font-extrabold text-white disabled:opacity-60"
                        onClick={() =>
                            router.post(
                                '/email/verification-notification',
                                {},
                                {
                                    preserveScroll: true,
                                    onStart: () => setSending(true),
                                    onSuccess: () => setVerificationSent(true),
                                    onFinish: () => setSending(false),
                                },
                            )
                        }
                    >
                        CONFIRMAR MI CORREO
                    </button>
                    {verificationSent && (
                        <p role="status">
                            Revisa tu correo. La confirmación te devuelve aquí;
                            después podrás continuar.
                        </p>
                    )}
                    {props.errors &&
                        Object.values(
                            props.errors as Record<string, string>,
                        ).map((error) => (
                            <p key={error} role="alert">
                                {error}
                            </p>
                        ))}
                    <button
                        className="min-h-11 underline"
                        onClick={() => router.reload()}
                    >
                        YA CONFIRMÉ MI CORREO
                    </button>
                </section>
            ) : context.type === 'EXPERIENCE' ? (
                <ExperienceShow
                    {...(props as unknown as ComponentProps<
                        typeof ExperienceShow
                    >)}
                    continuationOnly
                />
            ) : context.type === 'BENEFIT' ? (
                <BenefitShow
                    {...(props as unknown as ComponentProps<
                        typeof BenefitShow
                    >)}
                    redemptionIntent
                    continuationOnly
                />
            ) : (
                <>
                    {!result && context.type !== 'CHALLENGE' && (
                        <header className="mb-6 space-y-3">
                            <p className="text-sm font-extrabold">
                                TU DESBLOQUEO
                            </p>
                            <h1 className="text-3xl font-extrabold">
                                VAMOS A DESBLOQUEARLO
                            </h1>
                            <p className="font-bold">{context.title}</p>
                            {context.provider && (
                                <p className="text-muted-foreground">
                                    {context.provider}
                                </p>
                            )}
                        </header>
                    )}
                    {context.type === 'CHALLENGE' ? (
                        <>
                            {!props.participation && (
                                <header className="mb-5 space-y-2">
                                    <h1 className="text-3xl font-extrabold">
                                        ENTRA AL RETO
                                    </h1>
                                    <p className="font-bold">{context.title}</p>
                                </header>
                            )}
                            <ChallengeShow
                                {...(props as unknown as ComponentProps<
                                    typeof ChallengeShow
                                >)}
                                embedded
                                conversionOnly
                                landingSlug={
                                    (props.presentation as { slug: string })
                                        .slug
                                }
                            />
                        </>
                    ) : (
                        <UnlockShow
                            {...(props as unknown as ComponentProps<
                                typeof UnlockShow
                            >)}
                            continuationOnly
                        />
                    )}
                </>
            )}
        </DeliveryLayout>
    );
}
