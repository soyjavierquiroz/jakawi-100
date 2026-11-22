import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    digitalMoney,
    type DigitalProductData,
} from '@/components/digital-card';
export default function DigitalShow({
    product: p,
    prelaunch,
    canCollect,
    authenticated,
    memberActive,
}: {
    product: DigitalProductData;
    prelaunch: boolean;
    canCollect: boolean;
    authenticated: boolean;
    memberActive: boolean;
}) {
    const { errors } = usePage().props;
    return (
        <>
            <Head title={p.name} />
            <main className="mx-auto max-w-5xl px-5 pt-8 pb-28">
                <Link
                    href="/productos-digitales"
                    className="text-brand underline"
                >
                    Productos digitales
                </Link>
                <div className="mt-6 grid gap-8 md:grid-cols-2">
                    {p.cover_url ? (
                        <img
                            src={p.cover_url}
                            alt={p.name}
                            className="w-full rounded-2xl"
                        />
                    ) : null}
                    <section>
                        <p className="text-sm font-bold text-brand">
                            Digital · disponible online
                        </p>
                        <h1 className="mt-3 text-3xl font-extrabold sm:text-4xl">
                            {p.name}
                        </h1>
                        <p className="mt-2 text-muted-foreground">
                            {p.partner.name}
                        </p>
                        <p className="mt-5 text-lg">{p.short_description}</p>
                        <p className="mt-6 text-2xl font-extrabold">
                            {digitalMoney(p.public_price, p.currency)}
                        </p>
                        {p.member_price ? (
                            <p className="mt-2">
                                Precio con membresía activa:{' '}
                                {digitalMoney(p.member_price, p.currency)}
                            </p>
                        ) : null}
                        {!prelaunch && canCollect ? (
                            <div className="mt-6">
                                {authenticated ? (
                                    <button
                                        onClick={() =>
                                            router.post(
                                                `/productos-digitales/${p.slug}/comprar`,
                                            )
                                        }
                                        className="min-h-12 rounded-xl bg-brand px-6 font-bold text-brand-foreground"
                                    >
                                        Comprar ·{' '}
                                        {digitalMoney(
                                            memberActive && p.member_price
                                                ? p.member_price
                                                : p.public_price,
                                            p.currency,
                                        )}
                                    </button>
                                ) : (
                                    <Link
                                        className="inline-flex min-h-12 items-center rounded-xl bg-brand px-6 font-bold text-brand-foreground"
                                        href={`/productos-digitales/${p.slug}/comprar`}
                                    >
                                        Iniciar sesión para comprar
                                    </Link>
                                )}
                                <p className="mt-3 text-sm text-muted-foreground">
                                    Un producto por pedido. Descarga privada
                                    tras verificar el pago.
                                </p>
                            </div>
                        ) : null}
                        {Object.values(errors ?? {}).map((e, i) => (
                            <p key={i} className="mt-3 text-destructive">
                                {String(e)}
                            </p>
                        ))}
                        <p className="mt-6 rounded-xl border border-border bg-surface p-4">
                            {prelaunch
                                ? 'Estamos en prelanzamiento. Las compras todavía no están habilitadas.'
                                : canCollect
                                  ? 'Pago por QR con verificación bancaria.'
                                  : 'Cobro pendiente de configurar'}
                        </p>
                    </section>
                </div>
                <section className="mt-10 space-y-6">
                    <div className="leading-7 whitespace-pre-wrap">
                        {p.description}
                    </div>
                    <div className="leading-7 whitespace-pre-wrap">
                        {p.commercial_content}
                    </div>
                    <h2 className="text-xl font-bold">Acceso y soporte</h2>
                    <p className="whitespace-pre-wrap">{p.access_terms}</p>
                    <p className="whitespace-pre-wrap text-muted-foreground">
                        {p.support_information}
                    </p>
                </section>
            </main>
        </>
    );
}
