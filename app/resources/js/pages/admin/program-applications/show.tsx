import { Link, useForm } from '@inertiajs/react';
import AdminLayout from '../layout';
import OwnershipBlock from '../ownership-block';

type Application = { id: number; program_type: string; status: string; channel_url: string | null; message: string | null; created_at: string; attribution_snapshot: { first?: Record<string, string | null>; conversion?: Record<string, string | null> } | null; user: { name: string; email: string; profile?: { whatsapp: string | null; city: string | null } } };
type Props = { application: Application; statuses: string[]; enrollment: { status: string } | null; ownership: any; ownerCandidates: { id: number; name: string }[] };

export default function ProgramApplicationShow({ application, statuses, enrollment, ownership, ownerCandidates }: Props) {
    const form = useForm({ status: application.status });
    const final = ['APPROVED', 'REJECTED'].includes(application.status);
    const touch = application.attribution_snapshot;
    return <AdminLayout title={`Solicitud ${application.program_type} · ${application.user.name}`}>
        <Link href="/admin/solicitudes-programas" className="text-brand underline">Volver al listado</Link>
        <dl className="mt-6 grid max-w-2xl grid-cols-[10rem_1fr] gap-3"><dt>Estado</dt><dd>{application.status}</dd><dt>Cuenta</dt><dd>{application.user.name}<br />{application.user.email}<br />{application.user.profile?.whatsapp ?? 'Sin WhatsApp'}</dd><dt>Ciudad del perfil</dt><dd>{application.user.profile?.city ?? 'Sin registrar'}</dd><dt>Canal</dt><dd>{application.channel_url ? <a className="text-brand underline" href={application.channel_url} rel="noopener noreferrer" target="_blank">{application.channel_url}</a> : 'No indicado'}</dd><dt>Mensaje</dt><dd className="whitespace-pre-wrap">{application.message || 'No indicado'}</dd><dt>Enviada</dt><dd>{application.created_at}</dd><dt>Enrollment</dt><dd>{enrollment?.status ?? 'Sin enrollment'}</dd><dt>Primer contacto</dt><dd>{touch?.first ? `${touch.first.landing_page ?? '—'} · ${touch.first.utm_source ?? 'Directo'} · ${touch.first.utm_campaign ?? '—'} · ${touch.first.referral_code ?? '—'}` : 'Sin atribución'}</dd><dt>Contacto al enviar</dt><dd>{touch?.conversion ? `${touch.conversion.landing_page ?? '—'} · ${touch.conversion.utm_source ?? 'Directo'} · ${touch.conversion.utm_campaign ?? '—'} · ${touch.conversion.referral_code ?? '—'}` : 'Sin atribución'}</dd></dl>
        <OwnershipBlock targetType="PROGRAM_APPLICATION" targetId={application.id} ownership={ownership} candidates={ownerCandidates} />
        {!final && <form className="mt-8 flex flex-wrap gap-3" onSubmit={event => { event.preventDefault(); form.post(`/admin/solicitudes-programas/${application.id}/estado`); }}><select className="rounded border border-border bg-background p-2" value={form.data.status} onChange={event => form.setData('status', event.target.value)}>{statuses.map(status => <option key={status}>{status}</option>)}</select><button disabled={form.processing} className="rounded bg-brand px-4 py-2 font-bold text-brand-foreground">{form.data.status === 'APPROVED' ? 'Aprobar y activar' : 'Actualizar estado'}</button></form>}
    </AdminLayout>;
}
