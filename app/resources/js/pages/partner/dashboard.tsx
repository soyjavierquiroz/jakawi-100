import { CityCommunityCard } from '@/components/city-community-card';
import {
    actionClass,
    primaryClass,
    type PartnerAccess,
} from '@/components/partner/editorial';
import { Link, usePage } from '@inertiajs/react';
import PartnerLayout from './layout';

export default function PartnerDashboard({
    community,
    partner,
    editorialSummary,
    activity,
}: any) {
    const base = `/partner/${partner.slug}`;
    const { partnerAccess: access } = usePage<{
        partnerAccess: PartnerAccess;
    }>().props;
    return (
        <PartnerLayout title="Inicio" partner={partner}>
            <div className="space-y-8">
                {access?.can_edit_content && (
                    <section
                        aria-label="Gestionar establecimiento"
                        className="flex flex-wrap gap-3"
                    >
                        <Link href={`${base}/perfil`} className={actionClass}>
                            Editar perfil
                        </Link>
                        <Link
                            href={`${base}/perfil#sedes`}
                            className={actionClass}
                        >
                            Gestionar sedes
                        </Link>
                    </section>
                )}
                {['paused', 'archived'].includes(partner.status) && (
                    <p className="rounded-xl border border-border p-4 text-sm">
                        El establecimiento está{' '}
                        {partner.status === 'paused' ? 'pausado' : 'archivado'}.
                        Puedes consultar información e históricos. JAKAWI
                        administra su estado.
                    </p>
                )}
                <section aria-labelledby="editorial-heading">
                    <h2
                        id="editorial-heading"
                        className="text-xl font-semibold"
                    >
                        Tu contenido editorial
                    </h2>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Prepara tus propuestas y sigue su revisión. JAKAWI
                        administra la aprobación y publicación.
                    </p>
                    <div className="mt-4 grid gap-4 lg:grid-cols-2">
                        {[
                            ['benefits', 'Promociones', 'promociones'],
                            ['experiences', 'Experiencias', 'experiencias'],
                        ].map(([key, label, path]) => (
                            <div
                                className="rounded-2xl border border-border bg-surface p-5"
                                key={key}
                            >
                                <h3 className="font-semibold">{label}</h3>
                                <ul className="mt-3 divide-y divide-border">
                                    {[
                                        ['draft', 'Borradores'],
                                        ['submitted', 'En revisión'],
                                        [
                                            'changes_requested',
                                            'Cambios solicitados',
                                        ],
                                        ['published', 'Publicados'],
                                        ['approved', 'Aprobados sin publicar'],
                                        ['rejected', 'Rechazados'],
                                    ].map(([state, text]) => (
                                        <li key={state} className="py-1">
                                            <Link
                                                href={`${base}/${path}?estado=${state}`}
                                                className="flex min-h-11 items-center justify-between gap-3 rounded-lg px-2 text-sm hover:bg-muted focus-visible:outline-2 focus-visible:outline-brand"
                                            >
                                                <span>{text}</span>
                                                <strong
                                                    className={`text-lg font-semibold ${state === 'changes_requested' && editorialSummary[key][state] > 0 ? 'text-brand' : ''}`}
                                                >
                                                    {
                                                        editorialSummary[key][
                                                            state
                                                        ]
                                                    }
                                                </strong>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>
                    <p className="mt-3 text-xs text-muted-foreground">
                        Los publicados se cuentan por su estado editorial, sin
                        asegurar vigencia ni habilitación comercial. Los demás
                        grupos corresponden a contenido sin publicar en estado
                        borrador.
                    </p>
                </section>
                {access?.can_edit_content && (
                    <section className="rounded-2xl border border-border bg-surface p-5">
                        <h2 className="text-lg font-semibold">
                            Continúa preparando tu contenido
                        </h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Si JAKAWI pidió cambios, revisa sus notas, guarda la
                            corrección y vuelve a enviar la propuesta.
                        </p>
                        <div className="mt-4 flex flex-wrap gap-3">
                            <Link
                                href={`${base}/promociones/crear`}
                                className={primaryClass}
                            >
                                Crear promoción
                            </Link>
                            <Link
                                href={`${base}/experiencias/crear`}
                                className={actionClass}
                            >
                                Crear experiencia
                            </Link>
                        </div>
                    </section>
                )}
                {activity && (
                    <section>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h2 className="text-xl font-semibold">
                                Actividad registrada · últimos 30 días
                            </h2>
                            <Link
                                href={`${base}/rendimiento`}
                                className={actionClass}
                            >
                                Ver rendimiento
                            </Link>
                        </div>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Las vistas son eventos registrados, no personas
                            únicas. Los canjes son históricos; estas cifras no
                            representan ventas ni ingresos.
                        </p>
                        <div className="mt-4 grid gap-3 sm:grid-cols-3">
                            {[
                                ['Vistas del perfil', activity.partner_views],
                                [
                                    'Vistas de promociones',
                                    activity.benefit_views,
                                ],
                                ['Canjes confirmados', activity.confirmed],
                            ].map(([label, value]) => (
                                <div
                                    key={label}
                                    className="rounded-2xl border border-border bg-surface p-5"
                                >
                                    <p className="text-sm text-muted-foreground">
                                        {label}
                                    </p>
                                    <p className="mt-2 text-2xl font-semibold">
                                        {value}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>
                )}
                {partner.public_url && (
                    <section className="rounded-2xl border border-border p-5">
                        <h2 className="font-semibold">Perfil público</h2>
                        <a
                            className="mt-2 block text-sm break-all underline"
                            href={partner.public_url}
                        >
                            Ver perfil de {partner.name} ↗
                        </a>
                        <p className="mt-2 text-sm text-muted-foreground">
                            El perfil, las sedes y cada propuesta conservan
                            estados independientes.
                        </p>
                    </section>
                )}
                <CityCommunityCard community={community} permanent />
            </div>
        </PartnerLayout>
    );
}
