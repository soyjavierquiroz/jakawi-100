import { useEffect, useRef, useState } from 'react';
import { AvatarCropDialog } from '@/components/media/avatar-crop-dialog';

type Props = {
    profileAvatar?: boolean;
    currentCropUrl?: string | null;
    name: string;
    label: string;
    currentUrl?: string | null;
    error?: string;
    disabled?: boolean;
    onChange?: (token: string, remove: boolean) => void;
    onBusy?: (busy: boolean) => void;
};

export function ImageUpload({
    name,
    label,
    currentUrl,
    error,
    disabled,
    onChange,
    onBusy,
    profileAvatar = false,
    currentCropUrl,
}: Props) {
    const input = useRef<HTMLInputElement>(null);
    const request = useRef<XMLHttpRequest | null>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [cropSource, setCropSource] = useState<string | null>(null);
    useEffect(
        () => () => {
            if (cropSource) URL.revokeObjectURL(cropSource);
        },
        [cropSource],
    );
    const [token, setToken] = useState('');
    const [removed, setRemoved] = useState(false);
    const [state, setState] = useState('');
    const [failure, setFailure] = useState('');
    const [busy, setBusy] = useState(false);
    const [progress, setProgress] = useState(0);
    useEffect(
        () => () => {
            if (preview) URL.revokeObjectURL(preview);
        },
        [preview],
    );
    useEffect(() => () => request.current?.abort(), []);
    const upload = (file?: File) => {
        if (!file || disabled) return;
        request.current?.abort();
        setPreview(URL.createObjectURL(file));
        setFailure('');
        setState('Subiendo imagen…');
        setProgress(0);
        setBusy(true);
        onBusy?.(true);
        setToken('');
        setRemoved(false);
        onChange?.('', false);
        const xhr = new XMLHttpRequest();
        request.current = xhr;
        xhr.open('POST', '/media/uploads');
        xhr.setRequestHeader('Accept', 'application/json');
        const csrf = document.cookie
            .split('; ')
            .find((value) => value.startsWith('XSRF-TOKEN='))
            ?.split('=')
            .slice(1)
            .join('=');
        if (csrf)
            xhr.setRequestHeader('X-XSRF-TOKEN', decodeURIComponent(csrf));
        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable)
                setProgress(Math.round((e.loaded / e.total) * 100));
        };
        xhr.upload.onload = () => setState('Procesando imagen…');
        const finish = () => {
            setBusy(false);
            onBusy?.(false);
        };
        xhr.onload = () => {
            let data: {
                token?: string;
                errors?: { image?: string[] };
                message?: string;
            } = {};
            try {
                data = JSON.parse(xhr.responseText);
            } catch {
                /* Show a readable error for proxy/server failures. */
            }
            finish();
            if (xhr.status === 200 && data.token) {
                setToken(data.token);
                onChange?.(data.token, false);
                setState('Imagen lista. Guarda para aplicar el cambio.');
            } else {
                setState('');
                setFailure(
                    data.errors?.image?.[0] ??
                        'No pudimos subir la imagen. Inténtalo de nuevo; la imagen guardada sigue disponible.',
                );
            }
        };
        xhr.onerror = () => {
            finish();
            setState('');
            setFailure('Error de conexión. Selecciona la imagen de nuevo.');
        };
        const body = new FormData();
        body.append('image', file);
        xhr.send(body);
    };
    const choose = (file?: File) => {
        if (!file || disabled || (profileAvatar && busy)) return;
        if (!profileAvatar) {
            upload(file);
            return;
        }
        if (
            !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
            file.size > 10 * 1024 * 1024
        ) {
            setFailure('Selecciona JPEG, PNG o WebP de hasta 10 MB.');
            return;
        }
        setFailure('');
        setCropSource(URL.createObjectURL(file));
        onBusy?.(true);
    };
    const closeCrop = () => {
        setCropSource(null);
        onBusy?.(false);
    };
    const reopen = async () => {
        const source = preview || currentCropUrl;
        if (!source || busy) return;
        setBusy(true);
        onBusy?.(true);
        setFailure('');
        try {
            const response = await fetch(source);
            if (!response.ok) throw new Error();
            const blob = await response.blob();
            setCropSource(URL.createObjectURL(blob));
            setBusy(false);
        } catch {
            setBusy(false);
            onBusy?.(false);
            setFailure(
                'No pudimos abrir el logo. Selecciona el original desde tu dispositivo.',
            );
        }
    };
    return (
        <fieldset
            disabled={disabled}
            className="min-w-0 space-y-3 rounded-xl border border-border p-4"
            aria-label={label}
        >
            <legend className="px-1 font-bold">{label}</legend>
            <div
                className="rounded border-2 border-dashed p-4 text-center"
                onDragOver={(e) => e.preventDefault()}
                onDrop={(e) => {
                    e.preventDefault();
                    choose(e.dataTransfer.files[0]);
                }}
            >
                <p className="text-sm">ARRÁSTRALA AQUÍ</p>
                <button
                    type="button"
                    className="mt-2 min-h-11 rounded border px-4 font-bold"
                    onClick={() => input.current?.click()}
                >
                    SELECCIONAR IMAGEN
                </button>
                <input
                    ref={input}
                    aria-label={`Seleccionar ${label}`}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="sr-only"
                    onChange={(e) => {
                        choose(e.target.files?.[0]);
                        e.target.value = '';
                    }}
                />
                <p className="mt-2 text-xs">JPEG, PNG o WebP · hasta 10 MB</p>
            </div>
            {!removed && (preview || currentUrl) && (
                <img
                    src={preview || currentUrl || undefined}
                    alt={`Vista previa: ${label}`}
                    className={
                        profileAvatar
                            ? 'mx-auto size-28 rounded-full bg-surface object-contain p-3'
                            : 'max-h-48 w-full rounded object-contain'
                    }
                />
            )}
            {profileAvatar && !removed && (preview || currentCropUrl) && (
                <button
                    type="button"
                    disabled={busy}
                    className="min-h-11 rounded border px-3 font-bold"
                    onClick={reopen}
                >
                    ENCUADRAR LOGO
                </button>
            )}
            {cropSource && (
                <AvatarCropDialog
                    key={cropSource}
                    source={cropSource}
                    allowContain
                    onClose={closeCrop}
                    onSave={(file) => {
                        setCropSource(null);
                        upload(file);
                    }}
                />
            )}
            <input type="hidden" name={`${name}_upload`} value={token} />
            <input
                type="hidden"
                name={`${name}_remove`}
                value={removed ? '1' : '0'}
            />
            {busy && (
                <progress
                    className="w-full"
                    max={100}
                    value={progress}
                    aria-label="Progreso de imagen"
                />
            )}
            <p role="status" className="text-sm">
                {state}
            </p>
            {(failure || error) && (
                <p role="alert" className="text-sm text-red-600">
                    {failure || error}
                </p>
            )}
            {(preview || currentUrl) && !removed && (
                <button
                    type="button"
                    disabled={busy}
                    className="min-h-11 rounded border px-3"
                    onClick={() => {
                        setPreview(null);
                        setToken('');
                        setRemoved(true);
                        setFailure('');
                        setState(
                            'Imagen retirada. Guarda para aplicar el cambio.',
                        );
                        onChange?.('', true);
                    }}
                >
                    QUITAR IMAGEN
                </button>
            )}
            {busy && (
                <input
                    className="sr-only"
                    aria-label="Espera mientras procesamos la imagen"
                    required
                    value=""
                    onChange={() => {}}
                />
            )}
            {failure && (
                <input
                    className="sr-only"
                    aria-label="Selecciona una imagen válida antes de guardar"
                    required
                    value=""
                    onChange={() => {}}
                />
            )}
        </fieldset>
    );
}
