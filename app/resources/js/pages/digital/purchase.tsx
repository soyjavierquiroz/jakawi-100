import { digitalMoney, type DigitalProductData } from '@/components/digital-card';
import { digitalPurchaseClass } from '@/components/digital-purchase-style';
import { JakawiImage } from '@/components/jakawi-image';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function DigitalPurchase({ product: p, authenticated, memberActive, canCollect, pendingOrderPrice = null }: {
    product: DigitalProductData;
    authenticated: boolean;
    memberActive: boolean;
    canCollect: boolean;
    pendingOrderPrice?: string | null;
}) {
    const { errors } = usePage().props;
    const price = pendingOrderPrice ?? (memberActive && p.member_price ? p.member_price : p.public_price);
    const available = canCollect && !p.coming_soon && Number(p.public_price) > 0;
    return <>
        <Head title={`Comprar ${p.name}`}><meta name="robots" content="noindex,nofollow" /></Head>
        <main className="mx-auto max-w-3xl px-[var(--space-gutter)] py-8 font-discovery text-foreground sm:px-6 sm:py-14">
            <Link href={`/productos-digitales/${p.slug}`} className="text-sm font-semibold text-brand underline underline-offset-4">Volver al producto</Link>
            <p className="mt-8 text-xs font-bold tracking-widest text-brand">TU COMPRA</p>
            <h1 className="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">Ya estás un paso más cerca</h1>
            <div className="mt-8 flex items-center gap-5 border-y border-border py-6">
                {p.cover_url && <JakawiImage src={p.cover_url} alt={`Portada de ${p.name}`} className="max-h-36 w-24 shrink-0 rounded-xl object-contain" loading="eager" />}
                <div className="min-w-0">
                    <h2 className="text-xl font-bold break-words">{p.name}</h2>
                    <p className="mt-3 text-3xl font-extrabold">{digitalMoney(price, p.currency)}</p>
                    {memberActive && p.member_price && <p className="mt-2 text-sm text-muted-foreground">Precio para miembros</p>}
                    {pendingOrderPrice && <p className="mt-2 text-sm text-muted-foreground">Tu pedido pendiente conserva su precio.</p>}
                </div>
            </div>
            <p className="mt-6 font-semibold">Pago con QR. Acceso inmediato.</p>
            {available ? <div className="mt-6 space-y-4">
                {authenticated ? <>
                    <button type="button" onClick={() => router.post(`/productos-digitales/${p.slug}/comprar`)} className={digitalPurchaseClass}>{pendingOrderPrice ? 'CONTINUAR PEDIDO' : 'CONTINUAR AL PAGO'} · {digitalMoney(price, p.currency)}</button>
                    <p className="text-sm leading-relaxed text-muted-foreground">En el siguiente paso verás el QR para pagar.</p>
                </> : <>
                    <p className="max-w-lg text-sm leading-relaxed text-muted-foreground">Continúa con tu cuenta para guardar tu compra y tener tu producto siempre a mano.</p>
                    <Link href="/login" className={digitalPurchaseClass}>CONTINUAR CON MI CUENTA</Link>
                    <p className="text-sm text-muted-foreground">¿Es tu primera compra? <Link href="/register" className="font-bold text-brand underline underline-offset-4">Crear cuenta y continuar</Link></p>
                </>}
            </div> : <p role="status" className="mt-6 text-muted-foreground">La compra todavía no está disponible.</p>}
            {Object.values(errors ?? {}).map((error, index) => <p key={index} role="alert" className="mt-3 text-destructive">{String(error)}</p>)}
        </main>
    </>;
}
