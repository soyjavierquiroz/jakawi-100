import { Head, Link } from '@inertiajs/react';
import AdminLayout from '../layout';

type Benefit = {
    id: number;
    title: string;
    benefit_type?: string | null;
    estimated_savings?: string | number | null;
    is_active: boolean;
    is_featured: boolean;
    merchant: { name: string };
};

export default function BenefitsIndex({ benefits }: { benefits: Benefit[] }) {
    return (
        <AdminLayout>
            <Head title="Beneficios admin" />
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h1 className="text-3xl font-semibold">Beneficios</h1>
                <Link className="inline-flex min-h-11 items-center justify-center rounded-md bg-brand px-4 text-sm font-semibold text-brand-foreground" href="/admin/benefits/create">
                    Crear beneficio
                </Link>
            </div>
            <div className="overflow-hidden rounded-md border border-border bg-surface">
                {benefits.map((benefit) => (
                    <div key={benefit.id} className="flex flex-col gap-3 border-b border-border p-4 last:border-b-0 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="font-semibold">{benefit.title}</p>
                            <p className="text-sm text-muted-foreground">
                                {benefit.merchant.name} · {benefit.benefit_type || 'Sin tipo'}
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="rounded-md border border-border px-2 py-1 text-xs text-muted-foreground">
                                {benefit.is_active ? 'Activo' : 'Inactivo'}
                            </span>
                            {benefit.is_featured ? (
                                <span className="rounded-md border border-border px-2 py-1 text-xs text-muted-foreground">Featured</span>
                            ) : null}
                            <Link className="inline-flex min-h-10 items-center rounded-md border border-border px-3 text-sm font-medium hover:bg-muted" href={`/admin/benefits/${benefit.id}/edit`}>
                                Editar
                            </Link>
                        </div>
                    </div>
                ))}
            </div>
        </AdminLayout>
    );
}
