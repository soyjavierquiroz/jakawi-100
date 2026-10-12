import { AcquisitionProvider } from '@/components/marketing/acquisition-context';
import { LandingAction } from '@/components/marketing/landing-action-context';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import ExperienceShow from '@/pages/experiences/show';
import UnlockShow from '@/pages/unlocks/show';
import BenefitShow from '@/pages/benefits/show';
import { useEffect, useState, type ComponentProps } from 'react';
import { MetaBrowserEvents } from '@/components/meta-browser-events';
import { Link, usePage } from '@inertiajs/react';
import { PublicFooter } from '@/components/public-footer';
export type MarketingTheme = 'dark-conversion' | 'light-conversion';

// Explicit marketing theme, independent from Product UI preference.
export function marketingThemeForPage(component: string): MarketingTheme {
    if (component === 'landing-presentations/experience')
        return 'light-conversion';
    return component === 'landing-presentations/challenge'
        ? 'dark-conversion'
        : 'light-conversion';
}

export default function MarketingLayout({
    children,
    theme,
}: {
    children: React.ReactNode;
    theme?: MarketingTheme;
}) {
    const page = usePage();
    const component = page.component;
    const configured = (
        page.props.presentation as { marketing_theme?: string } | undefined
    )?.marketing_theme;
    const persistedTheme =
        configured === 'DARK_CONVERSION'
            ? 'dark-conversion'
            : configured === 'LIGHT_CONVERSION'
              ? 'light-conversion'
              : undefined;
    const acquisition =
        component === 'landing-presentations/challenge' ||
        component === 'landing-presentations/experience' ||
        component === 'landing-presentations/benefit';
    const resumed = Boolean(page.props.acquisitionResumed);
    const [actionOpen, setActionOpen] = useState(resumed);
    const [actionVersion, setActionVersion] = useState(0);
    const beginAction = () => {
        setActionVersion((value) => value + 1);
        setActionOpen(true);
    };
    useEffect(() => {
        if (resumed) setActionOpen(true);
    }, [resumed, page.url]);
    useEffect(() => {
        if (resumed && component === 'landing-presentations/challenge') {
            document
                .getElementById('participar')
                ?.scrollIntoView({ behavior: 'smooth' });
        }
    }, [resumed, component]);
    return (
        <LandingAction.Provider value={beginAction}>
            <AcquisitionProvider>
                <div
                    data-marketing-theme={
                        theme ??
                        persistedTheme ??
                        marketingThemeForPage(component)
                    }
                    className="min-h-screen bg-background pb-[var(--pwa-install-clearance,0px)] font-discovery text-foreground"
                >
                    <MetaBrowserEvents />
                    {!acquisition && (
                        <header className="border-b border-border bg-background">
                            <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                                <>
                                    {acquisition ? (
                                        <img
                                            src="/jakawi-logo.svg"
                                            alt="JAKAWI"
                                            className="h-8 w-auto"
                                        />
                                    ) : (
                                        <Link
                                            href="/"
                                            aria-label="Ir al inicio de JAKAWI"
                                            className="focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand"
                                        >
                                            <img
                                                src="/jakawi-logo.svg"
                                                alt=""
                                                className="h-9 w-auto"
                                            />
                                        </Link>
                                    )}
                                </>
                                {!acquisition && (
                                    <div className="flex items-center gap-3 sm:gap-6">
                                        <Link
                                            href="/explorar"
                                            className="text-sm font-semibold text-muted-foreground hover:text-foreground"
                                        >
                                            Explorar
                                        </Link>
                                    </div>
                                )}
                            </div>
                        </header>
                    )}
                    {children}
                    {actionOpen &&
                        component === 'landing-presentations/benefit' && (
                            <BenefitShow
                                key={String(
                                    (page.props.presentation as { id: number })
                                        .id +
                                        ':' +
                                        actionVersion,
                                )}
                                {...(page.props as unknown as ComponentProps<
                                    typeof BenefitShow
                                >)}
                                redemptionIntent
                                continuationOnly
                            />
                        )}
                    {[
                        'landing-presentations/experience',
                        'landing-presentations/unlock',
                    ].includes(component) && (
                        <Dialog open={actionOpen} onOpenChange={setActionOpen}>
                            <DialogContent data-marketing-theme={theme ?? persistedTheme ?? marketingThemeForPage(component)} className="max-h-[90dvh] overflow-y-auto bg-background font-discovery text-foreground sm:max-w-xl">
                                <DialogTitle>
                                    Continúa con este{' '}
                                    {component.endsWith('experience')
                                        ? 'plan'
                                        : 'desbloqueo'}
                                </DialogTitle>
                                <DialogDescription>
                                    Revisa tus opciones y confirma sólo cuando
                                    estés listo.
                                </DialogDescription>
                                {component.endsWith('experience') ? (
                                    <ExperienceShow
                                        key={actionVersion}
                                        {...(page.props as unknown as ComponentProps<
                                            typeof ExperienceShow
                                        >)}
                                        continuationOnly
                                    />
                                ) : (
                                    <UnlockShow
                                        key={actionVersion}
                                        {...(page.props as unknown as ComponentProps<
                                            typeof UnlockShow
                                        >)}
                                        continuationOnly
                                    />
                                )}
                            </DialogContent>
                        </Dialog>
                    )}
                    <PublicFooter />
                </div>
            </AcquisitionProvider>
        </LandingAction.Provider>
    );
}
