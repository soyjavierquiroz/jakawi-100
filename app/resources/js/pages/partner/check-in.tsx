import {
    OperationFeedback,
    type Partner,
} from '@/components/partner/operation-feedback';
import { useForm } from '@inertiajs/react';
import PartnerLayout from './layout';
export default function CheckIn({
    partner,
    error,
}: {
    partner: Partner;
    error?: string;
}) {
    const form = useForm({ code: '' });
    return (
        <PartnerLayout title="Registrar asistencia" partner={partner}>
            <OperationFeedback error={error} />
            <form
                className="mt-5 max-w-lg space-y-4 rounded-md border border-border bg-surface p-5"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/partner/${partner.slug}/asistencias`);
                }}
            >
                <h2 className="text-xl font-semibold">Buscar reserva</h2>
                <p className="text-sm text-muted-foreground">
                    Introduce los seis caracteres del código o abre el QR
                    firmado del miembro. Consultar una reserva no registra
                    asistencia.
                </p>
                <label className="grid gap-2" htmlFor="check-in-code">
                    Código de asistencia
                    <input
                        id="check-in-code"
                        name="code"
                        className="rounded-md border border-border bg-background p-3 uppercase"
                        maxLength={6}
                        minLength={6}
                        value={form.data.code}
                        onChange={(e) =>
                            form.setData('code', e.target.value.toUpperCase())
                        }
                        required
                        autoComplete="off"
                        aria-describedby={
                            form.errors.code ? 'code-error' : undefined
                        }
                    />
                </label>
                {form.errors.code && (
                    <p id="code-error" role="alert" className="text-sm">
                        {form.errors.code}
                    </p>
                )}
                <button
                    className="rounded-md bg-brand px-4 py-3 text-brand-foreground disabled:opacity-50"
                    disabled={form.processing}
                >
                    Buscar reserva
                </button>
            </form>
        </PartnerLayout>
    );
}
