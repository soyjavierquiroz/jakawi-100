import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '../layout';
type Settings = {
    bank: string;
    account: string;
    holder: string;
    currency: string;
    enabled: boolean;
    exclusive_account: boolean;
};
export default function Settings({
    settings: s,
    ready,
}: {
    settings: Settings | null;
    ready: boolean;
}) {
    const form = useForm({
        bank: s?.bank ?? '',
        account: s?.account ?? '',
        holder: s?.holder ?? '',
        currency: s?.currency ?? 'BOB',
        enabled: s?.enabled ?? false,
        exclusive_account: s?.exclusive_account ?? false,
    });
    return (
        <AdminLayout title="QR y cuenta de JAKAWI Digital">
            <Head title="Cobro digital" />
            <p
                role="status"
                className="mb-5 rounded-xl border border-border p-4 font-semibold"
            >
                {ready
                    ? 'Cuenta autorizada para cobro digital'
                    : 'Cobro pendiente de configurar'}
            </p>
            <p className="mb-5 text-muted-foreground">
                La cuenta debe ser exclusiva de JAKAWI Digital. Cada producto tiene sus QR público y miembro de importe fijo en su editor. Esta
                configuración permite autorizar la venta de Glúteo Perfecto desde su editor. PRELAUNCH permanece activo para el resto de COM.
            </p>
            <form
                className="grid max-w-2xl gap-5"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/admin/digital/cobro', { forceFormData: true });
                }}
            >
                {(
                    [
                        ['bank', 'Banco'],
                        ['account', 'Cuenta receptora exclusiva'],
                        ['holder', 'Titular'],
                    ] as const
                ).map(([key, label]) => (
                    <label key={key} className="grid gap-2 font-semibold">
                        {label}
                        <input
                            required
                            className="min-h-12 rounded-xl border border-border bg-surface px-3"
                            value={form.data[key]}
                            onChange={(e) => form.setData(key, e.target.value)}
                        />
                    </label>
                ))}
                <label className="grid gap-2">
                    Moneda
                    <select
                        className="min-h-12 rounded-xl border border-border bg-surface px-3"
                        value={form.data.currency}
                        onChange={(e) =>
                            form.setData('currency', e.target.value)
                        }
                    >
                        <option>BOB</option>
                        <option>USD</option>
                    </select>
                </label>
                {(
                    [
                        [
                            'exclusive_account',
                            'Confirmo que esta cuenta es exclusiva de JAKAWI Digital y no recibe pagos de membresías.',
                        ],
                        [
                            'enabled',
                            'Autorizar esta cuenta para cobros de JAKAWI Digital.',
                        ],
                    ] as const
                ).map(([key, label]) => (
                    <label className="flex items-start gap-3" key={key}>
                        <input
                            type="checkbox"
                            className="mt-1 size-5 shrink-0"
                            checked={form.data[key]}
                            onChange={(e) =>
                                form.setData(key, e.target.checked)
                            }
                        />
                        {label}
                    </label>
                ))}
                {Object.values(form.errors).map((e, i) => (
                    <p role="alert" className="text-destructive" key={i}>
                        {e}
                    </p>
                ))}
                <button
                    disabled={form.processing}
                    className="min-h-12 rounded-xl bg-brand px-5 font-bold text-brand-foreground"
                >
                    Guardar configuración
                </button>
            </form>
        </AdminLayout>
    );
}
