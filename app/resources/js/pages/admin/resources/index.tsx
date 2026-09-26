import { Link, router } from '@inertiajs/react';
import AdminLayout from '../layout';
export default function ResourceIndex({ title, resource, items = [] }: any) {
    return (
        <AdminLayout title={title}>
            <Link
                className="rounded bg-brand px-4 py-2 text-brand-foreground"
                href={`/admin/${resource}/create`}
            >
                Crear
            </Link>
            <div className="mt-5 divide-y rounded border border-border">
                {items.map((item: any) => (
                    <div className="flex justify-between p-4" key={item.id}>
                        <span>
                            {item.name || item.title}{' '}
                            <small className="text-muted-foreground">
                                {item.status}
                            </small>
                        </span>
                        {resource === 'partners' ? <span className="text-sm text-muted-foreground">{item.referral_code ? `/r/${item.referral_code}` : 'Sin código'} · {item.acquisition?.clicks ?? 0} clics · {item.acquisition?.registrations ?? 0} registros · {item.acquisition?.purchases ?? 0} compras · Bs {item.acquisition?.revenue ?? '0.00'} <button className="ml-2 underline" onClick={() => router.post(`/admin/partners/${item.id}/referral-code`, { regenerate: Boolean(item.referral_code) })}>{item.referral_code ? 'Regenerar' : 'Generar'}</button></span> : null}
                        <Link href={`/admin/${resource}/${item.id}/edit`}>
                            Editar
                        </Link>
                    </div>
                ))}
                {!items.length ? (
                    <p className="p-4 text-muted-foreground">
                        Aún no hay registros.
                    </p>
                ) : null}
            </div>
        </AdminLayout>
    );
}
