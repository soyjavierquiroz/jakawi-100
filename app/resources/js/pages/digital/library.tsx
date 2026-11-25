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
    giftAccess = {data: [], next_page_url: null, prev_page_url: null},
}: {
    giftAccess?: {data: {id: number; gift_product_name: string; digital_allowed: boolean; status: string; delivery_status: string}[]; next_page_url: string | null; prev_page_url: string | null};
    orders: {
        data: Order[];
        next_page_url: string | null;
        prev_page_url: string | null;
    };
}) {
    const { prelaunchMode, flash } = usePage<{ prelaunchMode: boolean; flash?: { success?: string } }>().props;
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
                {flash?.success === '¡Tu regalo está listo!' && <section role="status" className="mt-6 rounded-2xl border border-brand/30 bg-brand-subtle p-5"><h2 className="text-2xl font-extrabold">¡Tu regalo está listo!</h2><p className="mt-2 text-muted-foreground">Ya puedes disfrutarlo desde Mis productos digitales.</p><a href="#mis-productos" className="mt-4 inline-flex min-h-12 items-center rounded-xl bg-brand px-5 font-bold text-brand-foreground">ABRIR MI BIBLIOTECA</a></section>}
                <div id="mis-productos" className="mt-7 scroll-mt-24 space-y-4">
                    {giftAccess.data.map(access => <article key={`gift-${access.id}`} className="rounded-2xl border border-border bg-surface p-5"><h2 className="text-xl font-bold">{access.gift_product_name}</h2><p className="mt-2">Regalo · {digitalState(access.status)}</p>{access.status === 'active' && access.delivery_status === 'ready' && access.digital_allowed && <a className="mt-3 inline-flex min-h-12 items-center font-bold text-brand underline" href={`/mi-jakawi/productos-digitales/accesos/${access.id}/pdf`}>Descargar PDF</a>}</article>)}
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
                {!orders.data.length && !giftAccess.data.length ? (
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
                <nav aria-label="Regalos digitales" className="mt-6 flex gap-5">{giftAccess.prev_page_url && <Link href={giftAccess.prev_page_url}>Regalos anteriores</Link>}{giftAccess.next_page_url && <Link href={giftAccess.next_page_url}>Más regalos</Link>}</nav>
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
