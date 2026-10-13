import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    exportLandingJson,
    parseLandingJson,
    subjectHintMismatch,
} from '@/lib/landing-json';
import { useState } from 'react';

export function LandingJsonControls({
    brief,
    form,
    title,
    fallbackHeadline,
    hasImage,
    hasVideo,
    onApply,
}: {
    brief: Record<string, unknown> | null;
    form: Record<string, string>;
    title: string;
    fallbackHeadline: string;
    hasImage: boolean;
    hasVideo: boolean;
    onApply: (patch: Record<string, string>) => void;
}) {
    const [mode, setMode] = useState<'import' | 'export' | 'brief' | null>(
        null,
    );
    const [text, setText] = useState('');
    const [errors, setErrors] = useState<string[]>([]);
    const [mismatch, setMismatch] = useState<string | null>(null);
    const [message, setMessage] = useState('');
    const button = 'rounded border border-border px-4 py-2 text-sm font-bold';
    const start = (next: 'import' | 'export') => {
        setText(next === 'export' ? exportLandingJson(form, title) : '');
        setErrors([]);
        setMismatch(null);
        setMessage('');
        setMode(next);
    };
    const apply = (confirmed = false) => {
        // Revalidate against the current form and subject, even after confirmation.
        const result = parseLandingJson(text, form, fallbackHeadline);
        if (!result.ok) {
            setErrors(result.errors);
            setMismatch(null);
            return;
        }
        if (!confirmed && subjectHintMismatch(result.subjectHint, title)) {
            setErrors([]);
            setMismatch(result.subjectHint!);
            return;
        }
        onApply(result.patch);
        const count = Object.entries(result.patch).filter(
            ([key, value]) => form[key] !== value,
        ).length;
        setMessage(
            `✓ JSON APLICADO · ${count} campos editoriales actualizados. Importar no guarda. Revisa y pulsa GUARDAR Y PREVISUALIZAR.${!((result.patch.media_mode ?? form.media_mode) === 'VSL' ? hasVideo : hasImage) && (result.patch.media_mode ?? form.media_mode) !== 'NONE' ? ' ⚠ Falta seleccionar/subir media.' : ''}`,
        );
        setMode(null);
    };
    const copyBrief = async () => {
        if (!brief) return;
        const json = JSON.stringify(brief, null, 2);
        try {
            await navigator.clipboard.writeText(json);
            setMessage('✓ BRIEF COPIADO');
        } catch {
            setText(json);
            setErrors([
                'No se pudo copiar. Selecciona el texto y cópialo manualmente.',
            ]);
            setMismatch(null);
            setMode('brief');
        }
    };
    return (
        <div className="col-span-full flex flex-wrap gap-3">
            {brief && (
                <button type="button" className={button} onClick={copyBrief}>
                    COPIAR BRIEF PARA IA
                </button>
            )}
            <button
                type="button"
                className={button}
                onClick={() => start('import')}
            >
                IMPORTAR JSON
            </button>
            <button
                type="button"
                className={button}
                onClick={() => start('export')}
            >
                EXPORTAR JSON
            </button>
            {brief && (
                <p className="w-full text-sm text-muted-foreground">
                    Pégalo en tu copywriter JAKAWI y vuelve con un
                    jakawi-landing-v1 para importar.
                </p>
            )}
            {message && (
                <p
                    role="status"
                    className="w-full rounded border border-border bg-background p-3 text-sm"
                >
                    {message}
                </p>
            )}
            <Dialog
                open={mode !== null}
                onOpenChange={(open) => {
                    if (!open) setMode(null);
                }}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogTitle>
                        {mode === 'brief'
                            ? 'BRIEF PARA IA'
                            : mode === 'import'
                              ? 'IMPORTAR JSON'
                              : 'EXPORTAR JSON'}
                    </DialogTitle>
                    <DialogDescription>
                        Solo presentación editorial. El producto, audiencia y
                        campaign key se conservan. Importar no guarda ni
                        publica.
                    </DialogDescription>
                    <label className="text-sm font-bold">
                        JSON
                        <textarea
                            aria-label="JSON"
                            className="mt-2 min-h-64 w-full rounded border border-border bg-surface p-3 font-mono text-xs"
                            readOnly={mode !== 'import'}
                            value={text}
                            onChange={(e) => {
                                setText(e.target.value);
                                setMismatch(null);
                                setErrors([]);
                            }}
                        />
                    </label>
                    {!!errors.length && (
                        <div role="alert" className="text-sm text-danger">
                            {mode === 'brief'
                                ? 'Copia manual:'
                                : 'JSON no compatible:'}
                            <ul className="list-disc pl-5">
                                {errors.map((error) => (
                                    <li key={error}>{error}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                    {mismatch && (
                        <p role="alert" className="text-sm">
                            Este JSON parece haber sido generado para: “
                            {mismatch}”. Actualmente editas: “{title}”.
                        </p>
                    )}
                    <div className="flex flex-wrap gap-3">
                        {mode === 'import' ? (
                            <button
                                type="button"
                                className={button}
                                onClick={() => apply(mismatch !== null)}
                            >
                                {mismatch
                                    ? 'APLICAR DE TODAS FORMAS'
                                    : 'VALIDAR Y APLICAR'}
                            </button>
                        ) : (
                            <>
                                <button
                                    type="button"
                                    className={button}
                                    onClick={async () => {
                                        try {
                                            await navigator.clipboard.writeText(
                                                text,
                                            );
                                            setMessage(
                                                mode === 'brief'
                                                    ? '✓ BRIEF COPIADO'
                                                    : 'JSON copiado',
                                            );
                                        } catch {
                                            setErrors([
                                                'No se pudo copiar. Selecciona el texto y cópialo manualmente.',
                                            ]);
                                        }
                                    }}
                                >
                                    {mode === 'brief'
                                        ? 'COPIAR BRIEF'
                                        : 'COPIAR JSON'}
                                </button>
                                {mode === 'export' && (
                                    <button
                                        type="button"
                                        className={button}
                                        onClick={() => {
                                            const url = URL.createObjectURL(
                                                new Blob([text], {
                                                    type: 'application/json',
                                                }),
                                            );
                                            const link =
                                                document.createElement('a');
                                            link.href = url;
                                            link.download =
                                                'jakawi-landing-v1.json';
                                            link.click();
                                            setTimeout(
                                                () => URL.revokeObjectURL(url),
                                                1000,
                                            );
                                        }}
                                    >
                                        DESCARGAR .JSON
                                    </button>
                                )}
                            </>
                        )}
                        <button
                            type="button"
                            className={button}
                            onClick={() => setMode(null)}
                        >
                            {mode === 'import' ? 'CANCELAR' : 'CERRAR'}
                        </button>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}
