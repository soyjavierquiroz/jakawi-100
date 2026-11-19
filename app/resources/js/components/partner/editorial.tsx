import { cn } from '@/lib/utils';
export type PartnerAccess = {
    can_manage: boolean;
    can_edit_content: boolean;
    can_operate: boolean;
    can_view_economics: boolean;
};
export type EditorialItem = { status: string; review_status: string };
export const publicationLabels: Record<string, string> = {
    draft: 'Borrador',
    published: 'Publicado',
    paused: 'Pausado',
    archived: 'Archivado',
};
export const reviewLabels: Record<string, string> = {
    draft: 'Borrador',
    submitted: 'En revisión',
    changes_requested: 'Cambios solicitados',
    rejected: 'Rechazado',
    approved: 'Aprobado por revisión',
};
export const editorialFilters = [
    ['', 'Todo el contenido'],
    ['draft', 'Borradores'],
    ['submitted', 'En revisión'],
    ['changes_requested', 'Cambios solicitados'],
    ['published', 'Publicados'],
    ['approved', 'Aprobados sin publicar'],
    ['rejected', 'Rechazados'],
];
export const canEditItem = (item?: EditorialItem | null) =>
    !item ||
    (item.status !== 'published' &&
        ['draft', 'changes_requested', 'rejected'].includes(
            item.review_status,
        ));
export const actionClass =
    'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-border bg-surface px-4 py-2 text-sm font-semibold hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-50';
export const primaryClass = cn(
    actionClass,
    'border-transparent bg-primary text-primary-foreground hover:bg-primary/90',
);
export const inputClass =
    'mt-2 block min-h-11 w-full min-w-0 rounded-xl border border-border bg-background px-3 py-2 text-base focus-visible:outline-2 focus-visible:outline-brand disabled:bg-muted disabled:text-muted-foreground';
export function PublicationStatus({ status }: { status: string }) {
    return (
        <span className="inline-flex rounded-full border border-border bg-muted px-3 py-1 text-xs font-semibold">
            {publicationLabels[status] ?? status}
        </span>
    );
}
export function ReviewStatus({ item }: { item: EditorialItem }) {
    return (
        <span
            className={`inline-flex rounded-full border px-3 py-1 text-xs font-semibold ${item.review_status === 'changes_requested' ? 'border-brand bg-brand-subtle' : 'border-border bg-muted'}`}
        >
            {item.status !== 'draft'
                ? (publicationLabels[item.status] ?? item.status)
                : (reviewLabels[item.review_status] ?? item.review_status)}
        </span>
    );
}
export function ReviewNote({
    item,
}: {
    item: { review_notes?: string; review_status: string };
}) {
    return item.review_notes ? (
        <aside className="rounded-xl border border-brand/40 bg-brand-subtle p-4">
            <p className="text-sm font-semibold">
                {item.review_status === 'changes_requested'
                    ? 'JAKAWI solicita estos cambios'
                    : 'Nota de revisión de JAKAWI'}
            </p>
            <p className="mt-2 text-sm break-words whitespace-pre-wrap">
                {item.review_notes}
            </p>
        </aside>
    ) : null;
}
