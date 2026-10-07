import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import SaleCreate from '../../promoter/sales/create';

type PurchaseRequest = { id: number; user: { id: number; name: string; email: string } };

export default function AdminSaleCreate(props: { purchaseRequest?: PurchaseRequest | null; customers: Array<{ id: number; name: string; email: string }>; membership: { price_bob: number; duration_days: number } }) {
    const purchaseRequest = props.purchaseRequest;
    const { data, setData, post, processing, errors } = useForm({
        user_id: purchaseRequest?.user.id ?? 0,
        membership_purchase_request_id: purchaseRequest?.id ?? 0,
        manual_reference: '',
        idempotency_key: crypto.randomUUID(),
    });
    if (!purchaseRequest) return <SaleCreate {...props} endpoint="/admin/sales" back="/admin/sales" />;
    const submit = (event: FormEvent) => { event.preventDefault(); post('/admin/sales'); };
    return <main className="mx-auto min-h-screen max-w-xl bg-background p-5 text-foreground">
        <Head title="Registrar venta asistida" />
        <Link href={`/admin/solicitudes-membresia/${purchaseRequest.id}`} className="text-sm text-brand">← Solicitud</Link>
        <h1 className="mt-5 text-3xl font-extrabold">Registrar venta asistida</h1>
        <p className="mt-3">{purchaseRequest.user.name} · {purchaseRequest.user.email}</p>
        <p className="mt-2 text-sm text-muted-foreground">Bs{props.membership.price_bob} · {props.membership.duration_days} días. Registra la venta solo después de confirmar el cobro por el canal asistido.</p>
        <form onSubmit={submit} className="mt-6 grid gap-4">
            <label className="grid gap-2 font-semibold">Referencia manual / recibo
                <input required value={data.manual_reference} onChange={event => setData('manual_reference', event.target.value)} className="min-h-12 rounded-xl border border-border bg-surface px-4" />
            </label>
            {errors.manual_reference ? <p className="text-sm text-destructive">{errors.manual_reference}</p> : null}
            {errors.membership_purchase_request_id ? <p className="text-sm text-destructive">{errors.membership_purchase_request_id}</p> : null}
            <button disabled={processing} className="min-h-12 rounded-xl bg-brand px-4 font-bold text-brand-foreground disabled:opacity-50">Confirmar y activar</button>
        </form>
    </main>;
}
