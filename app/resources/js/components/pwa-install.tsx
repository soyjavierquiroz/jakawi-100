import { useEffect, useRef, useState } from 'react';
import { Download, Share, X } from 'lucide-react';
import { usePwaInstall } from '@/hooks/use-pwa-install';

export function PwaInstall() {
    const pwa = usePwaInstall();
    const [hasMobileBottomNav, setHasMobileBottomNav] = useState(false);
    const panelRef = useRef<HTMLDivElement>(null);
    const isVisible = !pwa.dismissed && (pwa.canInstall || pwa.canShowIosHelp);

    useEffect(() => {
        const syncMobileBottomNav = () => {
            setHasMobileBottomNav(
                Boolean(document.querySelector('[data-mobile-bottom-nav]')),
            );
        };

        syncMobileBottomNav();

        const observer = new MutationObserver(syncMobileBottomNav);
        observer.observe(document.body, { childList: true, subtree: true });

        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        const root = document.documentElement;

        const syncClearance = () => {
            const panel = panelRef.current;

            if (!isVisible || !panel) {
                root.style.removeProperty('--pwa-install-clearance');
                return;
            }

            root.style.setProperty(
                '--pwa-install-clearance',
                `${Math.ceil(window.innerHeight - panel.getBoundingClientRect().top)}px`,
            );
        };

        syncClearance();

        const observer = new ResizeObserver(syncClearance);
        if (panelRef.current) {
            observer.observe(panelRef.current);
        }
        window.addEventListener('resize', syncClearance);

        return () => {
            observer.disconnect();
            window.removeEventListener('resize', syncClearance);
            root.style.removeProperty('--pwa-install-clearance');
        };
    }, [hasMobileBottomNav, isVisible, pwa.showIosHelp]);

    if (!isVisible) {
        return null;
    }

    return (
        <div
            ref={panelRef}
            data-pwa-install-panel
            className="fixed inset-x-4 z-50 mx-auto max-w-sm rounded-md border border-border bg-surface/95 p-3 text-foreground shadow-lg backdrop-blur"
            style={{
                bottom: hasMobileBottomNav
                    ? 'calc(max(4rem, calc(3.5rem + env(safe-area-inset-bottom))) + 1rem)'
                    : 'max(1rem, env(safe-area-inset-bottom))',
            }}
        >
            <div className="flex items-center gap-2">
                {pwa.canInstall ? (
                    <button
                        type="button"
                        onClick={() => void pwa.install()}
                        className="inline-flex min-h-11 min-w-0 flex-1 items-center justify-center gap-2 rounded-md bg-brand px-4 text-sm font-semibold text-brand-foreground transition hover:bg-brand/90"
                    >
                        <Download className="size-4 shrink-0" aria-hidden="true" />
                        Instalar JAKAWI
                    </button>
                ) : (
                    <button
                        type="button"
                        onClick={() => pwa.setShowIosHelp(!pwa.showIosHelp)}
                        className="inline-flex min-h-11 min-w-0 flex-1 items-center justify-center gap-2 rounded-md border border-border bg-background px-4 text-sm font-semibold text-foreground transition hover:bg-muted"
                    >
                        <Share className="size-4 shrink-0" aria-hidden="true" />
                        Instala JAKAWI
                    </button>
                )}
                <button
                    type="button"
                    onClick={pwa.dismiss}
                    aria-label="Cerrar"
                    className="inline-flex size-11 shrink-0 items-center justify-center rounded-md border border-border bg-background text-foreground transition hover:bg-muted"
                >
                    <X className="size-4" aria-hidden="true" />
                </button>
            </div>

            {pwa.showIosHelp ? (
                <ol className="mt-3 list-decimal space-y-1 pl-5 text-sm text-muted-foreground">
                    <li>Toca Compartir.</li>
                    <li>Elige &quot;Agregar a pantalla de inicio&quot;.</li>
                    <li>Confirma &quot;Agregar&quot;.</li>
                </ol>
            ) : null}
        </div>
    );
}
