import { useDigitalGiftAction, type GiftAction } from '@/components/digital-gift-action';
import { digitalMoney, type DigitalProductData } from '@/components/digital-card';
import { JakawiImage } from '@/components/jakawi-image';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { digitalPurchaseClass } from '@/components/digital-purchase-style';
import { DigitalProductGallery } from '@/components/digital-product-gallery';

// Some legacy editorial fields contain escaped CRLF from import/editor entry.
// Normalize only line separators for display; retain saved plain text and React escaping.
const editorialText = (text: string | null) => text?.replace(/\\r\\n|\\n|\\r/g, '\n') ?? '';

export default function DigitalShow({ product: p, canCollect, authenticated, memberActive, preview = false, giftAction, pendingOrderPrice = null }: {
    product: DigitalProductData;
    canCollect: boolean;
    authenticated: boolean;
    memberActive: boolean;
    preview?: boolean;
    giftAction?: GiftAction;
    pendingOrderPrice?: string | null;
}) {
    const gift = useDigitalGiftAction(giftAction);
    const { errors } = usePage().props;
    const publicCents = Math.round(Number(p.public_price) * 100);
    const saving = p.member_saving;
    const hasDiscount = !!saving;
    const authorizedPrice = pendingOrderPrice ?? (memberActive && p.member_price ? p.member_price : p.public_price);
    const canBuy = !preview && !p.coming_soon && canCollect && publicCents > 0;
    const hosted = p.delivery_type === 'hosted_app';
    const buyClass = digitalPurchaseClass;
    const buyLabel = `${pendingOrderPrice ? 'CONTINUAR PEDIDO ·' : 'COMPRAR AHORA ·'} ${digitalMoney(authorizedPrice, p.currency)}`;
    const buy = authenticated ? <button type="button" onClick={() => router.post(`/productos-digitales/${p.slug}/comprar`)} className={buyClass}>{buyLabel}</button> : <Link href={`/productos-digitales/${p.slug}/comprar`} className={buyClass}>{buyLabel}</Link>;
    const fullDescription = editorialText(p.description).trim();
    const commercial = editorialText(p.commercial_content).trim();
    // Saved fields remain independent; an identical legacy value is rendered only once.
    const showCommercial = commercial && commercial !== fullDescription && commercial !== p.short_description?.trim();
    return <>
        {gift.confirmation}
        <Head title={p.name}>{preview && <meta name="robots" content="noindex,nofollow" />}</Head>
        <main className="mx-auto w-full max-w-6xl px-[var(--space-gutter)] pt-6 pb-24 font-discovery text-foreground sm:px-6 lg:px-8 lg:pt-10">
            {preview && <p role="status" className="mb-5 rounded-xl border border-border bg-surface p-4">Vista previa de ficha · Compras desactivadas</p>}
            <Link href="/productos-digitales" className="text-sm font-semibold text-brand underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand">Productos digitales</Link>
            <div className={`mt-6 grid min-w-0 gap-7 md:items-center md:gap-10 lg:gap-16 ${p.cover_url ? 'md:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]' : ''}`}>
                {p.cover_url && <div data-testid="product-cover" className="mx-auto flex aspect-[5/6] w-full max-w-[320px] items-center justify-center overflow-hidden rounded-[var(--radius-card)] border border-border/60 bg-surface-muted p-2 sm:max-w-[360px] md:aspect-[4/5] md:max-h-[520px] md:max-w-[416px]">
                    <JakawiImage src={p.cover_url} srcset={p.cover_srcset} sizes="(min-width: 768px) 416px, (min-width: 400px) 360px, 90vw" alt={`Portada de ${p.name}`} loading="eager" priority className="h-full min-h-0 w-full rounded-xl object-contain" />
                </div>}
                <section aria-labelledby="product-name" className="min-w-0">
                    <p className="discovery-eyebrow text-brand">PRODUCTO DIGITAL{p.coming_soon ? ' · PRÓXIMAMENTE' : ''}</p>
                    <h1 id="product-name" className="mt-3 text-[1.875rem] leading-[1.08] font-extrabold tracking-[-0.04em] break-words sm:text-4xl lg:text-5xl">{p.name}</h1>
                    {p.short_description?.trim() && <p className="mt-4 text-base leading-relaxed text-muted-foreground sm:text-lg">{p.short_description}</p>}
                    <div data-testid="product-prices" className="mt-6 rounded-2xl border border-brand/15 bg-surface p-5 shadow-sm sm:p-6">
                        {hasDiscount && <p className="text-xs font-bold tracking-[0.1em] text-brand">PRECIO ESPECIAL PARA MIEMBROS</p>}
                        <p className="mt-2 text-5xl leading-none font-extrabold tracking-tight text-foreground sm:text-[3.5rem]">{digitalMoney(hasDiscount ? p.member_price : p.public_price, p.currency)}</p>
                        {hasDiscount && <>
                            <p className="mt-4 flex flex-wrap items-baseline gap-x-2 text-sm font-medium text-muted-foreground">Precio regular: <span className="text-[1.375rem] font-bold text-foreground">{memberActive ? <s className="decoration-foreground/40">{digitalMoney(p.public_price, p.currency)}</s> : digitalMoney(p.public_price, p.currency)}</span></p>
                            <p className="mt-3 text-base leading-relaxed font-extrabold text-brand">Ahorras {digitalMoney(saving!.amount, p.currency)} ({saving!.percent}%)</p>
                            {!memberActive && <p className="mt-1 text-xs leading-relaxed text-muted-foreground">Disfruta este precio con tu membresía activa.</p>}
                        </>}
                    </div>
                    {!preview && giftAction?.kind === 'welcome' && <div className="mt-5"><h2 className="font-bold">Tu membresía incluye este regalo</h2><p className="mt-2 text-sm leading-relaxed text-muted-foreground">Puedes activar este producto sin costo adicional y guardarlo en tu biblioteca digital.</p></div>}
                    <div className="mt-5 flex flex-col items-start gap-3">
                        {!preview && giftAction?.kind ? <button type="button" onClick={gift.click} className={buyClass}>{gift.label}</button> : canBuy ? buy : null}
                        {preview && <button type="button" disabled className={buyClass}>{buyLabel} · Vista previa</button>}
                    </div>
                    {!preview && giftAction?.kind === 'welcome' && canBuy && <div className="mt-4">
                        <button type="button" onClick={() => router.post(`/productos-digitales/${p.slug}/comprar`)} className="min-h-12 rounded-xl border border-border px-5 py-3 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand">{buyLabel}</button>
                        <p className="mt-2 text-sm text-muted-foreground">Comprar conserva tu regalo pendiente para otro producto.</p>
                    </div>}
                    {Object.values(errors ?? {}).map((error, index) => <p key={index} role="alert" className="mt-3 text-destructive">{String(error)}</p>)}
                    <div className="mt-5 space-y-2 text-sm leading-relaxed text-muted-foreground">
                        {p.coming_soon ? <p>Presentación editorial en revisión. Las compras y la entrega todavía no están habilitadas.</p> : <>
                            <p className="font-semibold text-foreground">Pago con QR. Acceso inmediato.</p>
                            {pendingOrderPrice && !preview && <p>Tu pedido pendiente conserva el importe confirmado al crearlo.</p>}
                            {!canBuy && !preview && !giftAction?.kind && <p>La compra todavía no está disponible.</p>}
                        </>}
                        {!preview && giftAction?.kind === 'owned' && <p>Este producto ya está en tu biblioteca digital.</p>}
                        {!preview && giftAction?.kind && !['owned', 'welcome'].includes(giftAction.kind) && <p>Confirma tu regalo para guardarlo en Mis productos digitales.</p>}
                    </div>
                </section>
            </div>
            {fullDescription && <section aria-labelledby="description-heading" className="mt-14 max-w-3xl sm:mt-20">
                <h2 id="description-heading" className="text-2xl font-extrabold tracking-tight sm:text-3xl">Esto es lo que incluye</h2>
                <div className="mt-5 text-base leading-8 whitespace-pre-wrap break-words text-muted-foreground">{editorialText(p.description)}</div>
            </section>}
            {showCommercial && <section aria-labelledby="commercial-heading" className="mt-12 max-w-3xl sm:mt-16">
                <h2 id="commercial-heading" className="text-2xl font-extrabold tracking-tight sm:text-3xl">Cómo te ayuda en la práctica</h2>
                <div className="mt-5 text-base leading-8 whitespace-pre-wrap break-words text-muted-foreground">{editorialText(p.commercial_content)}</div>
            </section>}
            <DigitalProductGallery key={p.id} product={p} />
            <section aria-labelledby="access-heading" className="mt-14 border-t border-border pt-8 sm:mt-20">
                <h2 id="access-heading" className="text-lg font-bold">Tu acceso y soporte</h2>
                <div className="mt-6 grid gap-7 text-sm leading-7 text-muted-foreground md:grid-cols-3">
                    <div><h3 className="mb-2 font-semibold text-foreground">Cómo recibirás tu producto</h3><p>Pago con QR y verificación bancaria.</p><p className="mt-2">{hosted ? 'Una vez verificado el pago, encontrarás el acceso en tu biblioteca de Mi JAKAWI.' : 'Recibirás acceso a la descarga privada de tu producto al confirmarse el pago.'}</p>{hosted && <p className="mt-2">Disponible desde JAKAWI.STORE. Necesitas conexión a Internet para utilizar la aplicación.</p>}</div>
                    {p.access_terms?.trim() && <div><h3 className="mb-2 font-semibold text-foreground">Condiciones de acceso</h3><p className="whitespace-pre-wrap break-words">{p.access_terms}</p></div>}
                    {p.support_information?.trim() && <div><h3 className="mb-2 font-semibold text-foreground">¿Necesitas ayuda?</h3><p className="whitespace-pre-wrap break-words">{p.support_information}</p></div>}
                </div>
            </section>
        </main>
    </>;
}
