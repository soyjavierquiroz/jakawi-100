import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AdminLayout from '../layout';
type Product = {
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
    category?: string;
    gallery_paths?: string[];
    gallery_urls?: string[];
};
export default function ProductForm({
    product: p,
    partner,
}: {
    product: Product | null;
    partner: { name: string };
}) {
    const form = useForm({
        category: p?.category ?? 'fitness',
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
        ['member_price', 'Precio miembro opcional'],
        ['support_information', 'Información de soporte'],
        ['access_terms', 'Condiciones de acceso'],
    ] as const;
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/admin/productos-digitales' + (p ? `/${p.slug}` : ''), {
            forceFormData: true,
        });
    };
    return (
        <AdminLayout
            title={p ? 'Editar producto digital' : 'Crear producto digital'}
        >
            <Head title="Producto digital" />
            <p className="mb-5 text-muted-foreground">
                {partner.name} · PDF privado · administrado por JAKAWI
            </p>
            {p && <nav aria-label="Edición y vistas previas" className="mb-6 flex flex-wrap gap-3"><Link className="min-h-11 rounded border border-border p-3 font-bold" href={`/admin/productos-digitales/${p.slug}/landings`}>Editar Landings</Link><a className="min-h-11 rounded border border-border p-3 font-bold" href={`/admin/productos-digitales/${p.slug}/preview`} target="_blank" rel="noreferrer">Preview de ficha</a></nav>}
            <form onSubmit={submit} className="grid max-w-3xl gap-5">
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
                                onChange={(e) =>
                                    form.setData(key, e.target.value)
                                }
                            />
                        ) : (
                            <input
                                className="min-h-12 rounded-xl border border-border bg-surface px-3"
                                value={form.data[key]}
                                onChange={(e) =>
                                    form.setData(key, e.target.value)
                                }
                                type={key.includes('price') ? 'number' : 'text'}
                                step={
                                    key.includes('price') ? '0.01' : undefined
                                }
                                min={key.includes('price') ? '0.01' : undefined}
                                required={key === 'name' || key === 'slug'}
                            />
                        )}
                    </label>
                ))}
                <label className="grid gap-2">Categoría<select className="min-h-12 rounded-xl border border-border bg-surface px-3" value={form.data.category} onChange={e=>form.setData('category',e.target.value)}>{['food','cafe','fitness','wellness','beauty','entertainment','nightlife','shopping','services','experiences'].map(c=><option key={c} value={c}>{c}</option>)}</select></label>
                <label className="grid gap-2">Galería (hasta 8 imágenes)<input type="file" multiple accept="image/png,image/jpeg,image/webp" onChange={e=>form.setData('gallery',Array.from(e.target.files||[]))}/></label>
                {!!p?.gallery_paths?.length && <div className="grid grid-cols-2 gap-3">{p.gallery_paths.map((path,i)=><label key={path} className="text-sm"><img src={p.gallery_urls?.[i]} alt="Imagen de galería" className="mb-2 w-full rounded-xl"/><input type="checkbox" checked={form.data.gallery_keep.includes(path)} onChange={e=>{const keep=e.target.checked?[...form.data.gallery_keep,path]:form.data.gallery_keep.filter(k=>k!==path);form.setData('gallery_keep',keep);form.setData('gallery_clear',keep.length===0);}}/> Conservar</label>)}</div>}
                <label className="grid gap-2">
                    Moneda
                    <select
                        value={form.data.currency}
                        onChange={(e) =>
                            form.setData('currency', e.target.value)
                        }
                        className="min-h-12 rounded-xl border border-border bg-surface px-3"
                    >
                        <option>BOB</option>
                        <option>USD</option>
                    </select>
                </label>
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
                </label>
                <label className="grid gap-2">
                    PDF privado (máximo 25 MB)
                    <input
                        type="file"
                        accept="application/pdf"
                        onChange={(e) =>
                            form.setData('pdf', e.target.files?.[0] ?? null)
                        }
                    />
                    {p?.has_file ? (
                        <Link
                            className="text-brand underline"
                            href={`/admin/productos-digitales/${p.slug}/pdf`}
                        >
                            Revisar PDF actual
                        </Link>
                    ) : (
                        <span className="text-sm text-muted-foreground">
                            Archivo pendiente. Puedes guardar un borrador.
                        </span>
                    )}
                </label>
                {Object.values(form.errors).map((error, i) => (
                    <p role="alert" className="text-destructive" key={i}>
                        {error}
                    </p>
                ))}
                <div className="flex gap-4">
                    <button
                        disabled={form.processing}
                        className="min-h-12 rounded-xl bg-brand px-6 font-bold text-brand-foreground"
                    >
                        Guardar
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
