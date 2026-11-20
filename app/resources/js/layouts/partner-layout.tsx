import ThemeToggle from '@/components/theme-toggle';
import {
    PublicationStatus,
    type PartnerAccess,
} from '@/components/partner/editorial';
import { Link, usePage } from '@inertiajs/react';
import { useState, type PropsWithChildren } from 'react';

type Partner = { name: string; slug: string; status?: string };
export default function PartnerLayout({ children }: PropsWithChildren) {
    const { url, props } = usePage<{
        prelaunchMode?: boolean;
        flash?: { success?: string; error?: string };
        errors?: Record<string, string>;
        supportMode?: { mode: string };
        partner?: Partner;
        partnerAccess?: PartnerAccess;
    }>();
    const {
        partner,
        partnerAccess: access,
        supportMode,
        flash,
        errors,
        prelaunchMode,
    } = props;
    const [menuOpen, setMenuOpen] = useState(false);
    const base = `/partner/${partner?.slug}`;
    const path = url.split('?')[0];
    const sections: Record<string, string> = {
        perfil: 'Perfil y sedes',
        sedes: 'Perfil y sedes',
        promociones: 'Promociones',
        experiencias: 'Experiencias',
        retos: 'Retos',
        desbloqueos: 'Desbloqueos',
        rendimiento: 'Rendimiento',
        reservas: 'Reservas',
        validar: 'Validar canje',
        asistencias: 'Registrar asistencia',
    };
    const currentSection =
        path === base
            ? 'Inicio'
            : (sections[path.slice(base.length + 1).split('/')[0]] ?? 'Portal');
    const navLink = (label: string, href: string, exact = false) => {
        const active = exact
            ? path === href
            : path === href || path.startsWith(`${href}/`);
        return (
            <Link
                key={href}
                href={href}
                onClick={() => setMenuOpen(false)}
                aria-current={active ? 'page' : undefined}
                className={`flex min-h-11 items-center rounded-xl px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-brand ${active ? 'bg-brand-subtle font-semibold text-foreground ring-1 ring-brand/40' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}
            >
                {label}
            </Link>
        );
    };
    return (
        <main className="min-h-screen bg-background text-foreground">
            <div className="mx-auto grid w-full max-w-7xl gap-6 p-4 md:grid-cols-[220px_minmax(0,1fr)] md:gap-8 md:p-8">
                <aside className="min-w-0 md:border-r md:border-border md:pr-6">
                    <p className="text-lg font-bold tracking-tight">
                        JAKAWI <span className="text-brand">Partners</span>
                    </p>
                    <div className="mt-3"><ThemeToggle /></div>
                    {partner && (
                        <button
                            type="button"
                            aria-expanded={menuOpen}
                            aria-controls="partner-menu"
                            onClick={() => setMenuOpen(!menuOpen)}
                            className="mt-4 flex min-h-11 w-full items-center justify-between rounded-xl border border-border bg-surface px-4 text-sm font-semibold md:hidden"
                        >
                            <span>Menú · {currentSection}</span>
                            <span aria-hidden="true">
                                {menuOpen ? '−' : '+'}
                            </span>
                        </button>
                    )}
                    <div
                        id="partner-menu"
                        className={
                            partner && !menuOpen ? 'hidden md:block' : 'block'
                        }
                    >
                        {partner && (
                            <nav
                                aria-label="Portal Partner"
                                className="mt-5 space-y-4"
                            >
                                <div className="grid grid-cols-2 gap-1 md:grid-cols-1">
                                    {navLink('Inicio', base, true)}
                                    {access?.can_edit_content &&
                                        navLink(
                                            'Perfil y sedes',
                                            `${base}/perfil`,
                                        )}
                                    {access?.can_edit_content &&
                                        path.includes('/sedes/') &&
                                        navLink('Editar sede', path, true)}
                                </div>
                                <div>
                                    <p className="mb-2 px-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        Contenido
                                    </p>
                                    <div className="grid grid-cols-2 gap-1 md:grid-cols-1">
                                        {navLink(
                                            'Promociones',
                                            `${base}/promociones`,
                                        )}
                                        {navLink(
                                            'Experiencias',
                                            `${base}/experiencias`,
                                        )}
                                        {access?.can_manage &&
                                            navLink('Retos', `${base}/retos`)}
                                        {access?.can_manage &&
                                            navLink(
                                                'Desbloqueos',
                                                `${base}/desbloqueos`,
                                            )}
                                    </div>
                                </div>
                                <div>
                                    <p className="mb-2 px-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        Resultados e historial
                                    </p>
                                    <div className="grid grid-cols-2 gap-1 md:grid-cols-1">
                                        {access?.can_manage &&
                                            navLink(
                                                'Rendimiento',
                                                `${base}/rendimiento`,
                                            )}
                                        {navLink(
                                            'Reservas',
                                            `${base}/reservas`,
                                        )}
                                    </div>
                                </div>
                                {access?.can_operate &&
                                    (prelaunchMode ? (
                                        <p className="px-3 text-xs leading-relaxed text-muted-foreground">
                                            Validación de canjes y registro de
                                            asistencias: deshabilitados durante
                                            el prelanzamiento.
                                        </p>
                                    ) : (
                                        <div className="grid grid-cols-2 gap-1 md:grid-cols-1">
                                            {navLink(
                                                'Validar canje',
                                                `${base}/validar`,
                                            )}
                                            {navLink(
                                                'Registrar asistencia',
                                                `${base}/asistencias`,
                                            )}
                                        </div>
                                    ))}
                                {access?.can_view_economics && (
                                    <a
                                        className="block px-3 py-2 text-sm underline"
                                        href="https://jakawi.club/afiliados"
                                    >
                                        Mis referidos y comisiones ↗
                                    </a>
                                )}
                            </nav>
                        )}
                        {!supportMode && (
                            <div className="mt-5 flex flex-wrap gap-2 border-t border-border pt-3 md:flex-col">
                                {partner &&
                                    navLink(
                                        'Cambiar establecimiento',
                                        '/partner',
                                        true,
                                    )}
                                <Link
                                    href="/logout"
                                    method="post"
                                    as="button"
                                    className="min-h-11 px-3 text-left text-sm text-muted-foreground"
                                >
                                    Cerrar sesión
                                </Link>
                            </div>
                        )}
                    </div>
                </aside>
                <div className="min-w-0">
                    {partner && (
                        <header className="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-border pb-5">
                            <div className="min-w-0">
                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Tu establecimiento
                                </p>
                                <p className="mt-1 text-xl font-semibold break-words">
                                    {partner.name}
                                </p>
                            </div>
                            {partner.status && (
                                <PublicationStatus status={partner.status} />
                            )}
                        </header>
                    )}
                    {prelaunchMode && (
                        <aside
                            role="status"
                            className="mb-6 rounded-2xl border border-brand/30 bg-brand-subtle p-4 text-sm leading-relaxed"
                        >
                            <p className="font-semibold">
                                Prelanzamiento · piloto editorial
                            </p>
                            <p className="mt-1">
                                Puedes consultar información y preparar
                                contenido para revisión. Reservas, asistencias,
                                canjes, participaciones, premios y pagos están
                                deshabilitados.
                            </p>
                            <p className="mt-2 font-medium">
                                Publicado no significa habilitado para operar.
                            </p>
                        </aside>
                    )}
                    {supportMode?.mode === 'manage' && (
                        <p className="mb-5 rounded-lg border border-border bg-surface p-4 text-sm">
                            Gestión editorial y rendimiento operativo. Las
                            cifras económicas, referidos y comisiones se
                            consultan desde soporte CLUB con sus propias
                            autorizaciones.
                        </p>
                    )}
                    {flash?.success && (
                        <p
                            role="status"
                            className="mb-4 rounded-xl border border-border bg-success-surface p-4"
                        >
                            {flash.success}
                        </p>
                    )}
                    {flash?.error && (
                        <p
                            role="alert"
                            className="mb-4 rounded-xl border border-destructive p-4"
                        >
                            {flash.error}
                        </p>
                    )}
                    {errors && Object.keys(errors).length > 0 && (
                        <div
                            role="alert"
                            className="mb-4 rounded-xl border border-destructive p-4"
                        >
                            <p className="font-semibold">
                                Revisa la información antes de continuar.
                            </p>
                            {Object.values(errors).map((error, i) => (
                                <p key={i}>{error}</p>
                            ))}
                        </div>
                    )}
                    {children}
                </div>
            </div>
        </main>
    );
}
