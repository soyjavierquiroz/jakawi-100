import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
const labels: Record<string, string> = { pending: 'Reserva solicitada · Pendiente de confirmación', confirmed: 'Reserva confirmada', rejected: 'Solicitud rechazada', cancelled: 'Reserva cancelada' };
const partyLabel = (size: number) => size === 1 ? 'Sólo tú' : `Tú + ${size - 1} acompañante${size === 2 ? '' : 's'}`;
export default function ExperienceShow({ experience, hasActiveMembership }: any) {
    const [partySizes, setPartySizes] = useState<Record<number, number>>({});
    return (
        <main className="min-h-screen bg-background p-5 text-foreground">
            <Head title={experience.title} />
            <Link href="/experiencias">Experiencias</Link>
            <h1 className="mt-6 text-4xl font-semibold">{experience.title}</h1>
            <p className="mt-3 whitespace-pre-line text-muted-foreground">
                {experience.description}
            </p>
            {experience.reservation_method && experience.reservation_method !== 'jakawi' ? (
                <a
                    className="mt-5 inline-block rounded-md bg-brand px-4 py-3 font-semibold text-brand-foreground"
                    href={`/experiencias/${experience.slug}/reservar`}
                >
                    Reservar
                </a>
            ) : null}
            <section className="mt-8">
                <h2 className="text-xl font-semibold">Próximas fechas</h2>
                {experience.sessions?.filter((s: any) => new Date(s.starts_at) >= new Date() && s.status === 'scheduled').map((s: any) => (
                    <div key={s.id} className="flex items-center justify-between gap-3 py-3">
                        <p>{new Date(s.starts_at).toLocaleString('es-BO')} · {s.location?.name || s.venue_label}</p>
                        {experience.reservation_method === 'jakawi' && s.reservation ? <span className="text-sm font-medium">{labels[s.reservation.status]} · {s.reservation.party_size === 1 ? '1 persona' : `${s.reservation.party_size} personas`}</span> : null}
                        {experience.reservation_method === 'jakawi' && !s.reservation && s.reservable ? <div className="flex items-center gap-2"><label className="text-sm" htmlFor={`party-size-${s.id}`}>Número de personas</label><select id={`party-size-${s.id}`} className="rounded-md border border-border bg-background px-2 py-2 text-sm" value={partySizes[s.id] ?? 1} onChange={(event) => setPartySizes({ ...partySizes, [s.id]: Number(event.target.value) })}>{Array.from({ length: 10 }, (_, index) => index + 1).map((size) => <option key={size} value={size}>{size} · {partyLabel(size)}</option>)}</select><button className="rounded-md bg-brand px-3 py-2 text-sm font-semibold text-brand-foreground disabled:opacity-50" disabled={!hasActiveMembership} onClick={() => router.post(`/experiencias/${experience.slug}/reservas`, { experience_session_id: s.id, party_size: partySizes[s.id] ?? 1 })}>Solicitar reserva</button></div> : null}
                    </div>
                ))}
            </section>
        </main>
    );
}
