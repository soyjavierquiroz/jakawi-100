import { ListPagination, type ListPage } from '@/components/list-pagination';
import { LandingVideo, type LandingVideoConfig } from '@/components/media/jakawi-vsl-player/LandingVideo';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AdminLayout from '../layout';

type Partner = { id: number; name: string };
type Filters = { q?: string; partner?: string; type?: string; status?: string; video?: string };
type Landing = {
    id: number; name: string; headline: string | null; status: string; updated_at: string | null;
    partners: Partner[]; product: { title: string; type: string } | null;
    has_video: boolean; video_note: string; video: LandingVideoConfig | null;
    edit_url: string | null; preview_url: string | null;
};
const control = 'mt-1 min-h-11 w-full min-w-0 rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-brand';
const action = 'inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 text-sm font-bold text-brand underline focus-visible:outline-2 focus-visible:outline-brand';
const types: Record<string, string> = { EXPERIENCE: 'Experience', BENEFIT: 'Benefit', CHALLENGE: 'Reto', UNLOCK: 'Desbloqueo' };

export default function Catalog({ landings, pagination, filters, partnerChoices, selectedPartner }: {
    landings: Landing[]; pagination: ListPage; filters: Filters; partnerChoices: Partner[]; selectedPartner: Partner | null;
}) {
    const [form, setForm] = useState<Filters>(filters);
    const [partnerSearch, setPartnerSearch] = useState('');
    const [choices, setChoices] = useState(partnerChoices);
    const [partnerState, setPartnerState] = useState('');
    const [viewing, setViewing] = useState<Landing | null>(null);
    useEffect(() => { setForm(filters); setChoices(partnerChoices); }, [filters, partnerChoices]);
    useEffect(() => {
        const controller = new AbortController();
        const timer = setTimeout(async () => {
            setPartnerState('Buscando Partners…');
            try {
                const response = await fetch(`/admin/landings/partners?q=${encodeURIComponent(partnerSearch)}`, { signal: controller.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('lookup');
                const found: Partner[] = await response.json();
                setChoices(current => {
                    const selected = current.find(p => String(p.id) === String(form.partner)) ?? selectedPartner;
                    return selected && !found.some(p => p.id === selected.id) ? [selected, ...found] : found;
                });
                setPartnerState(found.length ? `${found.length} Partners encontrados${found.length === 20 ? '; escribe para precisar la búsqueda' : ''}.` : 'No hay Partners con ese nombre.');
            } catch {
                if (!controller.signal.aborted) setPartnerState('No se pudo buscar. Intenta de nuevo.');
            }
        }, 300);
        return () => { clearTimeout(timer); controller.abort(); };
    }, [partnerSearch, form.partner, selectedPartner]);
    const change = (key: keyof Filters, value: string) => setForm(current => ({ ...current, [key]: value }));
    const filterSelect = (key: keyof Filters, label: string, options: [string, string][]) => (
        <label className="min-w-0 text-sm font-bold">{label}<select className={control} value={form[key] ?? ''} onChange={e => change(key, e.target.value)}>{options.map(([value, text]) => <option key={value} value={value}>{text}</option>)}</select></label>
    );
    return <AdminLayout title="Marketing Landings">
        <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-muted-foreground">Encuentra y administra las presentaciones de tus productos.</p>
            <Link href="/admin/landings/new" className="inline-flex min-h-11 items-center rounded-[var(--radius-control)] bg-brand px-5 py-3 text-sm font-extrabold text-brand-foreground focus-visible:outline-2 focus-visible:outline-brand">Nueva Landing</Link>
        </div>
        <form className="mt-5 grid min-w-0 gap-4 rounded border border-border bg-surface p-4 sm:grid-cols-2 xl:grid-cols-4" onSubmit={e => {
            e.preventDefault();
            router.get('/admin/landings', Object.fromEntries(Object.entries(form).filter(([, value]) => value)), { preserveScroll: true });
        }}>
            <label className="min-w-0 text-sm font-bold sm:col-span-2 xl:col-span-4">Buscar Landing, Partner o producto
                <input type="search" name="q" className={control} value={form.q ?? ''} onChange={e => change('q', e.target.value)} maxLength={200} placeholder="Título, nombre del Partner o del producto" />
            </label>
            <div className="min-w-0 sm:col-span-2 xl:col-span-1">
                <label className="text-sm font-bold">Buscar Partner<input type="search" className={control} value={partnerSearch} onChange={e => setPartnerSearch(e.target.value)} maxLength={200} aria-describedby="partner-lookup-status" placeholder="Escribe el nombre…" /></label>
                <label className="mt-2 block text-sm font-bold">Partner<select className={control} value={form.partner ?? ''} onChange={e => change('partner', e.target.value)}><option value="">Todos los Partners</option>{choices.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}</select></label>
                <p id="partner-lookup-status" role="status" className="mt-1 text-xs text-muted-foreground">{partnerState || 'Busca y selecciona un Partner real.'}</p>
            </div>
            {filterSelect('type', 'Tipo de producto', [['', 'Todos los tipos'], ['EXPERIENCE', 'Experience'], ['BENEFIT', 'Benefit'], ['CHALLENGE', 'Reto'], ['UNLOCK', 'Desbloqueo']])}
            {filterSelect('status', 'Estado', [['', 'Todos los estados'], ['DRAFT', 'DRAFT · Borrador'], ['PUBLISHED', 'PUBLISHED · Publicada'], ['ARCHIVED', 'ARCHIVED · Archivada']])}
            {filterSelect('video', 'Video', [['', 'Todos'], ['yes', 'Con video'], ['no', 'Sin video']])}
            <div className="flex flex-wrap items-center gap-3 sm:col-span-2 xl:col-span-4">
                <button type="submit" className="min-h-11 rounded-[var(--radius-control)] bg-brand px-5 py-2 text-sm font-bold text-brand-foreground focus-visible:outline-2 focus-visible:outline-brand">Aplicar filtros</button>
                <Link href="/admin/landings" className={action}>Limpiar filtros</Link>
            </div>
        </form>
        <p className="mt-5 text-sm text-muted-foreground">{pagination.total} Landings · Orden: última modificación · El video se reproduce al abrirlo.</p>
        {landings.length === 0 ? <section className="mt-4 rounded border border-border bg-surface p-6" role="status"><h2 className="text-lg font-bold">No hay Landings{Object.values(filters).some(Boolean) ? ' que coincidan con estos filtros' : ' todavía'}.</h2><p className="mt-2 text-sm">{filters.video === 'yes' ? 'No se encontraron Landings con un video válido configurado en su composición.' : 'Prueba otra búsqueda o limpia los filtros.'}</p><Link className={action} href="/admin/landings">Limpiar filtros</Link></section> :
            <table className="mt-4 block w-full table-fixed text-left xl:table">
                <caption className="sr-only">Landings ordenadas por última modificación descendente</caption>
                <thead className="hidden border-b border-border bg-surface text-sm xl:table-header-group"><tr>{['Landing', 'Partner', 'Producto', 'Estado', 'Video', 'Última modificación', 'Acciones'].map(label => <th key={label} scope="col" className="p-3">{label}</th>)}</tr></thead>
                <tbody className="grid min-w-0 gap-4 md:grid-cols-2 xl:table-row-group">{landings.map(item => <tr key={item.id} className="grid min-w-0 gap-3 rounded border border-border bg-surface p-4 xl:table-row xl:border-x-0 xl:border-t-0 xl:p-0 [&>td]:min-w-0 [&>td]:break-words xl:[&>td]:p-3">
                    <th scope="row" className="min-w-0 text-lg font-extrabold break-words xl:p-3 xl:text-sm">{item.name || 'Título pendiente'}{item.headline && item.headline !== item.name && <span className="mt-1 block text-xs font-normal text-muted-foreground">{item.headline}</span>}</th>
                    <td><span className="block text-xs text-muted-foreground xl:sr-only">Partner</span>{item.partners.length ? item.partners.map(p => <span className="block text-sm" key={p.id}>{p.name}</span>) : <span className="text-sm text-muted-foreground">Partner pendiente</span>}</td>
                    <td><span className="block text-xs text-muted-foreground xl:sr-only">Producto</span>{item.product ? <><span className="block text-xs text-muted-foreground">{types[item.product.type]}</span><span className="text-sm">{item.product.title}</span></> : <span className="text-sm text-muted-foreground">Producto pendiente</span>}</td>
                    <td><span className="block text-xs text-muted-foreground xl:sr-only">Estado</span><span className="inline-block rounded border border-border px-2 py-1 text-xs font-bold">{item.status}</span></td>
                    <td><span className="block text-xs text-muted-foreground xl:sr-only">Video</span><span className={`text-sm font-bold ${item.has_video ? 'text-brand' : 'text-muted-foreground'}`}>{item.has_video ? 'Sí · Con video' : 'No'}</span><span className="block text-xs text-muted-foreground">{item.video_note}</span></td>
                    <td><span className="block text-xs text-muted-foreground xl:sr-only">Última modificación</span><time className="text-sm" dateTime={item.updated_at ?? undefined}>{item.updated_at ? new Intl.DateTimeFormat('es-BO', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'America/La_Paz' }).format(new Date(item.updated_at)) : 'Fecha pendiente'}</time></td>
                    <td><div className="flex flex-wrap gap-1">{item.preview_url && <a href={item.preview_url} target="_blank" rel="noopener noreferrer" className={action} aria-label={`Vista previa de ${item.name} (nueva pestaña)`}>Vista previa</a>}{item.edit_url && <Link href={item.edit_url} className={action} aria-label={`Editar ${item.name}`}>Editar</Link>}{item.video && <button type="button" className={action} onClick={() => setViewing(item)} aria-label={`Ver video de ${item.name}`}>Ver video</button>}{!item.edit_url && <span className="text-sm text-muted-foreground">Asociación pendiente</span>}</div></td>
                </tr>)}</tbody>
            </table>}
        <ListPagination page={pagination} />
        <Dialog open={viewing !== null} onOpenChange={open => { if (!open) setViewing(null); }}><DialogContent className="max-h-[90dvh] overflow-y-auto"><DialogTitle className="pr-6 break-words">Video · {viewing?.name}</DialogTitle><DialogDescription>Video configurado en la Landing. El reproductor indica si la fuente no permite reproducirlo.</DialogDescription>{viewing?.video && <LandingVideo key={viewing.id} config={{ ...viewing.video, autoplay: 'off' }} />}<DialogClose className={action}>Cerrar video</DialogClose></DialogContent></Dialog>
    </AdminLayout>;
}
