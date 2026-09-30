import { Link, router } from '@inertiajs/react';
import AdminLayout from '../layout';

type City = Record<string, string | number | null>;

export default function ExpansionIndex({ cities, filters, statuses }: { cities: City[]; filters: { status: string | null }; statuses: string[] }) {
    return <AdminLayout title="Expansión"><div className="space-y-4">
        <label className="grid w-fit gap-1 text-sm">Estado<select value={filters.status ?? ''} onChange={(event) => router.get('/admin/expansion', event.target.value ? { status: event.target.value } : {}, { preserveState: true, replace: true })}><option value="">Todos</option>{statuses.map((status) => <option key={status} value={status}>{status}</option>)}</select></label>
        <div className="overflow-x-auto rounded border border-border"><table className="w-full text-left text-sm"><thead className="border-b border-border text-muted-foreground"><tr><th className="p-3">Ciudad</th><th className="p-3">Estado</th><th className="p-3">Interesados</th><th className="p-3">Solicitudes Partner</th><th className="p-3">Submitted</th><th className="p-3">Qualified</th><th className="p-3">Approved</th><th className="p-3">Locations</th><th className="p-3">Published Partners</th><th className="p-3">Last demand activity</th></tr></thead><tbody>{cities.map((city) => <tr className="border-b border-border last:border-0" key={city.id as number}><td className="p-3"><Link className="underline" href={`/admin/ciudades/${city.slug}`}>{city.name}</Link></td><td className="p-3">{city.status}</td><td className="p-3">{city.interest_total}</td><td className="p-3">{city.application_total}</td><td className="p-3">{city.application_submitted}</td><td className="p-3">{city.application_qualified}</td><td className="p-3">{city.application_approved}</td><td className="p-3">{city.location_total}</td><td className="p-3">{city.published_partner_total}</td><td className="p-3">{city.interest_latest_activity ?? '—'}</td></tr>)}</tbody></table></div>
    </div></AdminLayout>;
}
