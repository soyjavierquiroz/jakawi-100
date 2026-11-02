import { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import { AdminNavigation } from './navigation';

export default function AdminLayout({ title, children }: { title: string; children: React.ReactNode }) {
    const [menuOpen, setMenuOpen] = useState(() => typeof window === 'undefined' || window.matchMedia('(min-width: 1024px)').matches);
    useEffect(() => {
        const desktop = window.matchMedia('(min-width: 1024px)');
        const update = () => setMenuOpen(desktop.matches);
        desktop.addEventListener('change', update);
        return () => desktop.removeEventListener('change', update);
    }, []);
    return <div className="admin-shell">
        <Head title={title} />
        <a href="#admin-content" className="admin-skip">Ir al contenido</a>
        <header className="admin-header"><a href="/admin" aria-label="JAKAWI · Administración"><img src="/jakawi-logo.svg" alt="JAKAWI" width="122" height="40" /></a><div className="admin-header-tools"><span>Administración COM</span><a href="/mi-jakawi">Mi JAKAWI ↗</a></div></header>
        <div className="admin-workspace">
            <aside className="admin-sidebar"><details className="admin-menu" open={menuOpen} onToggle={(event) => setMenuOpen(event.currentTarget.open)}><summary>Menú de administración</summary><AdminNavigation /></details></aside>
            <main id="admin-content" className="admin-content"><h1>{title}</h1><div className="admin-page">{children}</div></main>
        </div>
    </div>;
}
