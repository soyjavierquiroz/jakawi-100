import { Head, Link, usePage } from '@inertiajs/react';
import BenefitShow from '@/pages/benefits/show';
import ExperienceShow from '@/pages/experiences/show';
import ChallengeShow from '@/pages/social-challenges/show';
import UnlockShow from '@/pages/unlocks/show';
import type { ComponentProps } from 'react';
import type { AcquisitionContext } from '@/components/marketing/acquisition-context';

/** Operational full page. No marketing template, identity modal or dialog provider. */
export default function Conversion() {
    const { props } = usePage();
    const context = props.acquisition as AcquisitionContext;
    const result = props.conversionStage === 'result';
    return (
        <div
            className="min-h-screen bg-background font-discovery text-foreground"
            data-marketing-theme="light-conversion"
        >
            <Head title={result ? 'Tu resultado' : 'Continúa tu plan'}>
                <meta name="robots" content="noindex,nofollow" />
            </Head>
            <header className="border-b border-border px-5 py-4">
                <img src="/jakawi-logo.svg" alt="JAKAWI" className="h-8" />
            </header>
            <main className="mx-auto max-w-3xl px-5 pt-6 pb-12 sm:px-8 sm:pt-10">
                {!result && (
                    <Link
                        href={props.landingUrl as string}
                        className="mb-6 inline-flex min-h-11 items-center font-bold underline"
                    >
                        ← Volver a la propuesta
                    </Link>
                )}
                {context.type === 'EXPERIENCE' ? (
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
                        {!result && (
                            <header className="mb-6 space-y-3">
                                <p className="text-sm font-extrabold">
                                    {context.type === 'CHALLENGE'
                                        ? 'TU RETO'
                                        : 'TU DESBLOQUEO'}
                                </p>
                                <h1 className="text-3xl font-extrabold">
                                    {context.type === 'CHALLENGE'
                                        ? 'ENTRA AL RETO'
                                        : 'VAMOS A DESBLOQUEARLO'}
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
                            <ChallengeShow
                                {...(props as unknown as ComponentProps<
                                    typeof ChallengeShow
                                >)}
                                embedded
                                landingSlug={
                                    (props.presentation as { slug: string })
                                        .slug
                                }
                            />
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
            </main>
        </div>
    );
}
