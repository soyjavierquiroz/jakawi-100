import { Head, Link } from '@inertiajs/react';
import AdminLayout from '../layout';
import { digitalMoney } from '@/components/digital-card';
type Product = {
    id: number;
    name: string;
    slug: string;
    status: string;
    public_price: string | null;
    currency: string;
    member_price: string | null;
    delivery_type: string;
    category: string | null;
    sale: {available:boolean; publish_missing:string[]};
};
export default function Products({
    products,
    partner,
    categoryLabels,
}: {
    products: { data: Product[]; prev_page_url?:string|null; next_page_url?:string|null };
    partner: { name: string };
    categoryLabels: Record<string,string>;
}) {
    return (
        <AdminLayout title="Productos digitales">
            <Head title="Productos digitales" />
            <p className="text-muted-foreground">
                {partner.name} · sin ubicación física
            </p>
            <div className="my-6 flex flex-wrap gap-3">
                <Link
                    className="rounded-xl bg-brand px-5 py-3 font-bold text-brand-foreground"
                    href="/admin/productos-digitales/crear"
                >
                    Crear producto
                </Link>
                <Link
                    className="rounded-xl border border-border px-5 py-3"
                    href="/productos-digitales"
                >
                    Ver catálogo
                </Link>
            </div>
            <div className="space-y-3">
                {products.data.map((p) => (
                    <Link
                        key={p.id}
                        href={`/admin/productos-digitales/${p.slug}/editar`}
                        className="block rounded-xl border border-border bg-surface p-5"
                    >
                        <span className="font-bold">{p.name}</span>
                        <p className="mt-2 text-sm">
                            {p.status === 'published'
                                ? 'Publicado'
                                : 'Borrador'}{' '}
                            · {p.delivery_type === 'hosted_app' ? 'APP' : 'PDF'} · {categoryLabels[p.category ?? ''] ?? 'Categoría pendiente'}
                            {' · Público: '}{digitalMoney(p.public_price, p.currency)}{' / Miembro: '}{digitalMoney(p.member_price, p.currency)}
                        </p>
                        <p className="mt-2 font-semibold">{p.sale.available ? 'Venta activa' : p.sale.publish_missing.length === 0 ? 'Listo para publicar y activar venta' : 'Venta pendiente'}</p>
                        {!!p.sale.publish_missing.length && <p className="mt-2 text-sm text-muted-foreground">{p.sale.publish_missing.join(' ')}</p>}
                    </Link>
                ))}
            </div>
            <nav aria-label="Páginas de productos" className="my-5 flex gap-5">{products.prev_page_url && <Link href={products.prev_page_url}>Anterior</Link>}{products.next_page_url && <Link href={products.next_page_url}>Siguiente</Link>}</nav>
            {!products.data.length ? (
                <p>
                    No hay productos creados. Los precios y materiales se
                    configuran aquí.
                </p>
            ) : null}
        </AdminLayout>
    );
}
