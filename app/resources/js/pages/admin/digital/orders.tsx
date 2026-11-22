import { Head, Link, router } from '@inertiajs/react';
import { digitalMoney } from '@/components/digital-card';
import { digitalState } from '@/components/digital-state';
import AdminLayout from '../layout';
type Order = {
    id: number;
    reference: string;
    product_name: string;
    amount: string;
    currency: string;
    status: string;
    user: { name: string };
};
export default function Orders({
    orders,
    totals,
}: {
    orders: {
        data: Order[];
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    totals: { currency: string; gross: string; refunds: string }[];
}) {
    return (
        <AdminLayout title="Pedidos digitales">
            <Head title="Pedidos digitales" />
            <p className="text-muted-foreground">
                Contabilidad de JAKAWI Digital · separada de membresías
            </p>
            <div className="my-5 flex flex-wrap gap-3">
                {totals.map((t) => (
                    <p
                        key={t.currency}
                        className="rounded-xl border border-border p-4"
                    >
                        Bruto cobrado: {digitalMoney(t.gross, t.currency)} ·
                        Devuelto: {digitalMoney(t.refunds, t.currency)} · Cobro
                        tras devoluciones:{' '}
                        {digitalMoney(
                            (Number(t.gross) - Number(t.refunds)).toFixed(2),
                            t.currency,
                        )}
                    </p>
                ))}
            </div>
            <p className="mb-5 text-sm text-muted-foreground">
                Los importes no descuentan costes ni comisiones bancarias. No
                representan margen neto.
            </p>
            <form
                className="mb-5 flex gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get('/admin/digital/pedidos', {
                        q: new FormData(e.currentTarget).get('q') as string,
                    });
                }}
            >
                <input
                    name="q"
                    aria-label="Buscar pedido"
                    placeholder="Referencia o producto"
                    className="min-h-12 min-w-0 flex-1 rounded-xl border border-border bg-surface px-3"
                />
                <button className="rounded-xl border border-border px-4">
                    Buscar
                </button>
            </form>
            <div className="space-y-3">
                {orders.data.map((o) => (
                    <Link
                        className="block rounded-xl border border-border bg-surface p-5"
                        key={o.id}
                        href={`/admin/digital/pedidos/${o.reference}`}
                    >
                        <p className="font-bold">
                            {o.product_name} · {o.user.name}
                        </p>
                        <p className="mt-2">
                            {digitalMoney(o.amount, o.currency)} ·{' '}
                            {digitalState(o.status)}
                        </p>
                        <p className="mt-2 text-xs break-all text-muted-foreground">
                            {o.reference}
                        </p>
                    </Link>
                ))}
            </div>
            {!orders.data.length ? <p>No hay pedidos digitales.</p> : null}
            <nav className="mt-5 flex gap-4">
                {orders.prev_page_url ? (
                    <Link href={orders.prev_page_url}>Anterior</Link>
                ) : null}
                {orders.next_page_url ? (
                    <Link href={orders.next_page_url}>Siguiente</Link>
                ) : null}
            </nav>
        </AdminLayout>
    );
}
