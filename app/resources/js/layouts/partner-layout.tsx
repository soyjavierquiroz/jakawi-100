import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';

type Partner = { name: string; slug: string };

/** The root shell for every partner-facing Inertia page. */
export default function PartnerLayout({ children }: PropsWithChildren) {
    const { partner } = usePage<{ partner?: Partner }>().props;

    return (
        <main className="min-h-screen bg-background p-5 text-foreground">
            <div className="mx-auto flex w-full max-w-5xl gap-8">
                <aside className="w-52 shrink-0 border-r border-border pr-5 text-sm">
                    <p className="font-semibold">JAKAWI Partner</p>
                    {partner ? (
                        <>
                            <p className="mt-3 font-medium">{partner.name}</p>
                            <p className="mt-1 text-xs text-muted-foreground">Portal Partner</p>
                            <nav className="mt-6 flex flex-col gap-3">
                                <Link href={`/partner/${partner.slug}`}>Inicio</Link>
                                <Link href={`/partner/${partner.slug}/rendimiento`}>Rendimiento</Link>
                                <Link href={`/partner/${partner.slug}/promociones`}>Promociones</Link>
                                <Link href={`/partner/${partner.slug}/experiencias`}>Experiencias</Link>
                                <Link href={`/partner/${partner.slug}/reservas`}>Reservas</Link>
                                <Link href={`/partner/${partner.slug}/validar`}>Validar</Link>
                                <Link href={`/partner/${partner.slug}/asistencias`}>Registrar asistencia</Link>
                                <Link href="/partner">Cambiar partner</Link>
                                <Link href="/logout" method="post" as="button" className="text-left">Cerrar sesión</Link>
                            </nav>
                        </>
                    ) : (
                        <nav className="mt-6 flex flex-col gap-3">
                            <Link href="/logout" method="post" as="button" className="text-left">Cerrar sesión</Link>
                        </nav>
                    )}
                </aside>
                <div className="min-w-0 flex-1">{children}</div>
            </div>
        </main>
    );
}
