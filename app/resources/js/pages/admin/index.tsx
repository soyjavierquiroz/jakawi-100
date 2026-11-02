import { Link } from '@inertiajs/react';
import AdminLayout from './layout';

export default function AdminIndex({ stats }: any) {
    return <AdminLayout title="Administración JAKAWI">
        <p className="admin-intro">Gestiona miembros, pagos y catálogo de JAKAWI.</p>
        <section aria-labelledby="daily-title"><h2 id="daily-title" className="admin-section-title">Miembros y pagos</h2>
            <div className="admin-shortcuts">
                <Link href="/admin/memberships"><span>Membresías</span><p>Consulta los miembros y el estado de sus membresías.</p><strong>Ver membresías →</strong></Link>
                <a href="/admin/conciliacion-membresias"><span>Pagos y conciliación</span><p>Consulta pagos, extractos y casos que requieren revisión.</p><strong>Revisar pagos →</strong></a>
            </div>
            <div className="admin-quick-catalog" aria-label="Accesos de catálogo"><Link href="/admin/partners">Partners →</Link><Link href="/admin/benefits">Beneficios →</Link><Link href="/admin/redemptions">Canjes →</Link></div>
        </section>
        <section aria-labelledby="overview-title"><h2 id="overview-title" className="admin-section-title">Vista general</h2>
            <div className="admin-stats">{[
                ['Membresías activas', stats.memberships, '/admin/memberships'],
                ['Partners', stats.partners, '/admin/partners'],
                ['Ubicaciones', stats.locations, '/admin/locations'],
                ['Beneficios', stats.benefits, '/admin/benefits'],
                ['Experiencias', stats.experiences, '/admin/experiences'],
                ['Canjes confirmados', stats.redemptions, '/admin/redemptions'],
            ].map(([name, value, href]) => <Link key={String(name)} href={String(href)}><p>{name}</p><strong>{value}</strong></Link>)}</div>
        </section>
        <p className="admin-intro">Las herramientas secundarias y la configuración avanzada están disponibles en el menú. Las comisiones de afiliados se gestionan en CLUB con su propia autorización.</p>
    </AdminLayout>;
}
