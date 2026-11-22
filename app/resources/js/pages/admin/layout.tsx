import ThemeToggle from '@/components/theme-toggle';
import { useEffect, useRef, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { AdminNavigation } from './navigation';

export default function AdminLayout({ title, children }: { title: string; children: React.ReactNode }) {
    const [desktop, setDesktop] = useState(() => typeof window === 'undefined' || window.matchMedia('(min-width: 1024px)').matches);
    const [menuOpen, setMenuOpen] = useState(false);
    const dialog = useRef<HTMLDialogElement>(null);
    const { url } = usePage();
    useEffect(() => {
        const media = window.matchMedia('(min-width: 1024px)');
        const update = () => { setDesktop(media.matches); setMenuOpen(false); };
        media.addEventListener('change', update);
        return () => media.removeEventListener('change', update);
    }, []);
    useEffect(() => { setMenuOpen(false); }, [url]);
    useEffect(() => {
        if (menuOpen) dialog.current?.showModal(); else dialog.current?.close();
    }, [menuOpen]);
    return <div className="admin-shell">
        <Head title={title} />
        <a href="#admin-content" className="admin-skip">Ir al contenido</a>
        <header className="admin-header"><a href="/admin" aria-label="JAKAWI · Administración"><img src="/jakawi-logo.svg" alt="JAKAWI" width="122" height="40" /></a><div className="admin-header-tools"><span>Administración COM</span><ThemeToggle /><a href="/mi-jakawi">Mi JAKAWI ↗</a></div></header>
        <div className="admin-workspace">
            <aside className="admin-sidebar">{desktop ? <AdminNavigation /> : <>
                <button type="button" className="admin-menu-trigger" aria-expanded={menuOpen} aria-controls="admin-drawer" onClick={() => setMenuOpen(true)}>Menú de administración</button>
                <dialog ref={dialog} id="admin-drawer" className="admin-drawer" aria-labelledby="admin-drawer-title" onClose={() => setMenuOpen(false)} onKeyDown={event => {
                        if (event.key !== 'Tab') return;
                        const items = Array.from(event.currentTarget.querySelectorAll<HTMLElement>('a[href], button:not([disabled]), [tabindex="0"]')).filter(item => item.getClientRects().length);
                        const first = items[0], last = items[items.length - 1];
                        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
                        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
                    }} onClick={event => { if (event.target === event.currentTarget) setMenuOpen(false); }}>
                    <div className="admin-drawer-heading"><h2 id="admin-drawer-title">Administración</h2><button type="button" onClick={() => setMenuOpen(false)} aria-label="Cerrar menú">Cerrar</button></div>
                    <AdminNavigation onNavigate={() => setMenuOpen(false)} />
                </dialog>
            </>}</aside>
            <main id="admin-content" className="admin-content"><h1>{title}</h1><div className="admin-page">{children}</div></main>
        </div>
    </div>;
}
