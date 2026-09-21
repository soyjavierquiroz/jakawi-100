import { Head, Link } from '@inertiajs/react';
import AdminLayout from './layout';

export default function AdminIndex({
    stats,
}: {
    stats: {
        merchants: number;
        activeMerchants: number;
        benefits: number;
        activeBenefits: number;
    };
}) {
    return (
        <AdminLayout>
            <Head title="Admin" />
            <div className="space-y-2">
                <h1 className="text-3xl font-semibold">Admin</h1>
                <p className="text-sm text-muted-foreground">
                    Operación mínima de comercios y beneficios.
                </p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
                <div className="rounded-md border border-border bg-surface p-4">
                    <p className="text-sm text-muted-foreground">Comercios activos</p>
                    <p className="mt-1 text-2xl font-semibold">{stats.activeMerchants} / {stats.merchants}</p>
                    <Link className="mt-4 inline-flex min-h-10 items-center rounded-md bg-brand px-4 text-sm font-semibold text-brand-foreground" href="/admin/merchants">
                        Administrar comercios
                    </Link>
                </div>
                <div className="rounded-md border border-border bg-surface p-4">
                    <p className="text-sm text-muted-foreground">Beneficios activos</p>
                    <p className="mt-1 text-2xl font-semibold">{stats.activeBenefits} / {stats.benefits}</p>
                    <Link className="mt-4 inline-flex min-h-10 items-center rounded-md bg-brand px-4 text-sm font-semibold text-brand-foreground" href="/admin/benefits">
                        Administrar beneficios
                    </Link>
                </div>
            </div>
        </AdminLayout>
    );
}
