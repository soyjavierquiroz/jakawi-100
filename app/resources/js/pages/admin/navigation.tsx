import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, ExternalLink, Megaphone, Settings, ShoppingBag, Store } from 'lucide-react';
import { useEffect, useState } from 'react';
import groups from '../../../admin-navigation.json';

const icons = [Store, ShoppingBag, Megaphone, Settings];
const stateKey = 'jakawi-admin-groups-v1';

export function AdminNavigation({ onNavigate }: { onNavigate?: () => void }) {
    const page = usePage();
    const path = page.url.split(/[?#]/)[0].replace(/\/$/, '') || '/';
    const active = groups.flatMap(group => group.links).map(([href]) => href)
        .filter(href => path === href || path.startsWith(`${href}/`)).sort((a, b) => b.length - a.length)[0];
    const [closed, setClosed] = useState<string[]>(() => {
        try {
            const stored = JSON.parse(sessionStorage.getItem(stateKey) || 'null');
            if (Array.isArray(stored)) return stored.filter((id): id is string => typeof id === 'string');
        } catch { /* Use the compact initial menu when storage is unavailable. */ }
        return groups.map(group => group.id);
    });
    // A navigation always reveals its destination, including after a full Blade visit.
    useEffect(() => {
        const group = groups.find(group => group.links.some(([href]) => href === active));
        if (group) setClosed(previous => previous.filter(id => id !== group.id));
    }, [path, active]);
    const toggle = (id: string) => setClosed(previous => {
        const next = previous.includes(id) ? previous.filter(value => value !== id) : [...previous, id];
        try { sessionStorage.setItem(stateKey, JSON.stringify(next)); } catch { /* Storage may be disabled. */ }
        return next;
    });
    return <nav aria-label="Administración" className="admin-nav">
        <Link href="/admin" onClick={onNavigate} aria-current={path === '/admin' ? 'page' : undefined}>Inicio del admin</Link>
        {page.props.canSupport === true && <Link href="/admin/soporte" onClick={onNavigate} aria-current={path === '/admin/soporte' || path.startsWith('/admin/soporte/') ? 'page' : undefined}>Usuarios · Ver como usuario</Link>}
        {groups.map((group, index) => {
            const Icon = icons[index];
            const isActive = group.links.some(([href]) => href === active);
            const open = isActive || !closed.includes(group.id);
            return <section key={group.id} className="admin-nav-group">
                <h2><button type="button" aria-expanded={open} aria-disabled={isActive || undefined} title={isActive ? 'La sección de la página actual permanece abierta' : undefined} aria-controls={`admin-group-${group.id}`} onClick={() => { if (!isActive) toggle(group.id); }}><Icon aria-hidden="true" size={17} /><span>{group.label}</span><ChevronDown aria-hidden="true" size={16} className="admin-nav-chevron" /></button></h2>
                <div id={`admin-group-${group.id}`} hidden={!open}>
                    {group.links.map(([href, label]) => href === '/admin/conciliacion-membresias'
                        ? <a key={href} href={href} aria-current={active === href ? 'page' : undefined}>{label}</a>
                        : <Link key={href} href={href} onClick={onNavigate} aria-current={active === href ? 'page' : undefined}>{label}</Link>)}
                    {group.id === 'growth' && <a href="https://jakawi.club/admin/comisiones" target="_blank" rel="noopener noreferrer">Comisiones — CLUB <ExternalLink aria-hidden="true" size={14} /><small>Enlace externo · acceso propio de CLUB</small></a>}
                </div>
            </section>;
        })}
    </nav>;
}
