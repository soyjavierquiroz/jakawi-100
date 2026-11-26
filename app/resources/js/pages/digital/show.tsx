import { useDigitalGiftAction, type GiftAction } from '@/components/digital-gift-action';
import { digitalMoney, type DigitalProductData } from '@/components/digital-card';
import { JakawiImage } from '@/components/jakawi-image';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

// Some legacy editorial fields contain escaped CRLF from import/editor entry.
// Normalize only line separators for display; retain saved plain text and React escaping.
const editorialText = (text: string | null) => text?.replace(/\\r\\n|\\n|\\r/g, '\n') ?? '';

function ProductGallery({ product: p }: { product: DigitalProductData }) {
    const track = useRef<HTMLDivElement>(null);
    const [position, setPosition] = useState({ index: 0, end: false });
    const images = (p.gallery_urls ?? []).filter(Boolean);
    useEffect(() => {
        const el = track.current;
        if (!el) return;
        const update = () => setPosition(previous => ({ ...previous, end: el.scrollLeft + el.clientWidth >= el.scrollWidth - 2 }));
        const observer = new ResizeObserver(update);
        observer.observe(el);
        update();
        return () => observer.disconnect();
    }, [images.length]);
    if (!images.length) return null;
    const moveTo = (index: number) => {
        const element = track.current;
        const slide = element?.children[Math.max(0, Math.min(images.length - 1, index))] as HTMLElement | undefined;
        if (!element || !slide) return;
        element.scrollTo({ left: slide.offsetLeft - element.offsetLeft, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
    };
    return (
        <section aria-labelledby="gallery-heading" className="mt-14 border-t border-border pt-10 sm:mt-20">
            <div className="mb-6 flex items-center justify-between gap-4">
                <h2 id="gallery-heading" className="text-2xl font-extrabold tracking-tight sm:text-3xl">Galería</h2>
                {images.length > 1 && <div className="hidden gap-2 md:flex">
                    <button type="button" aria-label="Imagen anterior" aria-controls="product-gallery" disabled={position.index === 0} onClick={() => moveTo(position.index - 1)} className="grid size-11 place-items-center rounded-full border border-border focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand disabled:opacity-30"><ChevronLeft aria-hidden="true" size={20} /></button>
                    <button type="button" aria-label="Imagen siguiente" aria-controls="product-gallery" disabled={position.end} onClick={() => moveTo(position.index + 1)} className="grid size-11 place-items-center rounded-full border border-border focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand disabled:opacity-30"><ChevronRight aria-hidden="true" size={20} /></button>
                </div>}
            </div>
            <div id="product-gallery" ref={track} role="region" aria-label={`Imágenes de ${p.name}`} aria-roledescription={images.length > 1 ? 'carrusel' : undefined} tabIndex={images.length > 1 ? 0 : undefined}
                onKeyDown={event => {
                    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
                    event.preventDefault();
                    moveTo(event.key === 'Home' ? 0 : event.key === 'End' ? images.length - 1 : position.index + (event.key === 'ArrowRight' ? 1 : -1));
                }}
                onScroll={() => {
                    const el = track.current;
                    if (!el) return;
                    const width = (el.children[0] as HTMLElement)?.getBoundingClientRect().width + 20;
                    setPosition({ index: Math.round(el.scrollLeft / width), end: el.scrollLeft + el.clientWidth >= el.scrollWidth - 2 });
                }}
                className={`relative grid snap-x snap-mandatory grid-flow-col gap-5 overflow-x-auto overscroll-x-contain rounded-2xl pb-3 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand ${images.length === 1 ? 'auto-cols-[100%] md:auto-cols-[minmax(0,400px)]' : 'auto-cols-[100%] md:auto-cols-[calc((100%-20px)/2)] lg:auto-cols-[calc((100%-40px)/3)]'}`}>
                {images.map((url, index) => <figure key={`${url}-${index}`} role="group" aria-label={`${index + 1} de ${images.length}`} className="min-w-0 snap-start rounded-2xl border border-border/60 bg-surface-muted p-2">
                    <JakawiImage src={url} srcset={p.gallery_srcsets?.[index]} sizes="(min-width: 1024px) 340px, (min-width: 768px) 45vw, 90vw" alt={`${p.name} · Imagen de apoyo ${index + 1}`} loading="lazy" className="h-[min(110vw,420px)] w-full rounded-xl object-contain md:h-[400px]" />
                </figure>)}
            </div>
            {images.length > 1 && <div className="mt-2 flex flex-wrap items-center justify-center gap-1 md:hidden" aria-label="Elegir imagen">
                {images.map((_, index) => <button type="button" key={index} aria-label={`Ver imagen ${index + 1}`} aria-current={position.index === index ? 'true' : undefined} aria-controls="product-gallery" onClick={() => moveTo(index)} className="grid size-11 place-items-center rounded-full focus-visible:outline-2 focus-visible:outline-brand"><span className={`h-1.5 rounded-full ${position.index === index ? 'w-5 bg-brand' : 'w-1.5 bg-foreground/25'}`} /></button>)}
            </div>}
        </section>
    );
}

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
    const memberCents = Math.round(Number(p.member_price) * 100);
    const hasDiscount = memberCents > 0 && publicCents > memberCents;
    const saving = publicCents - memberCents;
    const authorizedPrice = pendingOrderPrice ?? (memberActive && p.member_price ? p.member_price : p.public_price);
    const canBuy = !preview && !p.coming_soon && canCollect && publicCents > 0;
    const hosted = p.delivery_type === 'hosted_app';
    const delivery = hosted ? 'Accede a tu aplicación desde Mi JAKAWI una vez confirmado el pago.' : 'Un producto por pedido. Descarga privada tras verificar el pago.';
    const buyClass = 'inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-brand px-6 py-3 text-center font-bold text-brand-foreground focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand sm:w-auto';
    const buyLabel = `${pendingOrderPrice ? 'CONTINUAR PEDIDO ·' : 'COMPRAR POR'} ${digitalMoney(authorizedPrice, p.currency)}`;
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
                    <div data-testid="product-prices" className="mt-6 border-t border-border pt-5">
                        {hasDiscount && <p className="text-xs font-bold tracking-[0.1em] text-brand">PRECIO ESPECIAL PARA MIEMBROS</p>}
                        <p className="mt-2 text-[2.75rem] leading-none font-extrabold tracking-tight text-foreground sm:text-5xl">{digitalMoney(hasDiscount ? p.member_price : p.public_price, p.currency)}</p>
                        {hasDiscount && <>
                            <p className="mt-3 text-sm text-muted-foreground">Precio público: {memberActive ? <s>{digitalMoney(p.public_price, p.currency)}</s> : digitalMoney(p.public_price, p.currency)}</p>
                            <p className="mt-3 text-sm leading-relaxed font-bold text-brand">AHORRA {digitalMoney((saving / 100).toFixed(2), p.currency)} ({Math.round(saving / publicCents * 100)}%) CON TU MEMBRESÍA.</p>
                        </>}
                    </div>
                    {!preview && giftAction?.kind === 'welcome' && <div className="mt-5"><h2 className="font-bold">Tu membresía incluye este regalo</h2><p className="mt-2 text-sm leading-relaxed text-muted-foreground">Puedes activar este producto sin costo adicional y guardarlo en tu biblioteca digital.</p></div>}
                    <div className="mt-5 flex flex-col items-start gap-3">
                        {!preview && giftAction?.kind ? <button type="button" onClick={gift.click} className={buyClass}>{gift.label}</button> : canBuy ? buy : null}
                        {!memberActive && hasDiscount && !preview && <Link href="/membresia" className="inline-flex min-h-11 items-center text-xs font-bold text-brand underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand">CONOCE EL PRECIO DE MIEMBRO</Link>}
                        {preview && <button type="button" disabled className={`${buyClass} opacity-50`}>{buyLabel} · Vista previa</button>}
                    </div>
                    {!preview && giftAction?.kind === 'welcome' && canBuy && <div className="mt-4">
                        <button type="button" onClick={() => router.post(`/productos-digitales/${p.slug}/comprar`)} className="min-h-12 rounded-xl border border-border px-5 py-3 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand">{buyLabel}</button>
                        <p className="mt-2 text-sm text-muted-foreground">Comprar conserva tu regalo pendiente para otro producto.</p>
                    </div>}
                    {Object.values(errors ?? {}).map((error, index) => <p key={index} role="alert" className="mt-3 text-destructive">{String(error)}</p>)}
                    <div className="mt-5 space-y-2 text-sm leading-relaxed text-muted-foreground">
                        {p.coming_soon ? <p>Presentación editorial en revisión. Las compras y la entrega todavía no están habilitadas.</p> : <>
                            <p>{delivery}</p>
                            {pendingOrderPrice && !preview && <p>Tu pedido pendiente conserva el importe confirmado al crearlo.</p>}
                            {canBuy ? <p>Pago por QR con verificación bancaria.</p> : !preview && !giftAction?.kind ? <p>La compra todavía no está disponible.</p> : null}
                        </>}
                        {!preview && giftAction?.kind === 'owned' && <p>Este producto ya está en tu biblioteca digital.</p>}
                        {!preview && giftAction?.kind && !['owned', 'welcome'].includes(giftAction.kind) && <p>Confirma tu regalo para guardarlo en Mis productos digitales.</p>}
                    </div>
                </section>
            </div>
            {fullDescription && <section aria-labelledby="description-heading" className="mt-14 max-w-3xl sm:mt-20">
                <h2 id="description-heading" className="text-2xl font-extrabold tracking-tight sm:text-3xl">Conoce la aplicación</h2>
                <div className="mt-5 text-base leading-8 whitespace-pre-wrap break-words text-muted-foreground">{editorialText(p.description)}</div>
            </section>}
            {showCommercial && <section aria-labelledby="commercial-heading" className="mt-12 max-w-3xl sm:mt-16">
                <h2 id="commercial-heading" className="text-2xl font-extrabold tracking-tight sm:text-3xl">Descubre lo que puedes hacer</h2>
                <div className="mt-5 text-base leading-8 whitespace-pre-wrap break-words text-muted-foreground">{editorialText(p.commercial_content)}</div>
            </section>}
            <ProductGallery key={p.id} product={p} />
            <section aria-labelledby="access-heading" className="mt-14 border-t border-border pt-8 sm:mt-20">
                <h2 id="access-heading" className="text-lg font-bold">Acceso, condiciones y soporte</h2>
                <div className="mt-6 grid gap-7 text-sm leading-7 text-muted-foreground md:grid-cols-3">
                    <div><h3 className="mb-2 font-semibold text-foreground">Cómo recibirás tu producto</h3><p>{hosted ? 'Una vez verificado el pago, encontrarás el acceso en tu biblioteca de Mi JAKAWI.' : 'Recibirás acceso a la descarga privada de tu producto al confirmarse el pago.'}</p>{hosted && <p className="mt-2">Disponible desde JAKAWI.STORE. Necesitas conexión a Internet para utilizar la aplicación.</p>}</div>
                    {p.access_terms?.trim() && <div><h3 className="mb-2 font-semibold text-foreground">Condiciones de acceso</h3><p className="whitespace-pre-wrap break-words">{p.access_terms}</p></div>}
                    {p.support_information?.trim() && <div><h3 className="mb-2 font-semibold text-foreground">¿Necesitas ayuda?</h3><p className="whitespace-pre-wrap break-words">{p.support_information}</p></div>}
                </div>
            </section>
        </main>
    </>;
}
