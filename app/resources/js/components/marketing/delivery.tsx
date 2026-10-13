import { usePage } from '@inertiajs/react';

export function AccountAccessMessage() {
    const { newAcquisitionAccount, accessEmailQueued } = usePage().props;
    if (!newAcquisitionAccount) return null;
    return (
        <aside className="mt-6 border-t border-border pt-5 text-sm">
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
    accountAccess = true,
}: {
    heading: string;
    subject: string;
    children: React.ReactNode;
    instructionalMedia?: React.ReactNode;
    successCue?: string;
    accountAccess?: boolean;
}) {
    return (
        <section aria-label="Entrega" className="space-y-4 break-words">
            {successCue && (
                <p className="inline-flex rounded-full bg-success-surface px-3 py-2 text-xs font-extrabold text-success">
                    ✓ {successCue}
                </p>
            )}
            <h1
                className={`text-3xl leading-tight font-extrabold ${successCue ? 'text-foreground' : 'text-success'}`}
            >
                {successCue ? '' : '✓ '}
                {heading}
            </h1>
            <h2 className="text-2xl leading-tight font-extrabold">{subject}</h2>
            <div className="space-y-3 text-sm leading-6 whitespace-pre-line">
                {children}
            </div>
            {instructionalMedia}
            {accountAccess && <AccountAccessMessage />}
        </section>
    );
}
