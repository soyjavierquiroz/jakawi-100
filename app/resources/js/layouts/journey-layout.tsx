import { Link } from '@inertiajs/react';
import { PublicFooter } from '@/components/public-footer';

/** Shared shell for future public resource journeys. */
export default function JourneyLayout({ children }: { children: React.ReactNode }) {
    return <div className="min-h-screen bg-background pb-[var(--pwa-install-clearance,0px)] font-discovery text-foreground">
        <header className="border-b border-border bg-background"><div className="mx-auto flex h-14 max-w-6xl items-center px-4 sm:px-6 lg:px-8"><Link href="/" aria-label="Ir al inicio de JAKAWI" className="focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand"><img src="/jakawi-mark.svg" alt="" className="size-8" /></Link></div></header>
        {children}
        <PublicFooter />
    </div>;
}
