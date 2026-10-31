import { Head, Link } from '@inertiajs/react';

const groups = [
    ['Catálogo y experiencias', [
        ['/admin/partners', 'Partners'], ['/admin/locations', 'Locations'],
        ['/admin/benefits', 'Beneficios'], ['/admin/experiences', 'Experiencias'],
        ['/admin/retos', 'Retos sociales'], ['/admin/redemptions', 'Canjes'],
    ]],
    ['Operación comercial', [
        ['/admin/memberships', 'Membresías'], ['/admin/sales', 'Ventas y cobros'],
        ['/admin/solicitudes-membresia', 'Solicitudes de membresía'],
        ['/admin/promoters', 'Operadores de ventas'], ['/admin/payouts', 'Pagos a Partners'],
        ['/admin/reward-rules', 'Reglas JP y Partners'], ['/admin/adjustments', 'Ajustes JP y Partners'],
    ]],
    ['Marketing y administración', [
        ['/admin/landings', 'Marketing Landings'], ['/admin/campaigns', 'Campañas operativas'],
        ['/admin/attribution', 'Atribución comercial'], ['/admin/review', 'Revisión'],
        ['/admin/ciudades', 'Ciudades'], ['/admin/expansion', 'Expansión'],
        ['/admin/solicitudes-partner', 'Solicitudes Partner'], ['/admin/settings', 'Ajustes'],
    ]],
] as const;

export default function AdminLayout({ title, children }: { title: string; children: React.ReactNode }) {
    return <main className="min-h-screen bg-background p-5 text-foreground">
        <Head title={title} />
        <nav aria-label="Administración" className="space-y-3 text-sm">
            <Link href="/admin" className="font-semibold">Admin</Link>
            {groups.map(([name, links]) => <div key={name} className="flex flex-wrap gap-x-4 gap-y-2">
                <span className="w-full font-semibold text-muted-foreground">{name}</span>
                {links.map(([href, label]) => <Link key={href} href={href}>{label}</Link>)}
            </div>)}
            <a className="inline-block font-semibold" href="https://jakawi.club/afiliados" target="_blank" rel="noopener noreferrer">AFILIADOS Y COMISIONES — CLUB</a>
        </nav>
        <a href="/admin/conciliacion-membresias" className="mt-4 inline-block font-semibold text-brand">Conciliación de membresías</a>
        <h1 className="mt-8 text-3xl font-semibold">{title}</h1>
        <div className="mt-6">{children}</div>
    </main>;
}
