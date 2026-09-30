import { Link, useForm } from '@inertiajs/react';
import AdminLayout from '../layout';

const statuses = ['COMING_SOON', 'UNLOCKING', 'PREPARING', 'ACTIVE', 'PAUSED'];
export default function CityShow({ city }: { city: any }) {
    const form = useForm({ status: city.status });
    return <AdminLayout title={city.name}><div className="max-w-xl space-y-4 rounded border border-border p-4">
        <dl className="grid grid-cols-2 gap-3"><dt>Ciudad</dt><dd>{city.name}</dd><dt>Slug</dt><dd>{city.slug}</dd><dt>Estado</dt><dd>{city.status}</dd><dt>País</dt><dd>{city.country_code}</dd><dt>Región</dt><dd>{city.region || '—'}</dd><dt>Prioridad</dt><dd>{city.priority}</dd></dl>
        <Link className="underline" href={`/admin/ciudades/${city.slug}/editar`}>Editar ciudad</Link>
        <form className="flex flex-wrap items-end gap-2" onSubmit={(event) => { event.preventDefault(); form.post(`/admin/ciudades/${city.slug}/estado`); }}><label className="grid gap-1 text-sm">Cambiar estado<select value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>{statuses.map((status) => <option key={status} value={status}>{status}</option>)}</select></label><button className="rounded border px-3 py-2" disabled={form.processing}>Actualizar estado</button></form>
    </div></AdminLayout>;
}
