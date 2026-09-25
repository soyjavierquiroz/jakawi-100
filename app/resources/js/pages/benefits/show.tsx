import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { register } from '@/routes';
import type { BenefitSummary } from '@/types';

function formatSavings(value: BenefitSummary['estimated_savings']) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const amount = Number(value);

    if (Number.isNaN(amount)) {
        return null;
    }

    return new Intl.NumberFormat('es-BO', {
        style: 'currency',
        currency: 'BOB',
        maximumFractionDigits: 2,
    }).format(amount);
}

function formatDate(value?: string | null) {
    if (!value) {
        return null;
    }

    return new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'medium',
    }).format(new Date(value));
}

export default function BenefitShow({
    benefit,
    hasActiveMembership,
    locations = [],
}: {
    benefit: BenefitSummary;
    hasActiveMembership: boolean;
    locations?: {
        id: number;
        name: string;
        zone?: string | null;
        address?: string | null;
        slug: string;
        whatsapp?: string | null;
        maps_url?: string | null;
    }[];
}) {
    const { auth } = usePage().props;
    const savings = formatSavings(benefit.estimated_savings);
    const startsAt = formatDate(benefit.starts_at);
    const endsAt = formatDate(benefit.ends_at);
    const [confirming, setConfirming] = useState(false);
    const [locationId, setLocationId] = useState<number | null>(locations[0]?.id ?? null);

    return (
        <>
            <Head title={benefit.title} />
            <main className="min-h-screen bg-background text-foreground">
                <section className="mx-auto flex w-full max-w-4xl flex-col gap-6 px-4 py-5 sm:px-6 sm:py-10">
                    <Link
                        href="/beneficios"
                        className="inline-flex min-h-10 items-center gap-2 self-start text-sm font-medium text-muted-foreground transition hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        Beneficios
                    </Link>

                    <div className="overflow-hidden rounded-md border border-border bg-surface">
                        <div className="aspect-[16/10] bg-muted">
                            {benefit.image_url ? (
                                <img
                                    src={benefit.image_url}
                                    alt=""
                                    className="h-full w-full object-cover"
                                />
                            ) : (
                                <div className="flex h-full items-center justify-center text-sm font-medium text-muted-foreground">
                                    JAKAWI
                                </div>
                            )}
                        </div>
                        <div className="space-y-6 p-4 sm:p-6">
                            <div className="space-y-3">
                                <p className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                    {benefit.partner?.name}
                                </p>
                                <h1 className="text-3xl leading-tight font-semibold sm:text-5xl">
                                    {benefit.title}
                                </h1>
                                {benefit.description ? (
                                    <p className="text-base leading-7 text-muted-foreground">
                                        {benefit.description}
                                    </p>
                                ) : null}
                            </div>

                            <dl className="grid gap-3 sm:grid-cols-2">
                                {savings ? (
                                    <div className="rounded-md border border-border bg-background p-4">
                                        <dt className="text-xs font-semibold text-muted-foreground uppercase">
                                            Ahorro estimado
                                        </dt>
                                        <dd className="mt-1 text-lg font-semibold text-success">
                                            {savings}
                                        </dd>
                                    </div>
                                ) : null}
                                {endsAt || startsAt ? (
                                    <div className="rounded-md border border-border bg-background p-4">
                                        <dt className="text-xs font-semibold text-muted-foreground uppercase">
                                            Vigencia
                                        </dt>
                                        <dd className="mt-1 text-sm text-foreground">
                                            {startsAt
                                                ? `Desde ${startsAt}`
                                                : 'Disponible ahora'}
                                            {endsAt ? ` hasta ${endsAt}` : ''}
                                        </dd>
                                    </div>
                                ) : null}
                            </dl>

                            {benefit.terms ? (
                                <section className="space-y-2">
                                    <h2 className="text-lg font-semibold">
                                        Condiciones
                                    </h2>
                                    <p className="text-sm leading-6 whitespace-pre-line text-muted-foreground">
                                        {benefit.terms}
                                    </p>
                                </section>
                            ) : null}

                            <div className="rounded-md border border-border bg-background p-4">
                                {!auth.user ? (
                                    <>
                                        <p className="text-sm font-semibold">
                                            Necesitas JAKAWI para usar este
                                            beneficio
                                        </p>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            Crea tu cuenta para activar tu
                                            membresia.
                                        </p>
                                    </>
                                ) : hasActiveMembership ? (
                                    <>
                                        {locations.length ? (
                                            <p className="text-sm font-semibold text-success">
                                                Disponible con tu JAKAWI
                                            </p>
                                        ) : (
                                            <p className="text-sm font-semibold">
                                                Actualmente no hay una ubicación
                                                disponible para este beneficio.
                                            </p>
                                        )}
                                    </>
                                ) : (
                                    <>
                                        <p className="text-sm font-semibold">
                                            Necesitas una membresia activa para
                                            usar este beneficio
                                        </p>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            Activa tu JAKAWI para acceder a
                                            beneficios.
                                        </p>
                                    </>
                                )}
                                {!auth.user ? (
                                    <Link
                                        href={register()}
                                        className="mt-4 inline-flex min-h-11 items-center justify-center rounded-md bg-brand px-4 text-sm font-semibold text-brand-foreground transition hover:bg-brand/90"
                                    >
                                        Crear cuenta
                                    </Link>
                                ) : !hasActiveMembership ? (
                                    <Link
                                        href="/mi-jakawi"
                                        className="mt-4 inline-flex min-h-11 items-center justify-center rounded-md bg-brand px-4 text-sm font-semibold text-brand-foreground transition hover:bg-brand/90"
                                    >
                                        Ver Mi JAKAWI
                                    </Link>
                                ) : locations.length ? (
                                    <button
                                        type="button"
                                        onClick={() => setConfirming(true)}
                                        className="mt-4 inline-flex min-h-11 items-center justify-center rounded-md bg-brand px-4 text-sm font-semibold text-brand-foreground transition hover:bg-brand/90"
                                    >
                                        Usar beneficio
                                    </button>
                                ) : null}
                            </div>
                            {locations.length ? (
                                <section className="space-y-2">
                                    <h2 className="text-lg font-semibold">
                                        Ubicaciones disponibles
                                    </h2>
                                    {locations.map((location) => (
                                        <div
                                            key={location.id}
                                            className="rounded-md border border-border p-3 text-sm"
                                        >
                                            <strong>{location.name}</strong>
                                            {location.address ? (
                                                <p>{location.address}</p>
                                            ) : null}
                                            <div className="mt-2 flex gap-3">
                                                {location.maps_url ? (
                                                    <a
                                                        href={`/lugares/${location.slug}/mapa`}
                                                    >
                                                        Cómo llegar
                                                    </a>
                                                ) : null}
                                                {location.whatsapp ? (
                                                    <a
                                                        href={`/lugares/${location.slug}/whatsapp`}
                                                    >
                                                        WhatsApp
                                                    </a>
                                                ) : null}
                                            </div>
                                        </div>
                                    ))}
                                </section>
                            ) : null}
                        </div>
                    </div>
                </section>
            </main>
            {confirming ? <div role="dialog" aria-modal="true" aria-labelledby="activation-title" className="fixed inset-0 z-50 flex items-end bg-black/40 sm:items-center sm:justify-center"><div className="w-full rounded-t-[28px] bg-surface p-6 shadow-xl sm:max-w-md sm:rounded-[28px]"><p className="text-xs font-bold tracking-wide text-brand uppercase">JAKAWI</p><h2 id="activation-title" className="mt-2 text-2xl font-bold">Estás por utilizar</h2><p className="mt-4 font-semibold">{benefit.partner?.name}</p><p className="text-muted-foreground">{benefit.title}</p>{locations.length > 1 ? <label className="mt-5 block text-sm font-semibold">Sucursal<select value={locationId ?? ''} onChange={e => setLocationId(Number(e.target.value))} className="mt-2 min-h-11 w-full rounded-xl border border-border bg-background px-3">{locations.map(location => <option key={location.id} value={location.id}>{location.name}{location.zone ? ` · ${location.zone}` : ''}</option>)}</select></label> : <p className="mt-5 text-sm"><strong>Sucursal:</strong> {locations[0]?.name}</p>}<p className="mt-4 text-sm leading-6 text-muted-foreground">Actívalo únicamente cuando estés en el establecimiento.</p><div className="mt-6 grid grid-cols-2 gap-3"><button onClick={() => setConfirming(false)} className="min-h-12 rounded-xl border border-border text-sm font-bold">Cancelar</button><button onClick={() => locationId && router.post(`/beneficios/${benefit.slug}/canjear`, { location_id: locationId })} disabled={!locationId} className="min-h-12 rounded-xl bg-brand px-3 text-sm font-bold text-brand-foreground disabled:opacity-50">Activar beneficio</button></div></div></div> : null}
        </>
    );
}
