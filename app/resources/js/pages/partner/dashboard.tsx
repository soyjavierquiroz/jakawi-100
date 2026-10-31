import { CityCommunityCard } from '@/components/city-community-card';
import { Link } from '@inertiajs/react';
import PartnerLayout from './layout';

export default function PartnerDashboard({ community, partner, pendingReservations, benefitDrafts, benefitSubmitted, experienceDrafts, experienceSubmitted }: any) {
    return <PartnerLayout title="Inicio" partner={partner}>
        <p className="mb-4 text-sm">Estado del establecimiento: {partner.status}</p>
        {partner.public_url && <section className="mb-8 rounded-xl border border-border bg-surface p-5">
            <h2 className="font-bold">Perfil público</h2>
            <a className="mt-2 block break-all text-sm text-brand underline" href={partner.public_url}>{partner.public_url}</a>
            <button className="mt-3 min-h-11 rounded border border-border px-4 font-bold" onClick={() => navigator.clipboard?.writeText(partner.public_url)}>Copiar enlace</button>
        </section>}
        {partner.status === 'draft' && <p className="mb-4">Puedes preparar contenido. La operación estará disponible cuando JAKAWI publique el establecimiento.</p>}
        {['paused', 'archived'].includes(partner.status) && <p className="mb-4">Establecimiento sin operación comercial. Puedes consultar su información e históricos.</p>}
        <div className="grid gap-3 sm:grid-cols-2">
            <Metric label="Promociones · borradores" value={benefitDrafts}/><Metric label="Promociones · en revisión" value={benefitSubmitted}/>
            <Metric label="Experiencias · borradores" value={experienceDrafts}/><Metric label="Experiencias · en revisión" value={experienceSubmitted}/>
            <Metric label="Reservas pendientes" value={pendingReservations}/>
        </div>
        {partner.can_manage && ['draft', 'published'].includes(partner.status) && <Link href={`/partner/${partner.slug}/retos`} className="mt-6 block rounded-xl border border-brand p-5 font-bold text-brand">Proponer y seguir retos →</Link>}
        <CityCommunityCard community={community} permanent />
    </PartnerLayout>;
}
function Metric({label,value}:{label:string;value:any}) { return <div className="rounded-md border border-border bg-surface p-5"><p className="text-sm text-muted-foreground">{label}</p><p className="mt-1 text-2xl font-semibold">{value}</p></div>; }
