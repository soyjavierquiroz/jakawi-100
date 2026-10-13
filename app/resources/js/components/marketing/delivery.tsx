import { Link, usePage } from '@inertiajs/react';

export function AccountAccessMessage() {
    const { newAcquisitionAccount, accessEmailQueued } = usePage().props;
    if (!newAcquisitionAccount) return null;
    return (
        <aside
            className="mt-6 rounded-2xl border border-border bg-surface-muted p-5 text-sm"
            aria-label="Acceso a tu cuenta"
        >
            <h3 className="font-extrabold">TU ACCESO JAKAWI</h3>
            <p className="mt-2 text-muted-foreground">
                {accessEmailQueued
                    ? 'Te enviamos un correo para que puedas asegurar tu acceso y volver cuando quieras.'
                    : 'Puedes asegurar tu acceso y volver cuando quieras.'}
            </p>
            <a
                className="mt-2 inline-flex min-h-11 items-center underline"
                href="/forgot-password"
            >
                Asegura tu acceso
            </a>
        </aside>
    );
}

export function Delivery({
    heading,
    subject,
    children,
    instructionalMedia,
    successCue,
    accountAccess = false,
    completed = false,
}: {
    heading: string;
    subject: string;
    children: React.ReactNode;
    instructionalMedia?: React.ReactNode;
    successCue?: string;
    accountAccess?: boolean;
    completed?: boolean;
}) {
    return (
        <section aria-label="Entrega" className="space-y-4 break-words">
            <header
                data-delivery-outcome={
                    completed || successCue ? 'completed' : 'pending'
                }
                className={`rounded-2xl border p-5 ${completed || successCue ? 'border-success bg-success-surface' : 'border-border bg-surface'}`}
            >
                {successCue && (
                    <p className="mb-3 inline-flex rounded-full bg-success px-3 py-2 text-xs font-extrabold text-success-foreground">
                        ✓ {successCue}
                    </p>
                )}
                <h1 className="text-3xl leading-tight font-extrabold text-foreground">
                    {completed && !successCue ? '✓ ' : ''}
                    {heading}
                </h1>
            </header>
            <h2 className="text-2xl leading-tight font-extrabold">{subject}</h2>
            <div className="space-y-3 text-sm leading-6 whitespace-pre-line">
                {children}
            </div>
            {instructionalMedia}
            {accountAccess && <AccountAccessMessage />}
        </section>
    );
}

/** Fixed operational shell; navigation follows all fulfillment and security content. */
export function DeliveryLayout({
    children,
    delivered = true,
}: {
    children: React.ReactNode;
    delivered?: boolean;
}) {
    const { auth } = usePage().props;
    return (
        <div
            className="min-h-screen bg-background font-discovery text-foreground"
            data-marketing-theme="light-conversion"
        >
            <header className="border-b border-border px-5 py-4">
                <Link
                    href="/"
                    aria-label="Ir al inicio de JAKAWI"
                    className="inline-flex min-h-11 items-center focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand"
                >
                    <img src="/jakawi-logo.svg" alt="" className="h-8 w-auto" />
                </Link>
            </header>
            <main className="mx-auto max-w-3xl px-5 pt-6 pb-12 sm:px-8 sm:pt-10">
                {children}
                {delivered && <AccountAccessMessage />}
                {delivered && auth.user && (
                    <nav
                        aria-label="Continuar con JAKAWI"
                        className="mt-10 border-t border-border pt-6"
                    >
                        <h2 className="text-xl font-extrabold">
                            SIGUE VIVIENDO TU CIUDAD
                        </h2>
                        <div className="mt-4 grid gap-3 sm:grid-cols-2">
                            <Link
                                href="/mi-jakawi"
                                className="funnel-primary-action flex min-h-12 items-center justify-center rounded-xl px-4 text-sm font-extrabold text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand"
                            >
                                IR A MI JAKAWI
                            </Link>
                            <Link
                                href="/explorar"
                                className="flex min-h-12 items-center justify-center rounded-xl border border-border bg-surface px-4 text-sm font-extrabold focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand"
                            >
                                EXPLORAR MÁS
                            </Link>
                        </div>
                    </nav>
                )}
            </main>
        </div>
    );
}
