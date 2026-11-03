import { ListPagination } from '@/components/list-pagination';
import { Head, Link, usePage } from '@inertiajs/react';
import PartnerLayout from '../layout';

const reviewLabels: Record<string, string> = { draft: 'Borrador', submitted: 'En revisión', changes_requested: 'Cambios solicitados', rejected: 'Rechazada', approved: 'Aprobada' };
const date = (value: string) => new Intl.DateTimeFormat('es-BO', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'America/La_Paz' }).format(new Date(value));
function status(item: any) {
    if (item.status === 'published') return item.ends_at && new Date(item.ends_at) < new Date() ? 'Vencida' : 'Publicada';
    return reviewLabels[item.review_status] ?? ({ paused: 'Pausada', archived: 'Archivada' }[item.status as string] ?? 'Sin publicar');
}
export default function ContentIndex({ partner, kind, items, state }: any) {
    const isBenefit = kind === 'benefit'; const label = isBenefit ? 'Promociones' : 'Experiencias';
    const base = `/partner/${partner.slug}/${isBenefit ? 'promociones' : 'experiencias'}`;
    const { partnerAccess } = usePage<{ partnerAccess: { can_edit_content: boolean } }>().props;
    return <PartnerLayout title={label} partner={partner}><Head title={label}/>
        <div className="flex flex-wrap items-center justify-between gap-3"><p className="text-sm text-muted-foreground">{isBenefit ? 'Tus beneficios comerciales y su revisión editorial.' : 'Tus experiencias y sesiones.'}</p>{partnerAccess?.can_edit_content && <Link className="min-h-11 rounded-lg bg-primary px-4 py-3 font-semibold text-primary-foreground" href={`${base}/crear`}>Crear {isBenefit ? 'promoción' : 'experiencia'}</Link>}</div>
        {isBenefit && <nav aria-label="Filtrar promociones" className="mt-5 flex flex-wrap gap-2">{[['', 'Todas'], ['vigentes', 'Publicadas y vigentes'], ['draft', 'Borradores'], ['submitted', 'En revisión'], ['changes_requested', 'Cambios solicitados'], ['expired', 'Vencidas']].map(([value, text]) => <Link key={value} href={value ? `${base}?estado=${value}` : base} aria-current={(state ?? '') === value ? 'page' : undefined} className={`rounded-lg border px-3 py-2 text-sm ${(state ?? '') === value ? 'bg-primary text-primary-foreground' : 'border-border'}`}>{text}</Link>)}</nav>}
        <div className="mt-5 space-y-4">{items.data.map((item: any) => <article className="rounded-xl border border-border bg-surface p-5" key={item.id}>
            <p className="mb-2 text-sm font-semibold">{status(item)}</p>
            {partnerAccess?.can_edit_content ? <Link className="text-lg font-semibold underline" href={`${base}/${item.slug}/editar`}>{item.title}</Link> : <h2 className="text-lg font-semibold">{item.title}</h2>}
            {isBenefit && <div className="mt-3 space-y-2 text-sm">
                <p><strong>Vigencia:</strong> {item.starts_at ? date(item.starts_at) : 'Sin fecha de inicio'} → {item.ends_at ? date(item.ends_at) : 'Sin fecha de fin'} (hora Bolivia)</p>
                {item.status === 'published' && item.starts_at && new Date(item.starts_at) > new Date() && <p>Vigencia por iniciar.</p>}
                {item.ends_at && new Date(item.ends_at) < new Date() && item.status !== 'published' && <p>Vencida por fecha de vigencia.</p>}
                <p className="whitespace-pre-wrap"><strong>Condiciones comerciales:</strong> {item.terms || 'Sin condiciones registradas.'}</p>
                <p><strong>Sedes:</strong> {item.applies_to_all_locations ? 'Todas las sedes' : item.locations?.map((l: any) => l.name).join(', ') || 'Sin sedes seleccionadas'}</p>
                <p><strong>Límite por miembro:</strong> {item.redemption_limit_per_member ?? 'Sin límite registrado'}</p>
                {item.status === 'published' && <p className="text-muted-foreground">La versión pública está protegida. Proponer cambios sobre publicaciones está pendiente del flujo de revisiones.</p>}
            </div>}
            {item.review_notes && <p className="mt-3 text-sm">{item.review_status === 'changes_requested' ? 'JAKAWI solicitó cambios' : 'Nota de revisión'}: {item.review_notes}</p>}
            {!isBenefit && <p className="text-sm text-muted-foreground">{item.sessions_count} sesiones</p>}
        </article>)}{!items.data.length && <p className="text-muted-foreground">No hay contenido en esta vista.</p>}</div><ListPagination page={items} /></PartnerLayout>;
}
