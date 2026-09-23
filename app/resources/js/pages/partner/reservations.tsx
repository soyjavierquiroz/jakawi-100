import { router } from '@inertiajs/react';
import PartnerLayout from './layout';

function List({ title, items, actions = false }: any) {
    return <section className="mt-6 rounded-md border border-border bg-surface p-5"><h2 className="text-xl font-semibold">{title}</h2>{items.length ? <div className="mt-3 divide-y divide-border">{items.map((r: any) => <div key={r.public_id} className="flex items-center justify-between gap-3 py-3 text-sm"><div><p className="font-semibold">{r.member_name} · {r.party_size === 1 ? '1 persona' : `${r.party_size} personas`}</p><p>{r.experience}</p><p className="text-muted-foreground">{new Date(r.starts_at).toLocaleString('es-BO')} · {r.venue}</p>{actions ? <p className="text-muted-foreground">Solicitada: {new Date(r.requested_at).toLocaleString('es-BO')}</p> : null}<p className="text-muted-foreground">{r.confirmed_attendee_count}{r.capacity !== null ? ` / ${r.capacity}` : ''} personas confirmadas</p></div>{actions ? <div className="flex gap-2"><button className="rounded bg-brand px-3 py-2 text-brand-foreground" onClick={() => router.post(`/partner/${r.partner_slug}/reservas/${r.public_id}/confirmed`)}>Confirmar</button><button className="rounded border px-3 py-2" onClick={() => router.post(`/partner/${r.partner_slug}/reservas/${r.public_id}/rejected`)}>Rechazar</button></div> : null}</div>)}</div> : <p className="mt-3 text-sm text-muted-foreground">Sin reservas.</p>}</section>;
}
export default function PartnerReservations({ partner, pending, upcomingConfirmed, history }: any) {
 return <PartnerLayout title="Reservas" partner={partner}><List title="Pendientes" items={pending} actions/><List title="Confirmadas próximas" items={upcomingConfirmed}/><List title="Historial" items={history}/></PartnerLayout>;
}
