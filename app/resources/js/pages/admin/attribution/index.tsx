import { Form } from '@inertiajs/react';
import AdminLayout from '../layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export default function Attribution({ users, windowDays, filters }: any) {
    return <AdminLayout title="Atribución">
        <Form method="get" action="/admin/attribution" className="mb-6 flex gap-2"><Input name="q" defaultValue={filters.q} placeholder="Buscar usuario" /><Button>Buscar</Button></Form>
        <Form method="put" action="/admin/attribution/settings" className="mb-8 flex max-w-md items-end gap-2"><label className="grid gap-1 text-sm">Ventana de atribución (días)<Input type="number" min="1" name="attribution_window_days" defaultValue={windowDays} /></label><Button>Guardar</Button></Form>
        <div className="space-y-5">{users.data.map((user: any) => { const relationship = user.referral_relationships.find((item: any) => item.status === 'active'); return <section key={user.id} className="rounded-md border border-border p-4"><h2 className="font-semibold">{user.name} · {user.email}</h2><p className="text-sm text-muted-foreground">Registrado: {user.created_at}</p><p className="mt-2 text-sm">Primer referente: {relationship?.referrer?.referral_code ?? 'ninguno'}</p><h3 className="mt-3 font-medium">Historial de contactos</h3><ul className="text-sm">{user.attribution_touches.map((touch: any) => <li key={touch.id}>{touch.occurred_at} · {touch.referral_code ?? 'directo'} · {touch.utm_source ?? '—'} / {touch.utm_medium ?? '—'} / {touch.utm_campaign ?? '—'} · {touch.landing_page}</li>)}</ul><h3 className="mt-3 font-medium">Conversiones</h3><ul className="text-sm">{user.conversions.map((conversion: any) => <li key={conversion.id}>{conversion.type} · {conversion.status} · {conversion.gross_amount} {conversion.currency}</li>)}</ul></section>; })}</div>
    </AdminLayout>;
}
