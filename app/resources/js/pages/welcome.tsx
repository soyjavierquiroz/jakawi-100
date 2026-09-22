import { Head, Link, usePage } from '@inertiajs/react';
import BenefitCard from '@/components/benefit-card';
import type { BenefitSummary } from '@/types';
/* @chisel-registration */
import { register } from '@/routes';
/* @end-chisel-registration */

export default function Welcome({
    featuredBenefits = [],
}: {
    featuredBenefits?: BenefitSummary[];
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
                                Vive más.
                                <br />
                                Gasta menos.
                            </h1>
                            <p className="max-w-xl text-lg leading-7 text-muted-foreground">
                                Accede a beneficios en comercios locales con una
                                membresía simple y ahorra en lo que disfrutas.
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
                                    <h2 className="text-2xl font-semibold">
                                        Beneficios destacados
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Primeras oportunidades disponibles en
                                        JAKAWI.
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
                </section>
            </main>
        </>
    );
}
