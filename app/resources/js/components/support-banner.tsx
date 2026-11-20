import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';

type Support = { id: string; label: string; expiresAt: string; mode: string; actor: string; partner: string };
export default function SupportBanner() {
    const support = usePage().props.supportMode as Support | null;
    useEffect(() => {
        if (!support) return;
        const timer = window.setTimeout(() => router.post('/soporte/salir'), Math.max(0, Date.parse(support.expiresAt) - Date.now()));
        return () => window.clearTimeout(timer);
    }, [support]);
    if (!support) return null;
    return <><div aria-hidden="true" style={{ height: 260 }} /><aside role="status" aria-label="Modo soporte" style={{ position: 'fixed', bottom: 0, left: 0, right: 0, zIndex: 2147483647, background: 'var(--action-solid)', color: 'var(--primary-foreground)', padding: '16px', boxShadow: 'var(--shadow-featured)', fontFamily: 'Montserrat, sans-serif' }}>
        <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3"><div className="min-w-0"><strong className="block break-words">{support.mode === 'manage' ? `Gestionando ${support.partner} como ${support.actor}` : `MODO SOPORTE — Estás viendo la cuenta de ${support.label}`}</strong><small>{support.mode === 'manage' ? 'Gestión editorial' : 'Sólo lectura'} · Fin {new Date(support.expiresAt).toLocaleTimeString()} · COM</small></div>{support.mode !== 'manage' && <button onClick={() => router.post('/soporte/club')} style={{ background: 'var(--primary-foreground)', color: 'var(--action-solid)', padding: '12px 16px', borderRadius: 12, fontWeight: 700 }}>Ver en CLUB</button>}<button onClick={() => router.post('/soporte/salir')} style={{ background: 'var(--primary-foreground)', color: 'var(--action-solid)', padding: '12px 16px', borderRadius: 12, fontWeight: 700 }}>{support.mode === 'manage' ? 'Terminar gestión' : 'Volver a mi administración'}</button></div>
    </aside></>;
}
