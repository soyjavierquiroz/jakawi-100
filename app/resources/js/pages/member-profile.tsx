import { Head, router } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import Cropper, { type Area } from 'react-easy-crop';
import { ChangeEvent, FormEvent, useEffect, useRef, useState } from 'react';
import AppearanceSelector from '@/components/appearance-selector';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Profile = {
    city: string | null;
    avatar_url: string | null;
    interests: string[];
    social_contexts: string[];
    preferred_days: string[];
    preferred_times: string[];
    completion_percentage: number;
    profile_completed_at: string | null;
};
type Options = {
    interests: string[];
    social_contexts: string[];
    preferred_days: string[];
    preferred_times: string[];
    cities: string[];
};

const labels: Record<string, string> = {
    food: 'Gastronomía',
    cafe: 'Café',
    fitness: 'Fitness',
    wellness: 'Wellness',
    beauty: 'Belleza',
    entertainment: 'Entretenimiento',
    nightlife: 'Vida nocturna',
    shopping: 'Shopping',
    services: 'Lifestyle y servicios',
    experiences: 'Experiencias',
    solo: 'Solo',
    pareja: 'En pareja',
    amigos: 'Con amigos',
    familia: 'En familia',
    entre_semana: 'Entre semana',
    fin_de_semana: 'Fin de semana',
    manana: 'Mañana',
    tarde: 'Tarde',
    noche: 'Noche',
};

const AVATAR_SIZE = 1024;

async function cropAvatar(source: string, crop: Area): Promise<File> {
    const image = await new Promise<HTMLImageElement>((resolve, reject) => {
        const element = new Image();
        element.onload = () => resolve(element);
        element.onerror = () => reject(new Error('No se pudo abrir la foto.'));
        element.src = source;
    });
    const canvas = document.createElement('canvas');
    canvas.width = AVATAR_SIZE;
    canvas.height = AVATAR_SIZE;
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
        AVATAR_SIZE,
        AVATAR_SIZE,
    );

    const blob = await new Promise<Blob>((resolve, reject) =>
        canvas.toBlob(
            (result) =>
                result
                    ? resolve(result)
                    : reject(new Error('No se pudo preparar la foto.')),
            'image/jpeg',
            0.88,
        ),
    );

    return new File([blob], 'avatar.jpg', { type: 'image/jpeg' });
}

function Choices({
    values,
    selected,
    onChange,
}: {
    values: string[];
    selected: string[];
    onChange: (value: string) => void;
}) {
    return (
        <div className="mt-3 flex flex-wrap gap-2">
            {values.map((value) => (
                <button
                    key={value}
                    type="button"
                    onClick={() => onChange(value)}
                    aria-pressed={selected.includes(value)}
                    className={`min-h-11 rounded-full border px-4 py-2 text-sm font-semibold transition-colors ${selected.includes(value) ? 'border-brand bg-brand text-brand-foreground' : 'border-border bg-surface-muted text-foreground hover:border-brand hover:bg-brand-subtle'}`}
                >
                    {labels[value] ?? value}
                </button>
            ))}
        </div>
    );
}

export default function MemberProfile({
    user,
    profile,
    options,
}: {
    user: { name: string; email: string };
    profile: Profile;
    options: Options;
}) {
    const [form, setForm] = useState({
        city: profile.city ?? 'Cochabamba',
        interests: profile.interests,
        social_contexts: profile.social_contexts,
        preferred_days: profile.preferred_days,
        preferred_times: profile.preferred_times,
        avatar: null as File | null,
    });
    const [avatarPreview, setAvatarPreview] = useState<string | null>(null);
    const [avatarPickerOpen, setAvatarPickerOpen] = useState(false);
    const [cropSource, setCropSource] = useState<string | null>(null);
    const [crop, setCrop] = useState({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [cropPixels, setCropPixels] = useState<Area | null>(null);
    const [cropping, setCropping] = useState(false);
    const galleryInput = useRef<HTMLInputElement>(null);
    const cameraInput = useRef<HTMLInputElement>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    useEffect(
        () => () => {
            if (avatarPreview) URL.revokeObjectURL(avatarPreview);
        },
        [avatarPreview],
    );
    const toggle = (
        field:
            | 'interests'
            | 'social_contexts'
            | 'preferred_days'
            | 'preferred_times',
        value: string,
    ) =>
        setForm((current) => ({
            ...current,
            [field]: current[field].includes(value)
                ? current[field].filter((item) => item !== value)
                : [...current[field], value],
        }));
    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.post(
            '/mi-jakawi/perfil',
            { ...form, _method: 'put' },
            {
                forceFormData: true,
                onStart: () => {
                    setSaving(true);
                    setErrors({});
                },
                onError: (newErrors) => setErrors(newErrors),
                onSuccess: () => {
                    setForm((current) => ({ ...current, avatar: null }));
                    setAvatarPreview(null);
                },
                onFinish: () => setSaving(false),
            },
        );
    };
    const avatarChange = (event: ChangeEvent<HTMLInputElement>) => {
        const image = event.target.files?.[0];
        event.target.value = '';
        if (!image) return;
        setAvatarPickerOpen(false);
        setCrop({ x: 0, y: 0 });
        setZoom(1);
        setCropPixels(null);
        setCropSource((current) => {
            if (current) URL.revokeObjectURL(current);
            return URL.createObjectURL(image);
        });
    };
    const closeCropper = () => {
        setCropSource((current) => {
            if (current) URL.revokeObjectURL(current);
            return null;
        });
        setCropping(false);
    };
    const useCroppedAvatar = async () => {
        if (!cropSource || !cropPixels) return;
        setCropping(true);
        try {
            const avatar = await cropAvatar(cropSource, cropPixels);
            setForm((current) => ({ ...current, avatar }));
            setErrors((current) => ({ ...current, avatar: '' }));
            setAvatarPreview((current) => {
                if (current) URL.revokeObjectURL(current);
                return URL.createObjectURL(avatar);
            });
            closeCropper();
        } catch (error) {
            setErrors((current) => ({
                ...current,
                avatar:
                    error instanceof Error
                        ? error.message
                        : 'No se pudo preparar la foto.',
            }));
            setCropping(false);
        }
    };
    const complete = profile.completion_percentage === 100;

    return (
        <>
            <Head title="Tu perfil JAKAWI" />
            <main className="min-h-screen bg-background px-4 pt-[max(1.5rem,env(safe-area-inset-top))] pb-28 text-foreground sm:px-6">
                <form
                    onSubmit={submit}
                    className="mx-auto flex w-full max-w-3xl flex-col gap-5"
                >
                    <header className="pb-1">
                        <a
                            href="/"
                            className="min-h-11 text-sm font-semibold text-muted-foreground underline underline-offset-4 hover:text-foreground"
                        >
                            Inicio
                        </a>
                        <div className="mt-5 flex items-center gap-2">
                            <span
                                className="size-2 rounded-full bg-action"
                                aria-hidden="true"
                            />
                            <p className="text-xs font-bold tracking-[0.16em] text-muted-foreground uppercase">
                                Tu perfil JAKAWI
                            </p>
                        </div>
                        <h1 className="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                            Tu perfil
                        </h1>
                        <p className="mt-3 leading-6 text-muted-foreground">
                            Cuéntanos qué te gusta para mostrarte lugares y
                            experiencias más relevantes.
                        </p>
                    </header>
                    <section className="rounded-3xl border border-border bg-surface-elevated p-5 sm:p-6">
                        <div className="flex items-center gap-4">
                            {avatarPreview || profile.avatar_url ? (
                                <img
                                    src={
                                        avatarPreview ??
                                        profile.avatar_url ??
                                        undefined
                                    }
                                    className="h-20 w-20 shrink-0 rounded-full border-4 border-brand-subtle object-cover sm:h-22 sm:w-22"
                                    alt="Tu avatar"
                                />
                            ) : (
                                <div className="flex h-20 w-20 shrink-0 items-center justify-center rounded-full border-4 border-brand-subtle bg-brand-subtle text-2xl font-bold sm:h-22 sm:w-22">
                                    {user.name.slice(0, 1).toUpperCase()}
                                </div>
                            )}
                            <div>
                                <p className="text-lg font-bold">{user.name}</p>
                                <p className="text-sm text-muted-foreground">
                                    {user.email}
                                </p>
                                <button
                                    type="button"
                                    className="mt-1 min-h-11 text-sm font-semibold text-brand underline underline-offset-4"
                                    onClick={() => setAvatarPickerOpen(true)}
                                >
                                    {avatarPreview || profile.avatar_url
                                        ? 'Cambiar foto'
                                        : 'Agregar foto opcional'}
                                </button>
                                {errors.avatar || errors.image ? (
                                    <p className="mt-1 text-sm text-destructive">
                                        {errors.avatar || errors.image}
                                    </p>
                                ) : null}
                            </div>
                        </div>
                        <label className="mt-6 block border-t border-border pt-5 text-sm font-semibold">
                            Ciudad
                            <select
                                value={form.city}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        city: event.target.value,
                                    }))
                                }
                                className="mt-2 block min-h-12 w-full rounded-xl border border-border bg-surface-muted px-3 py-2 text-foreground outline-none focus-visible:ring-2 focus-visible:ring-brand"
                            >
                                {options.cities.map((city) => (
                                    <option key={city}>{city}</option>
                                ))}
                            </select>
                        </label>
                    </section>
                    <section className="rounded-3xl border border-border bg-surface-elevated p-5 sm:p-6">
                        <div>
                            <p className="text-lg font-bold">
                                ¿Qué te interesa?
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Elige entre 3 y 5 si quieres; puedes seleccionar
                                más.
                            </p>
                            <Choices
                                values={options.interests}
                                selected={form.interests}
                                onChange={(value) => toggle('interests', value)}
                            />
                        </div>
                        <div className="mt-6 border-t border-border pt-6">
                            <p className="text-lg font-bold">
                                ¿Con quién sueles salir?
                            </p>
                            <Choices
                                values={options.social_contexts}
                                selected={form.social_contexts}
                                onChange={(value) =>
                                    toggle('social_contexts', value)
                                }
                            />
                        </div>
                        <div className="mt-6 border-t border-border pt-6">
                            <p className="text-lg font-bold">
                                ¿Cuándo sueles buscar planes?
                            </p>
                            <Choices
                                values={options.preferred_days}
                                selected={form.preferred_days}
                                onChange={(value) =>
                                    toggle('preferred_days', value)
                                }
                            />
                        </div>
                        <div className="mt-6 border-t border-border pt-6">
                            <p className="text-lg font-bold">
                                ¿En qué momento del día?
                            </p>
                            <Choices
                                values={options.preferred_times}
                                selected={form.preferred_times}
                                onChange={(value) =>
                                    toggle('preferred_times', value)
                                }
                            />
                        </div>
                    </section>
                    <div
                        className={`flex items-start gap-3 rounded-2xl border p-4 ${complete ? 'border-success bg-success-surface' : 'border-border bg-surface-muted'}`}
                    >
                        <span
                            className={`flex size-6 shrink-0 items-center justify-center rounded-full text-sm font-bold ${complete ? 'bg-success text-success-foreground' : 'bg-surface text-muted-foreground'}`}
                            aria-hidden="true"
                        >
                            {complete ? '✓' : '·'}
                        </span>
                        <div>
                            <p className="font-bold">
                                {complete
                                    ? 'Perfil completo'
                                    : `${profile.completion_percentage}% completo`}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {complete
                                    ? 'Tus preferencias ya están ayudando a personalizar JAKAWI.'
                                    : 'Completa las cuatro secciones para terminar tu perfil.'}
                            </p>
                        </div>
                    </div>
                    <section className="rounded-3xl border border-border bg-surface-elevated p-5 sm:p-6">
                        <p className="text-lg font-bold">Apariencia</p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Elige cómo se ve JAKAWI.
                        </p>
                        <div className="mt-4">
                            <AppearanceSelector />
                        </div>
                    </section>
                    <Button
                        className="h-12 w-full rounded-xl text-base font-bold"
                        type="submit"
                        disabled={saving}
                    >
                        {saving ? (
                            <>
                                <LoaderCircle className="size-4 animate-spin" />
                                Guardando…
                            </>
                        ) : (
                            'Guardar perfil'
                        )}
                    </Button>
                    <button
                        type="button"
                        onClick={() => router.post('/logout')}
                        className="min-h-11 self-center text-sm font-semibold text-muted-foreground underline underline-offset-4 hover:text-foreground"
                    >
                        Cerrar sesión
                    </button>
                </form>
            </main>
            <input
                ref={galleryInput}
                className="sr-only"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                onChange={avatarChange}
                data-test="avatar-gallery-input"
            />
            <input
                ref={cameraInput}
                className="sr-only"
                type="file"
                accept="image/*"
                capture="user"
                onChange={avatarChange}
                data-test="avatar-camera-input"
            />
            <Dialog open={avatarPickerOpen} onOpenChange={setAvatarPickerOpen}>
                <DialogContent className="max-w-sm p-5">
                    <DialogHeader>
                        <DialogTitle>Agregar foto</DialogTitle>
                        <DialogDescription>
                            Elige cómo quieres agregar tu foto de perfil.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <button
                            type="button"
                            className="min-h-11 rounded-md border border-border px-4 text-left font-semibold"
                            onClick={() => cameraInput.current?.click()}
                        >
                            Tomar foto
                        </button>
                        <button
                            type="button"
                            className="min-h-11 rounded-md border border-border px-4 text-left font-semibold"
                            onClick={() => galleryInput.current?.click()}
                        >
                            Elegir de galería
                        </button>
                        <button
                            type="button"
                            className="min-h-11 px-4 text-left font-semibold text-muted-foreground"
                            onClick={() => setAvatarPickerOpen(false)}
                        >
                            Cancelar
                        </button>
                    </div>
                </DialogContent>
            </Dialog>
            <Dialog
                open={cropSource !== null}
                onOpenChange={(open) => !open && closeCropper()}
            >
                <DialogContent className="h-[100dvh] max-w-none gap-0 rounded-none border-0 p-0 sm:h-auto sm:max-w-xl sm:rounded-lg sm:border">
                    <DialogHeader className="shrink-0 px-5 pt-6 sm:px-6">
                        <DialogTitle>Encuadra tu foto</DialogTitle>
                        <DialogDescription>
                            Mueve y acerca la imagen hasta que te guste.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="relative mx-auto mt-5 h-[min(66vw,420px)] max-h-[52dvh] w-[min(66vw,420px)] max-w-[calc(100vw-3rem)] overflow-hidden rounded-full bg-muted sm:h-[420px] sm:w-[420px]">
                        {cropSource ? (
                            <Cropper
                                image={cropSource}
                                crop={crop}
                                zoom={zoom}
                                aspect={1}
                                cropShape="round"
                                showGrid={false}
                                onCropChange={setCrop}
                                onZoomChange={setZoom}
                                onCropComplete={(_, pixels) =>
                                    setCropPixels(pixels)
                                }
                            />
                        ) : null}
                    </div>
                    <label className="mx-auto mt-6 block w-full max-w-sm px-5 text-sm font-semibold sm:px-6">
                        Acercar
                        <input
                            className="mt-3 w-full accent-[var(--brand)]"
                            type="range"
                            min="1"
                            max="3"
                            step="0.05"
                            value={zoom}
                            onChange={(event) =>
                                setZoom(Number(event.target.value))
                            }
                            aria-label="Acercar foto"
                        />
                    </label>
                    <div className="mt-auto flex shrink-0 gap-3 p-5 sm:mt-6 sm:p-6">
                        <button
                            type="button"
                            className="min-h-11 flex-1 rounded-md border border-border px-4 font-semibold"
                            onClick={closeCropper}
                            disabled={cropping}
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            className="min-h-11 flex-1 rounded-md bg-brand px-4 font-bold text-brand-foreground disabled:opacity-60"
                            onClick={useCroppedAvatar}
                            disabled={cropping || !cropPixels}
                        >
                            {cropping ? 'Preparando…' : 'USAR FOTO'}
                        </button>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
