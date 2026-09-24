import PartnerLayout from './layout';

export default function PartnerDashboard({ partner, pendingReservations, benefitDrafts, benefitSubmitted, experienceDrafts, experienceSubmitted }: { partner: { name: string; slug: string }; pendingReservations: number; benefitDrafts: number; benefitSubmitted: number; experienceDrafts: number; experienceSubmitted: number }) {
    return <PartnerLayout title="Inicio" partner={partner}><div className="grid gap-3 sm:grid-cols-2"><Metric label="Promociones · borradores" value={benefitDrafts}/><Metric label="Promociones · en revisión" value={benefitSubmitted}/><Metric label="Experiencias · borradores" value={experienceDrafts}/><Metric label="Experiencias · en revisión" value={experienceSubmitted}/><Metric label="Reservas pendientes" value={pendingReservations}/></div></PartnerLayout>;
}
function Metric({label,value}:{label:string;value:number}) { return <div className="rounded-md border border-border bg-surface p-5"><p className="text-sm text-muted-foreground">{label}</p><p className="mt-1 text-2xl font-semibold">{value}</p></div>; }
