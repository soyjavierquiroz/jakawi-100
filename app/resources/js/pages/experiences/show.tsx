import { Delivery } from '@/components/marketing/delivery';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, Clock3, MapPin, X } from 'lucide-react';
import { useRef, useState } from 'react';
import { JakawiImage } from '@/components/jakawi-image';
import { StatusBanner } from '@/components/state-panel';
import { trackJourneyIntent } from '@/lib/journey-analytics';

type Session = {
    id: number;
    starts_at: string;
    venue_label?: string | null;
    capacity?: number | null;
    status?: string;
    reservation?: {
        public_id: string;
        status: string;
        party_size: number;
    } | null;
    location?: {
        slug: string;
        name: string;
        address?: string | null;
        maps_url?: string | null;
    } | null;
};
type Target = {
    method: 'whatsapp' | 'url' | 'external' | 'phone';
    label: string;
};
type Partner = { id: number; slug: string; name: string; role: string };
type Experience = {
    id: number;
    prices?: Array<{ label: string; value: string; memberAdvantage: boolean }>;
    duration_minutes?: number | null;
    slug: string;
    title: string;
    short_description?: string | null;
    description?: string | null;
    reservation_method?: string;
    experience_type?: string | null;
    terms?: string | null;
    regular_price?: string | null;
    member_price?: string | null;
    image_url?: string | null;
    image_srcset?: Array<{ src: string; width: number }>;
    cover_url?: string | null;
    cover_srcset?: Array<{ src: string; width: number }>;
    partners?: Partner[];
    sessions?: Session[];
    reservation_targets?: Target[];
};

const date = (value: string) =>
    new Intl.DateTimeFormat('es-BO', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone: 'America/La_Paz',
    }).format(new Date(value));
const time = (value: string) =>
    new Intl.DateTimeFormat('es-BO', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'America/La_Paz',
    }).format(new Date(value));
const place = (session?: Session | null) =>
    session?.location?.name ?? session?.venue_label ?? null;

export default function ExperienceShow({
    experience,
    hasActiveMembership = false,
    restoredSessionId = null,
    availability = { available: true, reason: null },
    continuationOnly = false,
    externalReservationReady = false,
}: {
    experience: Experience;
    hasActiveMembership?: boolean;
    restoredSessionId?: number | null;
    availability?: { available: boolean; reason: string | null };
    continuationOnly?: boolean;
    externalReservationReady?: boolean;
}) {
    const { auth, presentation } = usePage().props;
    const landingId = continuationOnly
        ? (presentation as { id: number } | undefined)?.id
        : undefined;
    const sessions = experience.sessions ?? [];
    const [selectedSession, setSelectedSession] = useState<Session | null>(
        sessions.find((session) => session.id === restoredSessionId) ??
            (sessions.length === 1 ? sessions[0] : null),
    );
    const [reservationOpen, setReservationOpen] = useState(false);
    const intentSentFor = useRef<string | null>(null);
    const organizer =
        experience.partners?.find((partner) => partner.role === 'organizer') ??
        experience.partners?.[0];
    const collaborators =
        experience.partners?.filter(
            (partner) => partner.id !== organizer?.id,
        ) ?? [];
    const priceExists = experience.regular_price || experience.member_price;
    const internalReservation = experience.reservation_method === 'jakawi';
    const canReserve =
        sessions.length > 0 &&
        (internalReservation ||
            (experience.reservation_targets?.length ?? 0) > 0);
    const featuredSession = selectedSession ?? sessions[0] ?? null;
    const selectionRequired = sessions.length > 1 && !selectedSession;
    const internalAction = !auth.user
        ? 'CREAR CUENTA GRATIS'
        : !hasActiveMembership
          ? 'VER MEMBRESÍA'
          : 'RESERVAR';
    const reserve = () => {
        if (!internalReservation) {
            if (intentSentFor.current !== experience.slug) {
                intentSentFor.current = experience.slug;
                trackJourneyIntent('experience', experience.slug);
            }
            if (continuationOnly && selectedSession) {
                router.visit(
                    `/l/${(presentation as { slug: string }).slug}/resultado?session=${selectedSession.id}`,
                );
                return;
            }
            setReservationOpen(true);
            return;
        }
        if (!auth.user) {
            router.post(`/experiencias/${experience.slug}/reservas`, {
                experience_session_id: selectedSession!.id,
            });
            return;
        }
        if (!hasActiveMembership) {
            if (intentSentFor.current !== experience.slug) {
                intentSentFor.current = experience.slug;
                trackJourneyIntent('experience', experience.slug);
            }
            router.visit(
                `/membresia?journey=EXPERIENCE&action=RESERVE&resource_id=${experience.id}&experience_session_id=${selectedSession!.id}${landingId ? `&landing_presentation_id=${landingId}` : ''}`,
            );
            return;
        }
        router.post(
            `/experiencias/${experience.slug}/reservas`,
            {
                experience_session_id: selectedSession!.id,
                landing_presentation_id: landingId,
            },
            { preserveScroll: true },
        );
    };

    const sessionChoices =
        sessions.length > 1 && availability.available ? (
            <section
                className="mt-8 max-w-2xl"
                aria-labelledby="choose-session"
            >
                <div className="flex items-baseline justify-between gap-4">
                    <h2 id="choose-session" className="text-xl font-extrabold">
                        ELIGE TU FECHA
                    </h2>
                    {selectionRequired ? (
                        <p className="text-sm font-semibold text-muted-foreground">
                            Selecciona una para reservar
                        </p>
                    ) : null}
                </div>
                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                    {sessions.map((session) => (
                        <button
                            key={session.id}
                            type="button"
                            onClick={() => setSelectedSession(session)}
                            aria-pressed={selectedSession?.id === session.id}
                            className={`min-h-20 rounded-[var(--radius-control)] border p-4 text-left transition ${selectedSession?.id === session.id ? 'border-brand bg-brand-subtle ring-1 ring-brand' : 'hover:border-border-strong border-border bg-surface'}`}
                        >
                            <span className="block font-bold capitalize">
                                {date(session.starts_at)}
                            </span>
                            <span className="mt-1 block text-sm text-muted-foreground">
                                {time(session.starts_at)}
                                {place(session) ? ` · ${place(session)}` : ''}
                            </span>
                        </button>
                    ))}
                </div>
            </section>
        ) : null;
    const currentReservation = sessions.find(
        (session) => session.id === selectedSession?.id,
    )?.reservation;
    const existingReservation =
        currentReservation &&
        ['pending', 'confirmed'].includes(currentReservation.status)
            ? currentReservation
            : null;
    const reservationDialog = reservationOpen ? (
        <div
            className="fixed inset-0 z-50 flex items-end bg-overlay p-0 sm:items-center sm:justify-center sm:p-6"
            role="dialog"
            aria-modal="true"
            aria-labelledby="reservation-title"
        >
            <section className="w-full rounded-t-[28px] bg-surface p-5 pb-[max(1.5rem,env(safe-area-inset-bottom))] shadow-xl sm:max-w-lg sm:rounded-[28px]">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <p className="text-xs font-extrabold tracking-[0.14em] text-brand uppercase">
                            Experiencia JAKAWI
                        </p>
                        <h2
                            id="reservation-title"
                            className="mt-2 text-2xl font-extrabold"
                        >
                            RESERVA TU LUGAR
                        </h2>
                    </div>
                    <button
                        type="button"
                        onClick={() => setReservationOpen(false)}
                        aria-label="Cerrar"
                        className="inline-flex min-h-11 min-w-11 items-center justify-center rounded-full border border-border"
                    >
                        <X className="size-5" />
                    </button>
                </div>
                <p className="mt-4 leading-6 text-muted-foreground">
                    Esta experiencia es gestionada directamente por{' '}
                    {organizer?.name ?? 'el Partner'}.
                </p>
                {selectedSession ? (
                    <p className="mt-3 text-sm font-bold capitalize">
                        {date(selectedSession.starts_at)} ·{' '}
                        {time(selectedSession.starts_at)}
                    </p>
                ) : null}
                <div className="mt-6 space-y-3">
                    {experience.reservation_targets?.map((target, index) => (
                        <a
                            key={target.method}
                            href={`/experiencias/${experience.slug}/reservar?method=${target.method}`}
                            className={`flex min-h-13 items-center justify-center rounded-2xl px-4 text-sm font-extrabold ${index === 0 ? 'bg-brand text-brand-foreground' : 'border border-border bg-surface'}`}
                        >
                            {target.label}
                        </a>
                    ))}
                </div>
            </section>
        </div>
    ) : null;
    if (continuationOnly)
        return (
            <div className="space-y-5">
                {externalReservationReady && selectedSession ? (
                    <Delivery
                        heading="TODO LISTO PARA CONTINUAR TU RESERVA"
                        subject={experience.title}
                    >
                        {organizer && (
                            <p className="font-bold">{organizer.name}</p>
                        )}
                        <h3 className="font-extrabold">QUÉ TIENES QUE HACER</h3>
                        <ol className="list-decimal pl-5">
                            <li>
                                Continúa con el partner usando una de las
                                opciones de reserva.
                            </li>
                        </ol>
                        <p>
                            La reserva se gestiona directamente con{' '}
                            {organizer?.name ?? 'el partner'}. Aún no tienes una
                            reserva confirmada.
                        </p>
                        <dl className="space-y-2 rounded-2xl bg-reward p-4 text-[var(--ink)]">
                            <div>
                                <dt className="font-bold">
                                    Fecha y hora elegidas
                                </dt>
                                <dd>
                                    {date(selectedSession.starts_at)} ·{' '}
                                    {time(selectedSession.starts_at)}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-bold">Lugar</dt>
                                <dd>
                                    {place(selectedSession)}
                                    {selectedSession.location?.address
                                        ? ` · ${selectedSession.location.address}`
                                        : ''}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-bold">Estado</dt>
                                <dd>
                                    Reserva externa · pendiente de gestionar con
                                    el partner
                                </dd>
                            </div>
                        </dl>
                        {experience.terms && <p>{experience.terms}</p>}
                        <div className="grid gap-3">
                            {experience.reservation_targets?.map((target) => (
                                <a
                                    key={target.method}
                                    href={`/experiencias/${experience.slug}/reservar?method=${target.method}`}
                                    className="funnel-primary-action flex min-h-12 items-center justify-center rounded-xl px-4 text-center font-extrabold text-white"
                                >
                                    {target.label}
                                </a>
                            ))}
                        </div>
                    </Delivery>
                ) : existingReservation ? (
                    <Delivery
                        completed={existingReservation.status === 'confirmed'}
                        heading={
                            existingReservation.status === 'confirmed'
                                ? 'TU EXPERIENCIA ESTÁ RESERVADA'
                                : 'TU SOLICITUD ESTÁ REGISTRADA'
                        }
                        subject={experience.title}
                    >
                        {organizer && (
                            <p className="font-bold">{organizer.name}</p>
                        )}
                        <h3 className="font-extrabold">QUÉ TIENES QUE HACER</h3>
                        <ol className="list-decimal pl-5">
                            <li>
                                {existingReservation.status === 'pending'
                                    ? 'Espera la confirmación del partner antes de asistir.'
                                    : 'Presenta la referencia de tu reserva al llegar.'}
                            </li>
                        </ol>
                        <dl className="space-y-2 rounded-2xl bg-reward p-4 text-[var(--ink)]">
                            <div>
                                <dt className="font-bold">Fecha y hora</dt>
                                <dd>
                                    {date(selectedSession!.starts_at)} ·{' '}
                                    {time(selectedSession!.starts_at)}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-bold">Lugar</dt>
                                <dd>
                                    {place(selectedSession)} ·{' '}
                                    {selectedSession?.location?.address}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-bold">Estado</dt>
                                <dd>
                                    {existingReservation.status === 'confirmed'
                                        ? 'Confirmada'
                                        : 'Pendiente de confirmación'}{' '}
                                    · {existingReservation.party_size}{' '}
                                    persona(s)
                                </dd>
                            </div>
                            <div>
                                <dt className="font-bold">Referencia</dt>
                                <dd>{existingReservation.public_id}</dd>
                            </div>
                        </dl>
                        {experience.terms && <p>{experience.terms}</p>}
                        {selectedSession?.location?.maps_url && (
                            <a
                                href={selectedSession.location.maps_url}
                                className="funnel-primary-action inline-flex min-h-12 items-center justify-center rounded-xl px-4 font-extrabold text-white"
                                target="_blank"
                                rel="noreferrer"
                            >
                                Cómo llegar
                            </a>
                        )}
                    </Delivery>
                ) : (
                    <>
                        <header className="space-y-3">
                            <p className="text-sm font-extrabold">
                                TU EXPERIENCIA
                            </p>
                            <h1 className="text-3xl leading-tight font-extrabold">
                                ELIGE CUÁNDO QUIERES VIVIRLO
                            </h1>
                            <p className="font-bold">{experience.title}</p>
                            {organizer && (
                                <p className="text-muted-foreground">
                                    {organizer.name}
                                </p>
                            )}
                            <p>
                                Selecciona una fecha para continuar con tu
                                reserva.
                            </p>
                        </header>
                        <div
                            className="grid gap-3 sm:grid-cols-2"
                            aria-label="Fechas disponibles"
                        >
                            {sessions.map((session) => (
                                <button
                                    key={session.id}
                                    type="button"
                                    onClick={() => {
                                        if (
                                            session.reservation &&
                                            ['pending', 'confirmed'].includes(
                                                session.reservation.status,
                                            )
                                        ) {
                                            router.visit(
                                                `/l/${(presentation as { slug: string }).slug}/resultado?session=${session.id}`,
                                            );
                                            return;
                                        }
                                        setSelectedSession(session);
                                        setReservationOpen(false);
                                    }}
                                    aria-pressed={
                                        selectedSession?.id === session.id
                                    }
                                    className={`activation-option relative min-h-24 rounded-2xl border-2 p-4 text-left ${selectedSession?.id === session.id ? 'border-brand bg-brand-subtle shadow-md' : 'border-border bg-surface'}`}
                                >
                                    <span className="block font-extrabold capitalize">
                                        {date(session.starts_at)}
                                    </span>
                                    <span className="mt-2 block">
                                        {time(session.starts_at)} ·{' '}
                                        {place(session)}
                                    </span>
                                    {session.location?.address && (
                                        <span className="mt-1 block text-sm text-muted-foreground">
                                            {session.location.address}
                                        </span>
                                    )}
                                    {session.capacity != null && (
                                        <span className="mt-1 block text-xs text-muted-foreground">
                                            Capacidad: {session.capacity}{' '}
                                            personas
                                        </span>
                                    )}
                                    {selectedSession?.id === session.id && (
                                        <span className="mt-2 block text-sm font-extrabold text-brand">
                                            ✓ Seleccionada
                                        </span>
                                    )}
                                </button>
                            ))}
                        </div>
                        {!hasActiveMembership && internalReservation && (
                            <p className="text-sm">
                                Esta reserva requiere membresía activa. La fecha
                                elegida se conservará.
                            </p>
                        )}
                        {canReserve && availability.available ? (
                            <div className="sticky bottom-0 border-t border-border bg-background py-4">
                                <button
                                    type="button"
                                    onClick={reserve}
                                    disabled={!selectedSession}
                                    className="funnel-primary-action min-h-14 w-full rounded-xl font-extrabold text-white disabled:opacity-50"
                                >
                                    {!selectedSession
                                        ? 'ELIGE UNA FECHA'
                                        : `${internalReservation ? (hasActiveMembership ? 'RESERVAR' : 'CONTINUAR CON MEMBRESÍA PARA') : 'VER OPCIONES PARA'} EL ${new Intl.DateTimeFormat('es-BO', { day: 'numeric', month: 'long', timeZone: 'America/La_Paz' }).format(new Date(selectedSession.starts_at)).toUpperCase()}`}
                                </button>
                            </div>
                        ) : (
                            <p>
                                {availability.reason ??
                                    'NO HAY RESERVAS DISPONIBLES'}
                            </p>
                        )}
                    </>
                )}
            </div>
        );

    return (
        <>
            <Head title={experience.title} />
            <main className="min-h-screen bg-background pb-48 font-discovery text-foreground lg:pb-32">
                <section className="mx-auto max-w-5xl">
                    <div className="relative aspect-[4/3] overflow-hidden bg-surface-muted sm:aspect-[16/8] sm:rounded-b-[32px]">
                        <JakawiImage
                            src={experience.cover_url || experience.image_url}
                            srcset={
                                experience.cover_url
                                    ? experience.cover_srcset
                                    : experience.image_srcset
                            }
                            sizes="(min-width: 1024px) 960px, 100vw"
                            alt={experience.title}
                            className="h-full w-full object-cover"
                            fallbackClassName="border-0 bg-surface-muted"
                            loading="eager"
                            priority
                        />
                        <div
                            aria-hidden="true"
                            className="absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-foreground/25 to-transparent"
                        />
                        <Link
                            href="/explorar?type=experiences"
                            aria-label="Volver a explorar"
                            className="absolute top-4 left-4 inline-flex min-h-11 items-center gap-2 rounded-full bg-surface/95 px-4 text-sm font-bold shadow-sm"
                        >
                            <ArrowLeft className="size-4" />
                            Explorar
                        </Link>
                    </div>
                    <div className="px-4 pt-7 sm:px-8 sm:pt-10">
                        <p className="text-xs font-extrabold tracking-[0.14em] text-brand uppercase">
                            {experience.experience_type === 'event'
                                ? 'Evento JAKAWI'
                                : 'Experiencia JAKAWI'}
                            {experience.experience_type &&
                            experience.experience_type !== 'event'
                                ? ` · ${experience.experience_type}`
                                : ''}
                        </p>
                        <h1 className="mt-3 max-w-3xl text-[38px] leading-[0.95] font-extrabold tracking-tight sm:text-6xl">
                            {experience.title}
                        </h1>
                        {organizer ? (
                            <p className="text-foreground-soft mt-4 text-sm font-bold">
                                Organiza{' '}
                                <Link
                                    href={`/partners/${organizer.slug}`}
                                    className="text-brand hover:underline"
                                >
                                    {organizer.name}
                                </Link>
                            </p>
                        ) : null}
                        {experience.duration_minutes ? (
                            <p className="mt-3 text-sm text-muted-foreground">
                                Duración total propuesta:{' '}
                                {experience.duration_minutes} minutos
                            </p>
                        ) : null}
                        {experience.short_description ? (
                            <p className="text-foreground-soft mt-5 max-w-2xl text-lg leading-7 font-semibold">
                                {experience.short_description}
                            </p>
                        ) : null}

                        {!availability.available ? (
                            <StatusBanner
                                title={
                                    availability.reason ??
                                    'ESTA EXPERIENCIA NO ESTÁ DISPONIBLE AHORA'
                                }
                                description="Puedes seguir explorando otras experiencias."
                                tone="danger"
                                className="mt-7"
                            />
                        ) : null}
                        {availability.available && featuredSession ? (
                            <section
                                aria-labelledby="session-summary"
                                className="mt-8 max-w-2xl overflow-hidden rounded-[var(--radius-card)] border border-border bg-surface-elevated"
                            >
                                <div className="bg-brand px-5 py-3 text-brand-foreground">
                                    <p
                                        id="session-summary"
                                        className="text-xs font-extrabold tracking-[0.14em] uppercase"
                                    >
                                        {selectedSession
                                            ? 'Tu fecha'
                                            : 'Próxima fecha'}
                                    </p>
                                </div>
                                <div className="grid gap-4 px-5 py-5 sm:grid-cols-[1.35fr_0.7fr_1fr]">
                                    <p className="flex items-start gap-3 font-bold capitalize">
                                        <CalendarDays className="mt-0.5 size-5 shrink-0 text-brand" />
                                        {date(featuredSession.starts_at)}
                                    </p>
                                    <p className="flex items-start gap-3 font-bold">
                                        <Clock3 className="mt-0.5 size-5 shrink-0 text-brand" />
                                        {time(featuredSession.starts_at)}
                                    </p>
                                    {place(featuredSession) ? (
                                        <p className="flex items-start gap-3 font-bold">
                                            <MapPin className="mt-0.5 size-5 shrink-0 text-brand" />
                                            {place(featuredSession)}
                                        </p>
                                    ) : null}
                                </div>
                            </section>
                        ) : availability.available ? (
                            <StatusBanner
                                title="NO HAY PRÓXIMAS FECHAS"
                                description="Esta experiencia no tiene fechas disponibles ahora."
                                className="mt-7"
                            />
                        ) : null}

                        {sessionChoices}

                        {experience.description ? (
                            <section className="mt-10 max-w-2xl">
                                <h2 className="text-xl font-extrabold">
                                    QUÉ VAS A VIVIR
                                </h2>
                                <p className="mt-3 leading-7 whitespace-pre-line text-muted-foreground">
                                    {experience.description}
                                </p>
                            </section>
                        ) : null}
                        {experience.terms ? (
                            <section className="mt-10 max-w-2xl border-t border-border pt-7">
                                <h2 className="text-xl font-extrabold">
                                    CONDICIONES
                                </h2>
                                <p className="mt-3 text-sm leading-6 whitespace-pre-line text-muted-foreground">
                                    {experience.terms}
                                </p>
                            </section>
                        ) : null}

                        {organizer || place(featuredSession) ? (
                            <section className="mt-10 max-w-2xl border-t border-border pt-7">
                                <h2 className="text-xl font-extrabold">
                                    QUIÉN Y DÓNDE
                                </h2>
                                <div className="mt-4 space-y-4">
                                    {organizer ? (
                                        <div>
                                            <p className="text-xs font-extrabold tracking-[0.12em] text-muted-foreground uppercase">
                                                Organiza
                                            </p>
                                            <Link
                                                href={`/partners/${organizer.slug}`}
                                                className="mt-1 inline-flex min-h-11 items-center text-base font-bold text-brand"
                                            >
                                                {organizer.name}
                                            </Link>
                                            {collaborators.length ? (
                                                <p className="text-sm text-muted-foreground">
                                                    Con{' '}
                                                    {collaborators
                                                        .map(
                                                            (partner) =>
                                                                partner.name,
                                                        )
                                                        .join(' · ')}
                                                </p>
                                            ) : null}
                                        </div>
                                    ) : null}
                                    {place(featuredSession) ? (
                                        <div>
                                            <p className="text-xs font-extrabold tracking-[0.12em] text-muted-foreground uppercase">
                                                Lugar
                                            </p>
                                            <p className="mt-1 font-bold">
                                                {place(featuredSession)}
                                            </p>
                                            {featuredSession?.location
                                                ?.maps_url ? (
                                                <a
                                                    href={`/lugares/${featuredSession.location.slug}/mapa`}
                                                    className="mt-2 inline-flex min-h-11 items-center gap-2 text-sm font-bold text-muted-foreground hover:text-foreground"
                                                >
                                                    <MapPin className="size-4" />
                                                    Cómo llegar
                                                </a>
                                            ) : null}
                                        </div>
                                    ) : null}
                                </div>
                            </section>
                        ) : null}

                        {priceExists ? (
                            <section className="mt-10 max-w-2xl rounded-[var(--radius-card)] border border-border bg-surface p-5">
                                <h2 className="text-xs font-extrabold tracking-[0.14em] text-muted-foreground uppercase">
                                    Precio
                                </h2>
                                {experience.prices?.map((price) => (
                                    <p key={price.label} className="mt-3">
                                        <span className="block text-sm text-muted-foreground">
                                            {price.label}
                                        </span>
                                        <strong>{price.value}</strong>
                                    </p>
                                ))}
                                {!availability.available && (
                                    <p className="mt-3 text-sm text-muted-foreground">
                                        Propuesta comercial. No admite reservas
                                        ni pagos ahora.
                                    </p>
                                )}
                            </section>
                        ) : null}
                        {internalReservation &&
                        availability.available &&
                        !auth.user ? (
                            <p className="mt-7 max-w-2xl text-sm text-muted-foreground">
                                Crea una cuenta gratis para continuar con la
                                fecha elegida. La reserva requiere una membresía
                                activa.
                            </p>
                        ) : null}
                        {internalReservation &&
                        availability.available &&
                        auth.user &&
                        !hasActiveMembership ? (
                            <StatusBanner
                                title="SE REQUIERE MEMBRESÍA"
                                description="Necesitas una membresía activa para reservar esta experiencia. Consulta la membresía antes de continuar."
                                className="mt-7 max-w-2xl"
                            />
                        ) : null}
                        {!canReserve &&
                        sessions.length > 0 &&
                        availability.available ? (
                            <StatusBanner
                                title="LA RESERVA NO ESTÁ DISPONIBLE"
                                description="Esta experiencia no tiene un destino de reserva configurado. Consulta directamente con el organizador."
                                className="mt-10 max-w-2xl"
                            />
                        ) : null}
                    </div>
                </section>
                {canReserve && availability.available ? (
                    <div className="fixed right-0 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] left-0 z-30 border-t border-border bg-surface/95 p-4 backdrop-blur lg:bottom-0">
                        <div className="mx-auto max-w-5xl">
                            <button
                                type="button"
                                onClick={reserve}
                                disabled={selectionRequired}
                                className="min-h-14 w-full rounded-2xl bg-brand px-5 text-sm font-extrabold text-brand-foreground transition hover:bg-brand/90 disabled:cursor-not-allowed disabled:opacity-50 sm:max-w-sm"
                            >
                                {internalReservation
                                    ? internalAction
                                    : 'RESERVAR'}
                            </button>
                        </div>
                    </div>
                ) : null}
                {reservationDialog}
            </main>
        </>
    );
}
