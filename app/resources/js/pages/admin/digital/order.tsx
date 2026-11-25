import { Head, useForm, router } from '@inertiajs/react';
import { digitalMoney } from '@/components/digital-card';
import { digitalState } from '@/components/digital-state';
import AdminLayout from '../layout';
type Payment = {
    id: number;
    status: string;
    review_note: string | null;
    bank_reference: string | null;
};
type Order = {
    reference: string;
    product_name: string;
    status: string;
    amount: string;
    currency: string;
    member_at_purchase: boolean;
    buyer: { name: string; email: string };
    collection: {
        bank: string;
        account: string;
        holder: string;
        account_key: string;
    };
    payments: Payment[];
    access: {
        id: number;
        status: string;
        delivery_status: string;
        delivery_attempts: number;
    } | null;
};
function Review({
    payment: p,
    order: o,
}: {
    payment: Payment;
    order: Order;
}) {
    const confirm = useForm({
        bank_reference: '',
        bank_date: '',
        received_amount: '',
        currency: o.currency,
        account_key: o.collection.account_key,
        bank_verified: false,
        statement: null as File | null,
    });
    const reject = useForm({ reason: '' });
    return (
        <section className="mt-5 rounded-xl border border-border p-5">
            <h2 className="font-bold">
                Pago #{p.id} · {digitalState(p.status)}
            </h2>
            <a
                className="mt-3 inline-flex min-h-11 items-center text-brand underline"
                href={`/admin/digital/pagos/${p.id}/receipt`}
            >
                Descargar comprobante
            </a>
            {p.bank_reference ? (
                <>
                    <p>Ingreso: {p.bank_reference}</p>
                    <a
                        className="text-brand underline"
                        href={`/admin/digital/pagos/${p.id}/statement`}
                    >
                        Evidencia bancaria
                    </a>
                </>
            ) : null}
            {p.review_note ? <p>{p.review_note}</p> : null}
            {p.status === 'under_review' ? (
                <>
                    <form
                        className="mt-4 grid gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            confirm.post(
                                `/admin/digital/pagos/${p.id}/confirmar`,
                                { forceFormData: true },
                            );
                        }}
                    >
                        <p className="text-sm">
                            Verifica en el banco el abono real a{' '}
                            {o.collection.bank} · {o.collection.account} ·{' '}
                            {o.collection.holder}. El comprobante del comprador
                            no confirma el pago.
                        </p>
                        <label className="grid gap-2">
                            Referencia única de la operación bancaria
                            <input
                                required
                                value={confirm.data.bank_reference}
                                onChange={(e) =>
                                    confirm.setData(
                                        'bank_reference',
                                        e.target.value,
                                    )
                                }
                                className="min-h-12 rounded-xl border border-border bg-surface px-3"
                            />
                        </label>
                        <label className="grid gap-2">
                            Fecha del ingreso
                            <input
                                required
                                type="date"
                                value={confirm.data.bank_date}
                                onChange={(e) =>
                                    confirm.setData('bank_date', e.target.value)
                                }
                                className="min-h-12 rounded-xl border border-border bg-surface px-3"
                            />
                        </label>
                        <label className="grid gap-2">
                            Importe realmente ingresado ({o.currency})
                            <input
                                required
                                type="number"
                                step="0.01"
                                min="0.01"
                                value={confirm.data.received_amount}
                                onChange={(e) =>
                                    confirm.setData(
                                        'received_amount',
                                        e.target.value,
                                    )
                                }
                                className="min-h-12 rounded-xl border border-border bg-surface px-3"
                            />
                        </label>
                        <label className="grid gap-2">
                            Extracto/evidencia bancaria PDF (máx. 25 MB)
                            <input
                                required
                                type="file"
                                accept="application/pdf"
                                onChange={(e) =>
                                    confirm.setData(
                                        'statement',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </label>
                        <label className="flex items-start gap-3">
                            <input
                                type="checkbox"
                                className="mt-1 size-5 shrink-0"
                                required
                                checked={confirm.data.bank_verified}
                                onChange={(e) =>
                                    confirm.setData(
                                        'bank_verified',
                                        e.target.checked,
                                    )
                                }
                            />
                            He contrastado el ingreso real, cuenta, moneda,
                            importe y referencia; no es una aprobación basada
                            solo en el comprobante.
                        </label>
                        {Object.values(confirm.errors).map((e, i) => (
                            <p
                                role="alert"
                                className="text-destructive"
                                key={i}
                            >
                                {e}
                            </p>
                        ))}
                        <button
                            disabled={confirm.processing}
                            className="min-h-12 rounded-xl bg-brand px-4 font-bold text-brand-foreground disabled:opacity-50"
                        >
                            Confirmar ingreso y conceder acceso
                        </button>
                    </form>
                    <form
                        className="mt-6 grid gap-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            reject.post(
                                `/admin/digital/pagos/${p.id}/rechazar`,
                            );
                        }}
                    >
                        <label className="grid gap-2">
                            Motivo de rechazo
                            <textarea
                                required
                                className="rounded-xl border border-border bg-surface p-3"
                                value={reject.data.reason}
                                onChange={(e) =>
                                    reject.setData('reason', e.target.value)
                                }
                            />
                        </label>
                        <button
                            disabled={reject.processing}
                            className="min-h-12 rounded-xl border border-border px-4 disabled:opacity-50"
                        >
                            Rechazar comprobante
                        </button>
                        {Object.values(reject.errors).map((e, i) => (
                            <p role="alert" key={i}>
                                {e}
                            </p>
                        ))}
                    </form>
                </>
            ) : null}
        </section>
    );
}
export default function Order({
    order: o,
    audit,


}: {
    order: Order;
    audit: {
        id: number;
        action: string;
        created_at: string;
        metadata: Record<string, unknown>;
    }[];


}) {
    const refund = useForm({
        reference: '',
        reason: '',
        refund_completed: false,
    });
    const incident = useForm({ reason: '' });
    const base = `/admin/digital/pedidos/${o.reference}`;
    return (
        <AdminLayout title="Revisión de pedido digital">
            <Head title="Pedido digital" />
            <h2 className="text-2xl font-bold">{o.product_name}</h2>
            <p className="mt-2">
                {o.buyer.name} · {o.buyer.email}
            </p>
            <p className="mt-2 break-all">{o.reference}</p>
            <p className="mt-3 font-bold">
                {digitalMoney(o.amount, o.currency)} · {digitalState(o.status)}{' '}
                · {o.member_at_purchase ? 'Con membresía' : 'Sin membresía'}
            </p>

            {o.payments.map((p) => (
                <Review key={p.id} payment={p} order={o}  />
            ))}
            {o.access ? (
                <section className="mt-6 rounded-xl border border-border p-5">
                    <h2 className="font-bold">
                        Acceso: {digitalState(o.access.status)}
                    </h2>
                    <p>
                        Entrega: {digitalState(o.access.delivery_status)} ·
                        Intentos: {o.access.delivery_attempts}
                    </p>
                    {o.access.delivery_status === 'failed' &&
                    o.access.status === 'active' ? (
                        <button

                            className="mt-3 min-h-11 rounded-xl border border-border px-4"
                            onClick={() =>
                                router.post(
                                    `/admin/digital/accesos/${o.access!.id}/reintentar`,
                                )
                            }
                        >
                            Reintentar entrega
                        </button>
                    ) : null}
                </section>
            ) : null}
            {o.status === 'confirmed' ? (
                <form
                    className="mt-6 grid gap-4 rounded-xl border border-border p-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        refund.post(base + '/devolucion');
                    }}
                >
                    <h2 className="font-bold">
                        Registrar devolución total realizada
                    </h2>
                    <input
                        aria-label="Referencia de devolución"
                        required
                        placeholder="Referencia bancaria de devolución"
                        className="min-h-12 rounded-xl border border-border bg-surface px-3"
                        value={refund.data.reference}
                        onChange={(e) =>
                            refund.setData('reference', e.target.value)
                        }
                    />
                    <textarea
                        aria-label="Motivo de devolución"
                        required
                        placeholder="Motivo"
                        className="rounded-xl border border-border bg-surface p-3"
                        value={refund.data.reason}
                        onChange={(e) =>
                            refund.setData('reason', e.target.value)
                        }
                    />
                    <label className="flex items-start gap-3">
                        <input
                            type="checkbox"
                            required
                            checked={refund.data.refund_completed}
                            onChange={(e) =>
                                refund.setData(
                                    'refund_completed',
                                    e.target.checked,
                                )
                            }
                            className="mt-1 size-5 shrink-0"
                        />
                        La devolución ya se realizó. Registrar revocará futuras
                        descargas.
                    </label>
                    <button
                        disabled={refund.processing}
                        className="min-h-12 rounded-xl border border-border"
                    >
                        Registrar y revocar acceso
                    </button>
                    {Object.values(refund.errors).map((e, i) => (
                        <p role="alert" key={i}>
                            {e}
                        </p>
                    ))}
                </form>
            ) : null}
            <form
                className="mt-6 grid gap-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    incident.post(base + '/incidencia', {
                        onSuccess: () => incident.reset(),
                    });
                }}
            >
                <label className="grid gap-2">
                    Incidencia o nota de soporte
                    <textarea
                        required
                        className="rounded-xl border border-border bg-surface p-3"
                        value={incident.data.reason}
                        onChange={(e) =>
                            incident.setData('reason', e.target.value)
                        }
                    />
                </label>
                <button className="min-h-12 rounded-xl border border-border">
                    Registrar incidencia
                </button>
            </form>
            <section className="mt-8">
                <h2 className="font-bold">Historial de auditoría</h2>
                {audit.map((a) => (
                    <div
                        key={a.id}
                        className="mt-3 rounded-xl border border-border p-3 text-sm"
                    >
                        <p>
                            {a.action} ·{' '}
                            {new Date(a.created_at).toLocaleString('es-BO')}
                        </p>
                        {typeof a.metadata.reason === 'string' ? (
                            <p>{a.metadata.reason}</p>
                        ) : null}
                    </div>
                ))}
            </section>
        </AdminLayout>
    );
}
