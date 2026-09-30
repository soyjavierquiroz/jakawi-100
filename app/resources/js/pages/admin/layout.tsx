import { Head, Link } from '@inertiajs/react';
export default function AdminLayout({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <main className="min-h-screen bg-background p-5 text-foreground">
            <Head title={title} />
            <nav className="flex flex-wrap gap-4 text-sm">
                <Link href="/admin">Admin</Link>
                <Link href="/admin/review">Revisión</Link>
                <Link href="/admin/ciudades">Ciudades</Link>
                <Link href="/admin/expansion">Expansión</Link>
                <Link href="/admin/solicitudes-partner">Solicitudes Partner</Link>
                <Link href="/admin/partners">Partners</Link>
                <Link href="/admin/locations">Locations</Link>
                <Link href="/admin/benefits">Beneficios</Link>
                <Link href="/admin/experiences">Experiencias</Link>
                <Link href="/admin/memberships">Membresías</Link>
                <Link href="/admin/sales">Ventas</Link>
                <Link href="/admin/promoters">Promotores</Link>
                <Link href="/admin/affiliates">Afiliados</Link>
                <Link href="/admin/creators">Creadores</Link>
                <Link href="/admin/payouts">Pagos</Link>
                <Link href="/admin/reward-rules">Reglas de recompensa</Link>
                <Link href="/admin/campaigns">Campañas</Link>
                <Link href="/admin/redemptions">Canjes</Link>
                <Link href="/admin/attribution">Atribución</Link>
                <Link href="/admin/adjustments">Ajustes</Link>
            </nav>
            <h1 className="mt-8 text-3xl font-semibold">{title}</h1>
            <div className="mt-6">{children}</div>
        </main>
    );
}
