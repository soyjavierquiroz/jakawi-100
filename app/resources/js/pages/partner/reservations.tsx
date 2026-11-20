import { ListPagination } from '@/components/list-pagination';
import {
    OperationFeedback,
    sessionDate,
    useOperationalAccess,
    type Partner,
} from '@/components/partner/operation-feedback';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import PartnerLayout from './layout';

type Reservation = {
    public_id: string;
    status: string;
    member_name: string;
    party_size: number;
    requested_at: string;
    experience: string;
    partner_slug: string;
    starts_at: string;
    ends_at: string | null;
    timezone: string;
    venue: string | null;
    capacity: number | null;
    confirmed_attendee_count: number;
    checked_in_at: string | null;
    can_confirm: boolean;
};
const statuses: Record<string, string> = {
    pending: 'Pendiente de respuesta',
    confirmed: 'Confirmada',
    rejected: 'Rechazada',
    cancelled: 'Cancelada',
};
function ReservationRow({
    reservation: r,
    actions,
}: {
    reservation: Reservation;
    actions: boolean;
}) {
    const [review, setReview] = useState(false);
    const form = useForm({});
    const { blocked } = useOperationalAccess();
    const fits =
        r.capacity === null ||
        r.confirmed_attendee_count + r.party_size <= r.capacity;
    const submit = (status: string) =>
        form.post(
            `/partner/${r.partner_slug}/reservas/${r.public_id}/${status}`,
            { preserveScroll: true, onSuccess: () => setReview(false) },
        );
    return (
        <article className="min-w-0 py-5 text-sm">
            <div className="flex flex-col justify-between gap-4 lg:flex-row">
                <div className="min-w-0 space-y-1 break-words">
                    <p className="font-semibold">
                        {r.member_name} ·{' '}
                        {r.party_size === 1
                            ? '1 persona'
                            : `${r.party_size} personas`}
                    </p>
                    <h3 className="font-medium">{r.experience}</h3>
                    <p>
                        {sessionDate(r.starts_at, r.timezone)}
                        {r.ends_at &&
                            ` — ${sessionDate(r.ends_at, r.timezone)}`}
                    </p>
                    <p className="text-muted-foreground">
                        {r.venue || 'Sede por coordinar'} · {r.timezone}
                    </p>
                    <p className="text-muted-foreground">
                        {r.confirmed_attendee_count}
                        {r.capacity !== null ? ` / ${r.capacity}` : ''} personas
                        confirmadas
                        {r.capacity === null && ' · sin límite configurado'}
                    </p>
                    {actions && (
                        <p className="text-muted-foreground">
                            Solicitada:{' '}
                            {sessionDate(r.requested_at, r.timezone)}
                        </p>
                    )}
                    <p className="font-medium">
                        {statuses[r.status] || r.status}
                        {r.checked_in_at && ' · Asistencia registrada'}
                    </p>
                    {actions && !r.can_confirm && (
                        <p>
                            La sesión ya comenzó o no está programada. Solo
                            puedes rechazar esta solicitud.
                        </p>
                    )}
                    {actions && !fits && (
                        <p>
                            No hay cupos suficientes para este grupo. No puede
                            confirmarse.
                        </p>
                    )}
                </div>
                {actions && (
                    <div className="flex flex-wrap items-start gap-2 lg:shrink-0">
                        <button
                            className="rounded-md bg-brand px-4 py-3 font-medium text-brand-foreground disabled:opacity-50"
                            disabled={
                                blocked ||
                                form.processing ||
                                !r.can_confirm ||
                                !fits
                            }
                            onClick={() => setReview(!review)}
                        >
                            Revisar solicitud
                        </button>
                        <button
                            className="rounded-md border border-border px-4 py-3 disabled:opacity-50"
                            disabled={blocked || form.processing}
                            onClick={() => submit('rejected')}
                        >
                            Rechazar
                        </button>
                    </div>
                )}
            </div>
            {review && (
                <div className="mt-4 space-y-3 rounded-md bg-muted p-4">
                    <p>
                        Vas a confirmar a{' '}
                        <strong>
                            {r.party_size}{' '}
                            {r.party_size === 1 ? 'persona' : 'personas'}
                        </strong>{' '}
                        para {r.experience}. El sistema volverá a comprobar el
                        cupo disponible.
                    </p>
                    <div className="flex flex-wrap gap-3">
                        <button
                            className="rounded-md bg-brand px-4 py-3 text-brand-foreground disabled:opacity-50"
                            disabled={blocked || form.processing}
                            onClick={() => submit('confirmed')}
                        >
                            Confirmar grupo
                        </button>
                        <button
                            className="rounded-md border border-border px-4 py-3"
                            onClick={() => setReview(false)}
                        >
                            Volver
                        </button>
                    </div>
                </div>
            )}
        </article>
    );
}
function List({
    title,
    description,
    items,
    actions = false,
}: {
    title: string;
    description: string;
    items: Reservation[];
    actions?: boolean;
}) {
    return (
        <section className="mt-6 rounded-md border border-border bg-surface p-4 sm:p-6">
            <h2 className="text-xl font-semibold">{title}</h2>
            <p className="mt-2 text-sm text-muted-foreground">{description}</p>
            {items.length ? (
                <div className="mt-2 divide-y divide-border">
                    {items.map((r) => (
                        <ReservationRow
                            key={r.public_id}
                            reservation={r}
                            actions={actions}
                        />
                    ))}
                </div>
            ) : (
                <p className="mt-4 rounded-md bg-muted p-4 text-sm">
                    Sin reservas en esta sección.
                </p>
            )}
        </section>
    );
}
export default function PartnerReservations({
    partner,
    pending,
    upcomingConfirmed,
    history,
    pagination,
}: {
    partner: Partner;
    pending: Reservation[];
    upcomingConfirmed: Reservation[];
    history: Reservation[];
    pagination: Record<string, any>;
}) {
    return (
        <PartnerLayout title="Reservas" partner={partner}>
            <OperationFeedback />
            <List
                title="Pendientes"
                description="Son solicitudes, todavía sin plaza garantizada. Solo las confirmaciones ocupan cupo; los grupos se confirman completos."
                items={pending}
                actions
            />
            <ListPagination page={pagination.pending} />
            <List
                title="Confirmadas próximas"
                description="Reservas confirmadas para sesiones que aún no comenzaron."
                items={upcomingConfirmed}
            />
            <ListPagination page={pagination.upcomingConfirmed} />
            <List
                title="En curso e historial"
                description="Confirmadas cuya sesión ya comenzó, rechazadas y canceladas. La asistencia registrada permanece visible."
                items={history}
            />
            <ListPagination page={pagination.history} />
        </PartnerLayout>
    );
}
