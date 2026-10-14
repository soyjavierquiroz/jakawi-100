import { useMemo, useState } from 'react';
import Cropper, { type Area, type MediaSize, type Size } from 'react-easy-crop';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

// Shared profile crop/export, extracted from member-profile. PNG retains transparent
// padding for wide logos; the media pipeline normalizes it to the existing WebP master.
export async function cropAvatar(source: string, crop: Area): Promise<File> {
    const image = await new Promise<HTMLImageElement>((resolve, reject) => {
        const element = new Image();
        element.onload = () => resolve(element);
        element.onerror = () => reject(new Error('No se pudo abrir la foto.'));
        element.src = source;
    });
    const canvas = document.createElement('canvas');
    // Bound resolution without upscaling any selected source pixels.
    const size = Math.max(
        1,
        Math.floor(Math.min(1024, crop.width, crop.height)),
    );
    canvas.width = canvas.height = size;
    const context = canvas.getContext('2d');
    if (!context) throw new Error('No se pudo preparar la foto.');
    context.drawImage(
        image,
        crop.x,
        crop.y,
        crop.width,
        crop.height,
        0,
        0,
        size,
        size,
    );
    const blob = await new Promise<Blob>((resolve, reject) =>
        canvas.toBlob(
            (result) =>
                result
                    ? resolve(result)
                    : reject(new Error('No se pudo preparar la foto.')),
            'image/png',
        ),
    );
    return new File([blob], 'avatar.png', { type: 'image/png' });
}

export function AvatarCropDialog({
    source,
    allowContain = false,
    onClose,
    onSave,
}: {
    source: string;
    allowContain?: boolean;
    onClose: () => void;
    onSave: (file: File) => void;
}) {
    const [crop, setCrop] = useState({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [pixels, setPixels] = useState<Area | null>(null);
    const [media, setMedia] = useState<MediaSize | null>(null);
    const [frame, setFrame] = useState<Size | null>(null);
    const [side, setSide] = useState(0);
    const cropSize = useMemo(
        () =>
            allowContain && side ? { width: side, height: side } : undefined,
        [allowContain, side],
    );
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const minimum =
        allowContain && media && side
            ? side / Math.hypot(media.width, media.height)
            : 1;
    const fit = (loaded: MediaSize) => {
        setMedia(loaded);
        if (allowContain && side)
            setZoom(side / Math.hypot(loaded.width, loaded.height));
    };
    const save = async () => {
        if (!pixels || busy) return;
        setBusy(true);
        setError('');
        try {
            // react-easy-crop clips pixel coordinates to the source. For contain,
            // export its same transform including transparent space outside the source.
            const area =
                allowContain && media && frame
                    ? {
                          x:
                              (((media.width * zoom) / 2 -
                                  frame.width / 2 -
                                  crop.x) /
                                  (media.width * zoom)) *
                              media.naturalWidth,
                          y:
                              (((media.height * zoom) / 2 -
                                  frame.height / 2 -
                                  crop.y) /
                                  (media.height * zoom)) *
                              media.naturalHeight,
                          width:
                              (frame.width / (media.width * zoom)) *
                              media.naturalWidth,
                          height:
                              (frame.height / (media.height * zoom)) *
                              media.naturalHeight,
                      }
                    : pixels;
            onSave(await cropAvatar(source, area));
        } catch (failure) {
            setError(
                failure instanceof Error
                    ? failure.message
                    : 'No se pudo preparar la foto.',
            );
            setBusy(false);
        }
    };
    return (
        <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
            <DialogContent className="flex max-h-[95dvh] flex-col gap-0 overflow-y-auto p-0 sm:max-w-xl">
                <DialogHeader className="px-5 pt-6 sm:px-6">
                    <DialogTitle>Encuadra tu foto</DialogTitle>
                    <DialogDescription>
                        {allowContain
                            ? 'Mueve y ajusta el zoom. Puedes alejar para conservar todo el logo.'
                            : 'Mueve y acerca la imagen hasta que te guste.'}
                    </DialogDescription>
                </DialogHeader>
                <div
                    data-avatar-crop
                    className="relative mx-auto mt-5 aspect-square w-[min(66vw,420px)] shrink-0 overflow-hidden rounded-full bg-surface-muted"
                    ref={(node) => {
                        if (node) {
                            const measured = Math.min(
                                node.clientWidth,
                                node.clientHeight,
                            );
                            if (measured !== side) setSide(measured);
                        }
                    }}
                >
                    <Cropper
                        image={source}
                        crop={crop}
                        zoom={zoom}
                        aspect={1}
                        cropShape="round"
                        showGrid={false}
                        cropSize={cropSize}
                        minZoom={Math.min(minimum, zoom)}
                        maxZoom={3}
                        restrictPosition={!allowContain}
                        onMediaLoaded={fit}
                        onCropSizeChange={setFrame}
                        onCropChange={setCrop}
                        onZoomChange={setZoom}
                        onCropComplete={(_, area) => setPixels(area)}
                    />
                </div>
                <label className="mx-auto mt-6 block w-full max-w-sm px-5 text-sm font-semibold">
                    Ajustar zoom
                    <input
                        className="mt-3 w-full accent-[var(--brand)]"
                        type="range"
                        min={minimum}
                        max="3"
                        step="0.01"
                        value={zoom}
                        onChange={(event) =>
                            setZoom(Number(event.target.value))
                        }
                        aria-label="Acercar foto"
                    />
                </label>
                {allowContain && (
                    <button
                        type="button"
                        className="mx-auto mt-3 min-h-11 px-4 text-sm font-bold underline"
                        onClick={() => {
                            setCrop({ x: 0, y: 0 });
                            setZoom(minimum);
                        }}
                    >
                        MOSTRAR LOGO COMPLETO
                    </button>
                )}
                {error && (
                    <p role="alert" className="px-5 text-sm text-danger">
                        {error}
                    </p>
                )}
                <div className="flex gap-3 p-5 sm:p-6">
                    <button
                        type="button"
                        className="min-h-11 flex-1 rounded-md border border-border px-4 font-semibold"
                        onClick={onClose}
                        disabled={busy}
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        className="min-h-11 flex-1 rounded-md bg-brand px-4 font-bold text-brand-foreground disabled:opacity-60"
                        onClick={save}
                        disabled={busy || !pixels}
                    >
                        {busy ? 'Preparando…' : 'USAR FOTO'}
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
