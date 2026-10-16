import { ListPagination, type ListPage } from '@/components/list-pagination';
import { Link, router } from '@inertiajs/react';
import { ImageOff, Search } from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '../layout';

type Partner = { id: number; name: string; url?: string };
type Item = {
    id: number;
    title: string;
    slug: string;
    partners: Partner[];
    imageUrl: string | null;
    value: string | null;
    status: string;
    reviewStatus: string | null;
    updatedAt: string | null;
    startsAt: string | null;
    endsAt: string | null;
    landingStatus: string | null;
    editUrl: string;
    previewUrl: string | null;
    publicUrl: string | null;
    reviewUrl: string | null;
};
type Filters = {
    sort?: string;
    review_filter?: string;
    q?: string;
    status_filter?: string;
    partner_filter?: string;
    page?: number;
};
type Props = {
    title: string;
    resource: string;
    baseUrl: string;
    createUrl: string;
    prelaunch: boolean;
    filters: Filters;
    partners: Partner[];
    statuses: string[];
    items: ListPage & {
        data: Item[];
        total: number;
        from: number | null;
        to: number | null;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};
const labels: Record<string, string> = {
    draft: 'Borrador',
    published: 'Publicado',
    paused: 'Pausado',
    archived: 'Archivado',
    open: 'Abierto',
    closed: 'Cerrado',
    cancelled: 'Cancelado',
    DRAFT: 'Borrador',
    PUBLISHED: 'Publicada',
    ARCHIVED: 'Archivada',
    SUBMITTED: 'Pendiente de revisión',
    APPROVED: 'Aprobado',
    REJECTED: 'Rechazado',
    CHANGES_REQUESTED: 'Cambios solicitados',
};
function Badge({ status }: { status: string }) {
    const color = ['published', 'PUBLISHED', 'open', 'APPROVED'].includes(
        status,
    )
        ? 'border-[#24b47e]/40 bg-[#24b47e]/10 text-foreground'
        : ['draft', 'DRAFT', 'SUBMITTED'].includes(status)
          ? 'border-[#ffc83d]/60 bg-[#ffc83d]/20 text-foreground'
          : 'border-border bg-muted text-muted-foreground';
    return (
        <span
            className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold ${color}`}
        >
            {labels[status] ?? status}
        </span>
    );
}
const date = (value: string | null) =>
    value
        ? new Date(value).toLocaleDateString('es-BO', {
              timeZone: 'America/La_Paz',
              day: '2-digit',
              month: 'short',
              year: 'numeric',
          })
        : '—';
function Thumbnail({ src }: { src: string | null }) {
    const [failed, setFailed] = useState(false);
    return (
        <div className="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border bg-[#fff8f2] text-[#111111]/40">
            {src && !failed ? (
                <img
                    src={src}
                    alt=""
                    className="size-full object-cover"
                    loading="lazy"
                    onError={() => setFailed(true)}
                />
            ) : (
                <ImageOff className="size-5" aria-label="Sin imagen" />
            )}
        </div>
    );
}
export default function Catalog({
    title,
    resource,
    baseUrl,
    createUrl,
    prelaunch,
    filters,
    partners,
    statuses,
    items,
}: Props) {
    const [sort, setSort] = useState(filters.sort ?? 'updated');
    const [q, setQ] = useState(filters.q ?? '');
    const [status, setStatus] = useState(filters.status_filter ?? '');
    const [partner, setPartner] = useState(
        String(filters.partner_filter ?? ''),
    );
    const context = new URLSearchParams();
    if (filters.review_filter)
        context.set('review_filter', filters.review_filter);
    if (filters.sort) context.set('sort', filters.sort);
    if (filters.q) context.set('q', filters.q);
    if (filters.status_filter)
        context.set('status_filter', filters.status_filter);
    if (filters.partner_filter)
        context.set('partner_filter', String(filters.partner_filter));
    if (items.current_page > 1) context.set('page', String(items.current_page));
    const query = context.size ? `?${context}` : '';
    const inputClass =
        'min-h-11 w-full min-w-0 rounded-lg border border-border bg-background px-3 text-sm';
    const actionClass =
        'inline-flex min-h-11 items-center justify-center rounded-lg border border-border px-3 text-sm font-semibold hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand';
    return (
        <AdminLayout title={title}>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {items.total} propuestas · Más recientes primero
                    </p>
                    {prelaunch && (
                        <p className="mt-1 text-sm text-muted-foreground">
                            Pre-lanzamiento activo. Publicado no implica
                            disponibilidad comercial.
                        </p>
                    )}
                </div>
                <Link
                    href={createUrl}
                    className="inline-flex min-h-11 items-center rounded-lg bg-brand px-4 text-sm font-bold text-brand-foreground"
                >
                    Crear{' '}
                    {resource === 'benefits'
                        ? 'beneficio'
                        : resource === 'experiences'
                          ? 'experiencia'
                          : 'reto'}
                </Link>
            </div>
            {resource === 'challenges' && (
                <Link
                    href={`${baseUrl}?review_filter=SUBMITTED`}
                    className="mt-4 inline-flex min-h-11 items-center text-sm font-semibold text-brand underline"
                >
                    Pendientes de revisión
                </Link>
            )}
            <form
                className="my-5 grid gap-3 rounded-xl border border-border bg-card p-4 lg:grid-cols-[minmax(0,2fr)_1fr_1fr_auto_auto] lg:items-end"
                onSubmit={(event) => {
                    event.preventDefault();
                    router.get(
                        baseUrl,
                        {
                            q,
                            sort, status_filter: status,
                            partner_filter: partner,
                            review_filter: filters.review_filter ?? '',
                        },
                        { preserveState: true, preserveScroll: true },
                    );
                }}
            >
                <label className="min-w-0 text-sm font-medium">
                    Buscar producto o Partner
                    <div className="relative mt-1">
                        <Search className="absolute top-3.5 left-3 size-4 text-muted-foreground" />
                        <input
                            className={`${inputClass} pl-9`}
                            value={q}
                            onChange={(event) => setQ(event.target.value)}
                            placeholder="Nombre de propuesta o Partner"
                            maxLength={200}
                        />
                    </div>
                </label>
                <label className="min-w-0 text-sm font-medium">
                    Estado
                    <select
                        className={`${inputClass} mt-1`}
                        value={status}
                        onChange={(event) => setStatus(event.target.value)}
                    >
                        <option value="">Todos los estados</option>
                        {statuses.map((s) => (
                            <option key={s} value={s}>
                                {labels[s] ?? s}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="min-w-0 text-sm font-medium">
                    Partner
                    <select
                        className={`${inputClass} mt-1`}
                        value={partner}
                        onChange={(event) => setPartner(event.target.value)}
                    >
                        <option value="">Todos los Partners</option>
                        {partners.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name}
                            </option>
                        ))}
                    </select>
                </label>
                <button className={actionClass}>Aplicar filtros</button>
                <Link className={actionClass} href={baseUrl}>
                    Limpiar
                </Link>
                <label className="grid gap-1 text-sm">Ordenar<select className="min-h-11 rounded-lg border border-border bg-surface px-3" value={sort} onChange={e => setSort(e.target.value)}><option value="updated">Última actualización</option><option value="newest">Más recientes</option><option value="name">Nombre A–Z</option></select></label>
            </form>
            <div className="space-y-3 lg:space-y-0 lg:overflow-hidden lg:rounded-xl lg:border lg:border-border">
                <div
                    className="hidden grid-cols-[minmax(0,2fr)_minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1.2fr)_minmax(0,.9fr)_minmax(0,1.5fr)] gap-4 bg-muted/50 px-4 py-3 text-xs font-semibold tracking-wide text-muted-foreground lg:grid"
                    aria-hidden="true"
                >
                    {[
                        'Producto',
                        'Partner',
                        'Valor / Precio',
                        'Estado',
                        'Actualizado',
                        'Acciones',
                    ].map((label) => (
                        <span key={label}>{label}</span>
                    ))}
                </div>
                {items.data.map((item) => (
                    <article
                        key={item.id}
                        aria-label={item.title}
                        className="grid min-w-0 gap-4 rounded-xl border border-border bg-card p-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1.2fr)_minmax(0,.9fr)_minmax(0,1.5fr)] lg:items-center lg:rounded-none lg:border-0 lg:border-t"
                    >
                        <div className="flex min-w-0 items-center gap-3">
                            <Thumbnail src={item.imageUrl} />
                            <div className="min-w-0">
                                <h2 className="text-sm font-bold break-words">
                                    {item.title}
                                </h2>
                                {(item.startsAt || item.endsAt) && (
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Vigencia:{' '}
                                        {item.startsAt
                                            ? date(item.startsAt)
                                            : 'Sin inicio'}{' '}
                                        →{' '}
                                        {item.endsAt
                                            ? date(item.endsAt)
                                            : 'Sin cierre'}
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="min-w-0 text-sm break-words">
                            {item.partners.length ? (
                                item.partners.map((p) => (
                                    <Link
                                        key={p.id}
                                        href={p.url ?? '#'}
                                        className="block py-1 font-medium underline decoration-border underline-offset-4 hover:text-brand"
                                    >
                                        {p.name}
                                    </Link>
                                ))
                            ) : (
                                <span className="text-muted-foreground">
                                    Sin Partner asignado
                                </span>
                            )}
                        </div>
                        <div className="text-sm break-words">
                            <span className="mr-2 text-xs text-muted-foreground lg:hidden">
                                Valor / Precio:
                            </span>
                            {item.value ?? 'Sin valor definido'}
                        </div>
                        <div className="flex flex-wrap items-center gap-1.5">
                            <Badge status={item.status} />
                            {item.landingStatus && (
                                <span className="w-full text-xs text-muted-foreground">
                                    Landing:{' '}
                                    {labels[item.landingStatus] ??
                                        item.landingStatus}
                                </span>
                            )}
                            {item.reviewStatus && (
                                <span className="w-full text-xs text-muted-foreground">
                                    Revisión:{' '}
                                    {labels[item.reviewStatus] ??
                                        item.reviewStatus}
                                </span>
                            )}
                        </div>
                        <p className="text-xs text-muted-foreground">
                            <span className="lg:hidden">Actualizado: </span>
                            {date(item.updatedAt)}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Link
                                href={item.editUrl + query}
                                className={`${actionClass} border-brand/30 text-brand`}
                            >
                                Editar
                            </Link>
                            {item.previewUrl && (
                                <a
                                    href={item.previewUrl}
                                    className={actionClass}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Preview
                                    <span className="sr-only">
                                        {' '}
                                        (nueva pestaña)
                                    </span>
                                </a>
                            )}
                            {item.publicUrl && (
                                <a
                                    href={item.publicUrl}
                                    className={actionClass}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Página pública
                                    <span className="sr-only">
                                        {' '}
                                        (nueva pestaña)
                                    </span>
                                </a>
                            )}
                            {item.reviewUrl && (
                                <Link
                                    href={item.reviewUrl}
                                    className={actionClass}
                                >
                                    Gestión / revisión
                                </Link>
                            )}
                        </div>
                    </article>
                ))}
                {!items.data.length && (
                    <div className="rounded-xl border border-dashed border-border p-8 text-center lg:border-0">
                        <h2 className="font-semibold">
                            {filters.q ||
                            filters.status_filter ||
                            filters.partner_filter
                                ? 'Sin coincidencias'
                                : 'Aún no hay propuestas'}
                        </h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {filters.q ||
                            filters.status_filter ||
                            filters.partner_filter
                                ? 'Prueba con otro nombre o limpia los filtros.'
                                : 'Crea la primera propuesta para comenzar el catálogo.'}
                        </p>
                    </div>
                )}
            </div>
            <ListPagination page={items} />
        </AdminLayout>
    );
}
