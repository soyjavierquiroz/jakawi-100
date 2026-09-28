import { useEffect, useMemo, useState } from 'react';

export const PWA_INSTALL_DISMISSED_KEY = 'jakawi:pwa-install-dismissed';

function hasDismissedInstallPrompt() {
    if (typeof window === 'undefined') {
        return false;
    }

    try {
        return window.localStorage.getItem(PWA_INSTALL_DISMISSED_KEY) === 'true';
    } catch {
        return false;
    }
}

function isIosDevice() {
    if (typeof window === 'undefined') {
        return false;
    }

    return /iphone|ipad|ipod/i.test(window.navigator.userAgent);
}

export function isPwaStandalone() {
    if (typeof window === 'undefined') {
        return false;
    }

    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone === true
    );
}

export function usePwaInstall() {
    const [installEvent, setInstallEvent] =
        useState<BeforeInstallPromptEvent | null>(null);
    const [standalone, setStandalone] = useState(isPwaStandalone);
    const [installed, setInstalled] = useState(false);
    const [showIosHelp, setShowIosHelp] = useState(false);
    const [dismissed, setDismissed] = useState(hasDismissedInstallPrompt);

    useEffect(() => {
        const media = window.matchMedia('(display-mode: standalone)');
        const syncStandalone = () => setStandalone(isPwaStandalone());

        const handleBeforeInstallPrompt = (event: Event) => {
            event.preventDefault();
            setInstallEvent(event as BeforeInstallPromptEvent);
        };

        const handleAppInstalled = () => {
            setInstalled(true);
            setInstallEvent(null);
        };

        media.addEventListener('change', syncStandalone);
        window.addEventListener(
            'beforeinstallprompt',
            handleBeforeInstallPrompt,
        );
        window.addEventListener('appinstalled', handleAppInstalled);

        return () => {
            media.removeEventListener('change', syncStandalone);
            window.removeEventListener(
                'beforeinstallprompt',
                handleBeforeInstallPrompt,
            );
            window.removeEventListener('appinstalled', handleAppInstalled);
        };
    }, []);

    const canInstall = Boolean(installEvent) && !standalone && !installed;
    const canShowIosHelp =
        isIosDevice() && !standalone && !installed && !canInstall;

    return useMemo(
        () => ({
            canInstall,
            canShowIosHelp,
            showIosHelp,
            setShowIosHelp,
            standalone,
            dismissed,
            dismiss() {
                setDismissed(true);

                try {
                    window.localStorage.setItem(PWA_INSTALL_DISMISSED_KEY, 'true');
                } catch {
                    // The prompt still closes when storage is unavailable.
                }
            },
            async install() {
                if (!installEvent) {
                    return;
                }

                await installEvent.prompt();
                await installEvent.userChoice;
                setInstallEvent(null);
            },
        }),
        [canInstall, canShowIosHelp, dismissed, installEvent, showIosHelp, standalone],
    );
}
