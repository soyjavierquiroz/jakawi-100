import { ListPagination, type ListPage } from '@/components/list-pagination';
import {
    challengeStates,
    primaryAction,
    reviewStates,
    secondaryAction,
    type Partner,
} from '@/components/partner/proposal-fields';
import { Link } from '@inertiajs/react';
import PartnerHeading from '../layout';
type Challenge = {
    title: string;
    slug: string;
    status: string;
    review_status: string;
    review_comment: string | null;
    editable: boolean;
};
export default function Index({
    partner,
    challenges,
    canCreate,
}: {
    partner: Partner;
    challenges: ListPage & { data: Challenge[] };
    canCreate: boolean;
}) {
    const base = `/partner/${partner.slug}/retos`;
    return (
        <PartnerHeading title="Retos" partner={partner}>
            <div className="max-w-4xl space-y-5">
                <p className="text-sm text-muted-foreground">
                    Prepara propuestas y consulta su revisión. JAKAWI administra
                    la publicación, los ganadores y la entrega de premios.
                </p>
                {canCreate && (
                    <Link href={`${base}/crear`} className={primaryAction}>
                        Nuevo reto
                    </Link>
                )}
                {challenges.data.length ? (
                    challenges.data.map((c) => (
                        <article
                            key={c.slug}
                            className="space-y-3 rounded-md border border-border bg-surface p-4 sm:p-6"
                        >
                            <h2 className="text-xl font-semibold break-words">
                                {c.title}
                            </h2>
                            <p className="text-sm font-medium">
                                {reviewStates[c.review_status] ??
                                    c.review_status}{' '}
                                · {challengeStates[c.status] ?? c.status}
                            </p>
                            {c.review_comment && (
                                <aside
                                    role="note"
                                    className="rounded-md border border-brand/40 bg-muted p-4"
                                >
                                    <p className="font-semibold">
                                        Observaciones de JAKAWI
                                    </p>
                                    <p className="mt-2 text-sm break-words whitespace-pre-wrap">
                                        {c.review_comment}
                                    </p>
                                </aside>
                            )}
                            <div className="flex flex-wrap gap-3">
                                <Link
                                    className={secondaryAction}
                                    href={`${base}/${c.slug}/editar`}
                                >
                                    {c.editable
                                        ? 'Editar borrador'
                                        : 'Ver propuesta'}
                                </Link>
                                <a
                                    className={secondaryAction}
                                    href={`${base}/${c.slug}/preview`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Previsualizar reto
                                </a>
                            </div>
                        </article>
                    ))
                ) : (
                    <section className="rounded-md border border-border bg-surface p-6">
                        <h2 className="text-xl font-semibold">
                            Prepara tu primer reto
                        </h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Describe cómo participar, la evidencia requerida y
                            el premio propuesto. Guarda y revisa antes de
                            enviarlo.
                        </p>
                    </section>
                )}
                <ListPagination page={challenges} />
            </div>
        </PartnerHeading>
    );
}
