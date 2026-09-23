import { Head, Link, router } from '@inertiajs/react';
const labels: Record<string, string> = { pending: 'Reserva solicitada · Pendiente de confirmación', confirmed: 'Reserva confirmada', rejected: 'Solicitud rechazada', cancelled: 'Reserva cancelada' };
export default function ExperienceShow({ experience, hasActiveMembership }: any) {
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
                        {experience.reservation_method === 'jakawi' && s.reservation ? <span className="text-sm font-medium">{labels[s.reservation.status]}</span> : null}
                        {experience.reservation_method === 'jakawi' && !s.reservation && s.reservable ? <button className="rounded-md bg-brand px-3 py-2 text-sm font-semibold text-brand-foreground disabled:opacity-50" disabled={!hasActiveMembership} onClick={() => router.post(`/experiencias/${experience.slug}/reservas`, { experience_session_id: s.id })}>Solicitar reserva</button> : null}
                    </div>
                ))}
            </section>
        </main>
    );
}
