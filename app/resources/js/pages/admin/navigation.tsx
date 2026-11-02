import { Link, usePage } from '@inertiajs/react';
import groups from '../../../admin-navigation.json';

type Group = { label: string; description?: string; expanded?: boolean; links: string[][]; secondary?: Group[] };

export function AdminNavigation() {
    const path = usePage().url.split('?')[0];
    const current = (href: string) => path === href || path.startsWith(`${href}/`);
    const containsCurrent = (group: Group): boolean => group.links.some(([href]) => current(href)) || !!group.secondary?.some(containsCurrent);
    const links = (items: string[][]) => items.map(([href, label]) => href === '/admin/conciliacion-membresias'
        ? <a key={href} href={href} aria-current={current(href) ? 'page' : undefined}>{label}</a>
        : <Link key={href} href={href} aria-current={current(href) ? 'page' : undefined}>{label}</Link>);
    const secondary = (group: Group) => <details key={group.label} className="admin-nav-secondary" open={group.expanded || containsCurrent(group) || undefined}>
        <summary>{group.label}</summary>
        {group.description && <p className="admin-nav-note">{group.description}</p>}
        {links(group.links)}{group.secondary?.map(secondary)}
    </details>;
    return <nav aria-label="Administración" className="admin-nav">
        <Link href="/admin" className="admin-nav-home" aria-current={path === '/admin' ? 'page' : undefined}>Inicio del admin</Link>
        {groups.map((group) => <details key={group.label} className="admin-nav-group" open={group.label !== 'Configuración avanzada' || containsCurrent(group)}>
            <summary>{group.label}</summary>
            {group.description && <p className="admin-nav-note">{group.description}</p>}
            {links(group.links)}{group.secondary.map(secondary)}
        </details>)}
        <section className="admin-nav-group" aria-label="Comisiones"><h2 className="px-3 py-2 text-sm font-bold">Comisiones</h2><a className="admin-club-link" href="https://jakawi.club/admin/comisiones" target="_blank" rel="noopener noreferrer">Administración de comisiones — CLUB ↗<small>Enlace externo · acceso propio de CLUB</small></a></section>
    </nav>;
}
