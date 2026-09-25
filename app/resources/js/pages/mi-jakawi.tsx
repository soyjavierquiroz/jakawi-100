import { Head, router } from '@inertiajs/react';
import QRCode from 'qrcode';
import { useEffect, useState } from 'react';
import AppearanceSelector from '@/components/appearance-selector';

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

type ProfileSummary = { completion_percentage: number; completed: boolean };

type RecentRedemption = {
    public_id: string;
    partner_name: string;
    benefit_title: string;
    savings_amount?: string | null;
    confirmed_at: string;
};

function ReservationQr({ url }: { url: string }) {
    const [src, setSrc] = useState('');
    useEffect(() => {
        void QRCode.toDataURL(url, { width: 280, margin: 1 }).then(setSrc);
    }, [url]);
    return src ? (
        <img className="mt-3 w-40" src={src} alt="QR de reserva" />
    ) : null;
}

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
    reservations,
    profile,
}: {
    membership: Membership | null;
    membershipConfig: MembershipConfig;
    redemptionStats: RedemptionStats;
    recentRedemptions: RecentRedemption[];
    reservations: any[];
    profile: ProfileSummary;
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

                    <a
                        href="/mi-jakawi/perfil"
                        className="block rounded-md border border-border bg-surface p-5 transition-colors hover:bg-muted/50"
                    >
                        <p className="text-xl font-semibold">
                            {profile.completed
                                ? 'Perfil completo'
                                : 'Personaliza tu JAKAWI'}
                        </p>
                        <p className="mt-2 text-sm leading-6 text-muted-foreground">
                            Cuéntanos qué te gusta para mostrarte lugares y
                            experiencias más relevantes.
                        </p>
                        <div className="mt-4 h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full bg-foreground"
                                style={{
                                    width: `${profile.completion_percentage}%`,
                                }}
                            />
                        </div>
                        <p className="mt-2 text-sm font-medium">
                            {profile.completed
                                ? 'Tu perfil está completo'
                                : `${profile.completion_percentage}% completo · Personalizar`}
                        </p>
                    </a>

                    <section className="rounded-[20px] border border-border bg-surface p-5">
                        <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Apariencia</p>
                        <h2 className="mt-2 text-xl font-bold">Elige cómo se ve JAKAWI</h2>
                        <p className="mt-1 text-sm text-muted-foreground">Tu elección se aplica al instante.</p>
                        <div className="mt-4"><AppearanceSelector /></div>
                    </section>

                    {membership ? (
                        <div className="rounded-[22px] bg-foreground p-5 text-background">
                            <p className="text-xl font-bold">
                                Tu JAKAWI está activo
                            </p>
                            <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <dt className="text-xs font-semibold text-background/65 uppercase">
                                        Miembro desde
                                    </dt>
                                    <dd className="mt-1 text-base font-medium">
                                        {formatDate(membership.starts_at)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-semibold text-background/65 uppercase">
                                        Activo hasta
                                    </dt>
                                    <dd className="mt-1 text-base font-medium">
                                        {formatDate(membership.ends_at)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-semibold text-background/65 uppercase">
                                        Días restantes
                                    </dt>
                                    <dd className="mt-1 text-base font-medium">
                                        {membership.days_remaining}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-semibold text-background/65 uppercase">
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
                    {reservations.length > 0 ? (
                        <div className="rounded-md border border-border bg-surface p-5">
                            <h2 className="text-xl font-semibold">
                                Mis reservas
                            </h2>
                            <div className="mt-3 divide-y divide-border">
                                {reservations.map((reservation) => (
                                    <div
                                        key={reservation.public_id}
                                        className="py-3 text-sm"
                                    >
                                        <p className="font-semibold">
                                            {reservation.experience}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {formatDate(reservation.starts_at)}{' '}
                                            · {reservation.venue} ·{' '}
                                            {reservation.partner}
                                        </p>
                                        <p className="mt-1">
                                            {reservation.party_size === 1
                                                ? '1 persona'
                                                : `${reservation.party_size} personas`}{' '}
                                            ·{' '}
                                            {reservation.checked_in_at
                                                ? 'Asistencia registrada'
                                                : {
                                                      pending: 'Pendiente',
                                                      confirmed:
                                                          'Reserva confirmada',
                                                      rejected: 'Rechazada',
                                                      cancelled: 'Cancelada',
                                                  }[
                                                      reservation.status as keyof Record<
                                                          string,
                                                          string
                                                      >
                                                  ]}
                                        </p>
                                        {reservation.qr_url ? (
                                            <>
                                                <p className="mt-2 font-medium">
                                                    Muéstralo al llegar
                                                </p>
                                                <ReservationQr
                                                    url={reservation.qr_url}
                                                />
                                                <p>
                                                    Código:{' '}
                                                    <strong>
                                                        {
                                                            reservation.check_in_code
                                                        }
                                                    </strong>
                                                </p>
                                            </>
                                        ) : null}
                                        {reservation.can_cancel ? (
                                            <button
                                                className="mt-2 text-sm underline"
                                                onClick={() =>
                                                    router.post(
                                                        `/reservas/${reservation.public_id}/cancelar`,
                                                    )
                                                }
                                            >
                                                Cancelar reserva
                                            </button>
                                        ) : null}
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : null}
                </section>
            </main>
        </>
    );
}
