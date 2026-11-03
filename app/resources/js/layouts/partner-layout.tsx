import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';

type Partner = { name: string; slug: string };

/** The root shell for every partner-facing Inertia page. */
export default function PartnerLayout({ children }: PropsWithChildren) {
    const { partner, partnerAccess, supportMode, flash, errors } = usePage<{ flash?: { success?: string; error?: string }; errors?: Record<string, string>; supportMode?: { mode: string }; partner?: Partner; partnerAccess?: { can_manage: boolean; can_operate: boolean; can_view_economics: boolean } }>().props;

    return (
        <main className="min-h-screen bg-background p-5 text-foreground">
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-5 md:flex-row md:gap-8">
                <aside className="shrink-0 border-border text-sm md:w-52 md:border-r md:pr-5">
                    <p className="font-semibold">JAKAWI Partner</p>
                    {partner ? (
                        <>
                            <p className="mt-3 font-medium">{partner.name}</p>
                            <p className="mt-1 text-xs text-muted-foreground">Portal Partner</p>
                            <nav className="mt-6 flex flex-wrap gap-3 md:flex-col">
                                <Link href={`/partner/${partner.slug}`}>Inicio</Link>
                                {partnerAccess?.can_manage && <Link href={`/partner/${partner.slug}/perfil`}>Perfil y sedes</Link>}
                                {partnerAccess?.can_manage && supportMode?.mode !== 'manage' && <Link href={`/partner/${partner.slug}/rendimiento`}>Rendimiento</Link>}
                                <Link href={`/partner/${partner.slug}/promociones`}>Promociones</Link>
                                <Link href={`/partner/${partner.slug}/experiencias`}>Experiencias</Link>
                                <Link href={`/partner/${partner.slug}/retos`}>Retos</Link>
                                <Link href={`/partner/${partner.slug}/desbloqueos`}>Desbloqueos</Link>
                                <Link href={`/partner/${partner.slug}/reservas`}>Reservas</Link>
                                {partnerAccess?.can_operate && <Link href={`/partner/${partner.slug}/validar`}>Validar</Link>}
                                {partnerAccess?.can_operate && <Link href={`/partner/${partner.slug}/asistencias`}>Registrar asistencia</Link>}
                                {partnerAccess?.can_view_economics && <a href="https://jakawi.club/afiliados">Mis referidos y comisiones</a>}
                                {!supportMode && <Link href="/partner">Cambiar partner</Link>}
                                {!supportMode && <Link href="/logout" method="post" as="button" className="text-left">Cerrar sesión</Link>}
                            </nav>
                        </>
                    ) : (
                        <nav className="mt-6 flex flex-wrap gap-3 md:flex-col">
                            {!supportMode && <Link href="/logout" method="post" as="button" className="text-left">Cerrar sesión</Link>}
                        </nav>
                    )}
                </aside>
                <div className="min-w-0 flex-1">{flash?.success && <p role="status" className="mb-4 rounded border border-green-600 p-3">{flash.success}</p>}{flash?.error && <p role="alert" className="mb-4 rounded border border-red-600 p-3">{flash.error}</p>}{errors && Object.keys(errors).length > 0 && <div role="alert" className="mb-4 rounded border border-red-600 p-3">{Object.values(errors).map((error, i) => <p key={i}>{error}</p>)}</div>}{children}</div>
            </div>
        </main>
    );
}
