import { Link } from '@inertiajs/react';
import MobileBottomNav from '@/components/mobile-bottom-nav';
import { PublicFooter } from '@/components/public-footer';
import ThemeToggle from '@/components/theme-toggle';

/** Consumer pages never inherit staff or partner navigation. */
export default function ConsumerLayout({ children }: { children: React.ReactNode }) {
    return <div className="min-h-screen bg-background pb-[var(--pwa-install-clearance,0px)] text-foreground">
        <header className="sticky top-0 z-30 hidden border-b border-border/70 bg-background/90 backdrop-blur lg:block">
            <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-8">
                <Link
                    href="/"
                    aria-label="Ir al inicio de JAKAWI"
                    className="inline-flex focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand"
                >
                    <img
                        src="/jakawi-logo.svg"
                        alt=""
                        className="h-9 w-auto"
                    />
                </Link>
                <nav aria-label="Navegación principal" className="flex items-center gap-6 text-sm font-semibold text-muted-foreground">
                    <Link href="/explorar" className="transition hover:text-foreground">Explorar</Link>
                    <Link href="/mi-jakawi" className="transition hover:text-foreground">Mi JAKAWI</Link>
                    <Link href="/perfil" className="transition hover:text-foreground">Perfil</Link>
                    <ThemeToggle />
                </nav>
            </div>
        </header>
        <header className="sticky top-0 z-30 flex h-14 items-center border-b border-border/70 bg-background/90 px-4 backdrop-blur lg:hidden">
            <Link
                href="/"
                aria-label="Ir al inicio de JAKAWI"
                className="inline-flex focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand"
            >
                <img
                    src="/jakawi-mark.svg"
                    alt=""
                    className="size-8"
                />
            </Link>
        </header>
        {children}
        <PublicFooter />
        <MobileBottomNav />
    </div>;
}
