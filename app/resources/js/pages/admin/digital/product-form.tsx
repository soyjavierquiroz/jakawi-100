import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AdminLayout from '../layout';
type Product = {
    delivery_type?: 'pdf' | 'hosted_app';
    app_key?: string | null;
    name: string;
    slug: string;
    short_description: string | null;
    description: string | null;
    commercial_content: string | null;
    status: string;
    public_price: string | null;
    member_price: string | null;
    currency: string;
    support_information: string | null;
    access_terms: string | null;
    has_file: boolean;
    welcome_gift_enabled?: boolean;
    promotional_gift_enabled?: boolean;
    gift_pdf_final?: boolean;
    use_shared_qr?: boolean;
    qrs?: Record<'public'|'member', {has_file:boolean; shared?:boolean; verified:boolean; amount:string|null; currency:string}>;
    sale?: {eligible:boolean; enabled:boolean; available:boolean; pdf_verified:boolean; pdf_final:boolean; app_ready:boolean; publish_missing:string[]; missing:string[]};
    coming_soon?: boolean;
    category?: string;
    gallery_paths?: string[];
    gallery_urls?: string[];
};
export default function ProductForm({
    product: p,
    partner,
    hostedApps = ['gluteos'],
    categoryLabels,
}: {
    product: Product | null;
    partner: { name: string };
    hostedApps?: string[];
    categoryLabels: Record<string, string>;
}) {
    const form = useForm({
        delivery_type: p?.delivery_type ?? 'pdf',
        app_key: p?.app_key ?? '',
        ...(p?.sale?.eligible ? {pdf_final: p.sale.pdf_final} : {}),
        welcome_gift_enabled: p?.welcome_gift_enabled ?? false,
        promotional_gift_enabled: p?.promotional_gift_enabled ?? false,
        gift_pdf_final: p?.gift_pdf_final ?? false,
        use_shared_qr: p?.use_shared_qr ?? true,
        public_qr: null as File | null,
        member_qr: null as File | null,
        public_qr_confirmed: p?.qrs?.public.verified ?? false,
        member_qr_confirmed: p?.qrs?.member.verified ?? false,
        category: p?.category ?? '',
        gallery: [] as File[],
        gallery_keep: p?.gallery_paths ?? [],
        gallery_clear: false,
        name: p?.name ?? '',
        slug: p?.slug ?? '',
        short_description: p?.short_description ?? '',
        description: p?.description ?? '',
        commercial_content: p?.commercial_content ?? '',
        status: p?.status ?? 'draft',
        public_price: p?.public_price ?? '',
        member_price: p?.member_price ?? '',
        currency: p?.currency ?? 'BOB',
        support_information: p?.support_information ?? '',
        access_terms: p?.access_terms ?? '',
        pdf: null as File | null,
        cover: null as File | null,
    });
    const fields = [
        ['name', 'Nombre'],
        ['slug', 'Slug'],
        ['short_description', 'Descripción corta'],
        ['description', 'Descripción completa'],
        ['commercial_content', 'Contenido comercial'],
        ['public_price', 'Precio público'],
        ['member_price', 'Precio miembro'],
        ['support_information', 'Información de soporte'],
        ['access_terms', 'Condiciones de acceso'],
    ] as const;
    const error = (field: string) => Object.entries(form.errors).filter(([key]) => key === field || key.startsWith(field + '.')).map(([key, message]) => <span key={key} data-field-error role="alert" className="block text-sm font-normal text-destructive">{message}</span>);
    const save = (operation: 'save' | 'authorize_sale' | 'publish_sale' | 'draft') => {
        form.transform(data => Object.fromEntries(Object.entries({
            ...data, operation: operation === 'draft' ? 'save' : operation,
            status: operation === 'draft' ? 'draft' : data.status,
            // Optional fields must be omitted, never serialized as empty multipart values.
            sale_enabled: operation === 'authorize_sale' ? true : undefined,
        }).filter(([, value]) => value !== undefined).map(([key, value]) =>
            [key, typeof value === 'boolean' ? (value ? '1' : '0') : value],
        )));
        form.post('/admin/productos-digitales' + (p ? `/${p.slug}` : ''), {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onError: () => requestAnimationFrame(() => document.querySelector('[data-field-error]')?.scrollIntoView({block: 'center', behavior: 'smooth'})),
        });
    };
    const submit = (e: FormEvent) => { e.preventDefault(); save('save'); };
    return (
        <AdminLayout
            title={p ? 'Editar producto digital' : 'Crear producto digital'}
        >
            <Head title="Producto digital" />
            <p className="mb-5 text-muted-foreground">
                {partner.name} · {form.data.delivery_type === 'hosted_app' ? 'APP alojada en JAKAWI.STORE' : 'PDF privado'} · administrado por JAKAWI
            </p>
            {p && <nav aria-label="Edición y vistas previas" className="mb-6 flex flex-wrap gap-3"><Link className="min-h-11 rounded border border-border p-3 font-bold" href={`/admin/productos-digitales/${p.slug}/landings`}>Editar Landings</Link><a className="min-h-11 rounded border border-border p-3 font-bold" href={`/admin/productos-digitales/${p.slug}/preview`} target="_blank" rel="noreferrer">Preview de ficha</a></nav>}
            {p?.coming_soon && <p className="mb-5 rounded border border-border p-4">Disponible próximamente · visible al público, sin compras. Puedes guardar cambios editoriales sin precio ni PDF.</p>}
            {p && <Link className="mb-5 inline-flex min-h-12 items-center rounded border border-border p-3 font-bold" href={`/admin/productos-digitales/${p.slug}/regalar`}>ENVIAR COMO REGALO</Link>}
            <form onSubmit={submit} className="grid max-w-3xl gap-5">
                {p?.sale?.eligible && <section className="grid gap-4 rounded-xl border border-border p-5">
                    <p role="status" className="font-bold">Disponibilidad comercial: {p.sale.available ? 'Disponible' : 'Venta desactivada'}</p>
                    {form.data.delivery_type !== 'hosted_app' && <p>PDF: {p.sale.pdf_verified ? 'Archivo privado verificado' : 'Pendiente de subir o verificar'}</p>}
                    {form.data.delivery_type === 'hosted_app' && <p role="status">{p.sale.app_ready && form.data.app_key === p.app_key ? 'APP preparada para entregar' : 'APP pendiente de verificación técnica'}</p>}
                    {p.sale.publish_missing.map(item => <p key={item}>{item}</p>)}
                    <a className="text-brand underline" href="/admin/digital/cobro">Configurar cuenta y QR compartidos</a>
                    {form.data.delivery_type !== 'hosted_app' && <label className="flex gap-3"><input type="checkbox" checked={form.data.pdf_final ?? false} onChange={e=>form.setData('pdf_final',e.target.checked)}/>Confirmo el PDF definitivo para compradores.</label>}
                    {error('pdf_final')}
                    <p>Esta acción guarda los cambios, publica y autoriza expresamente la venta si todos los requisitos están completos. Los regalos se configuran por separado.</p>
                    <button type="button" onClick={() => save('publish_sale')} disabled={form.processing} className="min-h-12 rounded-xl bg-brand px-4 font-bold text-brand-foreground disabled:opacity-50">PUBLICAR Y ACTIVAR VENTA</button>
                    {error('sale_enabled')}
                </section>}
                <section className="grid gap-3 rounded-xl border border-border p-5">
                    <label>Método de entrega<select className="mt-2 block min-h-12 w-full rounded border bg-background p-2" value={form.data.delivery_type} onChange={e => {form.setData('delivery_type', e.target.value as 'pdf' | 'hosted_app'); form.setData('pdf_final', false); }}><option value="pdf">PDF privado</option><option value="hosted_app">APP alojada en JAKAWI.STORE</option></select></label>
                    {form.data.delivery_type === 'hosted_app' && <label>Aplicación autorizada<select className="mt-2 block min-h-12 w-full rounded border bg-background p-2" value={form.data.app_key} onChange={e => {form.setData('app_key', e.target.value); form.setData('pdf_final', false); }}><option value="">Elegir aplicación</option>{hostedApps.map(slug => <option key={slug} value={slug}>{slug} · jakawi.store/{slug}/</option>)}</select><p>Requiere APP funcional y protegida, cuenta bancaria, precios, QR y autorización comercial individual. El PDF anterior se conserva como archivo privado.</p></label>}
                    {error('delivery_type')}{error('app_key')}
                </section>
                <section className="grid gap-4 rounded-xl border border-border p-5"><h2 className="text-xl font-bold">Regalos digitales</h2><label className="flex gap-3"><input type="checkbox" checked={form.data.welcome_gift_enabled} onChange={e => form.setData('welcome_gift_enabled', e.target.checked)}/>Disponible como regalo de bienvenida</label><label className="flex gap-3"><input type="checkbox" checked={form.data.promotional_gift_enabled} onChange={e => form.setData('promotional_gift_enabled', e.target.checked)}/>Disponible para regalo promocional</label><label className="flex gap-3"><input type="checkbox" checked={form.data.gift_pdf_final} onChange={e => form.setData('gift_pdf_final', e.target.checked)}/>{form.data.delivery_type === 'hosted_app' ? 'Autorizo la APP protegida para regalos.' : 'Confirmo que el PDF privado actual es definitivo para regalos.'}</label></section>
                {error('gift_pdf_final')}{error('welcome_gift_enabled')}{error('promotional_gift_enabled')}
                {Object.keys(form.errors).length > 0 && <p role="status" className="rounded-xl border border-destructive p-4">Revisa los requisitos indicados. Si falta un requisito comercial, los datos válidos se guardan y la venta permanece pendiente. Los datos escritos se conservan; si recargas el editor, vuelve a seleccionar los archivos.</p>}
                {fields.map(([key, label]) => (
                    <label key={key} className="grid gap-2 font-semibold">
                        {label}
                        {[
                            'description',
                            'commercial_content',
                            'access_terms',
                            'support_information',
                        ].includes(key) ? (
                            <textarea
                                className="min-h-28 rounded-xl border border-border bg-surface p-3"
                                value={form.data[key]}
                                onChange={(e) => {
                                    form.setData(key, e.target.value);
                                    if (key === 'public_price') form.setData('public_qr_confirmed', false);
                                    if (key === 'member_price') form.setData('member_qr_confirmed', false);
                                }}
                            />
                        ) : (
                            <input
                                className="min-h-12 rounded-xl border border-border bg-surface px-3"
                                value={form.data[key]}
                                onChange={(e) => {
                                    form.setData(key, e.target.value);
                                    if (key === 'public_price') form.setData('public_qr_confirmed', false);
                                    if (key === 'member_price') form.setData('member_qr_confirmed', false);
                                }}
                                type={key.includes('price') ? 'number' : 'text'}
                                step={
                                    key.includes('price') ? '0.01' : undefined
                                }
                                min={key.includes('price') ? '0.01' : undefined}
                                required={key === 'name' || key === 'slug'}
                            />
                        )}
                        {error(key)}
                    </label>
                ))}
                <label className="grid gap-2">Categoría<select className="min-h-12 rounded-xl border border-border bg-surface px-3" value={form.data.category} onChange={e=>form.setData('category',e.target.value)}><option value="">Sin categoría</option>{Object.entries(categoryLabels).map(([key,label])=><option key={key} value={key}>{label}</option>)}</select>{error('category')}</label>
                <label className="grid gap-2">Galería (hasta 8 imágenes)<input type="file" multiple accept="image/png,image/jpeg,image/webp" onChange={e=>form.setData('gallery',Array.from(e.target.files||[]))}/>{error('gallery')}{error('gallery_keep')}</label>
                {!!p?.gallery_paths?.length && <div className="grid grid-cols-2 gap-3">{p.gallery_paths.map((path,i)=><label key={path} className="text-sm"><img src={p.gallery_urls?.[i]} alt="Imagen de galería" className="mb-2 w-full rounded-xl"/><input type="checkbox" checked={form.data.gallery_keep.includes(path)} onChange={e=>{const keep=e.target.checked?[...form.data.gallery_keep,path]:form.data.gallery_keep.filter(k=>k!==path);form.setData('gallery_keep',keep);form.setData('gallery_clear',keep.length===0);}}/> Conservar</label>)}</div>}
                <label className="grid gap-2">
                    Moneda
                    <select
                        value={form.data.currency}
                        onChange={(e) =>
                            { form.setData('currency', e.target.value); form.setData('public_qr_confirmed', false); form.setData('member_qr_confirmed', false); }
                        }
                        className="min-h-12 rounded-xl border border-border bg-surface px-3"
                    >
                        <option>BOB</option>
                        <option>USD</option>
                    </select>
                    {error('currency')}
                </label>
                <label className="grid gap-2">QR para cobrar<select className="min-h-12 rounded-xl border border-border bg-surface px-3" value={form.data.use_shared_qr ? 'shared' : 'specific'} onChange={e => {form.setData('use_shared_qr', e.target.value === 'shared'); form.setData('public_qr_confirmed',false); form.setData('member_qr_confirmed',false);}}><option value="shared">QR compartidos (predeterminado)</option><option value="specific">QR específicos del producto</option></select></label>
                {form.data.use_shared_qr && <p role="status">{p?.qrs?.public.shared && p.qrs.public.verified && p?.qrs?.member.shared && p.qrs.member.verified && form.data.currency === p.currency && Number(form.data.public_price) === Number(p.qrs.public.amount) && Number(form.data.member_price) === Number(p.qrs.member.amount) ? 'QR compartidos disponibles' : 'El backend buscará QR compartidos verificados para cada importe, moneda y cuenta al guardar.'} <a className="text-brand underline" href="/admin/digital/cobro">Subir o revisar QR compartidos</a>. Al guardar este modo se retiran las asociaciones a QR específicos; se conservan los archivos que necesiten las órdenes existentes.</p>}
                {!form.data.use_shared_qr && (['public','member'] as const).map(tier => <section key={tier} className="grid gap-4 rounded-xl border border-border p-5">
                    <label className="grid gap-2 font-semibold">{tier === 'public' ? 'QR público' : 'QR miembro'} (PNG/JPG, máximo 10 MB)
                        <input type="file" accept="image/png,image/jpeg" onChange={e=>{form.setData(`${tier}_qr`, e.target.files?.[0] ?? null); form.setData(`${tier}_qr_confirmed`, false);}}/>
                        {error(`${tier}_qr`)}
                    </label>
                    <p role="status">{p?.qrs?.[tier].verified ? 'Verificado para el precio, moneda y cuenta actuales' : 'Pendiente de cargar o volver a confirmar'}</p>
                    <p>Importe fijo: {tier === 'public' ? form.data.public_price || 'Pendiente' : form.data.member_price || 'Pendiente'} {form.data.currency}. Cuenta compartida: <a className="text-brand underline" href="/admin/digital/cobro">Cobro digital</a>.</p>
                    {p?.qrs?.[tier].has_file && <a className="text-brand underline" href={`/admin/productos-digitales/${p.slug}/qr/${tier}`} target="_blank" rel="noreferrer">Revisar QR {tier === 'public' ? 'público' : 'miembro'} actual</a>}
                    <label className="flex items-start gap-3"><input type="checkbox" checked={form.data[`${tier}_qr_confirmed`]} onChange={e=>form.setData(`${tier}_qr_confirmed`,e.target.checked)}/>Confirmo que el QR {tier === 'public' ? 'público' : 'miembro'} pertenece a la cuenta autorizada de JAKAWI Digital, es reutilizable y cobra el importe fijo y la moneda indicados.</label>
                    {error(`${tier}_qr_confirmed`)}
                </section>)}
                <label className="grid gap-2">
                    Estado
                    <select
                        className="min-h-12 rounded-xl border border-border bg-surface px-3"
                        value={form.data.status}
                        onChange={(e) => form.setData('status', e.target.value)}
                    >
                        <option value="draft">Borrador</option>
                        <option value="published">Publicado</option>
                    </select>
                    {error('status')}
                </label>
                <label className="grid gap-2">
                    Portada
                    <input
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        onChange={(e) =>
                            form.setData('cover', e.target.files?.[0] ?? null)
                        }
                    />
                    {error('cover')}
                </label>
                <label className="grid gap-2">
                    {form.data.delivery_type === 'hosted_app' ? 'Respaldo PDF privado opcional (máximo 25 MB)' : 'PDF privado (máximo 25 MB)'}
                    <input
                        type="file"
                        accept="application/pdf"
                        onChange={(e) =>
                            { form.setData('pdf', e.target.files?.[0] ?? null); form.setData('gift_pdf_final', false); form.setData('welcome_gift_enabled', false); form.setData('promotional_gift_enabled', false); if (p?.sale?.eligible) { form.setData('pdf_final', false);  } }
                        }
                    />
                    {error('pdf')}{error('file')}
                    {p?.has_file ? (
                        <a
                            className="text-brand underline"
                            href={`/admin/productos-digitales/${p.slug}/pdf`}
                        >
                            {form.data.delivery_type === 'hosted_app' ? 'Revisar PDF archivado' : 'Revisar PDF actual'}
                        </a>
                    ) : (
                        <span className="text-sm text-muted-foreground">
                            {form.data.delivery_type === 'hosted_app' ? 'La APP no requiere PDF.' : 'Archivo pendiente. Puedes guardar un borrador.'}
                        </span>
                    )}
                </label>
                {Object.values(form.errors).map((error, i) => (
                    <p role="alert" className="text-destructive" key={i}>
                        {error}
                    </p>
                ))}
                <div className="flex flex-wrap gap-4">
                    {form.data.status !== 'draft' && <button type="button" disabled={form.processing} onClick={() => save('draft')} className="min-h-12 rounded-xl border border-border px-4">Guardar borrador</button>}
                    <button
                        disabled={form.processing}
                        className="min-h-12 rounded-xl bg-brand px-6 font-bold text-brand-foreground"
                    >
                        {form.data.status === 'draft' ? 'Guardar borrador' : 'Guardar cambios'}
                    </button>
                    <Link
                        className="inline-flex min-h-12 items-center"
                        href="/admin/productos-digitales"
                    >
                        Volver
                    </Link>
                </div>
            </form>
        </AdminLayout>
    );
}
