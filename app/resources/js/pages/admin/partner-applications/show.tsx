import { Link, useForm } from '@inertiajs/react';
import AdminLayout from '../layout';
import OwnershipBlock from '../ownership-block';

const categories = ['food', 'cafe', 'fitness', 'wellness', 'beauty', 'entertainment', 'nightlife', 'shopping', 'services', 'experiences'];
const slugify = (name: string) => name.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

export default function PartnerApplicationShow({ application, statuses, ownership, ownerCandidates, accessCandidates, partnerOwners, ownerSearch, invitationEmailAvailable }: any) {
    const form = useForm({ status: application.status });
    const conversion = useForm({ name: application.business_name, slug: `${slugify(application.business_name).slice(0, 220) || 'negocio'}-${application.id}`, category: categories.includes(application.category) ? application.category : '' });
    const owner = useForm({ owner_user_id: '', authorization_confirmed: false, authorization_reason: '' });
    const search = useForm({ owner_q: ownerSearch || '' });
    const base = `/admin/solicitudes-partner/${application.id}`;
    return <AdminLayout title={application.business_name}>
        <dl className="grid max-w-xl grid-cols-2 gap-3">
            <dt>Ciudad</dt><dd>{application.city.name}</dd>
            <dt>Contacto</dt><dd>{application.contact_name}<br />{application.contact_phone}<br />{application.contact_email}</dd>
            <dt>Categoría</dt><dd>{application.category || '—'}</dd><dt>Mensaje</dt><dd>{application.message || '—'}</dd>
            <dt>Solicitud</dt><dd>{application.created_at}</dd><dt>Atribución</dt><dd>{application.attribution_snapshot ? `${application.attribution_snapshot.utm_source || 'Directa'}${application.attribution_snapshot.utm_campaign ? ` · ${application.attribution_snapshot.utm_campaign}` : ''}` : 'Sin atribución'}</dd>
        </dl>
        <OwnershipBlock targetType="PARTNER_APPLICATION" targetId={application.id} ownership={ownership} candidates={ownerCandidates} />
        <p className="mt-2 text-sm text-muted-foreground">El responsable comercial de la solicitud no recibe propiedad del establecimiento.</p>
        <form className="mt-6 flex gap-2" onSubmit={e => { e.preventDefault(); form.post(`${base}/estado`); }}>
            <select aria-label="Estado de solicitud" value={form.data.status} onChange={e => form.setData('status', e.target.value)}>{statuses.map((s: string) => <option key={s}>{s}</option>)}</select>
            <button disabled={form.processing} className="rounded border px-3 py-2">Actualizar estado</button>
        </form>
        {application.partner ? <section className="mt-6 rounded border p-5">
            <h2 className="font-semibold">Partner creado: {application.partner.name}</h2>
            <p>Estado: {application.partner.status}</p>
            <Link className="underline" href={`/admin/partners/${application.partner.id}/edit`}>Completar establecimiento y sucursales</Link>
        </section> : application.status === 'APPROVED' && <form className="mt-6 max-w-xl space-y-3 rounded border p-5" onSubmit={e => { e.preventDefault(); conversion.post(`${base}/convertir`); }}>
            <h2 className="font-semibold">Convertir en Partner</h2>
            <p className="text-sm">Se copiarán los datos de contacto y se conservará esta solicitud. El establecimiento se creará en borrador, sin accesos automáticos.</p>
            <label className="block">Nombre comercial<input required className="block w-full rounded border p-2" value={conversion.data.name} onChange={e => conversion.setData('name', e.target.value)}/></label>
            <label className="block">Slug público<input required className="block w-full rounded border p-2" value={conversion.data.slug} onChange={e => conversion.setData('slug', e.target.value)}/></label>
            <label className="block">Categoría<select className="block w-full rounded border p-2" value={conversion.data.category} onChange={e => conversion.setData('category', e.target.value)}><option value="">Sin categoría</option>{categories.map(c => <option key={c}>{c}</option>)}</select></label>
            <Errors errors={conversion.errors}/><button disabled={conversion.processing} className="rounded border px-4 py-2">Crear Partner en borrador</button>
        </form>}
        {application.partner && <section className="mt-6 max-w-xl space-y-4 rounded border p-5">
            <h2 className="font-semibold">Owner autorizado del establecimiento</h2>
            <ul>{partnerOwners.map((u: any) => <li key={u.id}>#{u.id} · {u.name} · {u.email} · {u.email_verified_at ? 'Correo verificado' : 'Correo sin verificar'}</li>)}</ul>
            <p className="text-sm">Selecciona una cuenta COM existente después de comprobar su autorización para representar al negocio. El email de contacto no otorga propiedad. No se cambian contraseñas ni se marca el correo como verificado.</p>
            {!invitationEmailAvailable && <p className="text-sm text-warning">Correo saliente no operativo: el transporte actual no entrega emails. No se envían invitaciones desde este flujo. La vinculación administrativa a una cuenta existente sigue disponible.</p>}
            <form className="flex gap-2" onSubmit={e => { e.preventDefault(); search.get(base, { preserveScroll: true }); }}>
                <input aria-label="Buscar cuenta owner" minLength={2} required placeholder="Nombre o email de cuenta COM" className="min-w-0 flex-1 rounded border p-2" value={search.data.owner_q} onChange={e => search.setData('owner_q', e.target.value)}/><button className="rounded border px-3">Buscar</button>
            </form>
            <form className="space-y-3" onSubmit={e => { e.preventDefault(); owner.post(`${base}/owner`, { preserveScroll: true, onSuccess: () => owner.reset() }); }}>
                <label className="block">Cuenta autorizada<select required className="block w-full rounded border p-2" value={owner.data.owner_user_id} onChange={e => owner.setData('owner_user_id', e.target.value)}><option value="">Selecciona una cuenta</option>{accessCandidates.map((u: any) => <option key={u.id} value={u.id}>#{u.id} · {u.name} · {u.email} · {u.email_verified_at ? 'Verificado' : 'Sin verificar'}</option>)}</select></label>
                <label className="block">Motivo y comprobación de autorización<textarea required minLength={10} maxLength={1000} className="block w-full rounded border p-2" value={owner.data.authorization_reason} onChange={e => owner.setData('authorization_reason', e.target.value)}/></label>
                <label className="flex items-start gap-2"><input required type="checkbox" checked={owner.data.authorization_confirmed} onChange={e => owner.setData('authorization_confirmed', e.target.checked)}/>He comprobado que esta cuenta está autorizada para gestionar el establecimiento como owner.</label>
                <Errors errors={owner.errors}/><button disabled={owner.processing} className="rounded border px-4 py-2">Vincular owner autorizado</button>
            </form>
            <p className="text-sm">Acceso con la cuenta existente en <a className="underline" href="/partner/login">/partner/login</a>. Si todavía no tiene cuenta, su titular puede usar el registro COM existente; después busca y vincula esa cuenta.</p>
        </section>}
    </AdminLayout>;
}
function Errors({ errors }: { errors: Record<string, string> }) { return <>{Object.entries(errors).map(([field, message]) => <p role="alert" className="text-sm text-destructive" key={field}>{message}</p>)}</>; }
