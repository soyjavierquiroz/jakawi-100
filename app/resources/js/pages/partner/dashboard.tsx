import PartnerLayout from './layout';

export default function PartnerDashboard({ partner, pendingReservations }: { partner: { name: string; slug: string }; pendingReservations: number }) {
    return <PartnerLayout title="Inicio" partner={partner}><div className="rounded-md border border-border bg-surface p-5"><p className="text-lg font-semibold">{partner.name}</p><p className="mt-2 text-sm text-muted-foreground">{pendingReservations === 1 ? '1 reserva pendiente' : `${pendingReservations} reservas pendientes`}</p></div></PartnerLayout>;
}
