import { CityCommunityCard } from '@/components/city-community-card';
import { Link, usePage } from '@inertiajs/react';
import PartnerLayout from './layout';

export default function PartnerDashboard({ community, partner, pendingReservations, benefitPublished, benefitDrafts, benefitSubmitted, experienceDrafts, experienceSubmitted, activity }: any) {
    const base = `/partner/${partner.slug}`;
    const { partnerAccess } = usePage<{ partnerAccess: { can_edit_content: boolean } }>().props;
    return <PartnerLayout title="Inicio" partner={partner}>
        <p className="mb-5 text-sm text-muted-foreground">Administra tus promociones y sigue la actividad de tu negocio.</p>
        <div className="mb-8 flex flex-wrap gap-3">
            {partnerAccess?.can_edit_content && <Link href={`${base}/promociones/crear`} className="min-h-11 rounded-lg bg-primary px-5 py-3 font-semibold text-primary-foreground">Crear promoción</Link>}
            <Link href={`${base}/promociones`} className="min-h-11 rounded-lg border border-border px-5 py-3 font-semibold">Gestionar mis promociones</Link>
            {partner.can_manage && <Link href={`${base}/rendimiento`} className="min-h-11 rounded-lg border border-border px-5 py-3 font-semibold">Ver rendimiento</Link>}
        </div>
        <section className="mb-8"><h2 className="mb-3 text-lg font-semibold">Por gestionar</h2><div className="grid gap-3 sm:grid-cols-2">
            <Metric label="Promociones publicadas y vigentes" value={benefitPublished} href={`${base}/promociones?estado=vigentes`}/>
            <Metric label="Promociones · borradores" value={benefitDrafts} href={`${base}/promociones?estado=draft`}/>
            <Metric label="Promociones · en revisión" value={benefitSubmitted} href={`${base}/promociones?estado=submitted`}/>
            <Metric label="Reservas pendientes" value={pendingReservations} href={`${base}/reservas?status=pending`}/>
        </div></section>
        {activity && <section className="mb-8"><h2 className="mb-1 text-lg font-semibold">Actividad · últimos 30 días</h2><p className="mb-3 text-sm text-muted-foreground">Las vistas son eventos registrados, no personas únicas, ventas ni ingresos.</p><div className="grid gap-3 sm:grid-cols-2">
            <Metric label="Vistas del perfil" value={activity.partner_views} href={`${base}/rendimiento#descubrimiento`}/>
            <Metric label="Vistas de beneficios" value={activity.benefit_views} href={`${base}/rendimiento#descubrimiento`}/>
            <Metric label="Canjes confirmados" value={activity.confirmed} href={`${base}/rendimiento#beneficios`}/>
        </div></section>}
        <section className="mb-8"><h2 className="mb-3 text-lg font-semibold">Experiencias</h2><div className="grid gap-3 sm:grid-cols-2"><Metric label="Experiencias · borradores" value={experienceDrafts} href={`${base}/experiencias`}/><Metric label="Experiencias · en revisión" value={experienceSubmitted} href={`${base}/experiencias`}/></div></section>
        {partner.public_url && <section className="mb-8 rounded-xl border border-border bg-surface p-5">
            <h2 className="font-bold">Perfil público</h2>
            <a className="mt-2 block break-all text-sm text-brand underline" href={partner.public_url}>{partner.public_url}</a>
            <button className="mt-3 min-h-11 rounded border border-border px-4 font-bold" onClick={() => navigator.clipboard?.writeText(partner.public_url)}>Copiar enlace</button>
        </section>}
        {partner.status === 'draft' && <p className="mb-4">Puedes preparar contenido. La operación estará disponible cuando JAKAWI publique el establecimiento.</p>}
        {['paused', 'archived'].includes(partner.status) && <p className="mb-4">Establecimiento sin operación comercial. Puedes consultar su información e históricos.</p>}
        {partner.can_manage && ['draft', 'published'].includes(partner.status) && <Link href={`${base}/retos`} className="mb-6 block text-sm text-brand underline">Proponer y seguir retos →</Link>}
        <CityCommunityCard community={community} permanent />
    </PartnerLayout>;
}
function Metric({label,value,href}:{label:string;value:number;href:string}) { return <Link href={href} className="block rounded-lg border border-border bg-surface p-5 hover:border-primary focus-visible:outline-2 focus-visible:outline-primary"><p className="text-sm text-muted-foreground">{label} →</p><p className="mt-1 text-2xl font-semibold">{value}</p></Link>; }
