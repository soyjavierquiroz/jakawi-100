import { Head, Link, router, usePage } from '@inertiajs/react';
import JourneyLayout from '@/layouts/journey-layout';

type ReturnIntent = { id: number; journey: 'EXPERIENCE' | 'BENEFIT' | 'UNLOCK' };
export default function Membresia({ price, durationDays, membership, requestStatus, returnIntent }: {
    price: number; durationDays: number;
    membership: { ends_at: string; confirmed_savings: string } | null;
    requestStatus: string | null; returnIntent: ReturnIntent | null;
}) {
    const { auth } = usePage().props;
    const label = returnIntent?.journey === 'EXPERIENCE' ? 'EXPERIENCIA' : returnIntent?.journey === 'BENEFIT' ? 'BENEFICIO' : 'DESBLOQUEO';
    return <JourneyLayout><Head title="Membresía JAKAWI" />
        <main className="mx-auto min-h-screen max-w-5xl px-4 pb-28 pt-12 text-foreground sm:px-8">
            <p className="discovery-eyebrow text-brand">MEMBRESÍA JAKAWI</p>
            <h1 className="mt-4 max-w-3xl text-4xl font-extrabold tracking-tight sm:text-6xl">Vive más con JAKAWI</h1>
            <p className="mt-5 max-w-2xl text-lg text-muted-foreground">Accede a beneficios disponibles para miembros y a experiencias que requieren membresía. Algunos desbloqueos también pueden requerirla según su configuración.</p>
            <section className="mt-9 max-w-2xl rounded-[var(--radius-card)] border border-border bg-surface-elevated p-6 sm:p-8">
                <p className="discovery-eyebrow text-brand">MEMBRESÍA ANUAL</p>
                <p className="mt-3 text-5xl font-extrabold">Bs{price}</p>
                <p className="mt-2 font-semibold">{durationDays} días de vigencia desde la activación</p>
                <ul className="mt-6 space-y-3 text-muted-foreground">
                    <li>✓ Beneficios disponibles para miembros</li>
                    <li>✓ Experiencias que requieren membresía</li>
                    <li>✓ Elegibilidad para desbloqueos cuando la configuración lo exija</li>
                    <li>✓ Acceso a Mi JAKAWI</li>
                </ul>
                {membership ? <div className="mt-8 rounded-2xl bg-brand-subtle p-4"><p className="font-extrabold">MEMBRESÍA ACTIVA</p><p className="mt-1 text-sm">Vigente hasta {new Date(membership.ends_at).toLocaleDateString('es-BO')}</p>{Number(membership.confirmed_savings) > 0 ? <p className="mt-2 text-sm">Ahorro realizado: Bs{membership.confirmed_savings}</p> : null}</div> : null}
                {requestStatus === 'REQUESTED' && !membership ? <div className="mt-8 rounded-2xl bg-brand-subtle p-4"><p className="font-extrabold">SOLICITUD RECIBIDA</p><p className="mt-2 text-sm">JAKAWI coordinará la activación y el cobro se confirmará por el canal asistido.</p></div> : null}
                <div className="mt-7 grid gap-3 sm:flex sm:flex-wrap">
                    {!auth.user ? <Link href="/membresia/registro" className="discovery-primary-action">CREAR CUENTA GRATIS</Link>
                        : membership ? <Link href="/mi-jakawi" className="discovery-primary-action">IR A MI JAKAWI</Link>
                        : requestStatus !== 'REQUESTED' ? <button type="button" onClick={() => router.post('/membresia/solicitar')} className="discovery-primary-action">SOLICITAR MEMBRESÍA</button> : null}
                    {membership && returnIntent ? <button type="button" onClick={() => router.post(`/membresia/volver/${returnIntent.id}`)} className="discovery-secondary-action">VOLVER A {label}</button> : null}
                </div>
            </section>
        </main>
    </JourneyLayout>;
}
