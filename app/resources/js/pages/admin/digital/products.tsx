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
};
export default function Products({
    products,
    partner,
}: {
    products: { data: Product[] };
    partner: { name: string };
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
                            · {digitalMoney(p.public_price, p.currency)}
                        </p>
                    </Link>
                ))}
            </div>
            {!products.data.length ? (
                <p>
                    No hay productos creados. Los precios y materiales se
                    configuran aquí.
                </p>
            ) : null}
        </AdminLayout>
    );
}
