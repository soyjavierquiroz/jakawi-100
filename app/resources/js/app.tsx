import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import PublicModeLayout from '@/layouts/public-mode-layout';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import PartnerLayout from '@/layouts/partner-layout';
import SettingsLayout from '@/layouts/settings/layout';
import ConsumerLayout from '@/layouts/consumer-layout';
import MarketingLayout from '@/layouts/marketing-layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => title === 'JAKAWI — Vive más tu ciudad | Próximamente en Cochabamba'
        ? title
        : (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        const resolveLayout = () => {
        switch (true) {
            case name === 'coming-soon':
                return null;
            case name === 'partners/index' || name === 'programs/index':
                return MarketingLayout;
            case name === 'landing-presentations/unlock' ||
                name === 'landing-presentations/experience' ||
                name === 'landing-presentations/challenge' ||
                name === 'landing-presentations/benefit':
                return MarketingLayout;
            case name === 'landing-presentations/conversion':
            case name === 'social-challenges/index':
                return null;
            case [
                'welcome',
                'explore',
                'cities/show',
                'benefits/index',
                'benefits/show',
                'social-challenges/show',
                'experiences/index',
                'experiences/show',
                'partners/show',
                'locations/show',
                'unlocks/show',
                'mi-jakawi',
                'membresia',
                'member-profile',
                'redemptions/show',
            ].includes(name):
                return ConsumerLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('partner/') ||
                ['redemptions/validate', 'redemptions/scan'].includes(name):
                return PartnerLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
        };
        const layout = resolveLayout();
        return [PublicModeLayout, ...(Array.isArray(layout) ? layout : layout ? [layout] : [])];
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: 'var(--brand)',
    },
});

// This will set light / dark mode on load...
initializeTheme();

if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        void navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
