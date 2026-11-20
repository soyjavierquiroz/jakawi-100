import {
    OperationFeedback,
    useOperationalAccess,
    type Partner,
} from '@/components/partner/operation-feedback';
import { useForm } from '@inertiajs/react';
import PartnerLayout from '../partner/layout';
type Result = { benefit_title: string; location_name: string; status: string };
export default function ValidateRedemption({
    partner,
    result,
    error,
    message,
}: {
    partner: Partner;
    result?: Result;
    error?: string;
    message?: string;
}) {
    const form = useForm({ code: '', pin: '' });
    const { blocked } = useOperationalAccess();
    return (
        <PartnerLayout title="Validar canje" partner={partner}>
            <OperationFeedback error={error} success={message} />
            {result && (
                <section className="mt-4 max-w-lg rounded-md border border-success/40 bg-surface p-5">
                    <h2 className="text-lg font-semibold">
                        {result.benefit_title}
                    </h2>
                    <p className="mt-1 text-sm">{result.location_name}</p>
                    <p className="mt-2 text-sm">
                        El canje figura confirmado en el historial.
                    </p>
                </section>
            )}
            <form
                className="mt-5 grid max-w-lg gap-4 rounded-md border border-border bg-surface p-5"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/partner/${partner.slug}/validar`, {
                        preserveScroll: true,
                        onFinish: () => form.reset('pin'),
                    });
                }}
            >
                <h2 className="text-xl font-semibold">Validación manual</h2>
                <p className="text-sm text-muted-foreground">
                    Comprueba la sede del código y utiliza su PIN. Un código
                    solo puede confirmarse una vez.
                </p>
                <label className="grid gap-2" htmlFor="redemption-code">
                    Código de canje
                    <input
                        id="redemption-code"
                        name="code"
                        className="rounded-md border border-border bg-background p-3 uppercase"
                        value={form.data.code}
                        onChange={(e) =>
                            form.setData('code', e.target.value.toUpperCase())
                        }
                        maxLength={6}
                        minLength={6}
                        required
                        autoComplete="off"
                    />
                </label>
                {form.errors.code && (
                    <p role="alert" className="text-sm">
                        {form.errors.code}
                    </p>
                )}
                <label className="grid gap-2" htmlFor="redemption-pin">
                    PIN de la sede
                    <input
                        id="redemption-pin"
                        name="pin"
                        type="password"
                        autoComplete="off"
                        className="rounded-md border border-border bg-background p-3"
                        value={form.data.pin}
                        onChange={(e) => form.setData('pin', e.target.value)}
                        inputMode="numeric"
                        pattern="[0-9]{6}"
                        maxLength={6}
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
                    Validar canje
                </button>
            </form>
        </PartnerLayout>
    );
}
