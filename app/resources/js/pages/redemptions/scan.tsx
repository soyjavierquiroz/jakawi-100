import {
    OperationFeedback,
    useOperationalAccess,
    type Partner,
} from '@/components/partner/operation-feedback';
import { useForm } from '@inertiajs/react';
import PartnerLayout from '../partner/layout';
type Redemption = {
    benefit_title: string;
    location_name: string;
    status: string;
    expires_at: string;
    confirmed_at: string | null;
};
export default function Scan({
    partner,
    redemption: r,
    error,
    message,
}: {
    partner: Partner;
    redemption: Redemption;
    error?: string;
    message?: string;
}) {
    const form = useForm({ pin: '' });
    const { blocked } = useOperationalAccess();
    const done = r.status === 'confirmed';
    const expired =
        r.status === 'expired' ||
        (!done && new Date(r.expires_at).getTime() <= Date.now());
    return (
        <PartnerLayout title="Confirmar canje" partner={partner}>
            <OperationFeedback
                error={error}
                success={
                    message ||
                    (done
                        ? 'Canje previamente utilizado. Ya está confirmado.'
                        : undefined)
                }
            />
            <section className="mt-5 max-w-lg space-y-4 rounded-md border border-border bg-surface p-5">
                <h2 className="text-xl font-semibold">
                    {done ? 'Canje confirmado' : 'Revisar canje'}
                </h2>
                <p className="font-medium">{r.benefit_title}</p>
                <p>{r.location_name}</p>
                {done ? (
                    <p className="text-sm">
                        Este código ya fue utilizado. No se registrará otro
                        canje.
                    </p>
                ) : expired ? (
                    <p role="alert">
                        Código expirado. El miembro debe generar un nuevo
                        código.
                    </p>
                ) : r.status !== 'pending' ? (
                    <p role="alert">
                        Este canje ya no está pendiente y no puede confirmarse.
                    </p>
                ) : (
                    <form
                        className="grid gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(
                                window.location.pathname +
                                    window.location.search,
                                { onFinish: () => form.reset('pin') },
                            );
                        }}
                    >
                        <p className="text-sm text-muted-foreground">
                            Comprueba la sede y utiliza su PIN para confirmar
                            una sola vez.
                        </p>
                        <label className="grid gap-2" htmlFor="scan-pin">
                            PIN de la sede
                            <input
                                id="scan-pin"
                                name="pin"
                                type="password"
                                autoComplete="off"
                                className="rounded-md border border-border bg-background p-3"
                                inputMode="numeric"
                                pattern="[0-9]{6}"
                                maxLength={6}
                                value={form.data.pin}
                                onChange={(e) =>
                                    form.setData('pin', e.target.value)
                                }
                                required
                            />
                        </label>
                        {form.errors.pin && (
                            <p role="alert" className="text-sm">
                                {form.errors.pin}
                            </p>
                        )}
                        <button
                            className="rounded-md bg-brand px-4 py-3 text-brand-foreground disabled:opacity-50"
                            disabled={blocked || form.processing}
                        >
                            Confirmar canje
                        </button>
                    </form>
                )}
            </section>
        </PartnerLayout>
    );
}
