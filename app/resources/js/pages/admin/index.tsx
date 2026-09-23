import AdminLayout from './layout';
export default function AdminIndex({ stats }: any) {
    return (
        <AdminLayout title="Administración JAKAWI">
            <div className="grid gap-3 sm:grid-cols-3">
                {[
                    ['Partners', stats.partners],
                    ['Locations', stats.locations],
                    ['Beneficios', stats.benefits],
                    ['Experiencias', stats.experiences],
                    ['Membresías activas', stats.memberships],
                    ['Canjes confirmados', stats.redemptions],
                ].map(([name, value]) => (
                    <div
                        key={String(name)}
                        className="rounded-md border border-border p-4"
                    >
                        <p className="text-sm text-muted-foreground">{name}</p>
                        <p className="text-3xl font-semibold">{value}</p>
                    </div>
                ))}
            </div>
        </AdminLayout>
    );
}
