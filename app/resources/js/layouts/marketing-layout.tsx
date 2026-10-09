import { MetaBrowserEvents } from '@/components/meta-browser-events';
import { Link } from '@inertiajs/react';
import { PublicFooter } from '@/components/public-footer';
import ThemeToggle from '@/components/theme-toggle';

export default function MarketingLayout({ children }: { children: React.ReactNode }) {
    return <div className="min-h-screen bg-background pb-[var(--pwa-install-clearance,0px)] font-discovery text-foreground">
        <MetaBrowserEvents />
        <header className="border-b border-border bg-background">
            <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <Link href="/" aria-label="Ir al inicio de JAKAWI" className="focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand"><img src="/jakawi-logo.svg" alt="" className="h-9 w-auto" /></Link>
                <div className="flex items-center gap-3 sm:gap-6"><Link href="/explorar" className="text-sm font-semibold text-muted-foreground hover:text-foreground">Explorar</Link><ThemeToggle /></div>
            </div>
        </header>
        {children}
        <PublicFooter />
    </div>;
}
