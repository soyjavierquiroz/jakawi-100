import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, MapPin, MessageCircle } from 'lucide-react';
import { useRef, useState } from 'react';
import { BrandEnergy } from '@/components/brand-energy';
import { JakawiImage } from '@/components/jakawi-image';
import { StatusBanner } from '@/components/state-panel';
import JourneyLayout from '@/layouts/journey-layout';
import { trackJourneyIntent } from '@/lib/journey-analytics';
import type { BenefitSummary } from '@/types';

type Location = {
    id: number;
    name: string;
    zone?: string | null;
    address?: string | null;
    slug: string;
    whatsapp?: string | null;
    maps_url?: string | null;
};
type Availability = { available: boolean; reason: string | null };

function savings(value: BenefitSummary['estimated_savings']) {
    const amount = Number(value);

    return value !== null &&
        value !== undefined &&
        value !== '' &&
        !Number.isNaN(amount)
        ? new Intl.NumberFormat('es-BO', {
              style: 'currency',
              currency: 'BOB',
              maximumFractionDigits: 2,
          }).format(amount)
        : null;
}

export default function BenefitShow({
    benefit,
    hasActiveMembership,
    locations = [],
    availability = { available: true, reason: null },
}: {
    benefit: BenefitSummary;
    hasActiveMembership: boolean;
    locations?: Location[];
    availability?: Availability;
}) {
    const { auth } = usePage().props;
    const [confirming, setConfirming] = useState(false);
    const intentSentFor = useRef<string | null>(null);
    const membershipIntent = () => {
        if (intentSentFor.current === benefit.slug) return;
        intentSentFor.current = benefit.slug;
        trackJourneyIntent('benefit', benefit.slug);
    };
    const [locationId, setLocationId] = useState<number | null>(null);
    const selectedLocation = locations.find((location) => location.id === locationId);
    const hasMedia = Boolean(benefit.hero_url ?? benefit.image_url);
    const canUse =
        availability.available &&
        !!auth.user &&
        hasActiveMembership &&
        locations.length > 0;
    const action = !availability.available ? null : !auth.user ? (
        <button type="button" onClick={() => router.post(`/beneficios/${benefit.slug}/canjear`)} className="benefit-primary-action">
            CREAR CUENTA GRATIS
        </button>
    ) : !hasActiveMembership ? (
        <Link href="/mi-jakawi" onClick={membershipIntent} className="benefit-primary-action">
            VER MEMBRESÍA
        </Link>
    ) : (
        <button
            type="button"
            onClick={() => {
                membershipIntent();
                setConfirming(true);
            }}
            className="benefit-primary-action"
        >
            USAR BENEFICIO
        </button>
    );
    const mobileAction = !availability.available
        ? null
        : !auth.user || !hasActiveMembership
          ? action
          : canUse
            ? action
            : null;
    const value = savings(benefit.estimated_savings);

    return (
        <JourneyLayout>
            <Head title={benefit.title} />
            <main className="min-h-screen bg-background pb-40 font-discovery text-foreground">
                <section className="mx-auto w-full max-w-5xl">
                    <div
                        className={
                            hasMedia
                                ? 'relative aspect-[4/3] overflow-hidden bg-surface-muted sm:aspect-[16/8] sm:rounded-b-[32px]'
                                : 'relative overflow-hidden bg-surface-muted px-4 pt-24 pb-8 sm:rounded-b-[32px] sm:px-8'
                        }
                    >
                        <JakawiImage
                            src={benefit.hero_url ?? benefit.image_url}
                            srcset={benefit.hero_srcset ?? benefit.image_srcset}
                            sizes="(min-width: 1024px) 960px, 100vw"
                            alt={benefit.title}
                            className={
                                hasMedia
                                    ? 'h-full w-full object-cover'
                                    : 'absolute inset-0 h-full w-full'
                            }
                            fallbackClassName="border-0 bg-surface-muted"
                            loading="eager"
                            priority
                        />
                        {hasMedia ? (
                            <div
                                aria-hidden="true"
                                className="absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-foreground/65 to-transparent"
                            />
                        ) : (
                            <div className="relative max-w-xl">
                                <p className="discovery-eyebrow text-brand">
                                    BENEFICIO JAKAWI
                                </p>
                                <h1 className="mt-3 max-w-[340px] text-[34px] leading-[0.95] font-extrabold tracking-tight sm:max-w-3xl sm:text-6xl">
                                    {benefit.title}
                                </h1>
                            </div>
                        )}
                        <Link
                            href="/explorar"
                            aria-label="Volver a explorar"
                            className="absolute top-4 left-4 inline-flex min-h-11 items-center gap-2 rounded-full bg-surface/95 px-4 text-sm font-bold shadow-sm"
                        >
                            <ArrowLeft className="size-4" />
                            Explorar
                        </Link>
                    </div>

                    <div className="px-4 pt-7 sm:px-8 sm:pt-10">
                        {hasMedia ? (
                            <>
                                <p className="discovery-eyebrow text-brand">
                                    BENEFICIO JAKAWI
                                </p>
                                <BrandEnergy className="mt-3" />
                                <h1 className="mt-4 max-w-[340px] text-[34px] leading-[0.95] font-extrabold tracking-tight sm:max-w-3xl sm:text-6xl">
                                    {benefit.title}
                                </h1>
                            </>
                        ) : (
                            <BrandEnergy className="mt-1" />
                        )}

                        {benefit.partner?.name ? (
                            <p className="text-foreground-soft mt-5 text-sm font-bold">
                                En {benefit.partner.name}
                            </p>
                        ) : null}
                        {benefit.short_description ? (
                            <p className="text-foreground-soft mt-3 max-w-2xl text-lg leading-7 font-semibold">
                                {benefit.short_description}
                            </p>
                        ) : null}
                        {auth.user && !hasActiveMembership && availability.available ? <StatusBanner title="SE REQUIERE MEMBRESÍA" description="Necesitas una membresía activa para usar este beneficio. Consulta Mi JAKAWI para continuar." className="mt-7" /> : null}

                        <section className="mt-8 overflow-hidden rounded-[var(--radius-card)] border border-border bg-surface-elevated">
                            <div className="bg-brand px-5 py-4 text-brand-foreground">
                                <p className="discovery-eyebrow">
                                    TU BENEFICIO
                                </p>
                                {value ? (
                                    <p className="mt-2 text-4xl leading-none font-extrabold tracking-tight sm:text-5xl">
                                        {value}
                                    </p>
                                ) : (
                                    <p className="mt-2 text-xl font-extrabold">
                                        Beneficio exclusivo para miembros
                                    </p>
                                )}
                            </div>
                            <p className="px-5 py-4 text-sm leading-6 text-muted-foreground">
                                {value
                                    ? 'Ahorro estimado al usar este beneficio.'
                                    : 'Actívalo cuando estés listo para aprovecharlo.'}
                            </p>
                        </section>

                        {benefit.description ? (
                            <section className="mt-10 max-w-2xl">
                                <h2 className="text-xl font-extrabold">
                                    SOBRE ESTE BENEFICIO
                                </h2>
                                <p className="mt-3 leading-7 text-muted-foreground">
                                    {benefit.description}
                                </p>
                            </section>
                        ) : null}

                        {!availability.available ? (
                            <StatusBanner
                                title={
                                    availability.reason ??
                                    'ESTE BENEFICIO NO ESTÁ DISPONIBLE AHORA'
                                }
                                description="Puedes seguir explorando otros beneficios y experiencias."
                                tone="danger"
                                className="mt-8"
                            />
                        ) : null}

                        <div className="mt-8 hidden max-w-xl sm:block">
                            {action}
                        </div>

                        <section className="mt-10 border-t border-border pt-7">
                            <h2 className="text-xl font-extrabold">
                                DÓNDE APROVECHARLO
                            </h2>
                            {locations.length ? (
                                <div className="mt-3 divide-y divide-border">
                                    {locations.map((location) => (
                                        <article
                                            key={location.id}
                                            className="py-5"
                                        >
                                            <p className="font-bold">
                                                {location.name}
                                            </p>
                                            {location.zone ||
                                            location.address ? (
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {location.zone ??
                                                        location.address}
                                                </p>
                                            ) : null}
                                            <div className="mt-3 flex flex-wrap gap-4">
                                                {location.maps_url ? (
                                                    <a
                                                        className="inline-flex min-h-11 items-center gap-2 text-sm font-bold text-muted-foreground hover:text-foreground"
                                                        href={`/lugares/${location.slug}/mapa`}
                                                    >
                                                        <MapPin className="size-4" />
                                                        Cómo llegar
                                                    </a>
                                                ) : null}
                                                {location.whatsapp ? (
                                                    <a
                                                        className="inline-flex min-h-11 items-center gap-2 text-sm font-bold text-muted-foreground hover:text-foreground"
                                                        href={`/lugares/${location.slug}/whatsapp`}
                                                    >
                                                        <MessageCircle className="size-4" />
                                                        WhatsApp
                                                    </a>
                                                ) : null}
                                            </div>
                                        </article>
                                    ))}
                                </div>
                            ) : (
                                <p className="mt-3 text-sm text-muted-foreground">
                                    No hay ubicaciones disponibles para este
                                    beneficio.
                                </p>
                            )}
                        </section>

                        <section className="mt-8 border-t border-border pt-7">
                            <h2 className="text-lg font-extrabold">
                                DISPONIBILIDAD Y CONDICIONES
                            </h2>
                            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                Válido para membresías activas. Sujeto a
                                disponibilidad.
                            </p>
                            {benefit.terms ? (
                                <details className="mt-4 text-sm text-muted-foreground">
                                    <summary className="cursor-pointer font-semibold text-foreground">
                                        Ver condiciones
                                    </summary>
                                    <p className="mt-3 leading-6 whitespace-pre-line">
                                        {benefit.terms}
                                    </p>
                                </details>
                            ) : null}
                        </section>
                    </div>
                </section>
            </main>

            {mobileAction ? (
                <div className="fixed inset-x-0 bottom-[calc(4.0625rem+env(safe-area-inset-bottom))] z-30 border-t border-border bg-surface/95 p-3 backdrop-blur sm:hidden">
                    {mobileAction}
                </div>
            ) : null}

            {confirming ? (
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="activation-title"
                    className="fixed inset-0 z-50 flex items-end bg-overlay sm:items-center sm:justify-center"
                >
                    <div className="w-full rounded-t-[28px] bg-surface p-6 shadow-xl sm:max-w-md sm:rounded-[28px]">
                        <p className="discovery-eyebrow text-brand">JAKAWI</p>
                        <h2
                            id="activation-title"
                            className="mt-2 text-2xl font-extrabold"
                        >
                            ¿ESTÁS EN EL ESTABLECIMIENTO?
                        </h2>
                        <p className="mt-5 text-sm text-muted-foreground">
                            Estás por activar:
                        </p>
                        <p className="mt-1 font-bold">{benefit.title}</p>
                        <p className="text-sm text-muted-foreground">
                            {benefit.partner?.name}{selectedLocation ? ` — ${selectedLocation.name}` : ''}
                        </p>
                        {locations.length ? (
                            <label className="mt-5 block text-sm font-semibold">
                                Elige tu sucursal
                                <select
                                    value={locationId ?? ''}
                                    onChange={(event) =>
                                        setLocationId(
                                            Number(event.target.value),
                                        )
                                    }
                                    className="mt-2 min-h-11 w-full rounded-xl border border-border bg-background px-3"
                                >
                                    <option value="" disabled>Selecciona una sucursal</option>
                                    {locations.map((location) => (
                                        <option
                                            key={location.id}
                                            value={location.id}
                                        >
                                            {location.name}
                                            {location.zone
                                                ? ` · ${location.zone}`
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        ) : null}
                        <p className="mt-4 text-sm leading-6 text-muted-foreground">
                            Actívalo solamente cuando estés listo para
                            utilizarlo.
                        </p>
                        <div className="mt-6 grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                onClick={() => setConfirming(false)}
                                className="min-h-12 rounded-xl border border-border text-sm font-bold"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                onClick={() =>
                                    locationId &&
                                    router.post(
                                        `/beneficios/${benefit.slug}/canjear`,
                                        { location_id: locationId },
                                    )
                                }
                                disabled={!locationId}
                                className="min-h-12 rounded-xl bg-brand px-3 text-sm font-bold text-brand-foreground disabled:opacity-50"
                            >
                                ACTIVAR BENEFICIO
                            </button>
                        </div>
                    </div>
                </div>
            ) : null}
        </JourneyLayout>
    );
}
