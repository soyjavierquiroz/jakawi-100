import { useForm } from '@inertiajs/react';
import AdminLayout from '../layout';

export default function CityForm({ city, statuses }: { city?: any; statuses: string[] }) {
    const form = useForm({ name: city?.name ?? '', slug: city?.slug ?? '', region: city?.region ?? '', country_code: city?.country_code ?? 'BO', status: city?.status ?? 'COMING_SOON', priority: city?.priority ?? 0, community_enabled: city?.community_enabled ?? false, community_name: city?.community_name ?? '', community_invite_url: city?.community_invite_url ?? '' });
    const submit = (event: React.FormEvent) => { event.preventDefault(); city ? form.put(`/admin/ciudades/${city.slug}`) : form.post('/admin/ciudades'); };
    return <AdminLayout title={city ? 'Editar ciudad' : 'Nueva ciudad'}><form onSubmit={submit} className="grid max-w-xl gap-4">
        <label className="grid gap-1 text-sm">Ciudad<input required value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></label>
        <label className="grid gap-1 text-sm">Slug<input required value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} /></label>
        <label className="grid gap-1 text-sm">País<input required maxLength={2} value={form.data.country_code} onChange={(e) => form.setData('country_code', e.target.value.toUpperCase())} /></label>
        <label className="grid gap-1 text-sm">Región<input value={form.data.region} onChange={(e) => form.setData('region', e.target.value)} /></label>
        {!city && <label className="grid gap-1 text-sm">Estado<select value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>{statuses.map((status) => <option key={status} value={status}>{status}</option>)}</select></label>}
        <label className="grid gap-1 text-sm">Prioridad<input required type="number" value={form.data.priority} onChange={(e) => form.setData('priority', Number(e.target.value))} /></label>
        <fieldset className="grid gap-3 rounded-xl border border-border p-4"><legend className="px-1 font-bold">Comunidad JAKAWI · WhatsApp</legend>
            <label className="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" checked={form.data.community_enabled} onChange={e => form.setData('community_enabled', e.target.checked)} />Habilitar comunidad para esta ciudad</label>
            <label className="grid gap-1 text-sm">Nombre visible<input maxLength={255} required={form.data.community_enabled} value={form.data.community_name} onChange={e => form.setData('community_name', e.target.value)} /></label>
            <label className="grid gap-1 text-sm">Enlace oficial de invitación<input type="url" maxLength={255} required={form.data.community_enabled} placeholder="https://chat.whatsapp.com/…" value={form.data.community_invite_url} onChange={e => form.setData('community_invite_url', e.target.value)} /></label>
            <p className="text-xs text-muted-foreground">Usa el enlace de WhatsApp sin parámetros. Crear la comunidad y permitir publicaciones solo a administradores son pasos manuales. Solo se mostrará en ciudades activas.</p>
        </fieldset>
        {Object.values(form.errors).length > 0 && <div role="alert" className="text-sm">{Object.entries(form.errors).map(([field, message]) => <p key={field}>{message}</p>)}</div>}
        <button className="w-fit rounded bg-brand px-4 py-2 font-bold text-brand-foreground" disabled={form.processing}>Guardar</button>
    </form></AdminLayout>;
}
