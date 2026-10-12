import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm, usePage } from '@inertiajs/react';
import { createContext, useContext, useState } from 'react';

export type AcquisitionContext = {
    type: 'BENEFIT' | 'EXPERIENCE' | 'CHALLENGE' | 'UNLOCK';
    subjectId: number;
    passwordRules: string;
    title: string;
    provider: string | null;
    headline: string;
    supportingCopy: string;
    endpoint: string;
};
const Acquisition = createContext<(mode?: 'register' | 'login') => void>(
    () => {},
);
export const useAcquisition = () => useContext(Acquisition);

export function AcquisitionProvider({
    children,
}: {
    children: React.ReactNode;
}) {
    const page = usePage();
    const context = page.props.acquisition as AcquisitionContext | undefined;
    const [open, setOpen] = useState(false);
    const [mode, setMode] = useState<'register' | 'login'>('register');
    const [preparing, setPreparing] = useState(false);
    const [failure, setFailure] = useState('');
    const form = useForm({ name: '', email: '', whatsapp: '', password: '' });
    const theme =
        (page.props.presentation as { marketing_theme?: string } | undefined)
            ?.marketing_theme === 'DARK_CONVERSION'
            ? 'dark-conversion'
            : 'light-conversion';
    const switchMode = (next: 'register' | 'login') => {
        setMode(next);
        form.clearErrors();
        form.setData('password', '');
        setFailure('');
    };
    const begin = (next: 'register' | 'login' = 'register') => {
        switchMode(next);
        setOpen(true);
    };
    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        if (!context || preparing || form.processing) return;
        setPreparing(true);
        setFailure('');
        try {
            const csrf =
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') ?? '';
            const xsrf = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
            const csrfHeaders: Record<string, string> = xsrf
                ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf[1]) }
                : { 'X-CSRF-TOKEN': csrf };
            const response = await fetch(context.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    ...csrfHeaders,
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ method: mode }),
            });
            if (!response.ok)
                throw new Error(
                    'No pudimos continuar. Recarga esta Landing e inténtalo de nuevo.',
                );
            form.post(mode === 'register' ? '/register' : '/login', {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);
                    form.reset();
                },
            });
        } catch (error) {
            setFailure(
                error instanceof Error ? error.message : 'Inténtalo de nuevo.',
            );
        } finally {
            setPreparing(false);
        }
    };
    const passwordMin = Number(
        context?.passwordRules.match(/minlength:\s*(\d+)/)?.[1] ?? 8,
    );
    const passwordHint =
        passwordMin > 8
            ? ` (mínimo ${passwordMin} caracteres, mayúsculas, minúsculas, número y símbolo)`
            : ` (mínimo ${passwordMin} caracteres)`;
    const busy = preparing || form.processing;
    const item = context
        ? {
              BENEFIT: 'BENEFICIO',
              EXPERIENCE: 'EXPERIENCIA',
              CHALLENGE: 'RETO',
              UNLOCK: 'DESBLOQUEO',
          }[context.type]
        : '';
    return (
        <Acquisition.Provider value={begin}>
            {children}
            {context && (
                <Dialog
                    open={open}
                    onOpenChange={(value) => {
                        if (!busy) setOpen(value);
                    }}
                >
                    <DialogContent
                        data-marketing-theme={theme}
                        className="max-h-[90dvh] overflow-y-auto bg-background font-discovery text-foreground sm:max-w-md"
                    >
                        <p className="pr-6 text-xs font-extrabold tracking-wide">
                            CONTINÚA CON TU {item}
                        </p>
                        <DialogTitle className="text-2xl leading-tight font-extrabold">
                            {mode === 'register'
                                ? context.headline
                                : `Inicia sesión y continúa con tu ${item.toLowerCase()}`}
                        </DialogTitle>
                        <DialogDescription className="text-sm text-muted-foreground">
                            {mode === 'register'
                                ? context.supportingCopy
                                : 'Al entrar a tu cuenta, continúas exactamente aquí.'}
                        </DialogDescription>
                        <div className="border-l-4 border-[var(--marketing-reward)] pl-3">
                            <p className="font-bold">{context.title}</p>
                            {context.provider && (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {context.provider}
                                </p>
                            )}
                        </div>
                        <form onSubmit={submit} className="grid gap-3">
                            {mode === 'register' && (
                                <div className="grid gap-1">
                                    <Label htmlFor="acquisition-name">
                                        Nombre
                                    </Label>
                                    <Input
                                        id="acquisition-name"
                                        required
                                        autoComplete="name"
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                            )}
                            <div className="grid gap-1">
                                <Label htmlFor="acquisition-email">
                                    Correo
                                </Label>
                                <Input
                                    id="acquisition-email"
                                    type="email"
                                    required
                                    autoComplete="email"
                                    value={form.data.email}
                                    onChange={(event) =>
                                        form.setData(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.email} />
                            </div>
                            {mode === 'register' && (
                                <div className="grid gap-1">
                                    <Label htmlFor="acquisition-whatsapp">
                                        Teléfono / WhatsApp
                                    </Label>
                                    <Input
                                        id="acquisition-whatsapp"
                                        type="tel"
                                        required
                                        autoComplete="tel"
                                        placeholder="71234567"
                                        value={form.data.whatsapp}
                                        onChange={(event) =>
                                            form.setData(
                                                'whatsapp',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.whatsapp}
                                    />
                                </div>
                            )}
                            <div className="grid gap-1">
                                <Label htmlFor="acquisition-password">
                                    Contraseña
                                    {mode === 'register' ? passwordHint : ''}
                                </Label>
                                <PasswordInput
                                    id="acquisition-password"
                                    required
                                    minLength={
                                        mode === 'register'
                                            ? passwordMin
                                            : undefined
                                    }
                                    passwordrules={
                                        mode === 'register'
                                            ? context.passwordRules
                                            : undefined
                                    }
                                    autoComplete={
                                        mode === 'register'
                                            ? 'new-password'
                                            : 'current-password'
                                    }
                                    value={form.data.password}
                                    onChange={(event) =>
                                        form.setData(
                                            'password',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.password} />
                            </div>
                            {failure && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {failure}
                                </p>
                            )}
                            <Button
                                type="submit"
                                disabled={busy}
                                className="funnel-primary-action min-h-12 w-full text-white"
                            >
                                {busy
                                    ? 'CONTINUANDO…'
                                    : mode === 'register'
                                      ? 'CREAR CUENTA Y CONTINUAR'
                                      : 'INICIAR SESIÓN Y CONTINUAR'}
                            </Button>
                        </form>
                        <p className="text-center text-sm">
                            {mode === 'register'
                                ? '¿Ya tienes cuenta? '
                                : '¿Aún no tienes cuenta? '}
                            <button
                                type="button"
                                disabled={busy}
                                className="min-h-11 font-bold underline"
                                onClick={() =>
                                    switchMode(
                                        mode === 'register'
                                            ? 'login'
                                            : 'register',
                                    )
                                }
                            >
                                {mode === 'register'
                                    ? 'INICIA SESIÓN'
                                    : 'CREA TU CUENTA'}
                            </button>
                        </p>
                        {mode === 'login' && (
                            <a
                                className="text-center text-sm underline"
                                href="/forgot-password"
                            >
                                Recupera tu acceso
                            </a>
                        )}
                        <p className="border-t border-border pt-3 text-xs leading-5 text-muted-foreground">
                            Tu cuenta JAKAWI también te da acceso a beneficios,
                            descuentos, premios y experiencias para vivir más tu
                            ciudad.
                        </p>
                    </DialogContent>
                </Dialog>
            )}
        </Acquisition.Provider>
    );
}
