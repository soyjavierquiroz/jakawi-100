import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { ImageUpload } from '@/components/media/image-upload';
import AdminLayout from '../layout';

const labels: Record<string, string> = {
    food: 'Gastronomía',
    cafe: 'Café',
    fitness: 'Actividad física',
    wellness: 'Bienestar',
    beauty: 'Belleza',
    entertainment: 'Entretenimiento',
    nightlife: 'Vida nocturna',
    shopping: 'Compras',
    services: 'Servicios',
    experiences: 'Experiencias',
    event: 'Evento',
    workshop: 'Taller',
    class: 'Clase',
    tour: 'Recorrido',
    tasting: 'Degustación',
    outdoor: 'Al aire libre',
    cultural: 'Cultural',
    social: 'Social',
    other: 'Otro',
    organizer: 'Organizador',
    host: 'Anfitrión',
    venue: 'Sede',
    sponsor: 'Patrocinador',
    participant: 'Participante',
    creator: 'Creador',
    provider: 'Proveedor',
    none: 'Pendiente de coordinación',
    whatsapp: 'WhatsApp',
    url: 'Sitio web',
    external: 'Reserva externa',
    phone: 'Teléfono',
    jakawi: 'Reserva interna JAKAWI · exige membresía',
    draft: 'Borrador',
    published: 'Publicada editorialmente',
    paused: 'Suspendida',
    archived: 'Archivada',
    legacy: 'Regla anterior · catálogo solo con sesiones',
    hidden: 'Oculta · propuesta interna',
    upcoming: 'Próximamente · sin reservas',
    active: 'Oferta disponible · requiere validación',
};
const control =
    'min-h-11 w-full min-w-0 rounded-xl border border-border bg-background px-3 py-2 text-base focus-visible:outline-2 focus-visible:outline-brand';
type Publication = {
    editorial: string;
    landing: string;
    catalog: string;
    explore: string;
    partner: string;
    operations: string;
    publicUrl: string | null;
    previewUrl: string;
    landingPreviewUrl: string | null;
    blockers: string[];
    visibilityReasons: string[];
    warnings: string[];
    landingNote: string;
};
type Choice = { id: number; name: string };
type Props = {
    item: any;
    publication: Publication | null;
    partners: Choice[];
    locations: Choice[];
    categories: string[];
    experienceTypes: string[];
};

export default function ExperienceForm({
    item,
    publication,
    partners,
    locations,
    categories,
    experienceTypes,
}: Props) {
    const page = usePage();
    const query = page.url.includes('?') ? `?${page.url.split('?')[1]}` : '';
    const [uploading, setUploading] = useState<Record<string, boolean>>({});
    const saving = useRef(false);
    const form = useForm<any>({
        slug: item?.slug ?? '',
        title: item?.title ?? '',
        short_description: item?.short_description ?? '',
        description: item?.description ?? '',
        terms: item?.terms ?? '',
        category: item?.category ?? '',
        experience_type: item?.experience_type ?? '',
        duration_minutes: item?.duration_minutes ?? '',
        regular_price: item?.regular_price ?? '',
        member_price: item?.member_price ?? '',
        currency: item?.currency ?? 'BOB',
        reservation_method: item?.reservation_method ?? 'none',
        reservation_url: item?.reservation_url ?? '',
        reservation_whatsapp: item?.reservation_whatsapp ?? '',
        reservation_phone: item?.reservation_phone ?? '',
        status: item?.status ?? 'draft',
        catalog_visibility: item?.catalog_visibility ?? 'hidden',
        operations_confirmed: item?.operations_confirmed ?? false,
        featured: item?.featured ?? false,
        sort_order: item?.sort_order ?? 0,
        published_at: item?.published_at ?? '',
        partners: (item?.partners ?? []).map((p: any) => ({
            partner_id: p.id,
            role: p.pivot.role,
            sort_order: p.pivot.sort_order,
        })),
    });
    useEffect(() => {
        const unload = (event: BeforeUnloadEvent) => {
            if (form.isDirty && !saving.current) {
                event.preventDefault();
                event.returnValue = '';
            }
        };
        window.addEventListener('beforeunload', unload);
        const stop = router.on('before', (event) => {
            if (
                form.isDirty &&
                !saving.current &&
                !window.confirm('Tienes cambios sin guardar. ¿Quieres salir?')
            )
                event.preventDefault();
        });
        return () => {
            window.removeEventListener('beforeunload', unload);
            stop();
        };
    }, [form.isDirty]);
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        saving.current = true;
        form.transform((data: any) => ({
            ...data,
            ...(item ? { _method: 'put' } : {}),
        }));
        form.post(
            item
                ? `/admin/experiences/${item.slug}${query}`
                : '/admin/experiences',
            {
                preserveScroll: true,
                onSuccess: () => form.setDefaults(),
                onFinish: () => {
                    saving.current = false;
                },
            },
        );
    };
    const field = (
        name: string,
        label: string,
        help?: string,
        type = 'text',
        options?: string[],
    ) => (
        <label
            key={name}
            className="grid content-start gap-2 text-sm font-semibold"
            htmlFor={name}
        >
            {label}
            {options ? (
                <select
                    id={name}
                    name={name}
                    className={control}
                    value={form.data[name] ?? ''}
                    onChange={(e) => form.setData(name, e.target.value)}
                    aria-invalid={!!form.errors[name]}
                    aria-describedby={`${name}-help`}
                >
                    {['category', 'experience_type'].includes(name) && (
                        <option value="">Pendiente de definir</option>
                    )}
                    {options.map((option) => (
                        <option key={option} value={option}>
                            {labels[option] ?? option}
                        </option>
                    ))}
                </select>
            ) : type === 'textarea' ? (
                <textarea
                    id={name}
                    name={name}
                    className={`${control} min-h-36 leading-6 font-normal`}
                    value={form.data[name] ?? ''}
                    onChange={(e) => form.setData(name, e.target.value)}
                    aria-invalid={!!form.errors[name]}
                    aria-describedby={`${name}-help`}
                />
            ) : (
                <input
                    id={name}
                    name={name}
                    className={control}
                    type={type}
                    min={type === 'number' ? 0 : undefined}
                    step={
                        name.includes('price')
                            ? '0.01'
                            : type === 'number'
                              ? '1'
                              : undefined
                    }
                    value={form.data[name] ?? ''}
                    onChange={(e) => form.setData(name, e.target.value)}
                    aria-invalid={!!form.errors[name]}
                    aria-describedby={`${name}-help`}
                />
            )}
            <span
                id={`${name}-help`}
                className="text-sm font-normal text-muted-foreground"
            >
                {help}
            </span>
            {form.errors[name] && (
                <span role="alert" className="text-sm font-normal text-red-600">
                    {form.errors[name]}
                </span>
            )}
        </label>
    );
    const section = (
        id: string,
        number: string,
        title: string,
        help: string,
        children: React.ReactNode,
    ) => (
        <section
            id={id}
            className="scroll-mt-5 space-y-5 rounded-2xl border border-border bg-surface p-4 sm:p-6"
        >
            <header>
                <p className="text-xs font-bold tracking-widest text-brand">
                    {number}
                </p>
                <h2 className="mt-1 text-xl font-bold">{title}</h2>
                <p className="mt-2 text-sm text-muted-foreground">{help}</p>
            </header>
            {children}
        </section>
    );
    const price = (value: string) =>
        value === '' || value == null
            ? 'Por definir'
            : `${form.data.currency === 'BOB' ? 'Bs' : form.data.currency} ${Number(value).toFixed(2)}`;
    const localWarnings = [
        form.data.short_description,
        form.data.description,
        form.data.terms,
    ].flatMap((text: string) =>
        (text ?? '').split(/[\n;|]|\s\/\s/u).flatMap((line) => {
            const memberContext =
                /para miembros|con membresía|miembro JAKAWI|precio preferencial/iu.test(
                    line,
                ) && !/sin membresía/iu.test(line);
            const expected = memberContext
                ? form.data.member_price
                : form.data.regular_price;
            return [
                ...line.matchAll(/(?:Bs\.?|BOB)\s*(\d+(?:[.,]\d{1,2})?)/giu),
            ]
                .filter(
                    (match) =>
                        expected === '' ||
                        expected == null ||
                        Number(match[1].replace(',', '.')) !== Number(expected),
                )
                .map(
                    (match) =>
                        `El texto menciona Bs ${match[1]}. Revisa si corresponde al precio preferencial y explica su elegibilidad.`,
                );
        }),
    );
    const warnings = [
        ...new Set([...(publication?.warnings ?? []), ...localWarnings]),
    ];
    const flash = page.props.flash as { success?: string } | undefined;
    return (
        <AdminLayout title={item ? 'Editar experiencia' : 'Nueva experiencia'}>
            <div className="mx-auto max-w-7xl space-y-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href={`/admin/experiences${query}`}
                        className="inline-flex min-h-11 items-center text-sm font-bold text-brand underline"
                    >
                        ← Volver al listado
                    </Link>
                    <p className="text-sm text-muted-foreground">
                        Diseña la oferta · Decide si se muestra · Confirma su
                        operación
                    </p>
                    <button
                        type="submit"
                        form="experience-editor"
                        className="min-h-11 rounded-xl bg-brand px-5 py-3 text-sm font-bold text-brand-foreground disabled:opacity-50"
                        disabled={
                            form.processing ||
                            Object.values(uploading).some(Boolean)
                        }
                    >
                        {form.processing ? 'Guardando…' : 'Guardar cambios'}
                    </button>
                </div>
                {flash?.success && (
                    <p
                        role="status"
                        className="rounded-xl border border-brand bg-brand-subtle p-4"
                    >
                        {flash.success}
                    </p>
                )}
                <nav
                    aria-label="Secciones de la experiencia"
                    className="flex flex-wrap gap-2"
                >
                    {[
                        ['identidad', 'Identidad'],
                        ['oferta', 'Oferta y precios'],
                        ['operacion', 'Operación'],
                        ['publicacion', 'Publicación'],
                    ].map(([id, label]) => (
                        <a
                            key={id}
                            href={`#${id}`}
                            className="inline-flex min-h-11 items-center rounded-full border border-border px-4 text-sm font-bold"
                        >
                            {label}
                        </a>
                    ))}
                </nav>
                <form
                    id="experience-editor"
                    onSubmit={submit}
                    className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]"
                >
                    <div className="min-w-0 space-y-5">
                        {Object.keys(form.errors).length > 0 && (
                            <div
                                role="alert"
                                className="rounded-xl border border-red-400 p-4"
                            >
                                <p className="font-bold">
                                    Revisa los campos señalados. Tus cambios
                                    siguen en el formulario.
                                </p>
                                {Object.entries(form.errors).map(
                                    ([key, message]) => (
                                        <a
                                            key={key}
                                            href={`#${key}`}
                                            className="mt-2 block text-sm underline"
                                        >
                                            {String(message)}
                                        </a>
                                    ),
                                )}
                            </div>
                        )}
                        {section(
                            'identidad',
                            '01',
                            'Identidad',
                            'Define la propuesta y quién la ofrece.',
                            <>
                                <div className="space-y-3">
                                    <h3 className="text-sm font-bold">
                                        Partners asociados
                                    </h3>
                                    {form.data.partners.length === 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            Sin Partner asociado. Selecciona uno
                                            antes de mostrar la propuesta.
                                        </p>
                                    )}
                                    {form.data.partners.map(
                                        (assignment: any, index: number) => (
                                            <div
                                                key={index}
                                                className="grid items-end gap-3 rounded-xl border border-border p-3 sm:grid-cols-[1fr_160px_auto]"
                                            >
                                                <label className="grid gap-2 text-sm">
                                                    Partner
                                                    <select
                                                        className={control}
                                                        aria-label={`Partner ${index + 1}`}
                                                        value={
                                                            assignment.partner_id
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'partners',
                                                                form.data.partners.map(
                                                                    (
                                                                        p: any,
                                                                        i: number,
                                                                    ) =>
                                                                        i ===
                                                                        index
                                                                            ? {
                                                                                  ...p,
                                                                                  partner_id:
                                                                                      Number(
                                                                                          e
                                                                                              .target
                                                                                              .value,
                                                                                      ),
                                                                              }
                                                                            : p,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <option value="">
                                                            Selecciona Partner
                                                        </option>
                                                        {partners.map((p) => (
                                                            <option
                                                                key={p.id}
                                                                value={p.id}
                                                            >
                                                                {p.name}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </label>
                                                <label className="grid gap-2 text-sm">
                                                    Función
                                                    <select
                                                        className={control}
                                                        value={assignment.role}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'partners',
                                                                form.data.partners.map(
                                                                    (
                                                                        p: any,
                                                                        i: number,
                                                                    ) =>
                                                                        i ===
                                                                        index
                                                                            ? {
                                                                                  ...p,
                                                                                  role: e
                                                                                      .target
                                                                                      .value,
                                                                              }
                                                                            : p,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        {[
                                                            'organizer',
                                                            'host',
                                                            'venue',
                                                            'sponsor',
                                                            'participant',
                                                            'creator',
                                                            'provider',
                                                            'other',
                                                        ].map((role) => (
                                                            <option
                                                                key={role}
                                                                value={role}
                                                            >
                                                                {labels[role]}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </label>
                                                <button
                                                    type="button"
                                                    className="min-h-11 px-2 text-sm underline"
                                                    onClick={() =>
                                                        form.setData(
                                                            'partners',
                                                            form.data.partners.filter(
                                                                (
                                                                    _: any,
                                                                    i: number,
                                                                ) =>
                                                                    i !== index,
                                                            ),
                                                        )
                                                    }
                                                    aria-label={`Quitar asociación ${index + 1}`}
                                                >
                                                    Quitar
                                                </button>
                                                {form.errors[
                                                    `partners.${index}.partner_id`
                                                ] && (
                                                    <p
                                                        role="alert"
                                                        className="text-sm text-red-600"
                                                    >
                                                        {
                                                            form.errors[
                                                                `partners.${index}.partner_id`
                                                            ]
                                                        }
                                                    </p>
                                                )}
                                            </div>
                                        ),
                                    )}
                                    <button
                                        type="button"
                                        className="min-h-11 text-sm font-bold text-brand underline"
                                        onClick={() =>
                                            form.setData('partners', [
                                                ...form.data.partners,
                                                {
                                                    partner_id: '',
                                                    role: 'organizer',
                                                    sort_order:
                                                        form.data.partners
                                                            .length,
                                                },
                                            ])
                                        }
                                    >
                                        + Asociar Partner
                                    </button>
                                </div>
                                {field('title', 'Título comercial')}
                                <details className="rounded-xl border border-border p-3">
                                    <summary className="cursor-pointer text-sm font-semibold">
                                        Dirección de la experiencia
                                    </summary>
                                    <div className="mt-3">
                                        {field(
                                            'slug',
                                            'Identificador del enlace',
                                            'Opcional al crear: generamos el enlace desde el título. Cambiarlo después modifica el enlace existente.',
                                        )}
                                    </div>
                                </details>
                                {field(
                                    'short_description',
                                    'Descripción corta',
                                    'Una frase que explique por qué participar.',
                                )}
                                {field(
                                    'description',
                                    'Descripción completa y qué incluye',
                                    'Explica qué recibe el participante. Señala lo que sigue siendo una propuesta.',
                                    'textarea',
                                )}
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {field(
                                        'category',
                                        'Categoría',
                                        undefined,
                                        'text',
                                        categories,
                                    )}
                                    {field(
                                        'experience_type',
                                        'Tipo de experiencia',
                                        undefined,
                                        'text',
                                        experienceTypes,
                                    )}
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {['image', 'cover'].map((name) => (
                                        <ImageUpload
                                            key={name}
                                            name={name}
                                            label={
                                                name === 'image'
                                                    ? 'Imagen de catálogo'
                                                    : 'Portada'
                                            }
                                            currentUrl={item?.[`${name}Url`]}
                                            error={form.errors[name]}
                                            onBusy={(busy) =>
                                                setUploading((value) => ({
                                                    ...value,
                                                    [name]: busy,
                                                }))
                                            }
                                            onChange={(token, remove) =>
                                                form.setData((data: any) => ({
                                                    ...data,
                                                    [`${name}_upload`]: token,
                                                    [`${name}_remove`]: remove,
                                                }))
                                            }
                                        />
                                    ))}
                                </div>
                            </>,
                        )}
                        {section(
                            'oferta',
                            '02',
                            'Oferta y precios',
                            'Los importes estructurados son la fuente del precio. No son un precio habitual acreditado del Partner.',
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {field(
                                        'regular_price',
                                        'Precio sin membresía',
                                        'Ej.: 75. Precio para el flujo externo sin membresía; las reservas internas siguen exigiendo membresía.',
                                        'number',
                                    )}
                                    {field(
                                        'member_price',
                                        'Precio con membresía · opcional',
                                        'Ej.: 60. Vacío = sin precio preferencial. No debe superar el precio base.',
                                        'number',
                                    )}
                                </div>
                                {field(
                                    'currency',
                                    'Moneda',
                                    'Código de 3 letras, por ejemplo BOB. No convierte importes automáticamente.',
                                )}
                                <aside
                                    className="rounded-xl bg-brand-subtle p-4"
                                    aria-label="Vista previa de precios"
                                >
                                    <h3 className="font-bold">
                                        Así se presentan los precios
                                    </h3>
                                    <p className="mt-3 text-sm">
                                        {form.data.reservation_method ===
                                        'jakawi'
                                            ? 'Precio base · reserva con membresía'
                                            : 'Sin membresía'}
                                        <strong className="mt-1 block text-2xl">
                                            {price(form.data.regular_price)}
                                        </strong>
                                    </p>
                                    {form.data.member_price !== '' &&
                                    form.data.member_price != null ? (
                                        <p className="mt-3 text-sm">
                                            {form.data.reservation_method ===
                                            'jakawi'
                                                ? 'Con membresía activa'
                                                : 'Precio preferencial previsto · activación y verificación pendientes'}
                                            <strong className="mt-1 block text-xl">
                                                {price(form.data.member_price)}
                                            </strong>
                                        </p>
                                    ) : (
                                        <p className="mt-3 text-sm">
                                            Sin precio preferencial por
                                            membresía.
                                        </p>
                                    )}
                                    <p className="mt-3 text-sm">
                                        {form.data.reservation_method ===
                                        'jakawi'
                                            ? 'La reserva interna exige cuenta verificada y membresía activa. No hay cobro de la experiencia en JAKAWI.'
                                            : 'La coordinación externa no verifica membresías. Un precio preferencial externo bloquea la activación hasta implementar esa verificación.'}
                                    </p>
                                </aside>
                                {field(
                                    'terms',
                                    'Condiciones comerciales y de participación',
                                    'Incluye forma de pago, vigencia, límites y condiciones. En el flujo externo son condiciones que debe controlar el Partner; el texto no las aplica automáticamente.',
                                    'textarea',
                                )}
                                {warnings.length > 0 && (
                                    <aside
                                        role="status"
                                        className="space-y-2 rounded-xl border border-amber-500 p-4"
                                    >
                                        <h3 className="font-bold">
                                            Revisar coherencia comercial
                                        </h3>
                                        {warnings.map((warning) => (
                                            <p
                                                key={warning}
                                                className="text-sm"
                                            >
                                                {warning}
                                            </p>
                                        ))}
                                        <p className="text-sm font-semibold">
                                            Puedes guardar la propuesta. Revisa
                                            el texto antes de presentarla; las
                                            discrepancias bloquean la
                                            activación.
                                        </p>
                                    </aside>
                                )}
                            </>,
                        )}
                        {section(
                            'operacion',
                            '03',
                            'Operación',
                            'Los datos físicos pueden quedar pendientes mientras preparas la experiencia.',
                            <>
                                {field(
                                    'duration_minutes',
                                    'Duración total de la experiencia',
                                    'En minutos. Incluye toda la actividad; no equivale necesariamente a la duración de la explicación guiada.',
                                    'number',
                                )}
                                {field(
                                    'reservation_method',
                                    'Método de coordinación / reserva',
                                    'Seleccionar un método no habilita reservas por sí solo. Puedes dejar pendiente el destino hasta confirmar la operación.',
                                    'text',
                                    [
                                        'none',
                                        'whatsapp',
                                        'url',
                                        'external',
                                        'phone',
                                        'jakawi',
                                    ],
                                )}
                                {form.data.reservation_method === 'whatsapp' &&
                                    field(
                                        'reservation_whatsapp',
                                        'WhatsApp confirmado',
                                        'Número internacional, por ejemplo +591 seguido del número real. Vacío = coordinación pendiente.',
                                        'tel',
                                    )}
                                {['url', 'external'].includes(
                                    form.data.reservation_method,
                                ) &&
                                    field(
                                        'reservation_url',
                                        'Destino de reserva confirmado',
                                        'URL real del Partner (https://…). Vacío = pendiente.',
                                        'url',
                                    )}
                                {form.data.reservation_method === 'phone' &&
                                    field(
                                        'reservation_phone',
                                        'Teléfono confirmado',
                                        'Número internacional real. Vacío = pendiente.',
                                        'tel',
                                    )}
                                <p className="rounded-xl border border-border p-4 text-sm text-muted-foreground">
                                    Pago directo y límites: descríbelos en las
                                    condiciones. JAKAWI no cobra estas
                                    experiencias. La reserva interna registra
                                    solicitudes y asistencia; el flujo externo
                                    no registra canjes, pagos ni uso por cuenta.
                                </p>
                                <label className="flex items-start gap-3 rounded-xl border border-border p-4 text-sm">
                                    <input
                                        type="checkbox"
                                        className="mt-1 size-5 shrink-0"
                                        checked={form.data.operations_confirmed}
                                        onChange={(e) =>
                                            form.setData(
                                                'operations_confirmed',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    <span>
                                        <strong>
                                            Operación confirmada con el Partner
                                        </strong>
                                        <span className="mt-1 block text-muted-foreground">
                                            He verificado fechas, lugar,
                                            capacidad, responsable, canal y
                                            condiciones de entrega. Esta
                                            confirmación no publica ni activa la
                                            experiencia por sí sola.
                                        </span>
                                    </span>
                                </label>
                                <h3 className="font-bold">
                                    Sesiones y horarios reales
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    {item?.sessions?.length
                                        ? `${item.sessions.length} sesiones configuradas. Revisa fechas, sede, capacidad y responsable abajo.`
                                        : 'No hay sesiones configuradas. No necesitas crearlas para mostrar una propuesta Próximamente.'}
                                </p>
                                {item ? (
                                    <p className="text-sm">
                                        <a
                                            className="font-bold text-brand underline"
                                            href="#sesiones"
                                        >
                                            Administrar sesiones confirmadas ↓
                                        </a>
                                    </p>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Guarda primero la experiencia para
                                        configurar sesiones.
                                    </p>
                                )}
                            </>,
                        )}
                    </div>
                    <aside
                        id="publicacion"
                        className="min-w-0 space-y-5 rounded-2xl border border-border bg-surface p-4 sm:p-6 xl:sticky xl:top-5"
                    >
                        <header>
                            <p className="text-xs font-bold tracking-widest text-brand">
                                04
                            </p>
                            <h2 className="mt-1 text-xl font-bold">
                                Publicación
                            </h2>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Mostrar una propuesta no confirma su
                                disponibilidad.
                            </p>
                        </header>
                        {publication && (
                            <>
                                <p className="text-xs font-bold text-muted-foreground uppercase">
                                    Situación guardada
                                </p>
                                <dl className="space-y-3 text-sm">
                                    {[
                                        ['Experience', publication.editorial],
                                        ['Landing', publication.landing],
                                        ['Catálogo', publication.catalog],
                                        ['Explorar', publication.explore],
                                        ['Partner', publication.partner],
                                        ['Operación', publication.operations],
                                    ].map(([key, value]) => (
                                        <div key={key}>
                                            <dt className="text-muted-foreground">
                                                {key}
                                            </dt>
                                            <dd className="mt-1 font-bold">
                                                {value}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                                <p className="text-sm text-muted-foreground">
                                    {publication.landingNote}
                                </p>
                            </>
                        )}
                        {field(
                            'status',
                            'Estado editorial',
                            'Preparar para presentación no confirma operación.',
                            'text',
                            ['draft', 'published', 'paused', 'archived'],
                        )}
                        {field(
                            'catalog_visibility',
                            'Visibilidad autorizada',
                            'Próximamente permite descubrir la propuesta sin fechas ni reservas.',
                            'text',
                            item?.catalog_visibility === 'legacy'
                                ? ['legacy', 'hidden', 'upcoming', 'active']
                                : ['hidden', 'upcoming', 'active'],
                        )}
                        {form.data.catalog_visibility === 'upcoming' && (
                            <p
                                role="status"
                                className="rounded-xl bg-brand-subtle p-3 text-sm font-semibold"
                            >
                                Se mostrará como Próximamente. No permitirá
                                reservar, pagar ni canjear. La landing debe
                                publicarse por separado.
                            </p>
                        )}
                        {publication &&
                            [
                                ...publication.visibilityReasons,
                                ...publication.blockers,
                            ].length > 0 && (
                                <details
                                    open
                                    className="rounded-xl border border-border p-3"
                                >
                                    <summary className="cursor-pointer text-sm font-bold">
                                        Qué falta para mostrar o activar
                                    </summary>
                                    <ul className="mt-3 list-disc space-y-2 pl-4 text-sm text-muted-foreground">
                                        {[
                                            ...new Set([
                                                ...publication.visibilityReasons,
                                                ...publication.blockers,
                                            ]),
                                        ].map((reason) => (
                                            <li key={reason}>{reason}</li>
                                        ))}
                                    </ul>
                                </details>
                            )}
                        {item && (
                            <div className="grid gap-2 text-sm">
                                <Link
                                    href={publication?.previewUrl ?? '#'}
                                    className="inline-flex min-h-11 items-center font-bold text-brand underline"
                                >
                                    Previsualizar ficha guardada
                                </Link>
                                {publication?.landingPreviewUrl && (
                                    <Link
                                        href={publication.landingPreviewUrl}
                                        className="inline-flex min-h-11 items-center font-bold text-brand underline"
                                    >
                                        Previsualizar landing · Admin
                                    </Link>
                                )}
                                <Link
                                    href={`/admin/experiencias/${item.slug}/landings`}
                                    className="inline-flex min-h-11 items-center font-bold text-brand underline"
                                >
                                    Editar / publicar presentación comercial
                                </Link>
                                {publication?.publicUrl && (
                                    <a
                                        href={publication.publicUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex min-h-11 items-center font-bold underline"
                                    >
                                        Abrir vista pública ↗
                                    </a>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    La previsualización usa los datos guardados.
                                    Guarda tus cambios antes de abrirla.
                                </p>
                            </div>
                        )}
                        <details className="text-sm">
                            <summary className="min-h-11 cursor-pointer font-semibold">
                                Orden en catálogo
                            </summary>
                            <label className="my-3 flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    checked={form.data.featured}
                                    onChange={(e) =>
                                        form.setData(
                                            'featured',
                                            e.target.checked,
                                        )
                                    }
                                />
                                Destacada
                            </label>
                            {field(
                                'sort_order',
                                'Prioridad',
                                'Menor número aparece antes.',
                                'number',
                            )}
                        </details>
                        <button
                            type="submit"
                            className="min-h-12 w-full rounded-xl bg-brand px-4 py-3 font-bold text-brand-foreground disabled:opacity-50"
                            disabled={
                                form.processing ||
                                Object.values(uploading).some(Boolean)
                            }
                        >
                            {form.processing
                                ? 'Guardando…'
                                : form.data.status === 'draft'
                                  ? 'Guardar borrador'
                                  : form.data.catalog_visibility === 'upcoming'
                                    ? 'Guardar y mostrar Próximamente'
                                    : form.data.catalog_visibility === 'active'
                                      ? 'Validar y activar experiencia'
                                      : 'Guardar propuesta'}
                        </button>
                        <p
                            aria-live="polite"
                            className="text-center text-xs text-muted-foreground"
                        >
                            {form.isDirty
                                ? 'Tienes cambios sin guardar'
                                : 'Sin cambios pendientes'}
                        </p>
                    </aside>
                </form>
                {item && (
                    <section
                        id="sesiones"
                        className="scroll-mt-5 space-y-4 rounded-2xl border border-border bg-surface p-4 sm:p-6"
                    >
                        <h2 className="text-xl font-bold">
                            Sesiones confirmadas
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Fechas en hora de Bolivia (UTC−4). Guarda los
                            cambios de la propuesta antes de editar sesiones.
                        </p>
                        {(item.sessions ?? []).map((session: any) => (
                            <SessionEditor
                                key={session.id}
                                session={session}
                                experience={item}
                                locations={locations}
                                disabled={form.isDirty}
                            />
                        ))}
                        <SessionEditor
                            key={`new-${item.updated_at}`}
                            experience={item}
                            locations={locations}
                            disabled={form.isDirty}
                        />
                    </section>
                )}
            </div>
        </AdminLayout>
    );
}

function SessionEditor({
    session,
    experience,
    locations,
    disabled,
}: {
    session?: any;
    experience: any;
    locations: Choice[];
    disabled: boolean;
}) {
    const local = (value?: string) =>
        value
            ? new Date(new Date(value).getTime() - 4 * 3600000)
                  .toISOString()
                  .slice(0, 16)
            : '';
    const form = useForm<any>({
        starts_at: local(session?.starts_at),
        ends_at: local(session?.ends_at),
        location_id: session?.location_id ?? '',
        reservation_partner_id: session?.reservation_partner_id ?? '',
        capacity: session?.capacity ?? '',
        status: session?.status ?? 'scheduled',
        venue_label: session?.venue_label ?? '',
    });
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            starts_at: data.starts_at ? `${data.starts_at}:00-04:00` : '',
            ends_at: data.ends_at ? `${data.ends_at}:00-04:00` : '',
        }));
        if (session)
            form.put(
                `/admin/experiences/${experience.slug}/sessions/${session.id}`,
                { preserveScroll: true },
            );
        else
            form.post(`/admin/experiences/${experience.slug}/sessions`, {
                preserveScroll: true,
            });
    };
    return (
        <details className="rounded-xl border border-border p-4">
            <summary className="min-h-11 cursor-pointer text-sm font-bold">
                {session
                    ? `${local(session.starts_at).replace('T', ' · ')} · ${session.status === 'scheduled' ? 'Programada' : 'Cancelada'}`
                    : '+ Agregar sesión real'}
            </summary>
            <form onSubmit={submit} className="mt-4 space-y-4">
                <fieldset
                    disabled={disabled || form.processing}
                    className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    {['starts_at', 'ends_at', 'capacity', 'venue_label'].map(
                        (name) => (
                            <label key={name} className="grid gap-2 text-sm">
                                {
                                    {
                                        starts_at: 'Inicio · hora Bolivia',
                                        ends_at: 'Fin · hora Bolivia',
                                        capacity: 'Capacidad confirmada',
                                        venue_label:
                                            'Lugar / punto de encuentro',
                                    }[name]
                                }
                                <input
                                    required={name === 'starts_at'}
                                    className={control}
                                    type={
                                        name.endsWith('_at')
                                            ? 'datetime-local'
                                            : name === 'capacity'
                                              ? 'number'
                                              : 'text'
                                    }
                                    min={name === 'capacity' ? 1 : undefined}
                                    value={form.data[name]}
                                    onChange={(e) =>
                                        form.setData(name, e.target.value)
                                    }
                                />
                            </label>
                        ),
                    )}
                    <label className="grid gap-2 text-sm">
                        Sede
                        <select
                            className={control}
                            value={form.data.location_id}
                            onChange={(e) =>
                                form.setData('location_id', e.target.value)
                            }
                        >
                            <option value="">Pendiente / lugar indicado</option>
                            {locations.map((location) => (
                                <option key={location.id} value={location.id}>
                                    {location.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-2 text-sm">
                        Partner responsable de reservas
                        <select
                            className={control}
                            value={form.data.reservation_partner_id}
                            onChange={(e) =>
                                form.setData(
                                    'reservation_partner_id',
                                    e.target.value,
                                )
                            }
                        >
                            <option value="">Pendiente</option>
                            {experience.partners.map((partner: Choice) => (
                                <option key={partner.id} value={partner.id}>
                                    {partner.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-2 text-sm">
                        Estado
                        <select
                            className={control}
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            <option value="scheduled">Programada</option>
                            <option value="cancelled">Cancelada</option>
                        </select>
                    </label>
                    <button className="min-h-11 rounded-xl bg-brand px-4 font-bold text-brand-foreground">
                        {session
                            ? 'Guardar sesión'
                            : 'Agregar sesión confirmada'}
                    </button>
                </fieldset>
                {disabled && (
                    <p className="text-sm text-muted-foreground">
                        Guarda primero los cambios de la propuesta.
                    </p>
                )}
                {Object.entries(form.errors).map(([key, message]) => (
                    <p role="alert" key={key} className="text-sm text-red-600">
                        {String(message)}
                    </p>
                ))}
            </form>
        </details>
    );
}
