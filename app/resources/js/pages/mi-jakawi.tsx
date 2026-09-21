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

function formatDate(value: string) {
    return new Intl.DateTimeFormat('es-BO', {
        dateStyle: 'medium',
    }).format(new Date(value));
}

export default function MiJakawi({
    membership,
    membershipConfig,
}: {
    membership: Membership | null;
    membershipConfig: MembershipConfig;
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
                        <h1 className="mt-2 text-3xl font-semibold">
                            Membresia
                        </h1>
                    </div>

                    {membership ? (
                        <div className="rounded-md border border-border bg-surface p-5">
                            <p className="text-xl font-semibold text-success">
                                Tu JAKAWI esta activo
                            </p>
                            <dl className="mt-5 grid gap-4">
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
                                        Dias restantes
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
                                        Bs{membershipConfig.price_bob} / {membershipConfig.duration_days} dias
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    ) : (
                        <div className="rounded-md border border-border bg-surface p-5">
                            <p className="text-xl font-semibold">
                                Tu JAKAWI aun no esta activo.
                            </p>
                            <p className="mt-3 text-sm leading-6 text-muted-foreground">
                                Activa tu membresia para usar beneficios.
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
                                        Duracion
                                    </dt>
                                    <dd className="mt-1 text-base font-medium">
                                        {membershipConfig.duration_days} dias
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
