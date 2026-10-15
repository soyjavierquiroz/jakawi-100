import { ImageUpload } from '@/components/media/image-upload';
import { Link, useForm, usePage } from '@inertiajs/react';
import { useRef, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import AdminLayout from '../layout';
import { actionClass, inputClass, primaryClass, StatusBadge, statusLabels } from './partner-location-ui';

type Props = {
    item?: any;
    partners?: { id: number; name: string }[];
    locations?: { id: number; name: string; partner_id: number }[];
};
const accessLabels = {
    public: 'Público: requiere membresía activa',
    authenticated: 'Con cuenta: requiere autenticación, no membresía',
    social_challenge_grant: 'Premio de reto social: requiere grant válido',
};
const accessHelp: Record<string, string> = {
    public: 'El canje requiere una membresía JAKAWI activa.',
    authenticated: 'El canje requiere iniciar sesión con una cuenta JAKAWI; no requiere membresía activa.',
    social_challenge_grant: 'El canje requiere una autorización (grant) válida obtenida mediante un reto social.',
};
const labels: Record<string, string> = {
    partner_id: 'Partner', slug: 'Slug', title: 'Título comercial',
    short_description: 'Descripción corta', description: 'Descripción completa', category: 'Categoría',
    benefit_type: 'Tipo de beneficio', terms: 'Términos y condiciones', estimated_savings: 'Ahorro estimado',
    access_mode: 'Modo de acceso', redemption_limit_per_member: 'Límite de canjes por miembro',
    starts_at: 'Inicio de vigencia', ends_at: 'Fin de vigencia', status: 'Estado', featured: 'Destacado',
    sort_order: 'Orden de presentación', published_at: 'Fecha de publicación', location_scope: 'Locations aplicables',
    location_ids: 'Locations seleccionadas', image: 'Imagen', image_upload: 'Imagen', image_remove: 'Imagen',
};
const helps: Record<string, string> = {
    short_description: 'Resumen que aparece en Explorer y otras tarjetas.',
    terms: 'Detalla las condiciones, restricciones y exclusiones del beneficio; el resumen presenta la propuesta.',
    estimated_savings: 'Ahorro orientativo para el miembro. No es el precio de venta.',
    redemption_limit_per_member: 'Déjalo vacío para conservar el comportamiento sin límite configurado.',
    published_at: 'Dato editorial independiente del inicio y fin de vigencia.',
};
const dateFields = ['starts_at', 'ends_at', 'published_at'];
// Show the stored wall time without a browser timezone conversion. Send the original value if untouched.
const dateInput = (value: string | null | undefined) => value?.slice(0, 23).replace(/Z$/, '') ?? '';
function Section({ id, title, description, children }: { id: string; title: string; description?: string; children: ReactNode }) {
    return <section id={id} aria-labelledby={`${id}-title`} className="min-w-0 rounded-xl border border-border bg-card p-4 sm:p-6">
        <h2 id={`${id}-title`} className="text-lg font-bold">{title}</h2>
        {description && <p className="mt-1 text-sm text-muted-foreground">{description}</p>}
        <div className="mt-5 grid min-w-0 gap-5 sm:grid-cols-2">{children}</div>
    </section>;
}
export default function BenefitForm({ item, partners = [], locations = [] }: Props) {
    const { url } = usePage();
    const catalogQuery = url.includes('?') ? `?${url.split('?')[1]}` : '';
    const [uploading, setUploading] = useState(false);
    const submitting = useRef(false);
    const { gallery_paths: _gallery, ...editable } = item ?? {};
    const form = useForm<any>({
        status: 'draft', access_mode: 'public', featured: false, location_scope: 'all', location_ids: [],
        image: null, cover: null, logo: null, redemption_pin: '', partners: [], is_primary: false,
        entity_type: 'organization', partner_type: 'business', location_type: 'branch', reservation_method: 'none', currency: 'BOB',
        ...editable,
        ...(item ? { location_scope: item.applies_to_all_locations ? 'all' : 'selected', location_ids: (item.locations ?? []).map((location: any) => location.id) } : {}),
        ...Object.fromEntries(dateFields.map(name => [name, dateInput(item?.[name])])),
    });
    const busy = form.processing || uploading;
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (busy || submitting.current) return;
        submitting.current = true;
        form.transform((data: any) => ({
            ...data,
            ...Object.fromEntries(dateFields.map(name => [name, data[name] === dateInput(item?.[name]) ? (item?.[name] ?? null) : data[name]])),
            ...(item ? { _method: 'put' } : {}),
        }));
        form.post(`/admin/benefits${item ? `/${item.slug}${catalogQuery}` : ''}`, {
            onSuccess: () => toast.success(item ? 'Beneficio guardado.' : 'Beneficio creado.'),
            onError: () => document.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus(),
            onFinish: () => { submitting.current = false; },
        });
    };
    const error = (name: string) => form.errors[name] && <p id={`${name}-error`} role="alert" className="text-sm text-red-700">{form.errors[name]}</p>;
    const field = (name: string) => {
        const choices: Record<string, string> | undefined = name === 'partner_id' ? Object.fromEntries(partners.map(p => [p.id, p.name]))
            : name === 'status' ? statusLabels : name === 'access_mode' ? accessLabels : undefined;
        const help = name === 'access_mode' ? accessHelp[form.data.access_mode] : helps[name];
        const common = { id: name, name, className: inputClass, 'aria-invalid': Boolean(form.errors[name]), 'aria-describedby': [help && `${name}-help`, form.errors[name] && `${name}-error`].filter(Boolean).join(' ') || undefined };
        const wide = ['title', 'short_description', 'description', 'terms', 'access_mode'].includes(name);
        return <div key={name} className={`grid min-w-0 content-start gap-2 ${wide ? 'sm:col-span-2' : ''}`}>
            <label htmlFor={name} className="text-sm font-semibold">{labels[name]}</label>
            {choices ? <select {...common} value={form.data[name] ?? ''} onChange={e => form.setData(name, e.target.value || null)}>
                {name === 'partner_id' && <option value="">Selecciona Partner</option>}
                {Object.entries(choices).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
            </select> : name === 'featured' ? <input {...common} type="checkbox" className="size-5 accent-brand" checked={Boolean(form.data[name])} onChange={e => form.setData(name, e.target.checked)} />
                : ['short_description', 'description', 'terms'].includes(name) ? <textarea {...common} rows={name === 'description' ? 7 : name === 'terms' ? 5 : 3} value={form.data[name] ?? ''} onChange={e => form.setData(name, e.target.value)} />
                    : <input {...common} type={dateFields.includes(name) ? 'datetime-local' : ['estimated_savings', 'redemption_limit_per_member', 'sort_order'].includes(name) ? 'number' : 'text'} step={dateFields.includes(name) ? 'any' : undefined} value={form.data[name] ?? ''} onChange={e => form.setData(name, e.target.value)} />}
            {help && <p id={`${name}-help`} className="text-sm text-muted-foreground">{help}</p>}{error(name)}
        </div>;
    };
    const availableLocations = locations.filter(location => String(location.partner_id) === String(form.data.partner_id));
    const selected = form.data.location_ids as number[];
    const save = <button type="submit" form="benefit-editor" disabled={busy} className={primaryClass}>{form.processing ? 'Guardando…' : uploading ? 'Procesando imagen…' : 'Guardar cambios'}</button>;
    return <AdminLayout title={item ? 'Editar Beneficio' : 'Crear Beneficio'}>
        <div className="mx-auto max-w-5xl space-y-6">
            <Link href={`/admin/benefits${catalogQuery}`} className={`${actionClass} border-transparent px-0`}>← Volver a Beneficios</Link>
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0 flex-1 space-y-2">
                    {item && <><div className="flex flex-wrap items-center gap-3"><p className="min-w-0 text-xl font-semibold break-words">{item.title}</p><StatusBadge status={item.status} /></div>
                        <p className="text-sm text-muted-foreground">Partner propietario: {partners.find(p => p.id === item.partner_id)?.name ?? 'Sin Partner'}</p>
                        <Link href={`/admin/beneficios/${item.slug}/landings`} className={`${actionClass} mt-1`}>Marketing Landings</Link></>}
                </div>{save}
            </div>
            <form id="benefit-editor" onSubmit={submit} className="space-y-6">
                <p role="status" aria-live="polite" className="text-sm text-muted-foreground">{form.processing ? 'Esperando confirmación del servidor…' : form.isDirty ? 'Cambios pendientes de guardar.' : 'Sin cambios pendientes.'}</p>
                {Object.keys(form.errors).length > 0 && <div role="alert" className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><p className="font-semibold">No se guardaron los cambios. Revisa los campos indicados.</p>{Object.entries(form.errors).map(([key, value]) => <p key={key}>{labels[key] ?? key}: {String(value)}</p>)}</div>}
                <fieldset disabled={form.processing} className="min-w-0 space-y-6">
                    <Section id="identidad" title="A. Identidad y propuesta">{['partner_id', 'slug', 'title', 'short_description', 'description', 'category', 'benefit_type'].map(field)}</Section>
                    <Section id="condiciones" title="B. Condiciones comerciales">{['terms', 'estimated_savings'].map(field)}</Section>
                    <Section id="acceso" title="C. Acceso y canje" description="El PIN de la Location sigue siendo obligatorio para validar el canje.">{['access_mode', 'redemption_limit_per_member'].map(field)}</Section>
                    <Section id="vigencia" title="D. Vigencia y ubicaciones" description="Las fechas conservan la zona horaria del backend. Deja los campos vacíos si no hay una fecha configurada.">
                        {['starts_at', 'ends_at'].map(field)}
                        <div className="grid min-w-0 gap-3 sm:col-span-2">
                            <label htmlFor="location_scope" className="text-sm font-semibold">Locations aplicables</label>
                            <select id="location_scope" className={inputClass} value={form.data.location_scope} aria-invalid={Boolean(form.errors.location_scope)} onChange={e => form.setData('location_scope', e.target.value)}><option value="all">Todas las Locations del Partner</option><option value="selected">Locations seleccionadas</option></select>{error('location_scope')}
                            {form.data.location_scope === 'all' ? <p className="text-sm text-muted-foreground">Se aplica a todas las sedes del Partner, según las reglas existentes.</p> : <fieldset id="location_ids" tabIndex={-1} aria-invalid={Boolean(form.errors.location_ids)} aria-describedby={form.errors.location_ids ? 'location_ids-error' : undefined} className="min-w-0 space-y-3 rounded-lg border border-border p-3">
                                <legend className="px-1 text-sm font-semibold">Locations seleccionadas ({selected.length})</legend>
                                {locations.filter(location => availableLocations.includes(location) || selected.includes(location.id)).map(location => <label key={location.id} className="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" className="size-5 shrink-0 accent-brand" checked={selected.includes(location.id)} onChange={e => form.setData('location_ids', e.target.checked ? [...selected, location.id] : selected.filter(id => id !== location.id))} /><span className="min-w-0 break-words">{location.name}{String(location.partner_id) !== String(form.data.partner_id) && ' — pertenece a otro Partner; revisa la selección'}</span></label>)}
                                {!availableLocations.length && <p className="text-sm text-muted-foreground">{form.data.partner_id ? 'Este Partner no tiene Locations disponibles.' : 'Selecciona un Partner para ver sus Locations.'}</p>}
                            </fieldset>}{error('location_ids')}
                            {Object.keys(form.errors).filter(key => key.startsWith('location_ids.')).map(key => <div key={key}>{error(key)}</div>)}
                        </div>
                    </Section>
                    <Section id="publicacion" title="E. Imagen y publicación" description="Durante prelaunch, publicar el beneficio no garantiza su disponibilidad comercial. La vigencia, el acceso y las sedes mantienen sus propias reglas.">
                        <div className="min-w-0 space-y-3 [&_img]:h-auto [&_img]:max-h-96 [&_img]:w-full [&_img]:object-contain">
                            <ImageUpload name="image" label="Imagen del beneficio" currentUrl={item?.imageUrl} error={form.errors.image || form.errors.image_upload || form.errors.image_remove} disabled={form.processing} onBusy={setUploading} onChange={(token, remove) => form.setData((data: any) => ({ ...data, image_upload: token, image_remove: remove }))} />
                            <p className="text-sm text-muted-foreground">Guarda el formulario para aplicar los cambios de imagen.</p>
                        </div>
                        <div className="grid min-w-0 content-start gap-5">{['status', 'featured', 'sort_order', 'published_at'].map(field)}</div>
                    </Section>
                </fieldset>
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4"><Link href={`/admin/benefits${catalogQuery}`} className={actionClass}>Volver al listado</Link>{save}</div>
            </form>
        </div>
    </AdminLayout>;
}
