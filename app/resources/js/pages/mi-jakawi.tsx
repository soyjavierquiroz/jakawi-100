import { Head } from '@inertiajs/react';

type Membership = {
    id: number;
    status: string;
    starts_at: string;
    ends_at: string;
    days_remaining: number;
    amount_paid?: string | null;
};

type MembershipConfig = {
    price_bob: number;
    duration_days: number;
};

type RedemptionStats = {
    count: number;
    savings_total: string;
};

type RecentRedemption = {
    public_id: string;
    partner_name: string;
    benefit_title: string;
    savings_amount?: string | null;
    confirmed_at: string;
};

function formatDate(value: string) {
    return new Intl.DateTimeFormat('es-BO', {
        dateStyle: 'medium',
    }).format(new Date(value));
}

export default function MiJakawi({
    membership,
    membershipConfig,
    redemptionStats,
    recentRedemptions,
}: {
    membership: Membership | null;
    membershipConfig: MembershipConfig;
    redemptionStats: RedemptionStats;
    recentRedemptions: RecentRedemption[];
}) {
    return (
        <>
            <Head title="Mi JAKAWI" />
            <main className="min-h-screen bg-background px-4 py-6 text-foreground sm:px-6">
                <section className="mx-auto flex w-full max-w-xl flex-col gap-5">
                    <div>
                        <p className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                            Mi JAKAWI
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold sm:text-4xl">
                            Tu membresía
                        </h1>
                    </div>

                    {membership ? (
                        <div className="rounded-md border border-border bg-surface p-5">
                            <p className="text-xl font-semibold text-success">
                                Tu JAKAWI está activo
                            </p>
                            <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <dt className="text-xs font-semibold text-muted-foreground uppercase">
                                        Miembro desde
                                    </dt>
                                    <dd className="mt-1 text-base font-medium">
                                        {formatDate(membership.starts_at)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-semibold text-muted-foreground uppercase">
                                        Activo hasta
                                    </dt>
                                    <dd className="mt-1 text-base font-medium">
                                        {formatDate(membership.ends_at)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-semibold text-muted-foreground uppercase">
                                        Días restantes
                                    </dt>
                                    <dd className="mt-1 text-base font-medium">
                                        {membership.days_remaining}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-semibold text-muted-foreground uppercase">
                                        Membresia
                                    </dt>
                                    <dd className="mt-1 text-base font-medium">
                                        Bs {membershipConfig.price_bob} /{' '}
                                        {membershipConfig.duration_days} días
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    ) : null}

                    {membership ? (
                        <div className="rounded-md border border-border bg-surface p-5">
                            <h2 className="text-xl font-semibold">
                                Tu actividad
                            </h2>
                            <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <dt className="text-xs font-semibold text-muted-foreground uppercase">
                                        Canjes realizados
                                    </dt>
                                    <dd className="mt-1 text-2xl font-semibold">
                                        {redemptionStats.count}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-semibold text-muted-foreground uppercase">
                                        Ahorro estimado con JAKAWI
                                    </dt>
                                    <dd className="mt-1 text-2xl font-semibold">
                                        Bs{' '}
                                        {Number(
                                            redemptionStats.savings_total,
                                        ).toFixed(2)}
                                    </dd>
                                </div>
                            </dl>
                            {recentRedemptions.length > 0 ? (
                                <div className="mt-5">
                                    <h3 className="text-sm font-semibold">
                                        Últimos canjes
                                    </h3>
                                    <div className="mt-2 divide-y divide-border">
                                        {recentRedemptions.map((redemption) => (
                                            <div
                                                key={redemption.public_id}
                                                className="py-3 text-sm"
                                            >
                                                <p className="font-semibold">
                                                    {redemption.benefit_title}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    {redemption.partner_name} ·{' '}
                                                    {formatDate(
                                                        redemption.confirmed_at,
                                                    )}
                                                </p>
                                                {redemption.savings_amount ? (
                                                    <p className="mt-1">
                                                        Ahorro estimado: Bs{' '}
                                                        {Number(
                                                            redemption.savings_amount,
                                                        ).toFixed(2)}
                                                    </p>
                                                ) : null}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ) : null}
                        </div>
                    ) : (
                        <div className="rounded-md border border-border bg-surface p-5">
                            <p className="text-xl font-semibold">
                                Tu JAKAWI aún no está activo.
                            </p>
                            <p className="mt-3 text-sm leading-6 text-muted-foreground">
                                Activa tu membresía para usar beneficios.
                            </p>
                            <dl className="mt-5 grid gap-4">
                                <div>
                                    <dt className="text-xs font-semibold text-muted-foreground uppercase">
                                        Precio
                                    </dt>
                                    <dd className="mt-1 text-base font-medium">
                                        Bs{membershipConfig.price_bob}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-semibold text-muted-foreground uppercase">
                                        Duración
                                    </dt>
                                    <dd className="mt-1 text-base font-medium">
                                        {membershipConfig.duration_days} días
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    )}
                </section>
            </main>
        </>
    );
}
