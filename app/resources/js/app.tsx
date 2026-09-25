import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { PwaInstall } from '@/components/pwa-install';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import PartnerLayout from '@/layouts/partner-layout';
import SettingsLayout from '@/layouts/settings/layout';
import ConsumerLayout from '@/layouts/consumer-layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case ['welcome', 'explore', 'benefits/index', 'benefits/show', 'experiences/index', 'experiences/show', 'mi-jakawi', 'member-profile', 'redemptions/show'].includes(name):
                return ConsumerLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('partner/') || ['redemptions/validate', 'redemptions/scan'].includes(name):
                return PartnerLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <PwaInstall />
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
