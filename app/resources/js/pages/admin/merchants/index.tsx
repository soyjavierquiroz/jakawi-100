import { Head, Link } from '@inertiajs/react';
import AdminLayout from '../layout';

type Merchant = {
    id: number;
    name: string;
    category?: string | null;
    city?: string | null;
    is_active: boolean;
    is_featured: boolean;
    sort_order: number;
    benefits_count: number;
};

export default function MerchantsIndex({ merchants }: { merchants: Merchant[] }) {
    return (
        <AdminLayout>
            <Head title="Comercios" />
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h1 className="text-3xl font-semibold">Comercios</h1>
                <Link className="inline-flex min-h-11 items-center justify-center rounded-md bg-brand px-4 text-sm font-semibold text-brand-foreground" href="/admin/merchants/create">
                    Crear comercio
                </Link>
            </div>
            <div className="overflow-hidden rounded-md border border-border bg-surface">
                {merchants.map((merchant) => (
                    <div key={merchant.id} className="flex flex-col gap-3 border-b border-border p-4 last:border-b-0 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="font-semibold">{merchant.name}</p>
                            <p className="text-sm text-muted-foreground">
                                {[merchant.category, merchant.city].filter(Boolean).join(' · ') || 'Sin categoría'} · {merchant.benefits_count} beneficios
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="rounded-md border border-border px-2 py-1 text-xs text-muted-foreground">
                                {merchant.is_active ? 'Activo' : 'Inactivo'}
                            </span>
                            {merchant.is_featured ? (
                                <span className="rounded-md border border-border px-2 py-1 text-xs text-muted-foreground">Featured</span>
                            ) : null}
                            <Link className="inline-flex min-h-10 items-center rounded-md border border-border px-3 text-sm font-medium hover:bg-muted" href={`/admin/merchants/${merchant.id}/edit`}>
                                Editar
                            </Link>
                        </div>
                    </div>
                ))}
            </div>
        </AdminLayout>
    );
}
