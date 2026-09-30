import { Form, Head, setLayoutProps } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
/* @chisel-registration */
import { register } from '@/routes';
/* @end-chisel-registration */
import { store } from '@/routes/login';
import { request } from '@/routes/password';
/* @chisel-passkeys */
import PasskeyVerify from '@/components/passkey-verify';
/* @end-chisel-passkeys */

type Props = {
    status?: string;
    canResetPassword: boolean;
    partnerPortal?: boolean;
};

export default function Login({ status, canResetPassword, partnerPortal = false }: Props) {
    setLayoutProps({
        title: partnerPortal ? 'GESTIONA TU PORTAL PARTNER' : 'VUELVE A VIVIR MÁS TU CIUDAD',
        description: partnerPortal
            ? 'Ingresa para gestionar reservas y validar beneficios.'
            : 'Ingresa para seguir descubriendo planes que valen la pena.',
    });

    return (
        <>
            <Head title={partnerPortal ? 'Portal Partner' : 'Iniciar sesión'} />

            {/* @chisel-passkeys */}
            <PasskeyVerify
                label="Ingresar con passkey"
                loadingLabel="Verificando passkey..."
                separator="O CONTINUAR CON CORREO"
                buttonClassName="h-12 rounded-[var(--radius-control)] border-border bg-surface font-discovery font-bold text-foreground shadow-none hover:bg-surface-muted"
                separatorClassName="my-8"
            />
            {/* @end-chisel-passkeys */}

            <Form
                {...(partnerPortal ? { action: '/partner/login', method: 'post' as const } : store.form())}
                resetOnSuccess={['password']}
                className="flex flex-col gap-8"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            {partnerPortal && (
                                <p className="text-sm leading-6 text-muted-foreground">
                                    Ingresa para gestionar reservas y validar beneficios.
                                </p>
                            )}
                            <div className="grid gap-3">
                                <Label htmlFor="email" className="text-sm font-bold text-foreground">
                                    Correo electrónico
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="tu@email.com"
                                    className="h-12 rounded-[var(--radius-control)] border-border bg-surface px-4 font-sans shadow-none"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-3">
                                <div className="flex items-center">
                                    <Label htmlFor="password" className="text-sm font-bold text-foreground">
                                        Contraseña
                                    </Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-sm font-bold text-brand underline decoration-action decoration-2 underline-offset-4 hover:text-brand/80"
                                            tabIndex={5}
                                        >
                                            ¿Olvidaste tu contraseña?
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Ingresa tu contraseña"
                                    className="h-12 rounded-[var(--radius-control)] border-border bg-surface px-4 font-sans shadow-none"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember" className="text-sm text-foreground">Recordarme</Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 h-13 w-full rounded-[var(--radius-control)] bg-brand font-discovery text-sm font-extrabold tracking-[0.08em] text-brand-foreground uppercase shadow-none hover:bg-brand/90"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                INICIAR SESIÓN
                            </Button>
                        </div>

                        {/* @chisel-registration */}
                        <div className="border-t border-border pt-6 text-center text-sm leading-6 text-muted-foreground">
                            {partnerPortal ? (
                                <TextLink href="/login" tabIndex={5} className="font-bold text-foreground underline decoration-action decoration-2 underline-offset-4">
                                    Ingresar como miembro
                                </TextLink>
                            ) : <>
                                <div>
                                    ¿Eres Partner?{' '}
                                    <TextLink href="/partner/login" tabIndex={5} className="font-medium text-muted-foreground underline decoration-border underline-offset-4 hover:text-foreground hover:decoration-action">
                                        Portal Partner
                                    </TextLink>
                                </div>
                                <div className="mt-4">
                                    ¿Aún no tienes cuenta?{' '}
                                    <TextLink href={register()} tabIndex={5} className="font-extrabold tracking-[0.04em] text-foreground underline decoration-action decoration-2 underline-offset-4">
                                        CREAR CUENTA
                                    </TextLink>
                                </div>
                            </>}
                        </div>
                        {/* @end-chisel-registration */}
                    </>
                )}
            </Form>

            {status && (
                <div className="rounded-[var(--radius-control)] bg-success-surface px-4 py-3 text-center text-sm font-medium text-foreground">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'VUELVE A VIVIR MÁS TU CIUDAD',
    description: 'Ingresa para seguir descubriendo planes que valen la pena.',
};
