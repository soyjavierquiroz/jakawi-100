import { Head, Link, router, useForm } from '@inertiajs/react';
import { digitalMoney } from '@/components/digital-card';
import { digitalState } from '@/components/digital-state';
export default function Order({
    order: o,
    prelaunch,
    canCollect,
}: {
    order: {
        reference: string;
        product_name: string;
        cover_url: string | null;
        public_price: string;
        status: string;
        amount: string;
        currency: string;
        expires_at: string;
        support_information: string | null;
        access_terms: string | null;
        member_at_purchase: boolean;
        collection: { bank: string; account: string; holder: string };
        payments: { id: number; status: string; review_note: string | null }[];
        access: { id: number; status: string; delivery_status: string } | null;
    };
    prelaunch: boolean;
    canCollect: boolean;
}) {
    const form = useForm({ receipt: null as File | null });
    const base = `/mi-jakawi/productos-digitales/pedidos/${o.reference}`;
    const reviewing = o.payments.some((payment) => payment.status === 'under_review');
    const payable = o.status === 'pending' && new Date(o.expires_at).getTime() > Date.now();
    const discount = Math.max(0, Number(o.public_price) - Number(o.amount)).toFixed(2);
    return (
        <>
            <Head title="Pedido digital" />
            <main className="mx-auto max-w-2xl px-5 pt-8 pb-28">
                <Link
                    className="text-brand underline"
                    href="/mi-jakawi/productos-digitales"
                >
                    Mi biblioteca digital
                </Link>
                <h1 className="mt-6 text-3xl font-extrabold">
                    {o.product_name}
                </h1>
                <p className="mt-4 text-sm break-all text-muted-foreground">
                    Referencia: {o.reference}
                </p>
                {o.cover_url && <img src={o.cover_url} alt={o.product_name} className="mt-5 max-h-56 w-full rounded-xl object-contain" />}
                <dl className="mt-5 space-y-3 rounded-2xl border border-border bg-surface p-5">
                    <div className="flex justify-between gap-4"><dt>Precio público</dt><dd>{digitalMoney(o.public_price, o.currency)}</dd></div>
                    {o.member_at_purchase && Number(discount) > 0 && <div className="flex justify-between gap-4 text-brand"><dt>Descuento de membresía</dt><dd>−{digitalMoney(discount, o.currency)}</dd></div>}
                    <div className="flex justify-between gap-4"><dt>Precio aplicado</dt><dd>{o.member_at_purchase ? 'Miembro' : 'Público'} · {digitalMoney(o.amount, o.currency)}</dd></div>
                    <div className="flex justify-between gap-4 border-t border-border pt-3 text-xl font-extrabold"><dt>Total</dt><dd>{digitalMoney(o.amount, o.currency)}</dd></div>
                </dl>
                <p className="mt-3 text-sm text-muted-foreground">Precio y QR fijados para la ventana de pago de este pedido.</p>
                <section role="status" className="mt-6 rounded-xl border border-border p-5">
                    {o.status === 'confirmed' ? <><h2 className="text-2xl font-extrabold">{o.access?.status === 'active' && o.access.delivery_status === 'ready' && !prelaunch ? '¡Tu producto está listo!' : 'Pago aprobado'}</h2><p className="mt-2">{o.access?.delivery_status === 'ready' && !prelaunch ? 'Encuentra tu producto en tu biblioteca digital.' : 'Estamos preparando tu acceso. Puedes consultar la entrega desde tu biblioteca.'}</p><Link href="/mi-jakawi/productos-digitales" className="mt-4 inline-flex min-h-12 items-center rounded-xl bg-brand px-5 font-bold text-brand-foreground">ABRIR MI BIBLIOTECA</Link></>
                    : reviewing ? <><h2 className="text-xl font-bold">Comprobante enviado</h2><p className="mt-2">Recibimos tu comprobante. Estamos verificando tu pago.</p><p className="mt-2 text-sm">No necesitas volver a pagar. El acceso se habilitará cuando confirmemos el ingreso bancario.</p><button onClick={() => router.reload({only: ['order', 'prelaunch', 'canCollect']})} className="mt-3 min-h-11 font-bold text-brand underline">Actualizar estado</button></>
                    : o.status === 'pending' && !payable ? <><h2 className="text-xl font-bold">Ventana de pago vencida</h2><p className="mt-2">Si ya pagaste, contacta con soporte e indica la referencia antes de realizar otro pago.</p></>
                    : o.payments[0]?.status === 'rejected' && o.status === 'pending' ? <><h2 className="text-xl font-bold">Pago rechazado</h2><p className="mt-2">{o.payments[0].review_note}</p><p className="mt-2">Revisa el motivo. Si el pago ya se realizó, adjunta un comprobante corregido sin volver a pagar. Si necesitas ayuda, contacta con soporte.</p></>
                    : o.status === 'pending' ? <><h2 className="text-xl font-bold">Pendiente de pago</h2><p className="mt-2">Realiza el pago utilizando este QR.</p></>
                    : <h2 className="text-xl font-bold">{digitalState(o.status)}</h2>}
                </section>
                {prelaunch ? (
                    <p
                        role="status"
                        className="mt-6 rounded-xl border border-border p-5"
                    >
                        Prelanzamiento: operaciones comerciales bloqueadas.
                    </p>
                ) : null}
                {payable && !reviewing && !prelaunch && canCollect ? (
                    <section className="mt-6 rounded-2xl border border-border bg-surface p-5">
                        <h2 className="text-xl font-bold">Pagar con QR</h2>
                        <p className="mt-3">
                            {o.collection.bank} · {o.collection.holder}
                        </p>
                        <p className="mt-1 break-all">
                            Cuenta: {o.collection.account}
                        </p>
                        <p className="mt-3 text-sm">
                            Este QR cobra el importe fijo del pedido. Incluye la referencia del pedido si tu banco lo permite. Comprueba el destinatario en tu banco antes
                            de pagar.
                        </p>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Válido hasta{' '}
                            {new Date(o.expires_at).toLocaleString('es-BO')}.
                        </p>
                        <img
                            src={base + '/qr'}
                            alt="QR de cobro JAKAWI Digital"
                            className="mx-auto my-6 max-h-80 max-w-full rounded-xl bg-white p-3"
                        />
                        {!o.payments.some(
                            (p) => p.status === 'under_review',
                        ) ? (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    form.post(base + '/comprobante', {
                                        forceFormData: true,
                                    });
                                }}
                            >
                                <label className="grid gap-3 font-semibold">
                                    Adjuntar comprobante
                                    <input
                                        required
                                        type="file"
                                        className="min-w-0 w-full max-w-full"
                                        accept="image/png,image/jpeg,application/pdf"
                                        onChange={(e) =>
                                            form.setData(
                                                'receipt',
                                                e.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                </label>
                                <p className="mt-3 text-sm text-muted-foreground">
                                    El comprobante inicia la revisión; el acceso
                                    se habilita al verificar el ingreso real.
                                </p>
                                <button
                                    disabled={form.processing}
                                    className="mt-4 min-h-12 rounded-xl bg-brand px-5 font-bold text-brand-foreground"
                                >
                                    Enviar a verificación
                                </button>
                            </form>
                        ) : (
                            <p className="font-bold">
                                Comprobante recibido. Pendiente de verificación
                                bancaria.
                            </p>
                        )}
                    </section>
                ) : null}
                {payable && !reviewing && !prelaunch && !canCollect ? (
                    <p className="mt-6">
                        Cobro pendiente de configurar o configuración
                        actualizada. Contacta con soporte antes de pagar.
                    </p>
                ) : null}
                {o.payments.map((p) => (
                    <p
                        className="mt-4 rounded-xl border border-border p-4"
                        key={p.id}
                    >
                        Pago: {digitalState(p.status)}
                        {p.review_note ? (
                            <span className="mt-2 block">{p.review_note}</span>
                        ) : null}
                    </p>
                ))}
                {o.access?.status === 'active' ? (
                    <div className="mt-6 rounded-xl border border-border p-5">
                        <p>
                            Pago confirmado · Entrega:{' '}
                            {digitalState(o.access.delivery_status)}
                        </p>
                        {o.access.delivery_status === 'ready' && !prelaunch ? (
                            <a
                                className="mt-3 inline-flex min-h-12 items-center rounded-xl bg-brand px-5 font-bold text-brand-foreground"
                                href={`/mi-jakawi/productos-digitales/accesos/${o.access.id}/pdf`}
                            >
                                Descargar PDF privado
                            </a>
                        ) : (
                            <p className="mt-3">
                                Puedes consultar este pedido y contactar con
                                soporte para recuperar la entrega.
                            </p>
                        )}
                    </div>
                ) : null}
                <section className="mt-6 space-y-3 rounded-xl border border-border p-5">
                    <h2 className="font-bold">Acceso y soporte</h2>
                    <p className="whitespace-pre-wrap text-sm">{o.access_terms}</p>
                    <p className="whitespace-pre-wrap text-sm text-muted-foreground">{o.support_information}</p>
                </section>
                {Object.values(form.errors).map((e, i) => (
                    <p role="alert" className="mt-3 text-destructive" key={i}>
                        {e}
                    </p>
                ))}
            </main>
        </>
    );
}
