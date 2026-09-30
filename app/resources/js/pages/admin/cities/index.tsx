import { Link } from '@inertiajs/react';
import AdminLayout from '../layout';

export default function CityIndex({ cities }: { cities: Array<any> }) {
    return <AdminLayout title="Ciudades">
        <Link className="rounded bg-brand px-4 py-2 font-bold text-brand-foreground" href="/admin/ciudades/crear">Crear ciudad</Link>
        <div className="mt-6 divide-y rounded border border-border">
            {cities.map((city) => <div className="flex items-center justify-between gap-4 p-4" key={city.id}>
                <div><p className="font-semibold">{city.name}</p><p className="text-sm text-muted-foreground">{city.status} · {city.country_code}{city.region ? ` · ${city.region}` : ''} · Prioridad {city.priority}</p></div>
                <Link className="underline" href={`/admin/ciudades/${city.slug}`}>Ver</Link>
            </div>)}
            {!cities.length && <p className="p-4 text-muted-foreground">Aún no hay ciudades.</p>}
        </div>
    </AdminLayout>;
}
