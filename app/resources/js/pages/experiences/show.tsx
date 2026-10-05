import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, Clock3, MapPin, X } from 'lucide-react';
import { useState } from 'react';
import { JakawiImage } from '@/components/jakawi-image';
import { StatusBanner } from '@/components/state-panel';

type Session = { id: number; starts_at: string; venue_label?: string | null; location?: { slug: string; name: string; maps_url?: string | null } | null };
type Target = { method: 'whatsapp' | 'url' | 'external'; label: string };
type Partner = { id: number; slug: string; name: string; role: string };
type Experience = {
    slug: string; title: string; short_description?: string | null; description?: string | null;
    reservation_method?: string;
    regular_price?: string | null; member_price?: string | null; image_url?: string | null;
    image_srcset?: Array<{ src: string; width: number }>; cover_url?: string | null;
    cover_srcset?: Array<{ src: string; width: number }>; partners?: Partner[]; sessions?: Session[];
    reservation_targets?: Target[];
};

const date = (value: string) => new Intl.DateTimeFormat('es-BO', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date(value));
const time = (value: string) => new Intl.DateTimeFormat('es-BO', { hour: '2-digit', minute: '2-digit' }).format(new Date(value));
const place = (session?: Session | null) => session?.location?.name ?? session?.venue_label ?? null;

export default function ExperienceShow({ experience, availability = { available: true, reason: null } }: { experience: Experience; availability?: { available: boolean; reason: string | null } }) {
    const sessions = experience.sessions ?? [];
    const [selectedSession, setSelectedSession] = useState<Session | null>(sessions.length === 1 ? sessions[0] : null);
    const [reservationOpen, setReservationOpen] = useState(false);
    const organizer = experience.partners?.find((partner) => partner.role === 'organizer') ?? experience.partners?.[0];
    const collaborators = experience.partners?.filter((partner) => partner.id !== organizer?.id) ?? [];
    const priceExists = experience.regular_price || experience.member_price;
    const internalReservation = experience.reservation_method === 'jakawi';
    const canReserve = sessions.length > 0 && (internalReservation || (experience.reservation_targets?.length ?? 0) > 0);
    const featuredSession = selectedSession ?? sessions[0] ?? null;
    const selectionRequired = sessions.length > 1 && !selectedSession;

    return <><Head title={experience.title} /><main className="min-h-screen bg-background pb-48 font-discovery text-foreground lg:pb-32">
        <section className="mx-auto max-w-5xl">
            <div className="relative aspect-[4/3] overflow-hidden bg-surface-muted sm:aspect-[16/8] sm:rounded-b-[32px]">
                <JakawiImage src={experience.cover_url || experience.image_url} srcset={experience.cover_url ? experience.cover_srcset : experience.image_srcset} sizes="(min-width: 1024px) 960px, 100vw" alt={experience.title} className="h-full w-full object-cover" fallbackClassName="border-0 bg-surface-muted" loading="eager" priority />
                <div aria-hidden="true" className="absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-foreground/25 to-transparent" />
                <Link href="/explorar?type=experiences" aria-label="Volver a explorar" className="absolute top-4 left-4 inline-flex min-h-11 items-center gap-2 rounded-full bg-surface/95 px-4 text-sm font-bold shadow-sm"><ArrowLeft className="size-4" />Explorar</Link>
            </div>
            <div className="px-4 pt-7 sm:px-8 sm:pt-10">
                <p className="text-xs font-extrabold tracking-[0.14em] text-brand uppercase">Experiencia JAKAWI</p>
                <h1 className="mt-3 max-w-3xl text-[38px] leading-[0.95] font-extrabold tracking-tight sm:text-6xl">{experience.title}</h1>
                {experience.short_description ? <p className="mt-5 max-w-2xl text-lg leading-7 font-semibold text-foreground-soft">{experience.short_description}</p> : null}

                {!availability.available ? <StatusBanner title={availability.reason ?? 'ESTA EXPERIENCIA NO ESTÁ DISPONIBLE AHORA'} description="Puedes seguir explorando otras experiencias." tone="danger" className="mt-7" /> : null}
                {availability.available && featuredSession ? <section aria-labelledby="session-summary" className="mt-8 max-w-2xl overflow-hidden rounded-[var(--radius-card)] border border-border bg-surface-elevated">
                    <div className="bg-brand px-5 py-3 text-brand-foreground"><p id="session-summary" className="text-xs font-extrabold tracking-[0.14em] uppercase">{selectedSession ? 'Tu fecha' : 'Próxima fecha'}</p></div>
                    <div className="grid gap-4 px-5 py-5 sm:grid-cols-[1.35fr_0.7fr_1fr]">
                        <p className="flex items-start gap-3 font-bold capitalize"><CalendarDays className="mt-0.5 size-5 shrink-0 text-brand" />{date(featuredSession.starts_at)}</p>
                        <p className="flex items-start gap-3 font-bold"><Clock3 className="mt-0.5 size-5 shrink-0 text-brand" />{time(featuredSession.starts_at)}</p>
                        {place(featuredSession) ? <p className="flex items-start gap-3 font-bold"><MapPin className="mt-0.5 size-5 shrink-0 text-brand" />{place(featuredSession)}</p> : null}
                    </div>
                </section> : availability.available ? <StatusBanner title="NO HAY PRÓXIMAS FECHAS" description="Esta experiencia no tiene fechas disponibles ahora." className="mt-7" /> : null}

                {sessions.length > 1 && availability.available ? <section className="mt-8 max-w-2xl" aria-labelledby="choose-session"><div className="flex items-baseline justify-between gap-4"><h2 id="choose-session" className="text-xl font-extrabold">ELIGE TU FECHA</h2>{selectionRequired ? <p className="text-sm font-semibold text-muted-foreground">Selecciona una para reservar</p> : null}</div><div className="mt-4 grid gap-3 sm:grid-cols-2">{sessions.map((session) => <button key={session.id} type="button" onClick={() => setSelectedSession(session)} aria-pressed={selectedSession?.id === session.id} className={`min-h-20 rounded-[var(--radius-control)] border p-4 text-left transition ${selectedSession?.id === session.id ? 'border-brand bg-brand-subtle ring-1 ring-brand' : 'border-border bg-surface hover:border-border-strong'}`}><span className="block font-bold capitalize">{date(session.starts_at)}</span><span className="mt-1 block text-sm text-muted-foreground">{time(session.starts_at)}{place(session) ? ` · ${place(session)}` : ''}</span></button>)}</div></section> : null}

                {experience.description ? <section className="mt-10 max-w-2xl"><h2 className="text-xl font-extrabold">QUÉ VAS A VIVIR</h2><p className="mt-3 whitespace-pre-line leading-7 text-muted-foreground">{experience.description}</p></section> : null}

                {organizer || place(featuredSession) ? <section className="mt-10 max-w-2xl border-t border-border pt-7"><h2 className="text-xl font-extrabold">QUIÉN Y DÓNDE</h2><div className="mt-4 space-y-4">{organizer ? <div><p className="text-xs font-extrabold tracking-[0.12em] text-muted-foreground uppercase">Organiza</p><Link href={`/partners/${organizer.slug}`} className="mt-1 inline-flex min-h-11 items-center text-base font-bold text-brand">{organizer.name}</Link>{collaborators.length ? <p className="text-sm text-muted-foreground">Con {collaborators.map((partner) => partner.name).join(' · ')}</p> : null}</div> : null}{place(featuredSession) ? <div><p className="text-xs font-extrabold tracking-[0.12em] text-muted-foreground uppercase">Lugar</p><p className="mt-1 font-bold">{place(featuredSession)}</p>{featuredSession?.location?.maps_url ? <a href={`/lugares/${featuredSession.location.slug}/mapa`} className="mt-2 inline-flex min-h-11 items-center gap-2 text-sm font-bold text-muted-foreground hover:text-foreground"><MapPin className="size-4" />Cómo llegar</a> : null}</div> : null}</div></section> : null}

                {priceExists ? <section className="mt-10 max-w-2xl rounded-[var(--radius-card)] border border-border bg-surface p-5"><h2 className="text-xs font-extrabold tracking-[0.14em] text-muted-foreground uppercase">Precio</h2>{experience.regular_price ? <p className="mt-3">Normal: Bs {experience.regular_price}</p> : null}{experience.member_price ? <p className="mt-2 text-lg font-bold">Miembro JAKAWI: Bs {experience.member_price}</p> : null}</section> : null}
                {!canReserve && sessions.length > 0 && availability.available ? <StatusBanner title="LA RESERVA NO ESTÁ DISPONIBLE" description="Esta experiencia no tiene un destino de reserva configurado. Consulta directamente con el organizador." className="mt-10 max-w-2xl" /> : null}
            </div>
        </section>
        {canReserve && availability.available ? <div className="fixed right-0 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] left-0 z-30 border-t border-border bg-surface/95 p-4 backdrop-blur lg:bottom-0"><div className="mx-auto max-w-5xl"><button type="button" onClick={() => internalReservation ? router.post(`/experiencias/${experience.slug}/reservas`, { experience_session_id: selectedSession!.id }) : setReservationOpen(true)} disabled={selectionRequired} className="min-h-14 w-full rounded-2xl bg-brand px-5 text-sm font-extrabold text-brand-foreground transition hover:bg-brand/90 disabled:cursor-not-allowed disabled:opacity-50 sm:max-w-sm">RESERVAR</button></div></div> : null}
        {reservationOpen ? <div className="fixed inset-0 z-50 flex items-end bg-overlay p-0 sm:items-center sm:justify-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="reservation-title"><section className="w-full rounded-t-[28px] bg-surface p-5 pb-[max(1.5rem,env(safe-area-inset-bottom))] shadow-xl sm:max-w-lg sm:rounded-[28px]"><div className="flex items-start justify-between gap-4"><div><p className="text-xs font-extrabold tracking-[0.14em] text-brand uppercase">Experiencia JAKAWI</p><h2 id="reservation-title" className="mt-2 text-2xl font-extrabold">RESERVA TU LUGAR</h2></div><button type="button" onClick={() => setReservationOpen(false)} aria-label="Cerrar" className="inline-flex min-h-11 min-w-11 items-center justify-center rounded-full border border-border"><X className="size-5" /></button></div><p className="mt-4 leading-6 text-muted-foreground">Esta experiencia es gestionada directamente por {organizer?.name ?? 'el Partner'}.</p>{selectedSession ? <p className="mt-3 text-sm font-bold capitalize">{date(selectedSession.starts_at)} · {time(selectedSession.starts_at)}</p> : null}<div className="mt-6 space-y-3">{experience.reservation_targets?.map((target, index) => <a key={target.method} href={`/experiencias/${experience.slug}/reservar?method=${target.method}`} className={`flex min-h-13 items-center justify-center rounded-2xl px-4 text-sm font-extrabold ${index === 0 ? 'bg-brand text-brand-foreground' : 'border border-border bg-surface'}`}>{target.label}</a>)}</div></section></div> : null}
    </main></>;
}
