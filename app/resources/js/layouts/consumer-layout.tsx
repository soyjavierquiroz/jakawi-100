import { Link } from '@inertiajs/react';
import MobileBottomNav from '@/components/mobile-bottom-nav';
import ThemeToggle from '@/components/theme-toggle';

/** Consumer pages never inherit staff or partner navigation. */
export default function ConsumerLayout({ children }: { children: React.ReactNode }) {
    return <div className="min-h-screen bg-background text-foreground">
        <header className="sticky top-0 z-30 hidden border-b border-border/70 bg-background/90 backdrop-blur lg:block">
            <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-8">
                <Link href="/" className="text-sm font-extrabold tracking-[0.16em]">JAKAWI</Link>
                <nav aria-label="Navegación principal" className="flex items-center gap-6 text-sm font-semibold text-muted-foreground">
                    <Link href="/explorar" className="transition hover:text-foreground">Explorar</Link>
                    <Link href="/mi-jakawi" className="transition hover:text-foreground">Mi JAKAWI</Link>
                    <Link href="/perfil" className="transition hover:text-foreground">Perfil</Link>
                    <ThemeToggle />
                </nav>
            </div>
        </header>
        {children}
        <MobileBottomNav />
    </div>;
}
