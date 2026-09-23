import { Head, Link, router } from '@inertiajs/react';

function List({ title, items, actions = false }: any) {
    return <section className="mt-6 rounded-md border border-border bg-surface p-5"><h2 className="text-xl font-semibold">{title}</h2>{items.length ? <div className="mt-3 divide-y divide-border">{items.map((r: any) => <div key={r.public_id} className="flex items-center justify-between gap-3 py-3 text-sm"><div><p className="font-semibold">{r.experience}</p><p className="text-muted-foreground">{new Date(r.starts_at).toLocaleString('es-BO')} · {r.venue} · {r.partner}{r.capacity ? ` · Capacidad ${r.capacity}` : ''}</p><p>{r.status}</p></div>{actions ? <div className="flex gap-2"><button className="rounded bg-brand px-3 py-2 text-brand-foreground" onClick={() => router.post(`/partner/reservas/${r.public_id}/confirmed`)}>Confirmar</button><button className="rounded border px-3 py-2" onClick={() => router.post(`/partner/reservas/${r.public_id}/rejected`)}>Rechazar</button></div> : null}</div>)}</div> : <p className="mt-3 text-sm text-muted-foreground">Sin reservas.</p>}</section>;
}
export default function PartnerReservations({ pending, upcomingConfirmed, history }: any) {
 return <main className="min-h-screen bg-background p-5 text-foreground"><Head title="Reservas"/><Link href="/partner">Portal Partner</Link><h1 className="mt-6 text-3xl font-semibold">Reservas</h1><List title="Pendientes" items={pending} actions/><List title="Confirmadas próximas" items={upcomingConfirmed}/><List title="Historial" items={history}/></main>;
}
