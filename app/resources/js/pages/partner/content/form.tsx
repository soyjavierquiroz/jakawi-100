import { ImageUpload } from '@/components/media/image-upload';
import {
    actionClass,
    primaryClass,
    inputClass,
    canEditItem,
    ReviewStatus,
    ReviewNote,
    type PartnerAccess,
} from '@/components/partner/editorial';
import { FormEvent, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import PartnerLayout from '../layout';

export default function ContentForm({ partner, kind, item, locations }: any) {
    const benefit = kind === 'benefit';
    const base = `/partner/${partner.slug}/${benefit ? 'promociones' : 'experiencias'}`;
    const { partnerAccess: access } = usePage<{
        partnerAccess: PartnerAccess;
    }>().props;
    const editable = access?.can_edit_content && canEditItem(item);
    const [busy, setBusy] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [uploading, setUploading] = useState<Record<string, boolean>>({});
    const processing = busy || Object.values(uploading).some(Boolean);
    const options = {
        onStart: () => setBusy(true),
        onFinish: () => setBusy(false),
        onSuccess: () => setDirty(false),
    };
    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (!editable || processing) return;
        const data = new FormData(e.currentTarget);
        if (benefit)
            for (const name of ['starts_at', 'ends_at']) {
                const value = data.get(name);
                if (value)
                    data.set(name, new Date(`${value}-04:00`).toISOString());
            }
        item
            ? router.post(`${base}/${item.slug}`, data, {
                  ...options,
                  headers: { 'X-HTTP-Method-Override': 'PUT' },
              })
            : router.post(base, data, options);
    };
    const input = (name: string, label: string, type = 'text') => (
        <label className="block min-w-0 text-sm font-medium" key={name}>
            {label}
            <input
                name={name}
                type={type}
                required={name === 'title'}
                min={type === 'number' ? '0' : undefined}
                defaultValue={
                    type === 'datetime-local'
                        ? item?.[name]
                            ? new Date(
                                  new Date(item[name]).getTime() - 4 * 3600000,
                              )
                                  .toISOString()
                                  .slice(0, 16)
                            : ''
                        : (item?.[name] ?? '')
                }
                className={inputClass}
            />
        </label>
    );
    const textarea = (name: string, label: string) => (
        <label className="block text-sm font-medium">
            {label}
            <textarea
                name={name}
                defaultValue={item?.[name] ?? ''}
                rows={4}
                className={inputClass}
            />
        </label>
    );
    return (
        <PartnerLayout
            title={`${item ? 'Detalle de' : 'Crear'} ${benefit ? 'promoción' : 'experiencia'}`}
            partner={partner}
        >
            <div className="space-y-5">
                <Link href={base} className={actionClass}>
                    ← Volver a {benefit ? 'promociones' : 'experiencias'}
                </Link>
                {item && <ReviewStatus item={item} />}
                {item && <ReviewNote item={item} />}
                <aside className="rounded-2xl border border-border bg-surface p-4 text-sm leading-relaxed">
                    {item?.status === 'published'
                        ? 'Esta versión pública está protegida. Para modificarla, solicita la intervención de JAKAWI. No puedes editarla ni reenviarla desde este portal.'
                        : !editable
                          ? 'Esta propuesta se consulta sin edición mientras JAKAWI gestiona su revisión o publicación.'
                          : 'Completa los datos y guarda el borrador. Guardar no envía a revisión ni publica. Cuando esté preparado, envía la versión guardada a JAKAWI.'}
                </aside>
                <form
                    onSubmit={submit}
                    onChange={() => setDirty(true)}
                    className="space-y-5"
                >
                    <fieldset
                        disabled={!editable || busy}
                        className="min-w-0 space-y-5"
                    >
                        <section className="space-y-4 rounded-2xl border border-border bg-surface p-5">
                            <h2 className="text-lg font-semibold">
                                1. Presenta tu propuesta
                            </h2>
                            {input('title', 'Título *')}
                            {input('short_description', 'Descripción corta')}
                            {textarea('description', 'Descripción')}
                            <div className="grid gap-4 sm:grid-cols-2">
                                {input('category', 'Categoría')}
                                {input(
                                    benefit
                                        ? 'benefit_type'
                                        : 'experience_type',
                                    benefit
                                        ? 'Tipo de beneficio'
                                        : 'Tipo de experiencia',
                                )}
                            </div>
                        </section>
                        <section className="space-y-4 rounded-2xl border border-border bg-surface p-5">
                            <h2 className="text-lg font-semibold">
                                2.{' '}
                                {benefit
                                    ? 'Sedes, vigencia y condiciones'
                                    : 'Condiciones de la experiencia'}
                            </h2>
                            {benefit ? (
                                <>
                                    <label className="block text-sm font-medium">
                                        Alcance
                                        <select
                                            name="location_scope"
                                            defaultValue={
                                                item?.applies_to_all_locations
                                                    ? 'all'
                                                    : 'selected'
                                            }
                                            className={inputClass}
                                        >
                                            <option value="all">
                                                Todas las sedes
                                            </option>
                                            <option value="selected">
                                                Sedes seleccionadas
                                            </option>
                                        </select>
                                    </label>
                                    <fieldset>
                                        <legend className="text-sm font-medium">
                                            Sedes seleccionadas
                                        </legend>
                                        <div className="mt-2 grid gap-2 sm:grid-cols-2">
                                            {locations.map((l: any) => (
                                                <label
                                                    key={l.id}
                                                    className="flex min-h-11 items-center gap-3 rounded-lg border border-border px-3 text-sm"
                                                >
                                                    <input
                                                        className="size-4 accent-brand"
                                                        type="checkbox"
                                                        name="location_ids[]"
                                                        value={l.id}
                                                        defaultChecked={item?.locations?.some(
                                                            (x: any) =>
                                                                x.id === l.id,
                                                        )}
                                                    />
                                                    {l.name}
                                                </label>
                                            ))}
                                        </div>
                                        {!locations.length && (
                                            <p className="mt-2 text-sm text-muted-foreground">
                                                No hay sedes registradas. Puedes
                                                gestionarlas desde Perfil y
                                                sedes.
                                            </p>
                                        )}
                                    </fieldset>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        {input(
                                            'starts_at',
                                            'Vigente desde · hora Bolivia',
                                            'datetime-local',
                                        )}
                                        {input(
                                            'ends_at',
                                            'Vigente hasta · hora Bolivia',
                                            'datetime-local',
                                        )}
                                    </div>
                                    {textarea(
                                        'terms',
                                        'Condiciones comerciales',
                                    )}
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        {input(
                                            'redemption_limit_per_member',
                                            'Límite de canjes por miembro',
                                            'number',
                                        )}
                                        {input(
                                            'estimated_savings',
                                            'Ahorro estimado para el miembro',
                                            'number',
                                        )}
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        El ahorro estimado no representa
                                        ingresos del establecimiento.
                                    </p>
                                </>
                            ) : (
                                <>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        {input(
                                            'duration_minutes',
                                            'Duración (minutos)',
                                            'number',
                                        )}
                                        {input(
                                            'regular_price',
                                            'Precio regular',
                                            'number',
                                        )}
                                        {input(
                                            'member_price',
                                            'Precio miembro',
                                            'number',
                                        )}
                                    </div>
                                    <label className="block text-sm font-medium">
                                        Método de reserva propuesto
                                        <select
                                            name="reservation_method"
                                            defaultValue={
                                                item?.reservation_method ??
                                                'none'
                                            }
                                            className={inputClass}
                                        >
                                            {[
                                                ['none', 'No aplica'],
                                                ['whatsapp', 'WhatsApp'],
                                                ['url', 'URL'],
                                                ['phone', 'Teléfono'],
                                                ['external', 'Externo'],
                                                ['jakawi', 'JAKAWI'],
                                            ].map(([value, label]) => (
                                                <option
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                    <p className="text-sm text-muted-foreground">
                                        Este dato prepara la propuesta. Las
                                        reservas están deshabilitadas durante el
                                        prelanzamiento.
                                    </p>
                                    {input('reservation_whatsapp', 'WhatsApp')}
                                    {input(
                                        'reservation_url',
                                        'URL de reserva',
                                        'url',
                                    )}
                                    {input(
                                        'reservation_phone',
                                        'Teléfono de reserva',
                                        'tel',
                                    )}
                                </>
                            )}
                        </section>
                        <section className="space-y-4 rounded-2xl border border-border bg-surface p-5">
                            <h2 className="text-lg font-semibold">
                                3. Imágenes
                            </h2>
                            <ImageUpload
                                name="image"
                                label="Imagen"
                                disabled={!editable || busy}
                                onBusy={(value) =>
                                    setUploading((current) => ({
                                        ...current,
                                        image: value,
                                    }))
                                }
                                onChange={() => setDirty(true)}
                                currentUrl={item?.imageUrl}
                            />
                            {!benefit && (
                                <ImageUpload
                                    name="cover"
                                    label="Portada"
                                    disabled={!editable || busy}
                                    onBusy={(value) =>
                                        setUploading((current) => ({
                                            ...current,
                                            cover: value,
                                        }))
                                    }
                                    onChange={() => setDirty(true)}
                                    currentUrl={item?.coverUrl}
                                />
                            )}
                        </section>
                    </fieldset>
                    {editable && (
                        <div className="flex flex-wrap items-center gap-3">
                            <button
                                disabled={processing}
                                className={primaryClass}
                            >
                                {processing ? 'Guardando…' : 'Guardar borrador'}
                            </button>
                            <p
                                role="status"
                                className="text-sm text-muted-foreground"
                            >
                                {dirty
                                    ? 'Cambios pendientes de guardar.'
                                    : 'Guarda antes de enviar a revisión.'}
                            </p>
                        </div>
                    )}
                </form>
                {item && editable && (
                    <section className="space-y-3 rounded-2xl border border-border p-5">
                        <h2 className="text-lg font-semibold">
                            Enviar la propuesta a JAKAWI
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Se enviará la versión guardada. Durante la revisión
                            no podrás editarla.
                        </p>
                        <button
                            disabled={processing || dirty}
                            onClick={() =>
                                router.post(
                                    `${base}/${item.slug}/enviar`,
                                    {},
                                    options,
                                )
                            }
                            className={actionClass}
                        >
                            {busy ? 'Procesando…' : 'Enviar a revisión'}
                        </button>
                        {dirty && (
                            <p className="text-sm text-muted-foreground">
                                Guarda los cambios pendientes antes de enviar.
                            </p>
                        )}
                    </section>
                )}
                {!benefit && item && (
                    <Sessions
                        partner={partner}
                        item={item}
                        locations={locations}
                        editable={editable}
                    />
                )}
            </div>
        </PartnerLayout>
    );
}
function Sessions({ partner, item, locations, editable }: any) {
    const sessions = [...(item.sessions ?? []), ...(editable ? [null] : [])];
    return (
        <section className="space-y-4 border-t border-border pt-5">
            <h2 className="text-xl font-semibold">Sesiones de la propuesta</h2>
            <p className="text-sm text-muted-foreground">
                Fechas, sede y capacidad forman parte del contenido editorial.
                Guardar una sesión no activa reservas.
            </p>
            {!sessions.length && (
                <p className="text-sm">Sin sesiones registradas.</p>
            )}
            {sessions.map((session: any) => (
                <form
                    key={session?.id ?? 'new'}
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.post(
                            `/partner/${partner.slug}/experiencias/${item.slug}/sessions${session ? `/${session.id}` : ''}`,
                            new FormData(e.currentTarget),
                            session
                                ? {
                                      headers: {
                                          'X-HTTP-Method-Override': 'PUT',
                                      },
                                  }
                                : {},
                        );
                    }}
                    className="rounded-2xl border border-border bg-surface p-5"
                >
                    <h3 className="mb-4 font-semibold">
                        {session
                            ? 'Sesión registrada'
                            : 'Añadir sesión al borrador'}
                    </h3>
                    <fieldset
                        disabled={!editable}
                        className="grid min-w-0 gap-4 sm:grid-cols-2"
                    >
                        <label className="min-w-0 text-sm">
                            Inicio *
                            <input
                                required
                                name="starts_at"
                                type="datetime-local"
                                defaultValue={session?.starts_at?.slice(0, 16)}
                                className={inputClass}
                            />
                        </label>
                        <label className="min-w-0 text-sm">
                            Fin
                            <input
                                name="ends_at"
                                type="datetime-local"
                                defaultValue={session?.ends_at?.slice(0, 16)}
                                className={inputClass}
                            />
                        </label>
                        <label className="text-sm">
                            Capacidad
                            <input
                                name="capacity"
                                type="number"
                                min="1"
                                defaultValue={session?.capacity ?? ''}
                                className={inputClass}
                            />
                        </label>
                        <label className="text-sm">
                            Lugar
                            <input
                                name="venue_label"
                                defaultValue={session?.venue_label ?? ''}
                                className={inputClass}
                            />
                        </label>
                        <label className="text-sm">
                            Sede
                            <select
                                name="location_id"
                                defaultValue={session?.location_id ?? ''}
                                className={inputClass}
                            >
                                <option value="">Sin sede</option>
                                {locations.map((l: any) => (
                                    <option value={l.id} key={l.id}>
                                        {l.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        {editable && (
                            <button className={`${actionClass} self-end`}>
                                {session ? 'Guardar sesión' : 'Añadir sesión'}
                            </button>
                        )}
                    </fieldset>
                </form>
            ))}
        </section>
    );
}
