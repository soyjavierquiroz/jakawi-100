import { useEffect, useRef, useState } from 'react';

// Fits the existing saved-state iframe; rendering and routes remain unchanged.
export function LandingPreviewPane({
    preview,
    baseUrl,
    revision,
    width,
    onWidth,
    dirty,
    saved,
    saving,
    formId,
    onClose,
}: {
    preview: { id: number; name: string; slug: string } | null;
    baseUrl: string;
    revision: number;
    width: number;
    onWidth: (width: number) => void;
    dirty: boolean;
    saved: boolean;
    saving: boolean;
    formId?: string;
    onClose?: () => void;
}) {
    const canvas = useRef<HTMLDivElement>(null);
    const [availableWidth, setAvailableWidth] = useState(390);
    const [toolbarHeight, setToolbarHeight] = useState(0);
    const [availableHeight, setAvailableHeight] = useState(680);
    useEffect(() => {
        const toolbar = formId
            ? document.querySelector<HTMLElement>('[data-workspace-toolbar]')
            : null;
        const measure = () => {
            setAvailableWidth(canvas.current?.clientWidth ?? 390);
            const top = toolbar?.offsetHeight ?? 0;
            setToolbarHeight(top);
            setAvailableHeight(
                window.innerWidth >= 1024
                    ? Math.max(240, window.innerHeight - top - 190)
                    : 600,
            );
        };
        const observer = new ResizeObserver(measure);
        if (canvas.current) observer.observe(canvas.current);
        if (toolbar) observer.observe(toolbar);
        window.addEventListener('resize', measure);
        measure();
        return () => {
            observer.disconnect();
            window.removeEventListener('resize', measure);
        };
    }, [formId]);
    const scale = Math.min(1, availableWidth / width);
    return (
        <section
            data-workspace-preview
            className="min-w-0 rounded border border-border bg-surface p-3 lg:sticky"
            style={{ top: toolbarHeight + 16 }}
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="min-w-0 text-sm font-extrabold break-words">
                    3 · PREVIEW
                    {preview && (
                        <span className="block text-xs font-normal text-muted-foreground">
                            {preview.name}
                        </span>
                    )}
                </h2>
                <div className="flex flex-wrap gap-2">
                    {[390, 430, 1440].map((size) => (
                        <button
                            key={size}
                            type="button"
                            aria-pressed={width === size}
                            className={`rounded border px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-brand ${width === size ? 'border-brand text-brand' : 'border-border'}`}
                            onClick={() => onWidth(size)}
                        >
                            {size === 1440 ? 'Desktop' : size}
                        </button>
                    ))}
                    {onClose && (
                        <button
                            type="button"
                            className="text-sm underline"
                            onClick={onClose}
                        >
                            CERRAR
                        </button>
                    )}
                </div>
            </div>
            {dirty ? (
                <div
                    role="status"
                    className="my-3 rounded border border-amber-500 bg-amber-50 p-3 text-sm text-amber-900"
                >
                    <strong>● CAMBIOS SIN GUARDAR</strong>
                    <p>El Preview todavía muestra la versión guardada.</p>
                    {formId && (
                        <button
                            type="submit"
                            form={formId}
                            disabled={saving}
                            className="mt-2 font-bold underline disabled:opacity-50"
                        >
                            GUARDAR Y ACTUALIZAR PREVIEW
                        </button>
                    )}
                </div>
            ) : (
                <p
                    role="status"
                    className={`my-3 text-xs ${saved ? 'text-green-700' : 'text-muted-foreground'}`}
                >
                    {saved ? '✓ PREVIEW ACTUALIZADO' : 'Versión guardada'} ·
                    Acciones de negocio desactivadas.
                </p>
            )}
            <div
                ref={canvas}
                className="min-w-0 overflow-hidden rounded bg-background"
                style={{ height: availableHeight }}
            >
                {preview ? (
                    <div
                        className="relative mx-auto"
                        style={{
                            width: width * scale,
                            height: availableHeight,
                        }}
                    >
                        <iframe
                            title={`Preview ${preview.name}`}
                            key={`${preview.id}:${revision}`}
                            src={`${baseUrl}/${preview.slug}/preview?revision=${revision}`}
                            style={{
                                width,
                                height: availableHeight / scale,
                                transform: `scale(${scale})`,
                                transformOrigin: 'top left',
                            }}
                            className="absolute top-0 left-0 block max-w-none border border-border bg-background"
                        />
                    </div>
                ) : (
                    <p className="p-5 text-sm text-muted-foreground">
                        Guarda la nueva landing para abrir su Preview.
                    </p>
                )}
            </div>
        </section>
    );
}
