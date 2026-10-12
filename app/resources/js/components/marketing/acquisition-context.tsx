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
import { LandingAction } from '@/components/marketing/landing-action-context';
import { useForm, usePage } from '@inertiajs/react';
import { createContext, useContext, useEffect, useState } from 'react';

export type AcquisitionContext = {
    type: 'BENEFIT' | 'EXPERIENCE' | 'CHALLENGE' | 'UNLOCK';
    subjectId: number;
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
    domainContent,
}: {
    children: React.ReactNode;
    domainContent: React.ReactNode;
}) {
    const page = usePage();
    const context = page.props.acquisition as AcquisitionContext | undefined;
    const user = (page.props.auth as { user?: { name: string } })?.user;
    const resumed = Boolean(page.props.acquisitionResumed);
    const [open, setOpen] = useState(resumed);
    const [mode, setMode] = useState<'register' | 'login'>('register');
    const [step, setStep] = useState(1);
    const [preparing, setPreparing] = useState(false);
    const [failure, setFailure] = useState('');
    const [collision, setCollision] = useState(false);
    const form = useForm({ name: '', email: '', whatsapp: '', password: '' });
    useEffect(() => {
        if (resumed) setOpen(true);
    }, [resumed]);
    const theme =
        (page.props.presentation as { marketing_theme?: string })
            ?.marketing_theme === 'DARK_CONVERSION'
            ? 'dark-conversion'
            : 'light-conversion';
    const switchMode = (next: 'register' | 'login') => {
        setMode(next);
        form.clearErrors();
        form.setData('password', '');
        setFailure('');
        setCollision(false);
    };
    const begin = (next: 'register' | 'login' = 'register') => {
        switchMode(next);
        setOpen(true);
    };
    const firstName = (user?.name || form.data.name).trim().split(/\s+/)[0];
    const promise = context?.title ?? '';
    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        if (!context || preparing || form.processing) return;
        if (mode === 'register' && step < 3) {
            setStep(step + 1);
            return;
        }
        setPreparing(true);
        setFailure('');
        try {
            const xsrf = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
            const csrfHeaders: Record<string, string> = xsrf
                ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf[1]) }
                : {
                      'X-CSRF-TOKEN':
                          document
                              .querySelector('meta[name="csrf-token"]')
                              ?.getAttribute('content') ?? '',
                  };
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
            form.transform((data) =>
                mode === 'register'
                    ? {
                          name: data.name,
                          email: data.email,
                          whatsapp: data.whatsapp,
                      }
                    : { email: data.email, password: data.password },
            );
            form.post(mode === 'register' ? '/register' : '/login', {
                preserveScroll: true,
                onSuccess: () => {
                    form.setData('password', '');
                },
                onError: (errors) => {
                    if (
                        mode === 'register' &&
                        (errors.email?.includes('YA TIENES') ||
                            errors.whatsapp?.includes('YA TIENES'))
                    ) {
                        setMode('login');
                        setCollision(true);
                        if (errors.whatsapp) form.setData('email', '');
                        form.clearErrors();
                    } else if (errors.name) setStep(1);
                    else if (errors.email && mode === 'register') setStep(2);
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
    const busy = preparing || form.processing;
    const finalAction = context
        ? {
              BENEFIT: /café/i.test(promise)
                  ? 'DAME MI CAFÉ'
                  : 'QUIERO MI BENEFICIO',
              EXPERIENCE: 'QUIERO VIVIR ESTA EXPERIENCIA',
              CHALLENGE: 'QUIERO PARTICIPAR',
              UNLOCK: 'QUIERO DESBLOQUEARLO',
          }[context.type]
        : '';
    const itemName = context
        ? {
              BENEFIT: /café/i.test(promise) ? 'CAFÉ' : 'BENEFICIO',
              EXPERIENCE: 'EXPERIENCIA',
              CHALLENGE: 'RETO',
              UNLOCK: 'DESBLOQUEO',
          }[context.type]
        : '';
    const heading = user
        ? `TU ${itemName} SIGUE AQUÍ`
        : collision
          ? 'YA TIENES JAKAWI'
          : mode === 'login'
            ? 'INICIA SESIÓN PARA CONTINUAR'
            : step === 1
              ? context?.headline
              : step === 2
                ? `PERFECTO, ${firstName.toUpperCase()}. TU ${itemName} SIGUE AQUÍ.`
                : `ÚLTIMO PASO, ${firstName.toUpperCase()}.`;
    return (
        <LandingAction.Provider value={() => setOpen(true)}>
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
                            {!user && mode === 'register' && (
                                <p className="border-l-2 border-[var(--marketing-reward)] pl-2 text-xs font-extrabold">
                                    {step} de 3
                                </p>
                            )}
                            {user && resumed && (
                                <p className="text-sm font-extrabold text-success">
                                    ✓ CUENTA LISTA
                                </p>
                            )}
                            <DialogTitle className="pr-6 text-2xl leading-tight font-extrabold">
                                {heading}
                            </DialogTitle>
                            <DialogDescription>
                                {user
                                    ? `${firstName}, ${promise} sigue aquí.`
                                    : mode === 'login'
                                      ? `Inicia sesión para continuar con ${promise}.`
                                      : step === 1
                                        ? context.supportingCopy
                                        : step === 3
                                          ? 'Te tomará solo unos segundos más.'
                                          : 'Tu acceso te permite volver cuando quieras.'}
                            </DialogDescription>
                            <div className="border-l-4 border-[var(--marketing-reward)] pl-3">
                                <p className="font-bold">{promise}</p>
                                {context.provider && (
                                    <p className="text-sm text-muted-foreground">
                                        {context.provider}
                                    </p>
                                )}
                            </div>
                            {user ? (
                                domainContent
                            ) : (
                                <>
                                    <form
                                        onSubmit={submit}
                                        className="grid gap-4"
                                    >
                                        {mode === 'register' && step === 1 && (
                                            <div className="grid gap-2">
                                                <Label htmlFor="acquisition-name">
                                                    Primero, ¿cómo te llamas?
                                                </Label>
                                                <Input
                                                    key="name"
                                                    id="acquisition-name"
                                                    autoFocus
                                                    required
                                                    maxLength={255}
                                                    autoComplete="name"
                                                    value={form.data.name}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'name',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    message={form.errors.name}
                                                />
                                            </div>
                                        )}
                                        {(mode === 'login' || step === 2) && (
                                            <div className="grid gap-2">
                                                <Label htmlFor="acquisition-email">
                                                    {mode === 'login'
                                                        ? 'Correo'
                                                        : '¿A qué correo te enviamos tu acceso a JAKAWI para que puedas volver cuando quieras?'}
                                                </Label>
                                                <Input
                                                    key="email"
                                                    id="acquisition-email"
                                                    autoFocus
                                                    required
                                                    type="email"
                                                    maxLength={255}
                                                    autoComplete="email"
                                                    placeholder="correo@email.com"
                                                    value={form.data.email}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'email',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    message={form.errors.email}
                                                />
                                            </div>
                                        )}
                                        {mode === 'register' && step === 3 && (
                                            <div className="grid gap-2">
                                                <Label htmlFor="acquisition-whatsapp">
                                                    ¿Cuál es tu WhatsApp?
                                                </Label>
                                                <Input
                                                    key="phone"
                                                    id="acquisition-whatsapp"
                                                    autoFocus
                                                    required
                                                    type="tel"
                                                    autoComplete="tel"
                                                    placeholder="+591 ..."
                                                    value={form.data.whatsapp}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'whatsapp',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        form.errors.whatsapp
                                                    }
                                                />
                                                <p className="text-xs text-muted-foreground">
                                                    Al continuar dejamos tu
                                                    cuenta lista. Después
                                                    revisarás los requisitos y
                                                    confirmarás tu acción.
                                                </p>
                                            </div>
                                        )}
                                        {mode === 'login' && (
                                            <div className="grid gap-2">
                                                <Label htmlFor="acquisition-password">
                                                    Contraseña
                                                </Label>
                                                <PasswordInput
                                                    id="acquisition-password"
                                                    required
                                                    autoComplete="current-password"
                                                    value={form.data.password}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'password',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        form.errors.password
                                                    }
                                                />
                                            </div>
                                        )}
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
                                                : mode === 'login'
                                                  ? 'INICIAR SESIÓN Y CONTINUAR'
                                                  : step < 3
                                                    ? 'CONTINUAR'
                                                    : finalAction}
                                        </Button>
                                    </form>
                                    {mode === 'register' && step > 1 && (
                                        <button
                                            type="button"
                                            disabled={busy}
                                            className="min-h-11 text-sm underline"
                                            onClick={() => {
                                                setStep(step - 1);
                                                form.clearErrors();
                                            }}
                                        >
                                            Volver
                                        </button>
                                    )}
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
                                </>
                            )}
                        </DialogContent>
                    </Dialog>
                )}
            </Acquisition.Provider>
        </LandingAction.Provider>
    );
}
