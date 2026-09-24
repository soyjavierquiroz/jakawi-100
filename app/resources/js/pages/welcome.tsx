import { Head, Link, usePage } from '@inertiajs/react';
import BenefitCard from '@/components/benefit-card';
import type { BenefitSummary } from '@/types';
/* @chisel-registration */
import { register } from '@/routes';
/* @end-chisel-registration */

export default function Welcome({
    featuredBenefits = [],
    featuredExperiences = [],
    membershipSummary,
    isPersonalizedHome = false,
}: {
    featuredBenefits?: BenefitSummary[];
    featuredExperiences?: any[];
    membershipSummary?: any;
    isPersonalizedHome?: boolean;
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="JAKAWI" />
            <main className="min-h-screen bg-background px-5 py-10 pb-28 text-foreground sm:px-6 sm:pb-10">
                <section className="mx-auto flex w-full max-w-4xl flex-col gap-10 pt-12 sm:pt-20">
                    <div className="space-y-5">
                        <p className="text-sm font-semibold tracking-[0.24em] text-muted-foreground sm:tracking-[0.28em]">
                            JAKAWI
                        </p>
                        <div className="space-y-3">
                            <h1 className="text-4xl leading-none font-semibold min-[375px]:text-5xl sm:text-7xl">
                                {auth.user
                                    ? 'Aprovecha tu JAKAWI hoy'
                                    : 'La app para vivir más tu ciudad.'}
                            </h1>
                            <p className="max-w-xl text-lg leading-7 text-muted-foreground">
                                Descubre lugares y experiencias, accede a
                                beneficios por ser miembro y encuentra nuevas
                                razones para salir, probar y volver. Vive más.
                                Gasta menos.
                            </p>
                        </div>
                    </div>

                    <nav className="flex flex-col gap-3 sm:flex-row">
                        {auth.user ? (
                            <>
                                <Link
                                    href="/beneficios"
                                    className="inline-flex min-h-12 items-center justify-center rounded-md bg-brand px-6 text-sm font-semibold text-brand-foreground transition hover:bg-brand/90"
                                >
                                    Ver beneficios
                                </Link>
                                <Link
                                    href="/mi-jakawi"
                                    className="inline-flex min-h-12 items-center justify-center rounded-md border border-border bg-surface/70 px-6 text-sm font-semibold text-foreground transition hover:bg-surface"
                                >
                                    Mi JAKAWI
                                </Link>
                            </>
                        ) : (
                            <>
                                <Link
                                    href="/beneficios"
                                    className="inline-flex min-h-12 items-center justify-center rounded-md bg-brand px-6 text-sm font-semibold text-brand-foreground transition hover:bg-brand/90"
                                >
                                    Ver beneficios
                                </Link>
                                {/* @chisel-registration */}
                                <Link
                                    href={register()}
                                    className="inline-flex min-h-12 items-center justify-center rounded-md border border-border bg-surface/70 px-6 text-sm font-semibold text-foreground transition hover:bg-surface"
                                >
                                    Crear cuenta
                                </Link>
                                {/* @end-chisel-registration */}
                            </>
                        )}
                    </nav>

                    {featuredBenefits.length ? (
                        <section className="flex flex-col gap-4 pt-6">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                <div className="space-y-1">
                                    <h2 className="text-2xl font-semibold">{isPersonalizedHome ? 'Beneficios para ti' : 'Beneficios destacados'}</h2>
                                    <p className="text-sm text-muted-foreground">
                                        {isPersonalizedHome ? 'Según tus intereses.' : 'Primeras oportunidades disponibles en JAKAWI.'}
                                    </p>
                                </div>
                                <Link
                                    href="/beneficios"
                                    className="inline-flex min-h-11 items-center justify-center rounded-md border border-border bg-surface px-4 text-sm font-semibold text-foreground transition hover:bg-muted sm:self-auto"
                                >
                                    Ver todos los beneficios
                                </Link>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-3">
                                {featuredBenefits.map((benefit) => (
                                    <BenefitCard
                                        key={benefit.id}
                                        benefit={benefit}
                                    />
                                ))}
                            </div>
                        </section>
                    ) : null}
                    {featuredExperiences.length ? (
                        <section className="space-y-3">
                            <h2 className="text-2xl font-semibold">{isPersonalizedHome ? 'Experiencias para ti' : 'Experiencias próximas'}</h2>
                            {isPersonalizedHome ? <p className="text-sm text-muted-foreground">Según tus intereses.</p> : null}
                            {featuredExperiences.map((experience) => (
                                <Link
                                    className="block rounded-md border border-border p-4"
                                    key={experience.id}
                                    href={`/experiencias/${experience.slug}`}
                                >
                                    {experience.title}
                                </Link>
                            ))}
                        </section>
                    ) : null}
                    {membershipSummary ? (
                        <section className="rounded-md border border-border bg-surface p-4">
                            <p className="font-semibold">
                                Has ahorrado Bs{' '}
                                {membershipSummary.confirmed_savings}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {membershipSummary.has_paid_for_itself
                                    ? 'Tu JAKAWI ya se pagó solo.'
                                    : `Te faltan Bs ${membershipSummary.remaining_to_payback} para recuperar tu membresía.`}
                            </p>
                        </section>
                    ) : null}
                </section>
            </main>
        </>
    );
}
