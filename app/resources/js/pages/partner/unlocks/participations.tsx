import { ListPagination, type ListPage } from '@/components/list-pagination';
import {
    primaryAction,
    secondaryAction,
    unlockStates,
    type Partner,
} from '@/components/partner/proposal-fields';
import { Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PartnerHeading from '../layout';
type Participation = {
    id: number;
    status: string;
    member_display_name: string;
    can_fulfill: boolean;
    fulfillment_blocker: string | null;
    created_at: string;
    interested_at: string | null;
    committed_at: string | null;
    confirmation_requested_at: string | null;
    confirmed_at: string | null;
    fulfilled_at: string | null;
    expired_at: string | null;
    no_show_at: string | null;
    cancelled_at: string | null;
};
const states: Record<string, string> = {
    INTERESTED: 'Interesado',
    COMMITTED: 'Comprometido',
    UNLOCKED_PENDING_CONFIRMATION: 'Pendiente de confirmación',
    CONFIRMED: 'Confirmado',
    FULFILLED: 'Cumplido',
    CANCELLED_ON_TIME: 'Cancelado a tiempo',
    NO_SHOW: 'No asistió',
    EXPIRED: 'Expirado',
    REMOVED: 'Retirado',
    WAITLISTED: 'En espera',
};
const date = (value: string) =>
    new Intl.DateTimeFormat('es-BO', {
        timeZone: 'UTC',
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
function Row({
    p,
    base,
    prelaunch,
}: {
    p: Participation;
    base: string;
    prelaunch: boolean;
}) {
    const [review, setReview] = useState(false);
    const form = useForm({ note: '' });
    const fields: [keyof Participation, string][] = [
        ['created_at', 'Registro'],
        ['interested_at', 'Interés'],
        ['committed_at', 'Compromiso'],
        ['confirmation_requested_at', 'Confirmación solicitada'],
        ['confirmed_at', 'Confirmación'],
        ['fulfilled_at', 'Cumplimiento'],
        ['cancelled_at', 'Cancelación'],
        ['expired_at', 'Expiración'],
        ['no_show_at', 'Inasistencia'],
    ];
    return (
        <article className="space-y-4 rounded-md border border-border bg-surface p-4 sm:p-6">
            <div className="flex flex-col justify-between gap-2 sm:flex-row">
                <h2 className="text-lg font-semibold">
                    {p.member_display_name} · Participación #{p.id}
                </h2>
                <p className="text-sm font-medium">
                    {states[p.status] ?? p.status}
                </p>
            </div>
            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                {fields
                    .filter(([key]) => !!p[key])
                    .map(([key, label]) => (
                        <div key={key}>
                            <dt className="text-muted-foreground">{label}</dt>
                            <dd>{date(String(p[key]))}</dd>
                        </div>
                    ))}
            </dl>
            <p className="text-sm">
                {p.status === 'FULFILLED' || p.fulfilled_at
                    ? 'Cumplimiento registrado.'
                    : p.status === 'CONFIRMED'
                      ? 'Confirmación registrada; cumplimiento pendiente.'
                      : p.confirmed_at
                        ? 'Confirmación registrada en el historial.'
                        : 'Sin confirmación registrada.'}
            </p>
            {p.fulfillment_blocker && (
                <p className="text-sm text-muted-foreground">
                    {p.fulfillment_blocker}
                </p>
            )}
            {p.status === 'CONFIRMED' && (
                <button
                    className={secondaryAction}
                    disabled={prelaunch || !p.can_fulfill || form.processing}
                    onClick={() => setReview(true)}
                >
                    Revisar cumplimiento
                </button>
            )}
            {review && (
                <form
                    className="space-y-4 rounded-md bg-muted p-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(`${base}/${p.id}/cumplir`, {
                            preserveScroll: true,
                            onSuccess: () => setReview(false),
                        });
                    }}
                >
                    <p className="font-medium">
                        Confirma que la prestación correspondiente a esta
                        participación fue realizada.
                    </p>
                    <p className="text-sm">
                        Se registrará tu usuario como operador y se aplicarán
                        las reglas de cumplimiento ya configuradas. Esta acción
                        no es una previsualización.
                    </p>
                    <label
                        className="grid gap-2 text-sm"
                        htmlFor={`note-${p.id}`}
                    >
                        Nota de cumplimiento (opcional)
                        <textarea
                            id={`note-${p.id}`}
                            name="note"
                            maxLength={2000}
                            rows={3}
                            className="w-full rounded-md border border-border bg-background p-3"
                            value={form.data.note}
                            onChange={(e) =>
                                form.setData('note', e.target.value)
                            }
                        />
                    </label>
                    {form.errors.note && (
                        <p role="alert" className="text-sm text-destructive">
                            {form.errors.note}
                        </p>
                    )}
                    <div className="flex flex-wrap gap-3">
                        <button
                            className={primaryAction}
                            disabled={
                                prelaunch || !p.can_fulfill || form.processing
                            }
                        >
                            Confirmar cumplimiento
                        </button>
                        <button
                            type="button"
                            className={secondaryAction}
                            disabled={form.processing}
                            onClick={() => setReview(false)}
                        >
                            Volver
                        </button>
                    </div>
                </form>
            )}
        </article>
    );
}
export default function Participations({
    partner,
    unlock,
    participations,
}: {
    partner: Partner & { status: string };
    unlock: {
        title: string;
        slug: string;
        status: string;
        fulfillment_starts_at: string | null;
        fulfillment_ends_at: string | null;
    };
    participations: ListPage & { data: Participation[] };
}) {
    const { prelaunchMode } = usePage<{ prelaunchMode: boolean }>().props;
    const base = `/partner/${partner.slug}/desbloqueos/${unlock.slug}/participaciones`;
    return (
        <PartnerHeading
            title="Participaciones del desbloqueo"
            partner={partner}
        >
            <div className="max-w-4xl space-y-5">
                <Link
                    className="inline-block py-2 text-sm underline"
                    href={`/partner/${partner.slug}/desbloqueos`}
                >
                    Volver a desbloqueos
                </Link>
                <div className="space-y-2">
                    <h2 className="text-xl font-semibold break-words">
                        {unlock.title}
                    </h2>
                    <p className="text-sm">
                        {unlockStates[unlock.status] ?? unlock.status}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Fechas en UTC. Confirmación y cumplimiento son registros
                        distintos.
                    </p>
                    {(unlock.fulfillment_starts_at ||
                        unlock.fulfillment_ends_at) && (
                        <p className="text-sm">
                            Ventana configurada:{' '}
                            {unlock.fulfillment_starts_at
                                ? date(unlock.fulfillment_starts_at)
                                : 'sin inicio definido'}{' '}
                            —{' '}
                            {unlock.fulfillment_ends_at
                                ? date(unlock.fulfillment_ends_at)
                                : 'sin fin definido'}
                            .
                        </p>
                    )}
                </div>
                {prelaunchMode && (
                    <p
                        role="note"
                        className="rounded-md border border-border bg-muted p-4 text-sm"
                    >
                        PRELAUNCH activo: puedes consultar participaciones e
                        historial. El cumplimiento está temporalmente
                        deshabilitado también en el servidor.
                    </p>
                )}
                {partner.status !== 'published' && (
                    <p role="note" className="text-sm">
                        El establecimiento no está habilitado para operar; su
                        historial sigue disponible.
                    </p>
                )}
                {participations.data.length ? (
                    participations.data.map((p) => (
                        <Row
                            key={p.id}
                            p={p}
                            base={base}
                            prelaunch={prelaunchMode}
                        />
                    ))
                ) : (
                    <div className="rounded-md border border-border bg-surface p-6">
                        <h2 className="text-xl font-semibold">
                            Aún no hay participaciones
                        </h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Aquí aparecerán los registros autorizados de este
                            desbloqueo cuando existan.
                        </p>
                    </div>
                )}
                <ListPagination page={participations} />
            </div>
        </PartnerHeading>
    );
}
