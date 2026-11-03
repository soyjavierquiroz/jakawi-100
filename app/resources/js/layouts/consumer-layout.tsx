import { DeliveryLayout } from '@/components/marketing/delivery';
import { Link, usePage } from '@inertiajs/react';
import MobileBottomNav from '@/components/mobile-bottom-nav';
import { CommunityFloatingBubble } from '@/components/community-floating-bubble';
import type { CityCommunityData } from '@/components/city-community-card';
import { personalLinkClass, personalSection } from '@/lib/personal-navigation';
import ThemeToggle from '@/components/theme-toggle';

/** Consumer pages never inherit staff or partner navigation. */
export default function ConsumerLayout({ children }: { children: React.ReactNode }) {
    const { component, url, props } = usePage<{ communityBubble: CityCommunityData | null }>();
    const activeSection = personalSection(url);
    if (component === 'redemptions/show') return <DeliveryLayout>{children}</DeliveryLayout>;
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
                <nav aria-label="Navegación principal" className="flex items-center gap-6 text-sm">
                    <Link href="/explorar" aria-current={activeSection === 'explore' ? 'page' : undefined} className={personalLinkClass(activeSection === 'explore')}>Explorar</Link>
                    <Link href="/mi-jakawi" aria-current={activeSection === 'account' ? 'page' : undefined} className={personalLinkClass(activeSection === 'account')}>Mi JAKAWI</Link>
                    <Link href="/perfil" aria-current={activeSection === 'profile' ? 'page' : undefined} className={personalLinkClass(activeSection === 'profile')}>Perfil</Link>
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
        <MobileBottomNav />
        <CommunityFloatingBubble key={`${url}-${props.communityBubble?.city_slug}`} community={props.communityBubble} />
    </div>;
}
