import { AcquisitionProvider } from '@/components/marketing/acquisition-context';
import ExperienceShow from '@/pages/experiences/show';
import UnlockShow from '@/pages/unlocks/show';
import BenefitShow from '@/pages/benefits/show';
import { type ComponentProps } from 'react';
import ChallengeShow from '@/pages/social-challenges/show';
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
    const domainContent = component.endsWith('/benefit') ? (
        <BenefitShow
            {...(page.props as unknown as ComponentProps<typeof BenefitShow>)}
            redemptionIntent
            continuationOnly
        />
    ) : component.endsWith('/experience') ? (
        <ExperienceShow
            {...(page.props as unknown as ComponentProps<
                typeof ExperienceShow
            >)}
            continuationOnly
        />
    ) : component.endsWith('/unlock') ? (
        <UnlockShow
            {...(page.props as unknown as ComponentProps<typeof UnlockShow>)}
            continuationOnly
        />
    ) : (
        <ChallengeShow
            {...(page.props as unknown as ComponentProps<typeof ChallengeShow>)}
            embedded
            landingSlug={(page.props.presentation as { slug: string })?.slug}
        />
    );
    return (
        <AcquisitionProvider domainContent={domainContent}>
            <div
                data-marketing-theme={
                    theme ?? persistedTheme ?? marketingThemeForPage(component)
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
                <PublicFooter />
            </div>
        </AcquisitionProvider>
    );
}
