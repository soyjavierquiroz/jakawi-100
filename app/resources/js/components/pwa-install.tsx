import { Download, Share } from 'lucide-react';
import { usePwaInstall } from '@/hooks/use-pwa-install';

export function PwaInstall() {
    const pwa = usePwaInstall();

    if (!pwa.canInstall && !pwa.canShowIosHelp) {
        return null;
    }

    return (
        <div className="fixed inset-x-4 bottom-4 z-50 mx-auto max-w-sm rounded-md border border-border bg-surface/95 p-3 text-foreground shadow-lg backdrop-blur">
            {pwa.canInstall ? (
                <button
                    type="button"
                    onClick={() => void pwa.install()}
                    className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-md bg-brand px-4 text-sm font-semibold text-brand-foreground transition hover:bg-brand/90"
                >
                    <Download className="size-4" aria-hidden="true" />
                    Instalar JAKAWI
                </button>
            ) : (
                <button
                    type="button"
                    onClick={() => pwa.setShowIosHelp(!pwa.showIosHelp)}
                    className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-md border border-border bg-background px-4 text-sm font-semibold text-foreground transition hover:bg-muted"
                >
                    <Share className="size-4" aria-hidden="true" />
                    Instala JAKAWI
                </button>
            )}

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
