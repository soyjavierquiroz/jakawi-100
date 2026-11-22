import { Head, Link, usePage } from '@inertiajs/react';
import { digitalMoney } from '@/components/digital-card';
import { digitalState } from '@/components/digital-state';
type Order = {
    id: number;
    reference: string;
    digital_allowed: boolean;
    product_name: string;
    amount: string;
    currency: string;
    status: string;
    access: { id: number; status: string; delivery_status: string } | null;
};
export default function Library({
    orders,
}: {
    orders: {
        data: Order[];
        next_page_url: string | null;
        prev_page_url: string | null;
    };
}) {
    const { prelaunchMode } = usePage<{ prelaunchMode: boolean }>().props;
    return (
        <>
            <Head title="Mis productos digitales" />
            <main className="mx-auto max-w-4xl px-5 pt-8 pb-28">
                <Link className="text-brand underline" href="/mi-jakawi">
                    Mi JAKAWI
                </Link>
                <h1 className="mt-6 text-3xl font-extrabold">
                    Mis productos digitales
                </h1>
                <p className="mt-3 text-muted-foreground">
                    Tus pedidos y PDF adquiridos, siempre en tu cuenta.
                </p>
                <div className="mt-7 space-y-4">
                    {orders.data.map((o) => (
                        <article
                            key={o.id}
                            className="rounded-2xl border border-border bg-surface p-5"
                        >
                            <h2 className="text-xl font-bold">
                                {o.product_name}
                            </h2>
                            <p className="mt-2">
                                {digitalMoney(o.amount, o.currency)} ·{' '}
                                {digitalState(o.status)}
                            </p>
                            {o.access ? (
                                <p className="mt-2 text-sm">
                                    Acceso: {digitalState(o.access.status)} ·{' '}
                                    {o.access.status === 'active' ? digitalState(o.access.delivery_status) : 'Descarga no disponible'}
                                </p>
                            ) : null}
                            <div className="mt-3 flex flex-wrap gap-4">
                                <Link
                                    href={`/mi-jakawi/productos-digitales/pedidos/${o.reference}`}
                                    className="inline-flex min-h-11 items-center text-brand underline"
                                >
                                    Ver pedido
                                </Link>
                                {o.access?.status === 'active' &&
                                o.access.delivery_status === 'ready' &&
                                (!prelaunchMode || o.digital_allowed) ? (
                                    <a
                                        className="inline-flex min-h-11 items-center font-bold text-brand underline"
                                        href={`/mi-jakawi/productos-digitales/accesos/${o.access.id}/pdf`}
                                    >
                                        Descargar PDF
                                    </a>
                                ) : null}
                            </div>
                        </article>
                    ))}
                </div>
                {!orders.data.length ? (
                    <p className="mt-8 rounded-xl border border-border p-5">
                        Aún no tienes productos digitales.{' '}
                        <Link
                            href="/productos-digitales"
                            className="text-brand underline"
                        >
                            Explorar catálogo
                        </Link>
                    </p>
                ) : null}
                <nav className="mt-6 flex gap-5">
                    {orders.prev_page_url ? (
                        <Link href={orders.prev_page_url}>Anterior</Link>
                    ) : null}
                    {orders.next_page_url ? (
                        <Link href={orders.next_page_url}>Siguiente</Link>
                    ) : null}
                </nav>
            </main>
        </>
    );
}
