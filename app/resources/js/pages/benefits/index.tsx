import { Head, Link } from '@inertiajs/react';
import BenefitCard from '@/components/benefit-card';
import type { BenefitSummary } from '@/types';

export default function BenefitsIndex({
    benefits,
}: {
    benefits: BenefitSummary[];
}) {
    return (
        <>
            <Head title="Beneficios" />
            <main className="min-h-screen bg-background px-4 py-6 text-foreground sm:px-6 sm:py-10">
                <section className="mx-auto flex w-full max-w-6xl flex-col gap-6">
                    <div className="space-y-3">
                        <Link
                            href="/"
                            className="text-sm font-medium text-muted-foreground transition hover:text-foreground"
                        >
                            JAKAWI
                        </Link>
                        <div className="space-y-2">
                            <h1 className="text-3xl leading-tight font-semibold sm:text-5xl">
                                Beneficios
                            </h1>
                            <p className="max-w-xl text-base leading-7 text-muted-foreground">
                                Comercios y experiencias seleccionadas para
                                vivir más y gastar menos.
                            </p>
                        </div>
                    </div>

                    {benefits.length ? (
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {benefits.map((benefit) => (
                                <BenefitCard
                                    key={benefit.id}
                                    benefit={benefit}
                                />
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-md border border-border bg-surface p-6 text-sm text-muted-foreground">
                            Todavía no hay beneficios disponibles.
                        </div>
                    )}
                </section>
            </main>
        </>
    );
}
