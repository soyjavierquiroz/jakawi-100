import { useDigitalGiftAction, type GiftAction } from '@/components/digital-gift-action';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    digitalMoney,
    type DigitalProductData,
} from '@/components/digital-card';
export default function DigitalShow({
    product: p,

    canCollect,
    authenticated,
    memberActive,
    preview = false,
    giftAction,
}: {
    product: DigitalProductData;

    canCollect: boolean;
    authenticated: boolean;
    memberActive: boolean;
    preview?: boolean;
    giftAction?: GiftAction;
}) {
    const gift = useDigitalGiftAction(giftAction);
    const { errors } = usePage().props;
    return (
        <>
            {gift.confirmation}
            <Head title={p.name}>{preview && <meta name="robots" content="noindex,nofollow" />}</Head>
            <main className="mx-auto w-full max-w-6xl px-[var(--space-gutter)] pt-8 pb-28 font-discovery text-foreground sm:px-6 lg:px-8 lg:pt-10">
                {preview && <p role="status" className="mb-5 rounded-xl border border-border bg-surface p-4">Vista previa de ficha · Borrador · Compras desactivadas</p>}
                <Link
                    href="/productos-digitales"
                    className="text-sm font-semibold text-brand underline underline-offset-4"
                >
                    Productos digitales
                </Link>
                <div className="mt-6 grid gap-8 md:grid-cols-2">
                    {p.cover_url ? (
                        <img
                            src={p.cover_url}
                            alt={p.name}
                            className="aspect-[5/4] w-full rounded-[var(--radius-card)] object-cover"
                        />
                    ) : null}
                    <section>
                        <p className="discovery-eyebrow text-brand">
                            {giftAction?.kind === 'welcome' ? 'INCLUIDO CON TU MEMBRESÍA' : p.coming_soon ? 'Digital · Próximamente' : (canCollect ? 'Disponible' : 'Digital')}
                        </p>
                        <h1 className="mt-3 text-[2rem] leading-tight font-extrabold tracking-[-0.05em] sm:text-5xl">
                            {p.name}
                        </h1>
                        <p className="mt-2 text-muted-foreground">
                            {p.partner.name}
                        </p>
                        <p className="mt-5 text-lg">{p.short_description}</p>
                        <p className="mt-6 text-2xl font-extrabold">
                            {p.public_price ? digitalMoney(memberActive && p.member_price ? p.member_price : p.public_price, p.currency) : (p.coming_soon ? 'Disponible próximamente' : 'Precio por configurar')}
                        </p>
                        {p.member_price ? (
                            <p className="mt-2">
                                {memberActive ? 'Precio normal:' : 'Precio con membresía activa:'}{' '}
                                {digitalMoney(memberActive ? p.public_price : p.member_price, p.currency)}
                            </p>
                        ) : null}
                        {!preview && giftAction?.kind === 'welcome' && <section className="mt-6 rounded-2xl border border-brand/30 bg-brand-subtle p-5"><h2 className="text-xl font-extrabold">Tu membresía incluye este regalo</h2><p className="mt-2 leading-relaxed text-muted-foreground">Puedes activar este producto sin costo adicional y guardarlo en tu biblioteca digital.</p></section>}
                        {!preview && giftAction?.kind ? <button onClick={gift.click} className="mt-6 min-h-12 rounded-xl bg-brand px-6 font-bold text-brand-foreground">{gift.label}</button> : !p.coming_soon && !preview && canCollect && p.public_price ? (
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
                                        COMPRAR AHORA ·{' '}
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
                                        COMPRAR AHORA
                                    </Link>
                                )}
                                <p className="mt-3 text-sm text-muted-foreground">
                                    Un producto por pedido. Descarga privada
                                    tras verificar el pago.
                                </p>
                            </div>
                        ) : null}
                        {!preview && giftAction?.kind === 'welcome' && canCollect && !p.coming_soon && <div className="mt-4">
                            <button onClick={() => router.post(`/productos-digitales/${p.slug}/comprar`)} className="min-h-12 rounded-xl border border-brand px-6 font-bold text-brand">COMPRAR · {digitalMoney(memberActive && p.member_price ? p.member_price : p.public_price, p.currency)}</button>
                            <p className="mt-2 text-sm text-muted-foreground">Comprar conserva tu regalo pendiente para otro producto.</p>
                        </div>}
                        {Object.values(errors ?? {}).map((e, i) => (
                            <p key={i} className="mt-3 text-destructive">
                                {String(e)}
                            </p>
                        ))}
                        {giftAction?.kind !== 'welcome' && <p className="mt-6 rounded-xl border border-border bg-surface p-4">
                            {giftAction?.kind === 'owned' ? 'Este producto ya está en tu biblioteca digital.' : giftAction?.kind ? 'Confirma tu regalo para guardarlo en Mis productos digitales.' : p.coming_soon ? 'Presentación editorial en revisión. Las compras y la entrega todavía no están habilitadas.'
                                : canCollect
                                  ? 'Pago por QR con verificación bancaria.'
                                  : 'Cobro pendiente de configurar'}
                        </p>}
                    </section>
                </div>
                {!!p.gallery_urls?.length && <div className="mt-8 grid gap-4 sm:grid-cols-3">{p.gallery_urls.map((url,i)=><img key={i} src={url} alt={`${p.name} · Imagen ${i+1}`} loading="lazy" className="w-full rounded-xl" />)}</div>}
                <section className="mt-10 space-y-6">
                    <div className="leading-7 whitespace-pre-wrap">
                        {p.commercial_content?.trim() || p.description}
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
