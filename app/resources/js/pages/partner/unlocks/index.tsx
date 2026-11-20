import { ListPagination, type ListPage } from '@/components/list-pagination';
import {
    primaryAction,
    secondaryAction,
    unlockStates,
    type Partner,
} from '@/components/partner/proposal-fields';
import { Link } from '@inertiajs/react';
import PartnerHeading from '../layout';
type Unlock = {
    id: number;
    slug: string;
    title: string;
    status: string;
    editable: boolean;
    metrics: Record<string, number>;
};
export default function Index({
    partner,
    unlocks,
    pagination,
    canCreate,
}: {
    partner: Partner;
    unlocks: Unlock[];
    pagination: ListPage;
    canCreate: boolean;
}) {
    const base = `/partner/${partner.slug}/desbloqueos`;
    return (
        <PartnerHeading title="Desbloqueos" partner={partner}>
            <div className="max-w-4xl space-y-5">
                <p className="text-sm text-muted-foreground">
                    Prepara propuestas, consulta la revisión y revisa
                    participaciones autorizadas. JAKAWI conserva la aprobación y
                    activación.
                </p>
                {canCreate && (
                    <Link href={`${base}/crear`} className={primaryAction}>
                        Nuevo desbloqueo
                    </Link>
                )}
                {unlocks.length ? (
                    unlocks.map((u) => (
                        <article
                            key={u.id}
                            className="space-y-4 rounded-md border border-border bg-surface p-4 sm:p-6"
                        >
                            <div className="flex flex-col justify-between gap-2 sm:flex-row">
                                <h2 className="min-w-0 text-xl font-semibold break-words">
                                    {u.title}
                                </h2>
                                <span className="text-sm font-medium">
                                    {unlockStates[u.status] ?? u.status}
                                </span>
                            </div>
                            <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                                {[
                                    ['committed', 'Comprometidos'],
                                    ['confirmed', 'Confirmados'],
                                    ['fulfilled', 'Cumplidos'],
                                    ['cancelled', 'Cancelados a tiempo'],
                                    ['expired', 'Expirados'],
                                    ['no_show', 'No asistieron'],
                                ].map(([key, label]) => (
                                    <div key={key}>
                                        <dt className="text-muted-foreground">
                                            {label}
                                        </dt>
                                        <dd className="font-semibold">
                                            {u.metrics[key]}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                            <p className="text-sm">
                                Cumplimiento: {u.metrics.fulfillment_rate}% de
                                participaciones comprometidas.
                            </p>
                            <div className="flex flex-wrap gap-3">
                                <Link
                                    className={secondaryAction}
                                    href={`${base}/${u.slug}/editar`}
                                >
                                    {u.editable
                                        ? 'Editar borrador'
                                        : 'Ver propuesta'}
                                </Link>
                                <Link
                                    className={secondaryAction}
                                    href={`${base}/${u.slug}/participaciones`}
                                >
                                    Ver participaciones
                                </Link>
                            </div>
                        </article>
                    ))
                ) : (
                    <div className="rounded-md border border-border bg-surface p-6">
                        <h2 className="text-xl font-semibold">
                            Prepara tu primer desbloqueo
                        </h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Define la propuesta, su mínimo de compromisos y sus
                            condiciones. Después podrás enviarla a revisión.
                        </p>
                    </div>
                )}
                <ListPagination page={pagination} />
            </div>
        </PartnerHeading>
    );
}
