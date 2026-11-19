import { ListPagination } from '@/components/list-pagination';
import {
    actionClass,
    primaryClass,
    editorialFilters,
    ReviewStatus,
    ReviewNote,
    canEditItem,
    type PartnerAccess,
} from '@/components/partner/editorial';
import { Link, usePage } from '@inertiajs/react';
import PartnerLayout from '../layout';

const date = (value: string) =>
    new Intl.DateTimeFormat('es-BO', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'America/La_Paz',
    }).format(new Date(value));
export default function ContentIndex({ partner, kind, items, state }: any) {
    const benefit = kind === 'benefit';
    const label = benefit ? 'Promociones' : 'Experiencias';
    const base = `/partner/${partner.slug}/${benefit ? 'promociones' : 'experiencias'}`;
    const { partnerAccess: access } = usePage<{
        partnerAccess: PartnerAccess;
    }>().props;
    const filters = benefit
        ? [
              ...editorialFilters,
              ['vigentes', 'Publicadas y vigentes'],
              ['expired', 'Vencidas por fecha'],
          ]
        : editorialFilters;
    return (
        <PartnerLayout title={label} partner={partner}>
            <div className="flex flex-wrap items-start justify-between gap-4">
                <p className="max-w-xl text-sm leading-relaxed text-muted-foreground">
                    Prepara borradores, envía propuestas y consulta la revisión
                    de JAKAWI. La aprobación, publicación y activación comercial
                    son controles independientes.
                </p>
                {access?.can_edit_content && (
                    <Link className={primaryClass} href={`${base}/crear`}>
                        Crear {benefit ? 'promoción' : 'experiencia'}
                    </Link>
                )}
            </div>
            <nav
                aria-label={`Filtrar ${label.toLowerCase()}`}
                className="mt-5 flex flex-wrap gap-2"
            >
                {filters.map(([value, text]) => (
                    <Link
                        key={value}
                        href={value ? `${base}?estado=${value}` : base}
                        aria-current={
                            (state ?? '') === value ? 'page' : undefined
                        }
                        className={`${actionClass} ${(state ?? '') === value ? 'border-brand bg-brand-subtle' : ''}`}
                    >
                        {text}
                    </Link>
                ))}
            </nav>
            <p className="mt-4 text-sm text-muted-foreground">
                {items.total}{' '}
                {items.total === 1
                    ? 'propuesta en esta vista'
                    : 'propuestas en esta vista'}
            </p>
            <div className="mt-4 space-y-4">
                {items.data.map((item: any) => (
                    <article
                        className="space-y-4 rounded-2xl border border-border bg-surface p-5"
                        key={item.id}
                    >
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="min-w-0">
                                <ReviewStatus item={item} />
                                <h2 className="mt-3 text-xl font-semibold break-words">
                                    {item.title}
                                </h2>
                                {item.short_description && (
                                    <p className="mt-2 text-sm break-words text-muted-foreground">
                                        {item.short_description}
                                    </p>
                                )}
                            </div>
                            {access?.can_edit_content && (
                                <Link
                                    className={actionClass}
                                    href={`${base}/${item.slug}/editar`}
                                >
                                    {canEditItem(item)
                                        ? item.review_status ===
                                          'changes_requested'
                                            ? 'Revisar y corregir'
                                            : 'Editar borrador'
                                        : 'Ver propuesta'}
                                </Link>
                            )}
                        </div>
                        <ReviewNote item={item} />
                        {benefit ? (
                            <dl className="grid gap-4 text-sm sm:grid-cols-2">
                                <div>
                                    <dt className="font-semibold">
                                        Vigencia · hora Bolivia
                                    </dt>
                                    <dd className="mt-1 text-muted-foreground">
                                        {item.starts_at
                                            ? date(item.starts_at)
                                            : 'Sin fecha de inicio'}{' '}
                                        →{' '}
                                        {item.ends_at
                                            ? date(item.ends_at)
                                            : 'Sin fecha de fin'}
                                        {item.ends_at &&
                                            new Date(item.ends_at) <
                                                new Date() && (
                                                <span className="block">
                                                    Vencida por fecha de
                                                    vigencia.
                                                </span>
                                            )}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="font-semibold">Sedes</dt>
                                    <dd className="mt-1 text-muted-foreground">
                                        {item.applies_to_all_locations
                                            ? 'Todas las sedes'
                                            : item.locations
                                                  ?.map((l: any) => l.name)
                                                  .join(', ') ||
                                              'Sin sedes seleccionadas'}
                                    </dd>
                                </div>
                                <div className="sm:col-span-2">
                                    <dt className="font-semibold">
                                        Condiciones comerciales
                                    </dt>
                                    <dd className="mt-1 break-words whitespace-pre-wrap text-muted-foreground">
                                        {item.terms ||
                                            'Sin condiciones registradas.'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="font-semibold">
                                        Límite por miembro
                                    </dt>
                                    <dd className="mt-1 text-muted-foreground">
                                        {item.redemption_limit_per_member ??
                                            'Sin límite registrado'}
                                    </dd>
                                </div>
                            </dl>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {item.sessions_count} sesiones registradas.
                                Preparar sesiones no habilita reservas.
                            </p>
                        )}
                        {item.status === 'published' ? (
                            <p className="border-t border-border pt-4 text-sm text-muted-foreground">
                                Contenido publicado: su edición está protegida
                                en este portal. Solicita a JAKAWI cualquier
                                cambio sobre la versión pública. Publicado no
                                significa habilitado para operar.
                            </p>
                        ) : item.review_status === 'submitted' ? (
                            <p className="text-sm text-muted-foreground">
                                Propuesta enviada a revisión de JAKAWI. La
                                edición queda cerrada mientras se revisa.
                            </p>
                        ) : item.review_status === 'approved' ? (
                            <p className="text-sm text-muted-foreground">
                                Revisión aprobada. La publicación corresponde a
                                JAKAWI y no habilita operaciones por sí sola.
                            </p>
                        ) : null}
                    </article>
                ))}
                {!items.data.length && (
                    <section className="rounded-2xl border border-dashed border-border bg-surface p-6">
                        <h2 className="text-lg font-semibold">
                            {state
                                ? 'No hay propuestas en este estado'
                                : `Prepara tu primera ${benefit ? 'promoción' : 'experiencia'}`}
                        </h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {state
                                ? 'Prueba otra vista para encontrar tu contenido.'
                                : access?.can_edit_content
                                  ? 'Guarda un borrador con los datos principales. Después podrás enviarlo a JAKAWI para revisión.'
                                  : 'Cuando el propietario o manager prepare contenido, podrás consultarlo aquí.'}
                        </p>
                        <div className="mt-4 flex flex-wrap gap-3">
                            {state && (
                                <Link href={base} className={actionClass}>
                                    Ver todo el contenido
                                </Link>
                            )}
                            {access?.can_edit_content && (
                                <Link
                                    href={`${base}/crear`}
                                    className={primaryClass}
                                >
                                    Crear{' '}
                                    {benefit ? 'promoción' : 'experiencia'}
                                </Link>
                            )}
                        </div>
                    </section>
                )}
            </div>
            <ListPagination page={items} />
        </PartnerLayout>
    );
}
