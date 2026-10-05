import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    referralCode?: string | null;
};

export default function Register({ referralCode }: Props) {
    return (
        <>
            <Head title="Crear cuenta gratis" />
            <Form {...store.form()} disableWhileProcessing className="flex flex-col gap-6">
                {({ processing, errors }) => (
                    <>
                        {referralCode && <input type="hidden" name="referral_code" value={referralCode} />}
                        <div className="grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nombre</Label>
                                <Input id="name" name="name" type="text" required autoFocus autoComplete="name" placeholder="Tu nombre" />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Correo</Label>
                                <Input id="email" name="email" type="email" required autoComplete="email" placeholder="tu@correo.com" />
                                <InputError message={errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="whatsapp">WhatsApp</Label>
                                <Input id="whatsapp" name="whatsapp" type="tel" required autoComplete="tel" inputMode="tel" placeholder="71234567" aria-describedby="whatsapp-hint" />
                                <p id="whatsapp-hint" className="text-sm text-muted-foreground">Número de Bolivia; incluye el código de país si usas otro país.</p>
                                <InputError message={errors.whatsapp} />
                            </div>
                            <Button type="submit" className="mt-2 w-full" disabled={processing} data-test="register-user-button">
                                {processing && <Spinner />}
                                Crear cuenta gratis
                            </Button>
                        </div>
                        <div className="text-center text-sm text-muted-foreground">
                            ¿Ya tienes cuenta?{' '}
                            <TextLink href={login()}>Inicia sesión</TextLink>
                            {' · '}
                            <TextLink href="/forgot-password">Recupera tu acceso</TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'Crea tu cuenta gratis',
    description: 'Solo necesitamos tu nombre, correo y WhatsApp.',
};
