import { ImageUpload } from '@/components/media/image-upload';
import { useState } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '../layout';

const fields: Record<string, string[]> = {
    partners: [
        'name',
        'slug',
        'entity_type',
        'partner_type',
        'legal_name',
        'tax_id',
        'description',
        'category',
        'website',
        'instagram',
        'facebook',
        'tiktok',
        'phone',
        'whatsapp',
        'email',
        'contact_name',
        'contact_phone',
        'contact_email',
        'status',
        'featured',
        'internal_notes',
        'sort_order',
        'published_at',
    ],
    locations: [
        'partner_id',
        'slug',
        'name',
        'location_type',
        'status',
        'is_primary',
        'country_code',
        'region',
        'city_id',
        'zone',
        'address',
        'address_reference',
        'latitude',
        'longitude',
        'maps_url',
        'google_place_id',
        'phone',
        'whatsapp',
        'email',
        'website',
        'instagram',
        'facebook',
        'tiktok',
        'timezone',
        'opening_hours',
        'manager_name',
        'manager_phone',
        'manager_email',
        'sort_order',
        'published_at',
    ],
    benefits: [
        'partner_id',
        'slug',
        'title',
        'short_description',
        'description',
        'terms',
        'category',
        'benefit_type',
        'access_mode',
        'estimated_savings',
        'redemption_limit_per_member',
        'status',
        'featured',
        'starts_at',
        'ends_at',
        'sort_order',
        'published_at',
    ],
    experiences: [
        'slug',
        'title',
        'short_description',
        'description',
        'terms',
        'category',
        'experience_type',
        'duration_minutes',
        'regular_price',
        'member_price',
        'currency',
        'reservation_method',
        'reservation_url',
        'reservation_whatsapp',
        'reservation_phone',
        'status',
        'featured',
        'sort_order',
        'published_at',
    ],
};
const defaults: Record<string, any> = {
    status: 'draft',
    access_mode: 'public',
    entity_type: 'organization',
    partner_type: 'business',
    location_type: 'branch',
    reservation_method: 'none',
    currency: 'BOB',
    featured: false,
    is_primary: false,
    location_scope: 'all',
    location_ids: [],
    partners: [],
    redemption_pin: '',
    image: null,
    cover: null,
    logo: null,
};
export default function ResourceForm({
    title,
    resource,
    item,
    partners = [],
    cities = [],
    publicUrl,
}: any) {
    const { url } = usePage();
    const catalogQuery = ['benefits', 'experiences'].includes(resource) && url.includes('?') ? `?${url.split('?')[1]}` : '';
    const [uploading, setUploading] = useState<Record<string, boolean>>({});
    const { gallery_paths: _galleryPaths, ...editableItem } = item ?? {};
    const form = useForm<any>({ ...defaults, ...editableItem,
        ...(resource === 'experiences' && item ? { partners: (item.partners ?? []).map((partner: any) => ({ partner_id: partner.id, role: partner.pivot.role, sort_order: partner.pivot.sort_order })) } : {}),
        ...(resource === 'benefits' && item ? { location_scope: item.applies_to_all_locations ? 'all' : 'selected', location_ids: (item.locations ?? []).map((location: any) => location.id) } : {}),
    });
    const submit = (e: any) => {
        e.preventDefault();
        if (item) {
            form.transform((data: any) => ({ ...data, _method: 'put' }));
            form.post(`/admin/${resource}/${['benefits', 'experiences'].includes(resource) ? item.slug : item.id}${catalogQuery}`, { forceFormData: true });
        } else {
            form.post(`/admin/${resource}`, { forceFormData: true });
        }
    };
    const options = (field: string) =>
        field === 'access_mode'
            ? ['public', 'authenticated', 'social_challenge_grant']
            : field === 'status'
            ? ['draft', 'published', 'paused', 'archived']
            : field === 'entity_type'
              ? ['organization', 'individual']
              : field === 'partner_type'
                ? [
                      'business',
                      'professional',
                      'creator',
                      'organizer',
                      'brand',
                      'other',
                  ]
                : field === 'location_type'
                  ? [
                        'branch',
                        'venue',
                        'meeting_point',
                        'online',
                        'mobile',
                        'other',
                    ]
                  : field === 'reservation_method'
                    ? ['none', 'whatsapp', 'url', 'phone', 'external', 'jakawi']
                    : [];
    return (
        <AdminLayout title={title}>
            {resource === 'partners' && publicUrl && <section className="mb-5 rounded-xl border border-border p-5"><h2 className="font-bold">PUBLIC PROFILE</h2><a href={publicUrl} className="mt-2 block break-all text-brand underline">{publicUrl}</a><button type="button" className="mt-3 min-h-11 rounded border border-border px-4 font-bold" onClick={() => navigator.clipboard?.writeText(publicUrl)}>COPIAR ENLACE</button></section>}
            {resource === 'experiences' && item?.slug && <Link href={`/admin/experiencias/${item.slug}/landings`} className="mb-5 inline-flex min-h-11 items-center font-bold text-brand underline">MARKETING LANDINGS</Link>}
            {resource === 'benefits' && item?.slug && <Link href={`/admin/beneficios/${item.slug}/landings`} className="mb-5 inline-flex min-h-11 items-center font-bold text-brand underline">MARKETING LANDINGS</Link>}
            <form onSubmit={submit} className="grid max-w-2xl gap-4">
                {fields[resource].map((field) => (
                    <label key={field} className="grid gap-1 text-sm">
                        {field.replaceAll('_', ' ')}
                        {field === 'partner_id' ? (
                            <select
                                value={form.data[field] || ''}
                                onChange={(e) =>
                                    form.setData(field, e.target.value || null)
                                }
                            >
                                <option value="">
                                    {resource === 'locations'
                                        ? 'Independiente'
                                        : 'Selecciona Partner'}
                                </option>
                                {partners.map((p: any) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                        ) : field === 'city_id' ? (
                            <select
                                value={form.data[field] || ''}
                                onChange={(e) => form.setData(field, e.target.value || null)}
                            >
                                <option value="">Sin ciudad (solo online)</option>
                                {cities.map((city: any) => <option key={city.id} value={city.id}>{city.name}</option>)}
                            </select>
                        ) : options(field).length ? (
                            <select
                                value={form.data[field] ?? ''}
                                onChange={(e) =>
                                    form.setData(field, e.target.value)
                                }
                            >
                                {options(field).map((x) => (
                                    <option key={x} value={x}>
                                        {field === 'access_mode' ? ({public: 'MEMBRESÍA JAKAWI', authenticated: 'CUENTA JAKAWI', social_challenge_grant: 'PREMIO DE RETO'}[x] ?? x) : x}
                                    </option>
                                ))}
                            </select>
                        ) : field === 'featured' || field === 'is_primary' ? (
                            <input
                                type="checkbox"
                                checked={Boolean(form.data[field])}
                                onChange={(e) =>
                                    form.setData(field, e.target.checked)
                                }
                            />
                        ) : [
                              'description',
                              'terms',
                              'internal_notes',
                              'opening_hours',
                          ].includes(field) ? (
                            <textarea
                                value={form.data[field] || ''}
                                onChange={(e) =>
                                    form.setData(field, e.target.value)
                                }
                            />
                        ) : (
                            <input
                                type={
                                    field.includes('_at')
                                        ? 'datetime-local'
                                        : field.includes('price') ||
                                            field.includes('savings') ||
                                            field === 'latitude' ||
                                            field === 'longitude' ||
                                            field === 'sort_order' ||
                                            field === 'duration_minutes' ||
                                            field ===
                                                'redemption_limit_per_member'
                                          ? 'number'
                                          : 'text'
                                }
                                value={form.data[field] || ''}
                                onChange={(e) =>
                                    form.setData(field, e.target.value)
                                }
                            />
                        )}
                    {field === 'access_mode' && form.data[field] === 'authenticated' && <p className="text-muted-foreground">Disponible para usuarios con cuenta JAKAWI. No requiere membresía activa.</p>}
                    </label>
                ))}
                {resource === 'locations' ? (
                    <>
                        <label>
                            PIN nuevo (6 dígitos)
                            <input
                                value={form.data.redemption_pin}
                                onChange={(e) =>
                                    form.setData(
                                        'redemption_pin',
                                        e.target.value,
                                    )
                                }
                            />
                        </label>
                        {item ? (
                            <p>
                                PIN configurado:{' '}
                                {item.has_redemption_pin ? 'Sí' : 'No'}
                            </p>
                        ) : null}
                    </>
                ) : null}
                {resource === 'benefits' ? (
                    <label>
                        Ubicaciones
                        <select
                            value={form.data.location_scope}
                            onChange={(e) =>
                                form.setData('location_scope', e.target.value)
                            }
                        >
                            <option value="all">Todas las Locations</option>
                            <option value="selected">
                                Locations seleccionadas
                            </option>
                        </select>
                    </label>
                ) : null}
                {(resource === 'partners' ? ['logo', 'cover'] : resource === 'experiences' ? ['image', 'cover'] : ['image']).map(name => <ImageUpload key={name} profileAvatar={resource === 'partners' && name === 'logo'} currentCropUrl={name === 'logo' ? item?.logoCropUrl : null} name={name} label={name === 'logo' ? 'Logo / foto de perfil' : name === 'cover' ? 'Portada' : 'Imagen'} currentUrl={item?.[`${name}Url`]} error={form.errors[name]} onBusy={busy => setUploading(value => ({...value, [name]: busy}))} onChange={(token, remove) => form.setData((data: Record<string, unknown>) => ({...data, [`${name}_upload`]: token, [`${name}_remove`]: remove}))} />)}
                {resource === 'partners' && (
                    <section
                        className="space-y-4"
                        aria-label="Imágenes del Partner"
                    >
                        <h2 className="text-lg font-bold">
                            IMÁGENES DEL PARTNER
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Hasta 3 imágenes, en este orden. Recomendado: 4:5 ·
                            1080 × 1350 px. Guarda para aplicar los cambios.
                        </p>
                        <div className="grid gap-4 lg:grid-cols-3">
                            {[1, 2, 3].map((slot) => {
                                const name = `gallery_${slot}`;
                                return (
                                    <ImageUpload
                                        key={name}
                                        name={name}
                                        label={`IMAGEN ${slot}`}
                                        currentUrl={
                                            item?.galleryUrls?.[slot - 1]
                                        }
                                        error={form.errors[name]}
                                        onBusy={(busy) =>
                                            setUploading((value) => ({
                                                ...value,
                                                [name]: busy,
                                            }))
                                        }
                                        onChange={(token, remove) =>
                                            form.setData(
                                                (
                                                    data: Record<
                                                        string,
                                                        unknown
                                                    >,
                                                ) => ({
                                                    ...data,
                                                    [`${name}_upload`]: token,
                                                    [`${name}_remove`]: remove,
                                                }),
                                            )
                                        }
                                    />
                                );
                            })}
                        </div>
                        {form.errors.gallery_paths && (
                            <p role="alert" className="text-sm text-red-600">
                                {form.errors.gallery_paths}
                            </p>
                        )}
                    </section>
                )}
                <button
                    className="rounded bg-brand p-3 text-brand-foreground"
                    disabled={form.processing || Object.values(uploading).some(Boolean)}
                >
                    Guardar
                </button>
                <Link href={`/admin/${resource}${catalogQuery}`}>Volver al listado</Link>
            </form>
        </AdminLayout>
    );
}
