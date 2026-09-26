import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, MapPin, X } from 'lucide-react';
import { useState } from 'react';
import { JakawiImage } from '@/components/jakawi-image';
import { StatusBanner } from '@/components/state-panel';

type Session = { id: number; starts_at: string; venue_label?: string | null; location?: { slug: string; name: string; maps_url?: string | null } | null };
type Target = { method: 'whatsapp' | 'url' | 'external'; label: string };
type Partner = { id: number; slug: string; name: string; role: string };
type Experience = {
    slug: string; title: string; short_description?: string | null; description?: string | null;
    regular_price?: string | null; member_price?: string | null; image_url?: string | null;
    image_srcset?: Array<{ src: string; width: number }>; cover_url?: string | null;
    cover_srcset?: Array<{ src: string; width: number }>; partners?: Partner[]; sessions?: Session[];
    reservation_targets?: Target[];
};

const date = (value: string) => new Intl.DateTimeFormat('es-BO', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date(value));
const time = (value: string) => new Intl.DateTimeFormat('es-BO', { hour: '2-digit', minute: '2-digit' }).format(new Date(value));
const place = (session?: Session) => session?.location?.name ?? session?.venue_label ?? null;

export default function ExperienceShow({ experience, availability = { available: true, reason: null } }: { experience: Experience; availability?: { available: boolean; reason: string | null } }) {
    const sessions = experience.sessions ?? [];
    const [selectedSession, setSelectedSession] = useState<Session | null>(sessions.length === 1 ? sessions[0] : null);
    const [reservationOpen, setReservationOpen] = useState(false);
    const organizer = experience.partners?.find((partner) => partner.role === 'organizer') ?? experience.partners?.[0];
    const collaborators = experience.partners?.filter((partner) => partner.id !== organizer?.id) ?? [];
    const priceExists = experience.regular_price || experience.member_price;
    const canReserve = sessions.length > 0 && (experience.reservation_targets?.length ?? 0) > 0;
    const location = selectedSession ?? sessions[0];

    return <><Head title={experience.title} /><main className="min-h-screen bg-background pb-28 text-foreground">
        <section className="mx-auto max-w-5xl">
            <div className="relative aspect-[4/3] bg-surface-muted sm:aspect-[16/8] sm:rounded-b-[32px]">
                <JakawiImage src={experience.cover_url || experience.image_url} srcset={experience.cover_url ? experience.cover_srcset : experience.image_srcset} sizes="(min-width: 1024px) 960px, 100vw" alt={experience.title} className="h-full w-full object-cover sm:rounded-b-[32px]" loading="eager" priority />
                <Link href="/explorar?type=experiences" aria-label="Volver a explorar" className="absolute top-4 left-4 inline-flex min-h-11 items-center gap-2 rounded-full bg-surface px-4 text-sm font-bold shadow-sm"><ArrowLeft className="size-4" />Explorar</Link>
            </div>
            <div className="px-4 pt-6 sm:px-8 sm:pt-10">
                <p className="text-xs font-extrabold tracking-[0.14em] text-brand uppercase">Experiencia JAKAWI</p>
                <h1 className="mt-2 max-w-3xl text-4xl leading-none font-extrabold tracking-tight sm:text-6xl">{experience.title}</h1>
                {!availability.available ? <StatusBanner title={availability.reason ?? 'ESTA EXPERIENCIA NO ESTÁ DISPONIBLE AHORA'} description="Puedes seguir explorando otras experiencias." tone="danger" className="mt-6" /> : null}
                {availability.available && location ? <div className="mt-6 grid gap-3 text-sm font-semibold sm:grid-cols-3">
                    <p className="flex items-center gap-2"><CalendarDays className="size-4 shrink-0 text-brand" />{date(location.starts_at)}</p>
                    <p className="flex items-center gap-2"><CalendarDays className="size-4 shrink-0 text-brand" />{time(location.starts_at)}</p>
                    {place(location) ? <p className="flex items-center gap-2"><MapPin className="size-4 shrink-0 text-brand" />{place(location)}</p> : null}
                </div> : availability.available ? <StatusBanner title="NO HAY PRÓXIMAS FECHAS" description="Esta experiencia no tiene fechas disponibles ahora." className="mt-6" /> : null}
                {experience.short_description || experience.description ? <section className="mt-10 max-w-2xl"><h2 className="text-xl font-extrabold">QUÉ VAS A VIVIR</h2>{experience.short_description ? <p className="mt-3 text-lg leading-7 text-foreground-soft">{experience.short_description}</p> : null}{experience.description ? <p className="mt-3 whitespace-pre-line leading-7 text-muted-foreground">{experience.description}</p> : null}</section> : null}
                {sessions.length > 1 ? <section className="mt-10"><h2 className="text-xl font-extrabold">ELIGE TU FECHA</h2><div className="mt-4 grid gap-3 sm:grid-cols-2">{sessions.map((session) => <button key={session.id} type="button" onClick={() => setSelectedSession(session)} className={`min-h-16 rounded-2xl border p-4 text-left ${selectedSession?.id === session.id ? 'border-brand bg-brand-subtle' : 'border-border bg-surface'}`}><span className="block font-bold">{date(session.starts_at)}</span><span className="mt-1 block text-sm text-muted-foreground">{time(session.starts_at)}{place(session) ? ` · ${place(session)}` : ''}</span></button>)}</div></section> : null}
                {location?.location?.maps_url ? <a href={`/lugares/${location.location.slug}/mapa`} className="mt-5 inline-flex min-h-11 items-center gap-2 text-sm font-bold text-brand"><MapPin className="size-4" />CÓMO LLEGAR</a> : null}
                {organizer ? <section className="mt-10 border-t border-border pt-7"><h2 className="text-xl font-extrabold">ORGANIZA</h2><Link href={`/partners/${organizer.slug}`} className="mt-3 inline-flex min-h-11 items-center text-base font-bold text-brand">{organizer.name}</Link>{collaborators.length ? <p className="mt-2 text-sm text-muted-foreground">Con {collaborators.map((partner) => partner.name).join(' · ')}</p> : null}</section> : null}
                {priceExists ? <section className="mt-10 rounded-2xl border border-border bg-surface p-5"><h2 className="text-xs font-extrabold tracking-[0.14em] text-muted-foreground uppercase">Precio</h2>{experience.regular_price ? <p className="mt-3">Normal: Bs {experience.regular_price}</p> : null}{experience.member_price ? <p className="mt-2 text-lg font-bold">Miembro JAKAWI: Bs {experience.member_price}</p> : null}</section> : null}
                {!canReserve && sessions.length > 0 && availability.available ? <StatusBanner title="LA RESERVA NO ESTÁ DISPONIBLE" description="Esta experiencia no tiene un destino de reserva configurado. Consulta directamente con el organizador." className="mt-10" /> : null}
            </div>
        </section>
        {canReserve && availability.available ? <div className="fixed right-0 bottom-0 left-0 z-20 border-t border-border bg-surface p-4 pb-[max(1rem,env(safe-area-inset-bottom))]"><div className="mx-auto max-w-5xl"><button type="button" onClick={() => setReservationOpen(true)} disabled={sessions.length > 1 && !selectedSession} className="min-h-13 w-full rounded-2xl bg-brand px-5 text-sm font-extrabold text-brand-foreground disabled:opacity-50 sm:max-w-sm">RESERVAR</button></div></div> : null}
        {reservationOpen ? <div className="fixed inset-0 z-30 flex items-end bg-overlay p-0 sm:items-center sm:justify-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="reservation-title"><section className="w-full rounded-t-[28px] bg-surface p-5 pb-[max(1.5rem,env(safe-area-inset-bottom))] shadow-xl sm:max-w-lg sm:rounded-[28px]"><div className="flex items-start justify-between gap-4"><div><p className="text-xs font-extrabold tracking-[0.14em] text-brand uppercase">Experiencia JAKAWI</p><h2 id="reservation-title" className="mt-2 text-2xl font-extrabold">RESERVA TU LUGAR</h2></div><button type="button" onClick={() => setReservationOpen(false)} aria-label="Cerrar" className="inline-flex min-h-11 min-w-11 items-center justify-center rounded-full border border-border"><X className="size-5" /></button></div><p className="mt-4 leading-6 text-muted-foreground">Esta experiencia es gestionada directamente por {organizer?.name ?? 'el Partner'}.</p>{selectedSession ? <p className="mt-3 text-sm font-bold">{date(selectedSession.starts_at)} · {time(selectedSession.starts_at)}</p> : null}<div className="mt-6 space-y-3">{experience.reservation_targets?.map((target, index) => <a key={target.method} href={`/experiencias/${experience.slug}/reservar?method=${target.method}`} className={`flex min-h-13 items-center justify-center rounded-2xl px-4 text-sm font-extrabold ${index === 0 ? 'bg-brand text-brand-foreground' : 'border border-border bg-surface'}`}>{target.label}</a>)}</div></section></div> : null}
    </main></>;
}
