import {
    OperationFeedback,
    sessionDate,
    useOperationalAccess,
    type Partner,
} from '@/components/partner/operation-feedback';
import { Link, useForm } from '@inertiajs/react';
import PartnerLayout from './layout';
type Reservation = {
    public_id: string;
    check_in_code: string;
    member_display_name: string;
    experience: string;
    party_size: number;
    starts_at: string;
    ends_at: string | null;
    timezone: string;
    venue: string | null;
    status: string;
    checked_in_at: string | null;
};
export default function Review({
    partner,
    reservation: r,
    error,
    checkInBlocker,
}: {
    partner: Partner;
    reservation: Reservation;
    error?: string;
    checkInBlocker?: string;
}) {
    const form = useForm({});
    const { blocked } = useOperationalAccess();
    const done = !!r.checked_in_at;
    return (
        <PartnerLayout title="Registrar asistencia" partner={partner}>
            <OperationFeedback
                error={error}
                success={
                    done
                        ? 'Asistencia registrada. Los reintentos no generan otro registro.'
                        : undefined
                }
            />
            <section className="mt-5 max-w-xl space-y-4 rounded-md border border-border bg-surface p-5">
                <h2 className="text-xl font-semibold">
                    {done ? 'Asistencia ya registrada' : 'Revisar asistencia'}
                </h2>
                <div className="space-y-1 break-words">
                    <p className="font-semibold">
                        {r.member_display_name} · {r.party_size}{' '}
                        {r.party_size === 1 ? 'persona' : 'personas'}
                    </p>
                    <p>{r.experience}</p>
                    <p className="font-mono">Código: {r.check_in_code}</p>
                    <p>{r.venue || 'Sede por coordinar'}</p>
                </div>
                <div className="rounded-md bg-muted p-4 text-sm">
                    <p className="font-medium">Horario de la sesión</p>
                    <p>Inicio: {sessionDate(r.starts_at, r.timezone)}</p>
                    <p>
                        Fin:{' '}
                        {r.ends_at
                            ? sessionDate(r.ends_at, r.timezone)
                            : 'Sin hora de fin ni duración configurada'}
                    </p>
                    <p className="mt-1 text-muted-foreground">
                        Zona horaria: {r.timezone}. La asistencia se permite
                        durante este intervalo.
                    </p>
                </div>
                {done ? (
                    <p>
                        Registrada: {sessionDate(r.checked_in_at!, r.timezone)}
                    </p>
                ) : (
                    <>
                        <p role="status" className="text-sm">
                            {checkInBlocker ||
                                'Reserva confirmada y dentro del horario de asistencia.'}
                        </p>
                        <button
                            className="w-full rounded-md bg-brand px-4 py-3 text-brand-foreground disabled:opacity-50 sm:w-auto"
                            disabled={
                                blocked || !!checkInBlocker || form.processing
                            }
                            onClick={() =>
                                form.post(
                                    `/partner/${partner.slug}/asistencias/${r.public_id}`,
                                )
                            }
                        >
                            Registrar asistencia
                        </button>
                    </>
                )}
                <Link
                    className="block py-2 text-sm underline"
                    href={`/partner/${partner.slug}/asistencias`}
                >
                    Buscar otra reserva
                </Link>
            </section>
        </PartnerLayout>
    );
}
