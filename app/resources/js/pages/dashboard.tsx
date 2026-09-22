import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard } from '@/routes';

type Membership = { id: number; ends_at: string };

type RedemptionStats = { count: number; savings_total: string };

export default function Dashboard({
    membership,
    redemptionStats,
}: {
    membership: Membership | null;
    redemptionStats: RedemptionStats;
}) {
    const { auth } = usePage().props;
    const firstName = auth.user?.name.trim().split(/\s+/)[0] || 'bienvenido';

    return (
        <>
            <Head title="Inicio" />
            <main className="min-h-full bg-background px-4 py-6 text-foreground sm:px-6 sm:py-10">
                <section className="mx-auto flex w-full max-w-3xl flex-col gap-5">
                    <div className="space-y-2">
                        <p className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                            Inicio
                        </p>
                        <h1 className="text-3xl font-semibold sm:text-4xl">
                            Hola, {firstName}
                        </h1>
                        <p className="text-base text-muted-foreground">
                            {membership
                                ? 'Tu JAKAWI está activo.'
                                : 'Activa tu membresía para disfrutar tus beneficios.'}
                        </p>
                    </div>

                    <Link
                        href="/beneficios"
                        className="inline-flex min-h-12 items-center justify-center rounded-md bg-brand px-5 text-sm font-semibold text-brand-foreground transition hover:bg-brand/90 sm:w-fit"
                    >
                        Explorar beneficios
                    </Link>

                    {membership ? (
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="rounded-md border border-border bg-surface p-4">
                                <p className="text-sm text-muted-foreground">
                                    Canjes realizados
                                </p>
                                <p className="mt-1 text-2xl font-semibold">
                                    {redemptionStats.count}
                                </p>
                            </div>
                            <div className="rounded-md border border-border bg-surface p-4">
                                <p className="text-sm text-muted-foreground">
                                    Ahorro estimado
                                </p>
                                <p className="mt-1 text-2xl font-semibold">
                                    Bs{' '}
                                    {Number(
                                        redemptionStats.savings_total,
                                    ).toFixed(2)}
                                </p>
                            </div>
                        </div>
                    ) : null}

                    <Link
                        href="/mi-jakawi"
                        className="inline-flex min-h-11 items-center justify-center rounded-md border border-border bg-surface px-5 text-sm font-semibold text-foreground transition hover:bg-muted sm:w-fit"
                    >
                        Ver Mi JAKAWI
                    </Link>
                </section>
            </main>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Inicio',
            href: dashboard(),
        },
    ],
};
