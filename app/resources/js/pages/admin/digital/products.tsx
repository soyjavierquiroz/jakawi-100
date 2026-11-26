import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import {
    Search,
    SlidersHorizontal,
    Package,
    X,
    Check,
    LoaderCircle,
    MoreHorizontal,
    Pencil,
    ExternalLink,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import AdminLayout from '../layout';
import './products.css';
import { digitalMoney } from '@/components/digital-card';

type Product = {
    id: number;
    name: string;
    slug: string;
    category: string | null;
    delivery_type: string;
    app_key: string | null;
    public_price: string | null;
    member_price: string | null;
    currency: string;
    status: string;
    thumbnail: string | null;
    sale: { available: boolean; publish_missing: string[]; qr: string };
};
type Filters = {
    q?: string;
    state?: string;
    category?: string;
    type?: string;
    qr?: string;
    sort?: string;
    per_page?: string;
};
type Result = {
    id: number;
    name: string;
    missing: string[];
    before?: Record<string, string | null>;
    after?: Record<string, string | null>;
};
type Preview = {
    results: Result[];
    ready: number;
    blocked: number;
    confirmation: string | null;
    message?: string;
};
type Changes = {
    category?: string | null;
    public_price?: string;
    member_price?: string;
    qr_mode?: string;
    fill_defaults?: boolean;
    operation?: string;
};
const base = '/admin/productos-digitales';
const control =
    'min-h-11 rounded-xl border border-border bg-surface px-3 text-sm outline-none focus:ring-2 focus:ring-brand';
const button =
    'inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-border px-3 text-sm font-semibold transition hover:bg-muted focus-visible:outline-2 focus-visible:outline-brand disabled:opacity-50';
const primary =
    button +
    ' border-transparent bg-brand text-brand-foreground hover:bg-brand/90';
const states = {
    all: 'Todos los estados',
    draft: 'Borradores',
    published: 'Publicados',
    active: 'Venta activa',
    ready: 'Listos para publicar y activar',
    pending: 'Venta pendiente',
    missing: 'Con requisitos faltantes',
};
const qrLabels: Record<string, string> = {
    shared: 'Compartido',
    specific: 'Específico',
    pending: 'Pendiente',
};

export default function Products({
    products,
    filters,
    counts,
    categoryLabels,
    types,
}: {
    products: {
        data: Product[];
        total: number;
        from: number | null;
        to: number | null;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: Filters;
    counts: { total: number; active: number; ready: number; missing: number };
    categoryLabels: Record<string, string>;
    types: string[];
}) {
    const [search, setSearch] = useState(filters.q ?? '');
    const [selected, setSelected] = useState<number[]>([]);
    const [busy, setBusy] = useState(false);
    const [panel, setPanel] = useState(false);
    const [changes, setChanges] = useState<Changes>({});
    const [preview, setPreview] = useState<Preview | null>(null);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [working, setWorking] = useState(false);
    const checkAll = useRef<HTMLInputElement>(null);
    const panelRef = useRef<HTMLDivElement>(null);
    const latestFilters = useRef(filters);
    latestFilters.current = filters;
    const visit = (patch: Partial<Filters>) => {
        router.get(
            base,
            { ...latestFilters.current, ...patch, page: 1 },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
            },
        );
    };
    useEffect(() => {
        setSearch(filters.q ?? '');
        setSelected([]);
        setPanel(false);
        setPreview(null);
    }, [JSON.stringify(filters), products.current_page]);
    useEffect(() => {
        if (search === (latestFilters.current.q ?? '')) return;
        const timer = setTimeout(() => visit({ q: search }), 300);
        return () => clearTimeout(timer);
    }, [search]);
    useEffect(() => {
        if (checkAll.current)
            checkAll.current.indeterminate =
                selected.length > 0 && selected.length < products.data.length;
    }, [selected, products.data]);
    useEffect(() => {
        if (!panel) return;
        const trigger = document.activeElement as HTMLElement | null;
        const overflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        panelRef.current?.querySelector<HTMLButtonElement>('button')?.focus();
        return () => {
            document.body.style.overflow = overflow;
            trigger?.focus();
        };
    }, [panel]);
    const toggle = (id: number) => {
        setSelected((s) =>
            s.includes(id) ? s.filter((v) => v !== id) : [...s, id],
        );
        setPreview(null);
    };
    const change = (key: keyof Changes, value: Changes[keyof Changes]) => {
        setChanges((s) => ({ ...s, [key]: value }));
        setPreview(null);
        setError('');
    };
    const include = (
        key: keyof Changes,
        enabled: boolean,
        value: Changes[keyof Changes],
    ) => {
        setChanges((s) => {
            const next = { ...s };
            if (enabled) Object.assign(next, { [key]: value });
            else delete next[key];
            return next;
        });
        setPreview(null);
    };
    const openPanel = (ids: number[], operation?: string) => {
        setSelected(ids);
        setChanges(operation ? { operation } : {});
        setPanel(true);
        setPreview(null);
        setError('');
    };
    const submit = async (phase: 'preview' | 'apply') => {
        setWorking(true);
        setError('');
        try {
            const csrf = document.cookie
                .split('; ')
                .find((c) => c.startsWith('XSRF-TOKEN='))
                ?.slice(11);
            const response = await fetch(base + '/lote', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(csrf ?? ''),
                },
                body: JSON.stringify({
                    ids: selected,
                    changes,
                    phase,
                    confirmation: preview?.confirmation,
                }),
            });
            const data = await response.json();
            if (!response.ok)
                throw new Error(
                    data.errors
                        ? Object.values(data.errors).flat().join(' ')
                        : (data.message ??
                              'No se pudo completar la operación.'),
                );
            setPreview(data);
            if (phase === 'apply') {
                setNotice(data.message);
                setPanel(false);
                setSelected([]);
                router.reload({
                    onStart: () => setBusy(true),
                    onFinish: () => setBusy(false),
                });
            }
        } catch (e) {
            setError(
                e instanceof Error
                    ? e.message
                    : 'Error de conexión. Inténtalo nuevamente.',
            );
        } finally {
            setWorking(false);
        }
    };
    const actions = (p: Product) => (
        <div className="flex flex-wrap items-center gap-1.5">
            <Link
                className={button}
                href={`${base}/${p.slug}/editar`}
                aria-label={`Editar ${p.name}`}
            >
                <Pencil size={15} />
                <span>Editar</span>
            </Link>
            <Link
                className={button}
                href={`${base}/${p.slug}/preview`}
                aria-label={`Ver ficha de ${p.name}`}
            >
                <ExternalLink size={15} />
                <span className="xl:hidden">Ficha</span>
            </Link>
            {!p.sale.available && !p.sale.publish_missing.length && (
                <button
                    className={primary}
                    onClick={() => openPanel([p.id], 'publish_sale')}
                >
                    Publicar y activar
                </button>
            )}
            <details className="relative">
                <summary
                    className={button + ' cursor-pointer list-none'}
                    aria-label={`Más opciones para ${p.name}`}
                >
                    <MoreHorizontal size={18} />
                </summary>
                <div className="absolute right-0 z-20 mt-2 w-56 rounded-xl border border-border bg-surface p-2 shadow-xl">
                    <button
                        className="w-full rounded-lg p-3 text-left text-sm hover:bg-muted"
                        onClick={(e) => {
                            e.currentTarget
                                .closest('details')
                                ?.removeAttribute('open');
                            openPanel([p.id]);
                        }}
                    >
                        Edición rápida
                    </button>
                    <button
                        className="w-full rounded-lg p-3 text-left text-sm hover:bg-muted"
                        onClick={(e) => {
                            e.currentTarget
                                .closest('details')
                                ?.removeAttribute('open');
                            openPanel(
                                [p.id],
                                p.sale.available
                                    ? 'disable_sale'
                                    : 'publish_sale',
                            );
                        }}
                    >
                        {p.sale.available
                            ? 'Desactivar venta'
                            : 'Revisar y publicar'}
                    </button>
                </div>
            </details>
        </div>
    );
    const badge = (label: string, active = false) => (
        <span
            className={`inline-flex rounded-md px-2 py-1 text-xs font-semibold ${active ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-muted text-muted-foreground'}`}
        >
            {label}
        </span>
    );
    const commercial = (p: Product) => (
        <div className="space-y-1.5">
            {badge(
                p.sale.available
                    ? 'Activa'
                    : p.sale.publish_missing.length
                      ? 'Pendiente'
                      : 'Listo',
                p.sale.available || !p.sale.publish_missing.length,
            )}
            {!!p.sale.publish_missing.length && (
                <details className="max-w-60 text-xs text-muted-foreground">
                    <summary className="cursor-pointer py-1 hover:text-foreground">
                        {p.sale.publish_missing.length} requisitos
                    </summary>
                    <ul className="mt-2 space-y-2 pl-3">
                        {p.sale.publish_missing.map((m) => (
                            <li key={m}>{m}</li>
                        ))}
                    </ul>
                </details>
            )}
        </div>
    );
    const thumbnail = (p: Product) =>
        p.thumbnail ? (
            <img
                src={p.thumbnail}
                loading="lazy"
                decoding="async"
                width="44"
                height="54"
                alt=""
                className="h-14 w-11 shrink-0 rounded-lg object-cover"
            />
        ) : (
            <div className="flex h-14 w-11 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                <Package size={20} />
            </div>
        );
    const selection = (p: Product) => (
        <input
            type="checkbox"
            className="h-4 w-4 accent-brand"
            aria-label={`Seleccionar ${p.name}`}
            checked={selected.includes(p.id)}
            onChange={() => toggle(p.id)}
        />
    );
    const filtered = !!(
        filters.q ||
        filters.category ||
        filters.type ||
        filters.qr ||
        (filters.state && filters.state !== 'all')
    );
    return (
        <AdminLayout title="Productos digitales">
            <div className="digital-admin">
                <Head title="Productos digitales" />
                <div className="mb-7 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                            JAKAWI Digital
                        </p>
                        <h2 className="mt-2 text-2xl font-bold tracking-tight">
                            Tu catálogo, bajo control
                        </h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Gestiona contenido, precios y ventas desde un solo
                            lugar.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link className={button} href="/productos-digitales">
                            Ver catálogo
                        </Link>
                        <Link className={primary} href={`${base}/crear`}>
                            Crear producto
                        </Link>
                    </div>
                </div>
                <div className="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {(
                        [
                            ['total', 'Total de productos', 'all'],
                            ['active', 'Venta activa', 'active'],
                            ['ready', 'Listos para publicar', 'ready'],
                            ['missing', 'Requisitos pendientes', 'missing'],
                        ] as const
                    ).map(([key, label, state]) => (
                        <button
                            key={key}
                            onClick={() => visit({ state })}
                            aria-pressed={(filters.state ?? 'all') === state}
                            className={`rounded-2xl border bg-surface p-4 text-left transition hover:border-brand ${(filters.state ?? 'all') === state ? 'border-brand ring-1 ring-brand/20' : 'border-border'}`}
                        >
                            <span className="text-sm text-muted-foreground">
                                {label}
                            </span>
                            <strong className="mt-2 block text-3xl font-semibold tracking-tight">
                                {counts[key]}
                            </strong>
                        </button>
                    ))}
                </div>
                {notice && (
                    <div
                        role="status"
                        className="mb-4 flex items-center justify-between rounded-xl bg-emerald-500/10 p-4 text-sm"
                    >
                        <span>{notice}</span>
                        <button
                            onClick={() => setNotice('')}
                            aria-label="Cerrar resultado"
                        >
                            <X size={18} />
                        </button>
                    </div>
                )}
                <section
                    className="rounded-2xl border border-border bg-surface"
                    aria-label="Catálogo digital"
                >
                    <div className="space-y-3 border-b border-border p-4">
                        <div className="relative">
                            <Search
                                className="absolute top-3.5 left-3 text-muted-foreground"
                                size={18}
                            />
                            <input
                                className={control + ' w-full pl-10'}
                                type="search"
                                aria-label="Buscar productos"
                                data-catalog-search
                                placeholder="Buscar por nombre, slug, categoría o APP…"
                                value={search}
                                onChange={(e) => {
                                    router.cancelAll();
                                    setSearch(e.target.value);
                                }}
                            />
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <SlidersHorizontal
                                size={18}
                                className="my-3 hidden text-muted-foreground sm:block"
                            />
                            <select
                                className={control + ' flex-1'}
                                aria-label="Estado"
                                value={filters.state ?? 'all'}
                                onChange={(e) =>
                                    visit({ state: e.target.value })
                                }
                            >
                                {Object.entries(states).map(([key, label]) => (
                                    <option key={key} value={key}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                            <select
                                className={control + ' flex-1'}
                                aria-label="Categoría"
                                value={filters.category ?? ''}
                                onChange={(e) =>
                                    visit({ category: e.target.value })
                                }
                            >
                                <option value="">Todas las categorías</option>
                                <option value="none">Sin categoría</option>
                                {Object.entries(categoryLabels).map(
                                    ([k, v]) => (
                                        <option key={k} value={k}>
                                            {v}
                                        </option>
                                    ),
                                )}
                            </select>
                            <select
                                className={control}
                                aria-label="Tipo de producto"
                                value={filters.type ?? ''}
                                onChange={(e) =>
                                    visit({ type: e.target.value })
                                }
                            >
                                <option value="">Todos los tipos</option>
                                {types.map((t) => (
                                    <option key={t} value={t}>
                                        {t === 'hosted_app'
                                            ? 'APP alojada'
                                            : t === 'pdf'
                                              ? 'Descarga digital'
                                              : t}
                                    </option>
                                ))}
                            </select>
                            <select
                                className={control}
                                aria-label="QR"
                                value={filters.qr ?? ''}
                                onChange={(e) => visit({ qr: e.target.value })}
                            >
                                <option value="">Todos los QR</option>
                                {Object.entries(qrLabels).map(([k, v]) => (
                                    <option key={k} value={k}>
                                        {v}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
                            <div
                                className="flex items-center gap-3"
                                aria-live="polite"
                            >
                                <span>{products.total} resultados</span>
                                {filtered && (
                                    <button
                                        className="font-semibold text-brand"
                                        onClick={() => {
                                            router.get(
                                                base,
                                                {},
                                                {
                                                    preserveState: true,
                                                    preserveScroll: true,
                                                },
                                            );
                                        }}
                                    >
                                        Limpiar filtros
                                    </button>
                                )}
                                {busy && (
                                    <LoaderCircle
                                        size={16}
                                        className="animate-spin"
                                    />
                                )}
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <select
                                    className={control}
                                    aria-label="Ordenar productos"
                                    value={filters.sort ?? 'attention'}
                                    onChange={(e) =>
                                        visit({ sort: e.target.value })
                                    }
                                >
                                    {Object.entries({
                                        attention: 'Requieren atención primero',
                                        name_asc: 'Nombre A–Z',
                                        name_desc: 'Nombre Z–A',
                                        created_desc: 'Más recientes',
                                        created_asc: 'Más antiguos',
                                        updated_desc: 'Última modificación',
                                        category: 'Categoría',
                                        price_asc: 'Precio: menor a mayor',
                                        price_desc: 'Precio: mayor a menor',
                                        commercial: 'Estado comercial',
                                    }).map(([k, v]) => (
                                        <option key={k} value={k}>
                                            {v}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    className={control}
                                    aria-label="Productos por página"
                                    value={filters.per_page ?? '25'}
                                    onChange={(e) =>
                                        visit({ per_page: e.target.value })
                                    }
                                >
                                    {[25, 50, 100].map((n) => (
                                        <option key={n} value={n}>
                                            {n} por página
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </div>
                    <div className="flex min-h-14 flex-wrap items-center gap-3 border-b border-border px-4 py-3 text-sm">
                        <label className="flex items-center gap-2">
                            <input
                                ref={checkAll}
                                type="checkbox"
                                className="h-4 w-4 accent-brand"
                                disabled={!products.data.length}
                                checked={
                                    products.data.length > 0 &&
                                    selected.length === products.data.length
                                }
                                onChange={(e) => {
                                    setSelected(
                                        e.target.checked
                                            ? products.data.map((p) => p.id)
                                            : [],
                                    );
                                    setPreview(null);
                                }}
                            />
                            Seleccionar página ({products.data.length})
                        </label>
                        {selected.length > 0 ? (
                            <>
                                <span className="font-semibold">
                                    {selected.length} seleccionados de esta
                                    página
                                </span>
                                <button
                                    className={primary}
                                    onClick={() => openPanel(selected)}
                                >
                                    Editar seleccionados
                                </button>
                                <button
                                    className={button}
                                    onClick={() =>
                                        openPanel(selected, 'publish_sale')
                                    }
                                >
                                    Publicar y activar venta
                                </button>
                                <button
                                    className={button}
                                    onClick={() => setSelected([])}
                                >
                                    Cancelar selección
                                </button>
                            </>
                        ) : (
                            <span className="text-xs text-muted-foreground">
                                La selección abarca solo esta página, nunca
                                todos los resultados.
                            </span>
                        )}
                    </div>
                    <div
                        aria-busy={busy}
                        className={busy ? 'opacity-60 transition-opacity' : ''}
                    >
                        <table className="hidden w-full table-fixed text-left text-sm xl:table">
                            <caption className="sr-only">
                                Productos digitales. Selecciona filas para
                                realizar cambios masivos.
                            </caption>
                            <colgroup>
                                <col style={{ width: 36 }} />
                                <col style={{ width: 56 }} />
                                <col style={{ width: '19%' }} />
                                <col style={{ width: '12%' }} />
                                <col style={{ width: '12%' }} />
                                <col style={{ width: '10%' }} />
                                <col style={{ width: '11%' }} />
                                <col style={{ width: '10%' }} />
                                <col />
                            </colgroup>
                            <thead className="border-b border-border bg-muted/40 text-xs text-muted-foreground">
                                <tr>
                                    {[
                                        'Selección',
                                        'Portada',
                                        'Producto',
                                        'Categoría',
                                        'Precios',
                                        'Editorial',
                                        'Venta',
                                        'QR',
                                        'Acciones',
                                    ].map((h, i) => (
                                        <th
                                            key={h}
                                            scope="col"
                                            className="px-3 py-3 font-medium"
                                        >
                                            {i < 2 ? (
                                                <span className="sr-only">
                                                    {h}
                                                </span>
                                            ) : (
                                                h
                                            )}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {products.data.map((p) => (
                                    <tr
                                        key={p.id}
                                        className={`border-b border-border/70 last:border-0 hover:bg-muted/30 ${selected.includes(p.id) ? 'bg-brand/5' : ''}`}
                                    >
                                        <td className="py-4 pr-2 pl-4">
                                            {selection(p)}
                                        </td>
                                        <td className="p-2">{thumbnail(p)}</td>
                                        <td className="max-w-56 px-3 py-4">
                                            <Link
                                                className="font-semibold hover:text-brand"
                                                href={`${base}/${p.slug}/editar`}
                                            >
                                                {p.name}
                                            </Link>
                                            <p className="mt-1 text-xs break-all text-muted-foreground">
                                                {p.slug}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {p.delivery_type ===
                                                'hosted_app'
                                                    ? 'APP alojada'
                                                    : 'Descarga digital'}
                                            </p>
                                        </td>
                                        <td className="max-w-36 px-3 py-4 text-muted-foreground">
                                            {categoryLabels[p.category ?? ''] ??
                                                'Sin categoría'}
                                        </td>
                                        <td className="px-3 py-4 whitespace-nowrap">
                                            <span className="font-semibold">
                                                {digitalMoney(
                                                    p.public_price,
                                                    p.currency,
                                                )}
                                            </span>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Miembro{' '}
                                                {digitalMoney(
                                                    p.member_price,
                                                    p.currency,
                                                )}
                                            </p>
                                        </td>
                                        <td className="px-3 py-4">
                                            {badge(
                                                p.status === 'published'
                                                    ? 'Publicado'
                                                    : 'Borrador',
                                            )}
                                        </td>
                                        <td className="px-3 py-4">
                                            {commercial(p)}
                                        </td>
                                        <td className="px-3 py-4">
                                            {badge(qrLabels[p.sale.qr])}
                                        </td>
                                        <td className="px-3 py-4">
                                            {actions(p)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <div className="divide-y divide-border xl:hidden">
                            {products.data.map((p) => (
                                <article
                                    key={p.id}
                                    className={`p-4 ${selected.includes(p.id) ? 'bg-brand/5' : ''}`}
                                >
                                    <div className="flex items-start gap-3">
                                        <div className="pt-4">
                                            {selection(p)}
                                        </div>
                                        {thumbnail(p)}
                                        <div className="min-w-0 flex-1">
                                            <Link
                                                className="font-semibold"
                                                href={`${base}/${p.slug}/editar`}
                                            >
                                                {p.name}
                                            </Link>
                                            <p className="mt-1 text-xs break-all text-muted-foreground">
                                                {p.slug}
                                            </p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {categoryLabels[
                                                    p.category ?? ''
                                                ] ?? 'Sin categoría'}{' '}
                                                ·{' '}
                                                {p.delivery_type ===
                                                'hosted_app'
                                                    ? 'APP'
                                                    : 'Descarga'}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="my-4 flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold">
                                                {digitalMoney(
                                                    p.public_price,
                                                    p.currency,
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Miembro{' '}
                                                {digitalMoney(
                                                    p.member_price,
                                                    p.currency,
                                                )}
                                            </p>
                                        </div>
                                        {badge(
                                            p.status === 'published'
                                                ? 'Publicado'
                                                : 'Borrador',
                                        )}
                                        {commercial(p)}
                                        {badge('QR ' + qrLabels[p.sale.qr])}
                                    </div>
                                    {actions(p)}
                                </article>
                            ))}
                        </div>
                        {!products.data.length && (
                            <div className="px-6 py-16 text-center">
                                <Package
                                    size={32}
                                    className="mx-auto mb-4 text-muted-foreground"
                                />
                                <h2 className="font-semibold">
                                    No hay productos con estos criterios
                                </h2>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Prueba otra búsqueda o limpia los filtros.
                                </p>
                            </div>
                        )}
                    </div>
                    <nav
                        aria-label="Paginación"
                        className="flex flex-wrap items-center justify-between gap-3 border-t border-border p-4 text-sm"
                    >
                        <span className="text-muted-foreground">
                            {products.from ?? 0}–{products.to ?? 0} de{' '}
                            {products.total}
                        </span>
                        <div className="flex items-center gap-3">
                            <button
                                className={button}
                                disabled={!products.prev_page_url || busy}
                                onClick={() =>
                                    router.visit(products.prev_page_url!, {
                                        preserveScroll: true,
                                        onStart: () => setBusy(true),
                                        onFinish: () => setBusy(false),
                                    })
                                }
                            >
                                <ChevronLeft size={16} />
                                Anterior
                            </button>
                            <span>
                                {products.current_page} / {products.last_page}
                            </span>
                            <button
                                className={button}
                                disabled={!products.next_page_url || busy}
                                onClick={() =>
                                    router.visit(products.next_page_url!, {
                                        preserveScroll: true,
                                        onStart: () => setBusy(true),
                                        onFinish: () => setBusy(false),
                                    })
                                }
                            >
                                Siguiente
                                <ChevronRight size={16} />
                            </button>
                        </div>
                    </nav>
                </section>
                {panel && (
                    <div
                        className="fixed inset-0 z-50 flex justify-end bg-black/40"
                        onMouseDown={(e) => {
                            if (e.target === e.currentTarget && !working)
                                setPanel(false);
                        }}
                    >
                        <div
                            ref={panelRef}
                            role="dialog"
                            aria-modal="true"
                            aria-labelledby="bulk-title"
                            tabIndex={-1}
                            onKeyDown={(e) => {
                                if (e.key === 'Escape' && !working)
                                    setPanel(false);
                                if (e.key === 'Tab') {
                                    const items = Array.from(
                                        e.currentTarget.querySelectorAll<HTMLElement>(
                                            'button:not(:disabled),input:not(:disabled),select:not(:disabled)',
                                        ),
                                    );
                                    const first = items[0],
                                        last = items[items.length - 1];
                                    if (
                                        e.shiftKey &&
                                        document.activeElement === first
                                    ) {
                                        e.preventDefault();
                                        last?.focus();
                                    } else if (
                                        !e.shiftKey &&
                                        document.activeElement === last
                                    ) {
                                        e.preventDefault();
                                        first?.focus();
                                    }
                                }
                            }}
                            className="flex h-full w-full max-w-xl flex-col bg-surface shadow-2xl outline-none"
                        >
                            <div className="flex items-center justify-between border-b border-border p-5">
                                <div>
                                    <h2
                                        id="bulk-title"
                                        className="text-lg font-bold"
                                    >
                                        Edición de seleccionados
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {selected.length} productos · solo se
                                        cambiarán los campos marcados
                                    </p>
                                </div>
                                <button
                                    className={button}
                                    disabled={working}
                                    aria-label="Cerrar edición"
                                    onClick={() => setPanel(false)}
                                >
                                    <X size={18} />
                                </button>
                            </div>
                            <div className="flex-1 space-y-5 overflow-y-auto p-5">
                                <fieldset
                                    disabled={working}
                                    className="space-y-5"
                                >
                                    <div>
                                        <label className="flex items-center gap-2 text-sm font-semibold">
                                            <input
                                                type="checkbox"
                                                checked={'category' in changes}
                                                onChange={(e) =>
                                                    include(
                                                        'category',
                                                        e.target.checked,
                                                        null,
                                                    )
                                                }
                                            />
                                            Asignar categoría editorial
                                        </label>
                                        {'category' in changes && (
                                            <select
                                                className={
                                                    control + ' mt-2 w-full'
                                                }
                                                aria-label="Nueva categoría"
                                                value={changes.category ?? ''}
                                                onChange={(e) =>
                                                    change(
                                                        'category',
                                                        e.target.value || null,
                                                    )
                                                }
                                            >
                                                <option value="">
                                                    Sin categoría
                                                </option>
                                                {Object.entries(
                                                    categoryLabels,
                                                ).map(([k, v]) => (
                                                    <option key={k} value={k}>
                                                        {v}
                                                    </option>
                                                ))}
                                            </select>
                                        )}
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Reemplaza la categoría anterior en
                                            los seleccionados.
                                        </p>
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        {(
                                            [
                                                'public_price',
                                                'member_price',
                                            ] as const
                                        ).map((key) => (
                                            <div key={key}>
                                                <label className="flex items-center gap-2 text-sm font-semibold">
                                                    <input
                                                        type="checkbox"
                                                        checked={key in changes}
                                                        onChange={(e) =>
                                                            include(
                                                                key,
                                                                e.target
                                                                    .checked,
                                                                '',
                                                            )
                                                        }
                                                    />
                                                    {key === 'public_price'
                                                        ? 'Precio público'
                                                        : 'Precio miembro'}
                                                </label>
                                                {key in changes && (
                                                    <input
                                                        className={
                                                            control +
                                                            ' mt-2 w-full'
                                                        }
                                                        aria-label={
                                                            key ===
                                                            'public_price'
                                                                ? 'Nuevo precio público'
                                                                : 'Nuevo precio miembro'
                                                        }
                                                        type="number"
                                                        min="0.01"
                                                        step="0.01"
                                                        value={changes[key]}
                                                        onChange={(e) =>
                                                            change(
                                                                key,
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Los importes usan la moneda actual de
                                        cada producto. Los QR deben coincidir
                                        con los nuevos precios.
                                    </p>
                                    <button
                                        className={button}
                                        disabled={products.data.some(
                                            (p) =>
                                                selected.includes(p.id) &&
                                                p.currency !== 'BOB',
                                        )}
                                        title="Disponible para productos en bolivianos"
                                        onClick={() => {
                                            setChanges((s) => ({
                                                ...s,
                                                public_price: '49.00',
                                                member_price: '29.00',
                                            }));
                                            setPreview(null);
                                        }}
                                    >
                                        Aplicar Bs 49 / Bs 29
                                    </button>
                                    <div>
                                        <label className="flex items-center gap-2 text-sm font-semibold">
                                            <input
                                                type="checkbox"
                                                checked={'qr_mode' in changes}
                                                onChange={(e) =>
                                                    include(
                                                        'qr_mode',
                                                        e.target.checked,
                                                        'shared',
                                                    )
                                                }
                                            />
                                            Cambiar modo de QR
                                        </label>
                                        {'qr_mode' in changes && (
                                            <select
                                                className={
                                                    control + ' mt-2 w-full'
                                                }
                                                aria-label="Nuevo modo QR"
                                                value={changes.qr_mode}
                                                onChange={(e) =>
                                                    change(
                                                        'qr_mode',
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                <option value="shared">
                                                    Compartidos compatibles
                                                </option>
                                                <option value="specific">
                                                    Específicos del producto
                                                </option>
                                            </select>
                                        )}
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Compartidos: se comprueban importe,
                                            moneda y cuenta autorizada.
                                        </p>
                                    </div>
                                    <label className="flex items-start gap-2 text-sm">
                                        <input
                                            className="mt-1"
                                            type="checkbox"
                                            checked={!!changes.fill_defaults}
                                            onChange={(e) =>
                                                include(
                                                    'fill_defaults',
                                                    e.target.checked,
                                                    true,
                                                )
                                            }
                                        />
                                        <span>
                                            Completar soporte y condiciones
                                            globales cuando falten
                                            <span className="mt-1 block text-xs text-muted-foreground">
                                                Solo APP alojadas. Conserva
                                                textos personalizados.
                                            </span>
                                        </span>
                                    </label>
                                    <div>
                                        <label
                                            htmlFor="bulk-operation"
                                            className="text-sm font-semibold"
                                        >
                                            Acción comercial
                                        </label>
                                        <select
                                            id="bulk-operation"
                                            className={control + ' mt-2 w-full'}
                                            value={changes.operation ?? ''}
                                            onChange={(e) => {
                                                if (e.target.value)
                                                    change(
                                                        'operation',
                                                        e.target.value,
                                                    );
                                                else
                                                    include(
                                                        'operation',
                                                        false,
                                                        '',
                                                    );
                                            }}
                                        >
                                            <option value="">
                                                Conservar estado actual
                                            </option>
                                            <option value="publish_sale">
                                                Publicar y activar venta
                                            </option>
                                            <option value="disable_sale">
                                                Desactivar venta
                                            </option>
                                        </select>
                                    </div>
                                </fieldset>
                                {error && (
                                    <p
                                        role="alert"
                                        className="rounded-xl bg-red-500/10 p-3 text-sm text-red-700 dark:text-red-300"
                                    >
                                        {error}
                                    </p>
                                )}
                                {preview && (
                                    <section
                                        aria-label="Vista previa"
                                        className="space-y-3 rounded-xl border border-border p-4"
                                    >
                                        <h3 className="font-bold">
                                            Vista previa · {preview.ready}{' '}
                                            listos / {preview.blocked}{' '}
                                            bloqueados
                                        </h3>
                                        <p className="text-xs text-muted-foreground">
                                            Se volverá a validar al aplicar. Los
                                            bloqueados se omiten sin modificar
                                            sus campos.
                                        </p>
                                        {preview.results.map((r) => (
                                            <div
                                                key={r.id}
                                                className="border-t border-border pt-3 text-sm"
                                            >
                                                <p className="flex items-start gap-2 font-semibold">
                                                    {r.missing.length ? (
                                                        <X
                                                            size={16}
                                                            className="shrink-0 text-amber-600"
                                                        />
                                                    ) : (
                                                        <Check
                                                            size={16}
                                                            className="shrink-0 text-emerald-600"
                                                        />
                                                    )}
                                                    {r.name}
                                                </p>
                                                {r.missing.map((m) => (
                                                    <p
                                                        className="mt-1 text-xs text-muted-foreground"
                                                        key={m}
                                                    >
                                                        {m}
                                                    </p>
                                                ))}
                                                {r.after &&
                                                    Object.entries(r.after)
                                                        .filter(
                                                            ([key, v]) =>
                                                                r.before?.[
                                                                    key
                                                                ] !== v,
                                                        )
                                                        .map(([key, v]) => (
                                                            <p
                                                                key={key}
                                                                className="mt-1 text-xs text-muted-foreground"
                                                            >
                                                                {key ===
                                                                'category'
                                                                    ? 'Categoría'
                                                                    : key ===
                                                                        'public_price'
                                                                      ? 'Precio público'
                                                                      : 'Precio miembro'}
                                                                :{' '}
                                                                {key ===
                                                                'category'
                                                                    ? (categoryLabels[
                                                                          r
                                                                              .before?.[
                                                                              key
                                                                          ] ??
                                                                              ''
                                                                      ] ??
                                                                      'Sin categoría')
                                                                    : (r
                                                                          .before?.[
                                                                          key
                                                                      ] ??
                                                                      '—')}{' '}
                                                                →{' '}
                                                                {key ===
                                                                'category'
                                                                    ? (categoryLabels[
                                                                          v ??
                                                                              ''
                                                                      ] ??
                                                                      'Sin categoría')
                                                                    : (v ??
                                                                      '—')}
                                                            </p>
                                                        ))}
                                            </div>
                                        ))}
                                    </section>
                                )}
                            </div>
                            <div className="space-y-2 border-t border-border p-5">
                                <p className="text-xs text-muted-foreground">
                                    Campos incluidos:{' '}
                                    {Object.keys(changes)
                                        .map(
                                            (k) =>
                                                ({
                                                    category: 'categoría',
                                                    public_price:
                                                        'precio público',
                                                    member_price:
                                                        'precio miembro',
                                                    qr_mode: 'QR',
                                                    fill_defaults:
                                                        'soporte y condiciones faltantes',
                                                    operation:
                                                        changes.operation ===
                                                        'publish_sale'
                                                            ? 'publicación y venta'
                                                            : 'desactivar venta',
                                                })[k],
                                        )
                                        .join(', ') || 'ninguno'}
                                    .
                                </p>
                                <button
                                    className={primary + ' w-full'}
                                    disabled={
                                        working ||
                                        !Object.keys(changes).length ||
                                        (!!preview && !preview.ready)
                                    }
                                    onClick={() =>
                                        submit(preview ? 'apply' : 'preview')
                                    }
                                >
                                    {working ? (
                                        <>
                                            <LoaderCircle
                                                size={17}
                                                className="animate-spin"
                                            />
                                            Verificando productos…
                                        </>
                                    ) : preview ? (
                                        'APLICAR A SELECCIONADOS'
                                    ) : (
                                        'Revisar vista previa'
                                    )}
                                </button>
                                {preview && (
                                    <p className="text-xs text-muted-foreground">
                                        Una confirmación para {preview.ready}{' '}
                                        productos válidos seleccionados.
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
