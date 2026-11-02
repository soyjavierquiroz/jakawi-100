import { ListFilters } from '@/components/list-filters';
import { ListPagination, type ListPage } from '@/components/list-pagination';
import { Head, Link } from '@inertiajs/react';
import AdminLayout from '../layout';

export type MembershipRow = { id: number; holder: string; email: string | null; identity: string; status: string; starts_at: string | null; ends_at: string | null; origin: string; confirmed_amount: string | null; payments: { id: string; label: string; reference: string; confirmed: boolean; amount: string | null; href: string }[] };
export const statuses: Record<string, string> = { active: 'Activa', expired: 'Vencida', cancelled: 'Cancelada', scheduled: 'Programada', unknown: 'Estado por revisar' };
export const date = (value: string | null) => value ? new Intl.DateTimeFormat('es-BO', { dateStyle: 'medium', timeZone: 'America/La_Paz' }).format(new Date(value)) : 'Sin fecha registrada';
export const money = (value: string | null) => value !== null ? `Bs ${new Intl.NumberFormat('es-BO', { minimumFractionDigits: 2 }).format(Number(value))}` : 'Sin cobro confirmado asociado';
export const action = 'inline-flex min-h-11 items-center justify-center rounded-xl border border-border bg-surface px-4 py-2 text-sm font-semibold hover:bg-muted';
export function MembershipNav() { return <nav aria-label="Gestión de membresías" className="flex flex-wrap gap-2"><a className={action} href="/admin/conciliacion-membresias">Pagos y conciliación</a><Link className={action} href="/admin/sales">Ventas en efectivo</Link></nav>; }

export default function Index({ memberships, summary }: { memberships: ListPage & { data: MembershipRow[] }; summary: Record<string, number> }) {
    return <AdminLayout title="Membresías"><Head title="Membresías" /><div className="grid min-w-0 gap-5">
        <div><p className="mt-2 text-sm text-muted-foreground">Titulares, vigencia y cobros asociados. Las nuevas membresías se activan al conciliar un pago bancario o registrar una venta en efectivo.</p></div>
        <MembershipNav />
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">{[['total', 'Registradas'], ['active', 'Activas'], ['expired', 'Vencidas'], ['cancelled', 'Canceladas'], ['scheduled', 'Programadas']].map(([key, label]) => <div className="rounded-2xl border border-border bg-surface p-4" key={key}><p className="text-sm text-muted-foreground">{label}</p><p className="mt-1 text-3xl font-semibold">{summary[key]}</p></div>)}</div>
        <section className="min-w-0 rounded-2xl border border-border bg-surface p-4 sm:p-5"><h2 className="font-semibold">Registro de membresías</h2><ListFilters fields={[{ name: 'status', label: 'Estado', options: Object.entries(statuses).filter(([v]) => v !== 'unknown').map(([value, label]) => ({ value, label })) }, { name: 'origin', label: 'Origen', options: [{ value: 'bank', label: 'Pago bancario · COM / CLUB' }, { value: 'cash', label: 'Venta en efectivo' }, { value: 'historical', label: 'Histórico / excepcional' }] }]} />
            <p className="mb-3 text-xs text-muted-foreground">Búsqueda por titular, correo o referencia del registro. Los resúmenes incluyen todo el historial.</p>
            <div className="divide-y divide-border">{memberships.data.map(m => <article key={m.id} className="grid min-w-0 gap-4 py-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-center"><div className="min-w-0"><Link className="font-semibold text-brand underline-offset-4 hover:underline" href={`/admin/memberships/${m.id}`}>{m.holder}</Link><p className="mt-1 break-words text-xs text-muted-foreground">{m.email}</p><p className="mt-1 text-xs text-muted-foreground">#{m.id} · {m.identity}</p><span className={`mt-2 inline-flex rounded-full px-3 py-1 text-xs font-semibold ${m.status === 'active' ? 'admin-status-success' : 'admin-status-neutral'}`}>{statuses[m.status]}</span></div><dl className="grid grid-cols-2 gap-3 text-sm lg:grid-cols-1"><div><dt className="text-xs text-muted-foreground">Inicio</dt><dd>{date(m.starts_at)}</dd></div><div><dt className="text-xs text-muted-foreground">Vencimiento</dt><dd>{date(m.ends_at)}</dd></div></dl><div className="text-sm"><p>{m.origin}</p><p className="mt-1 font-semibold">{money(m.confirmed_amount)}</p></div><Link className={action} href={`/admin/memberships/${m.id}`}>Ver membresía</Link></article>)}</div>
            {!memberships.data.length && <p className="py-8 text-center text-muted-foreground">No hay membresías que coincidan con los filtros.</p>}
            <ListPagination page={memberships} />
        </section>
    </div></AdminLayout>;
}
