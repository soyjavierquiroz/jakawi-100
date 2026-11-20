import {
    EditorialState,
    Field,
    primaryAction,
    ProposalSection,
    secondaryAction,
    unlockStates,
    type Partner,
} from '@/components/partner/proposal-fields';
import { Link, useForm } from '@inertiajs/react';
import PartnerHeading from '../layout';
type Unlock = {
    slug: string;
    title: string;
    short_description: string | null;
    description: string | null;
    terms: string | null;
    type: string;
    minimum_commitments: number;
    maximum_capacity: number | null;
    commitment_deadline: string | null;
    free_user_offer: string | null;
    member_offer: string | null;
    status: string;
};
type Location = { id: number; name: string; status: string };
export default function UnlockForm({
    partner,
    unlock,
    editable,
    locations,
    locationIds,
    reviewNotes,
}: {
    partner: Partner;
    unlock: Unlock | null;
    editable: boolean;
    locations: Location[];
    locationIds: number[];
    reviewNotes: {
        id: number;
        reason: string;
        to_status: string;
        created_at: string;
    }[];
}) {
    const base = `/partner/${partner.slug}/desbloqueos`;
    const f = useForm({
        title: unlock?.title ?? '',
        short_description: unlock?.short_description ?? '',
        description: unlock?.description ?? '',
        terms: unlock?.terms ?? '',
        type: unlock?.type ?? 'BENEFIT',
        minimum_commitments: unlock?.minimum_commitments ?? 10,
        maximum_capacity: unlock?.maximum_capacity ?? ('' as number | string),
        commitment_deadline: unlock?.commitment_deadline?.slice(0, 16) ?? '',
        free_user_offer: unlock?.free_user_offer ?? '',
        member_offer: unlock?.member_offer ?? '',
        location_ids: locationIds,
    });
    const send = useForm({});
    const field = (
        key: keyof typeof f.data,
        label: string,
        help?: string,
        multiline = false,
        type = 'text',
        required = false,
    ) => (
        <Field
            name={key}
            label={label}
            help={help}
            multiline={multiline}
            type={type}
            min={type === 'number' ? 1 : undefined}
            required={required}
            value={String(f.data[key])}
            error={f.errors[key]}
            onChange={(v) => f.setData(key, v as never)}
        />
    );
    const locationErrors = Object.entries(f.errors)
        .filter(([key]) => key.startsWith('location_ids'))
        .map(([, value]) => value);
    return (
        <PartnerHeading
            title={unlock ? 'Propuesta de desbloqueo' : 'Nuevo desbloqueo'}
            partner={partner}
        >
            <div className="max-w-4xl space-y-5">
                <Link
                    className="inline-block py-2 text-sm underline"
                    href={base}
                >
                    Volver a desbloqueos
                </Link>
                <EditorialState
                    label={
                        unlockStates[unlock?.status ?? 'DRAFT'] ??
                        unlock?.status ??
                        'Borrador'
                    }
                    editable={editable}
                />
                {reviewNotes.map((note) => (
                    <aside
                        key={note.id}
                        role="note"
                        className="rounded-md border border-brand/40 bg-muted p-4"
                    >
                        <h2 className="font-semibold">
                            Observaciones de JAKAWI ·{' '}
                            {unlockStates[note.to_status] ?? note.to_status}
                        </h2>
                        <p className="mt-2 text-sm break-words whitespace-pre-wrap">
                            {note.reason}
                        </p>
                    </aside>
                ))}
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.transform((data) => ({
                            ...data,
                            maximum_capacity:
                                data.maximum_capacity === ''
                                    ? null
                                    : data.maximum_capacity,
                            commitment_deadline:
                                data.commitment_deadline || null,
                        }));
                        const options = { onSuccess: () => f.setDefaults() };
                        unlock
                            ? f.put(`${base}/${unlock.slug}`, options)
                            : f.post(base, options);
                    }}
                >
                    <fieldset
                        disabled={!editable || f.processing}
                        className="min-w-0 space-y-5"
                    >
                        <ProposalSection
                            title="1. Tu propuesta"
                            description="Describe qué podría desbloquearse. Guardar el borrador no activa compromisos ni entrega beneficios."
                        >
                            {field(
                                'title',
                                'Título',
                                'Usa un nombre breve y reconocible.',
                                false,
                                'text',
                                true,
                            )}
                            <Field
                                name="type"
                                label="Tipo de propuesta"
                                value={f.data.type}
                                onChange={(v) => f.setData('type', v)}
                                error={f.errors.type}
                                options={[
                                    ['BENEFIT', 'Beneficio'],
                                    ['PRICE', 'Precio especial'],
                                    ['EXPERIENCE', 'Experiencia'],
                                    ['SECRET', 'Propuesta secreta'],
                                ]}
                                required
                                help="Elige el tipo existente que describe tu propuesta; JAKAWI revisará sus condiciones."
                            />
                            {field(
                                'short_description',
                                'Resumen',
                                'Explica la idea en una o dos frases.',
                                true,
                            )}
                            {field(
                                'description',
                                'Descripción',
                                'Detalla la propuesta y lo que recibirá quien participe.',
                                true,
                            )}
                        </ProposalSection>
                        <ProposalSection
                            title="2. Compromisos, capacidad y plazo"
                            description="Estos datos se proponen para revisión. No se aceptan compromisos reales durante PRELAUNCH."
                        >
                            {field(
                                'minimum_commitments',
                                'Mínimo de compromisos',
                                'Al menos 1. Es la cantidad necesaria para alcanzar la meta.',
                                false,
                                'number',
                                true,
                            )}
                            {field(
                                'maximum_capacity',
                                'Capacidad máxima',
                                'Opcional. Si la defines, debe ser igual o mayor al mínimo.',
                                false,
                                'number',
                            )}
                            {field(
                                'commitment_deadline',
                                'Plazo para comprometerse (UTC)',
                                'Opcional. Las fechas del formulario usan UTC, zona horaria de la aplicación.',
                                false,
                                'datetime-local',
                            )}
                        </ProposalSection>
                        <ProposalSection title="3. Ofertas y condiciones">
                            {field(
                                'free_user_offer',
                                'Oferta para usuarios sin membresía',
                                'Describe la propuesta para este público, si corresponde.',
                                true,
                            )}
                            {field(
                                'member_offer',
                                'Oferta para miembros',
                                'Describe la propuesta para miembros, si corresponde.',
                                true,
                            )}
                            <div className="sm:col-span-2">
                                {field(
                                    'terms',
                                    'Condiciones',
                                    'Incluye restricciones, disponibilidad y qué necesita saber el participante.',
                                    true,
                                )}
                            </div>
                            <p className="text-sm text-muted-foreground sm:col-span-2">
                                Garantías JP, bonus, elegibilidad y activación
                                se administran con las reglas existentes de
                                JAKAWI.
                            </p>
                        </ProposalSection>
                        <ProposalSection
                            title="4. Sedes"
                            description="Selecciona las sedes de este establecimiento que corresponden a la propuesta. Las selecciones existentes se conservan al guardar."
                        >
                            <div className="space-y-3 sm:col-span-2">
                                {locations.length ? (
                                    locations.map((location) => (
                                        <label
                                            key={location.id}
                                            className="flex min-h-11 items-center gap-3 rounded-md border border-border p-3"
                                        >
                                            <input
                                                type="checkbox"
                                                name="location_ids[]"
                                                value={location.id}
                                                checked={f.data.location_ids.includes(
                                                    location.id,
                                                )}
                                                onChange={(e) =>
                                                    f.setData(
                                                        'location_ids',
                                                        e.target.checked
                                                            ? [
                                                                  ...f.data
                                                                      .location_ids,
                                                                  location.id,
                                                              ]
                                                            : f.data.location_ids.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      location.id,
                                                              ),
                                                    )
                                                }
                                            />
                                            <span className="min-w-0 text-sm break-words">
                                                {location.name} ·{' '}
                                                {location.status === 'published'
                                                    ? 'Publicada'
                                                    : location.status ===
                                                        'draft'
                                                      ? 'Borrador'
                                                      : location.status}
                                            </span>
                                        </label>
                                    ))
                                ) : (
                                    <p className="text-sm">
                                        Aún no hay sedes. Puedes prepararlas
                                        desde Perfil y sedes.
                                    </p>
                                )}
                                {locationIds.some(
                                    (id) => !locations.some((l) => l.id === id),
                                ) && (
                                    <p role="alert" className="text-sm">
                                        Hay una sede guardada que no está
                                        disponible en este establecimiento.
                                        Contacta a JAKAWI para revisarla.
                                    </p>
                                )}
                                {locationErrors.map((error, i) => (
                                    <p
                                        role="alert"
                                        className="text-sm text-destructive"
                                        key={i}
                                    >
                                        {error}
                                    </p>
                                ))}
                                <p className="text-sm text-muted-foreground">
                                    Sedes seleccionadas:{' '}
                                    {f.data.location_ids.length}. Desmarcarlas
                                    todas elimina la selección de esta
                                    propuesta.
                                </p>
                            </div>
                        </ProposalSection>
                        {editable && (
                            <button
                                className={primaryAction}
                                disabled={f.processing}
                            >
                                Guardar borrador
                            </button>
                        )}
                    </fieldset>
                </form>
                <section className="space-y-3 rounded-md border border-border bg-surface p-5">
                    <h2 className="text-xl font-semibold">
                        5. Revisión y envío
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        JAKAWI revisa y decide la aprobación. Aprobado no
                        significa habilitado para operar.
                    </p>
                    {!unlock ? (
                        <p className="text-sm">
                            Guarda el borrador para enviarlo a revisión.
                        </p>
                    ) : editable ? (
                        <>
                            <p className="text-sm">
                                {f.isDirty
                                    ? 'Guarda los cambios antes de enviar la propuesta.'
                                    : 'El borrador guardado está listo para solicitar revisión.'}
                            </p>
                            <button
                                className={primaryAction}
                                disabled={
                                    f.isDirty || f.processing || send.processing
                                }
                                onClick={() =>
                                    send.post(`${base}/${unlock.slug}/enviar`)
                                }
                            >
                                Enviar a revisión
                            </button>
                        </>
                    ) : (
                        <p className="text-sm">
                            No hay acciones de edición o envío disponibles en
                            este estado.
                        </p>
                    )}
                    {unlock && (
                        <Link
                            className={secondaryAction}
                            href={`${base}/${unlock.slug}/participaciones`}
                        >
                            Ver participaciones
                        </Link>
                    )}
                </section>
            </div>
        </PartnerHeading>
    );
}
