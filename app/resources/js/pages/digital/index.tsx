import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    DigitalCard,
    type DigitalProductData,
} from '@/components/digital-card';
export default function DigitalIndex({
    products,
    query,
}: {
    products: {
        data: DigitalProductData[];
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    query: string;
}) {
    const [q, setQ] = useState(query);
    return (
        <>
            <Head title="Productos digitales" />
            <main className="mx-auto min-h-screen max-w-6xl px-5 pt-8 pb-28">
                <Link href="/explorar" className="text-sm text-brand underline">
                    Explorar
                </Link>
                <p className="mt-6 text-sm font-bold text-brand">
                    JAKAWI DIGITAL
                </p>
                <h1 className="mt-2 text-3xl font-extrabold sm:text-5xl">
                    Productos digitales
                </h1>
                <p className="mt-4 text-muted-foreground">
                    Digital · disponible online. Descubre contenido para abrir a
                    tu ritmo.
                </p>
                <form
                    className="mt-6 flex gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get('/productos-digitales', { q });
                    }}
                >
                    <input
                        aria-label="Buscar productos digitales"
                        placeholder="Buscar por nombre o contenido"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        className="min-h-12 min-w-0 flex-1 rounded-xl border border-border bg-surface px-4"
                    />
                    <button className="min-h-12 rounded-xl bg-brand px-5 font-bold text-brand-foreground">
                        Buscar
                    </button>
                </form>
                <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {products.data.map((p) => (
                        <DigitalCard key={p.id} product={p} />
                    ))}
                </div>
                {!products.data.length ? (
                    <p className="mt-10 rounded-2xl border border-border bg-surface p-6 text-muted-foreground">
                        Todavía no hay productos publicados para esta búsqueda.
                    </p>
                ) : null}
                <nav className="mt-6 flex gap-6" aria-label="Paginación">
                    {products.prev_page_url ? (
                        <Link href={products.prev_page_url}>Anterior</Link>
                    ) : null}
                    {products.next_page_url ? (
                        <Link href={products.next_page_url}>Siguiente</Link>
                    ) : null}
                </nav>
            </main>
        </>
    );
}
