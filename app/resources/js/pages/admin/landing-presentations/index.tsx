import { LandingJsonControls } from '@/components/marketing/LandingJsonControls';
import { LandingVideoFields } from '@/components/media/jakawi-vsl-player/LandingVideoFields';
import type { LandingVideoConfig } from '@/components/media/jakawi-vsl-player/LandingVideo';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../layout';

type SubjectType = 'BENEFIT' | 'EXPERIENCE' | 'CHALLENGE';
type Subject = {
    id: number;
    type: SubjectType | 'UNLOCK';
    title: string;
    slug: string;
    defaults: Record<string, string>;
    truth: { label: string; value: string }[];
    aiBrief: Record<string, unknown> | null;
    heroUrl?: string | null;
};
type Presentation = {
    id: number;
    name: string;
    slug: string;
    status: 'DRAFT' | 'PUBLISHED' | 'ARCHIVED';
    default_scope: string;
    publish_scope: string;
    vsl?: LandingVideoConfig | null;
    media_mode?: string | null;
    marketing_theme?: string | null;
    [key: string]: unknown;
};
const empty = {
    name: '',
    slug: '',
    campaign_key: '',
    eyebrow: '',
    headline: '',
    headline_emphasis: '',
    subheadline: '',
    hero_path: '',
    hero_alt: '',
    reward_display_override: '',
    final_cta_headline: '',
    final_cta_description: '',
    acquisition_headline: '',
    acquisition_supporting_copy: '',
    hero_layout: 'OFFER_FIRST',
    density: 'STANDARD',
    template_key: 'OFFER_CONVERSION',
    marketing_theme: 'LIGHT_CONVERSION',
    media_mode: 'IMAGE',
    default_scope: 'NONE',
};
const fieldLabels: Record<string, string> = {
    name: 'Nombre interno',
    slug: 'Slug único',
    headline: 'Headline',
    headline_emphasis: 'Texto destacado',
    subheadline: 'Descripción editorial',
    media_mode: 'Media',
    vsl: 'Video',
    final_cta_headline: 'Titular final',
    final_cta_description: 'Texto de apoyo final',
};
const signature = (
    form: Record<string, string>,
    video: LandingVideoConfig | null,
    subject: Subject | null,
) => JSON.stringify([form, video, subject?.type, subject?.id]);
const control =
    'w-full min-w-0 rounded border border-border bg-surface p-3 font-normal';
const button =
    'rounded-[var(--radius-control)] bg-brand px-4 py-3 text-sm font-extrabold text-brand-foreground';
export default function Index({
    subject,
    subjectChoices = {},
    autoCreate = false,
    presentations,
    baseUrl = '/admin/landings',
    productUrl = '/admin',
}: {
    subject: Subject | null;
    subjectChoices?: Partial<
        Record<SubjectType, { id: number; title: string; slug: string }[]>
    >;
    autoCreate?: boolean;
    presentations: Presentation[];
    baseUrl?: string;
    productUrl?: string;
}) {
    const { errors: pageErrors = {} } = usePage().props as {
        errors: Record<string, string>;
    };
    const [type, setType] = useState<SubjectType>(
        subject?.type === 'UNLOCK' ? 'BENEFIT' : (subject?.type ?? 'BENEFIT'),
    );
    const [selected, setSelected] = useState(String(subject?.id ?? ''));
    const [formSubject, setFormSubject] = useState(subject);
    const [selectionError, setSelectionError] = useState('');
    const [editing, setEditing] = useState<Presentation | null>(null);
    const [open, setOpen] = useState(autoCreate);
    const [video, setVideo] = useState<LandingVideoConfig | null>(null);
    const [form, setForm] = useState<Record<string, string>>({
        ...empty,
        ...subject?.defaults,
    });
    const [preview, setPreview] = useState<Presentation | null>(null);
    const [baseline, setBaseline] = useState(() =>
        signature({ ...empty, ...subject?.defaults }, null, subject),
    );
    const [saveErrors, setSaveErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState(false);
    const [publishWarning, setPublishWarning] = useState('');
    const [previewRevision, setPreviewRevision] = useState(0);
    const errors = open ? saveErrors : pageErrors;
    const dirty = signature(form, video, formSubject) !== baseline;
    const previewDirty =
        open && dirty && (!editing || preview?.id === editing.id);
    const currentMediaUrl =
        editing &&
        formSubject?.id === subject?.id &&
        formSubject?.type === subject?.type &&
        form.hero_path === String(editing.hero_path ?? '')
            ? editing.editorHeroUrl
            : !form.hero_path ||
                form.hero_path === formSubject?.defaults.hero_path
              ? formSubject?.heroUrl
              : null;
    const publish = (item: Presentation) => {
        if (open && dirty) {
            setPublishWarning('GUARDA TUS CAMBIOS ANTES DE PUBLICAR.');
            return;
        }
        router.post(`${baseUrl}/${item.slug}/publish`);
    };
    const [width, setWidth] = useState(390);
    const [clone, setClone] = useState<Presentation | null>(null);
    const [duplicate, setDuplicate] = useState({
        name: '',
        slug: '',
        campaign_key: '',
    });
    const offer = subject?.type !== 'UNLOCK';
    const begin = (item: Presentation | null) => {
        setType(
            subject?.type === 'UNLOCK'
                ? 'BENEFIT'
                : (subject?.type ?? 'BENEFIT'),
        );
        setFormSubject(subject);
        setSelectionError('');
        setEditing(item);
        setVideo(item?.vsl ?? null);
        setOpen(true);
        const nextForm = item
            ? Object.fromEntries(
                  Object.keys(empty).map((key) => [
                      key,
                      String(
                          key === 'default_scope'
                              ? (item.publish_scope ?? item.default_scope)
                              : (item[key] ?? empty[key as keyof typeof empty]),
                      ),
                  ]),
              )
            : { ...empty, ...subject?.defaults };
        setForm(nextForm);
        setBaseline(signature(nextForm, item?.vsl ?? null, subject));
        setSaveErrors({});
        setSaved(false);
        setPublishWarning('');
    };
    const field = (key: string, label: string, multiline = false) => (
        <label className="flex min-w-0 flex-col gap-1 text-sm font-bold">
            {label}
            {multiline ? (
                <textarea
                    name={key}
                    aria-invalid={!!errors[key]}
                    className={`${control} min-h-24 ${errors[key] ? 'border-danger' : ''}`}
                    value={form[key] ?? ''}
                    onChange={(e) =>
                        setForm((f) => ({ ...f, [key]: e.target.value }))
                    }
                />
            ) : (
                <input
                    name={key}
                    aria-invalid={!!errors[key]}
                    className={`${control} ${errors[key] ? 'border-danger' : ''}`}
                    value={form[key] ?? ''}
                    onChange={(e) =>
                        setForm((f) => ({ ...f, [key]: e.target.value }))
                    }
                />
            )}{' '}
            {errors[key] && <span className="text-danger">{errors[key]}</span>}
        </label>
    );
    const section = (label: string) => (
        <h3 className="col-span-full mt-5 border-b border-border pb-2 text-lg font-extrabold">
            {label}
        </h3>
    );
    const defaultItem = presentations.find(
        (item) => item.status === 'PUBLISHED' && item.default_scope !== 'NONE',
    );
    return (
        <AdminLayout
            title={
                subject ? `Landings: ${subject.title}` : 'Marketing Landings'
            }
        >
            <Head title="Marketing Landings" />
            {subject && (
                <Link
                    href={productUrl}
                    className="text-sm font-bold text-brand underline"
                >
                    ← Volver al producto
                </Link>
            )}
            {(!subject || offer) && (
                <section className="mt-5 grid min-w-0 gap-4 rounded border border-border bg-surface p-4 sm:grid-cols-3">
                    <h2 className="col-span-full text-xl font-extrabold">
                        Nueva Marketing Landing
                    </h2>
                    <label className="text-sm font-bold">
                        Tipo de producto
                        <select
                            aria-label="Tipo de producto"
                            className={control}
                            value={type}
                            onChange={(e) => {
                                setType(e.target.value as SubjectType);
                                setSelected('');
                            }}
                        >
                            <option value="BENEFIT">Benefit</option>
                            <option value="EXPERIENCE">Experience</option>
                            <option value="CHALLENGE">Challenge</option>
                        </select>
                    </label>
                    <label className="text-sm font-bold">
                        Producto
                        <select
                            aria-label="Producto"
                            className={control}
                            value={selected}
                            onChange={(e) => setSelected(e.target.value)}
                        >
                            <option value="">Seleccionar…</option>
                            {subjectChoices[type]?.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.title}
                                </option>
                            ))}
                        </select>
                    </label>
                    <button
                        disabled={!selected}
                        className={`${button} self-end disabled:opacity-50`}
                        onClick={() =>
                            router.get('/admin/landings', {
                                subject_type: type,
                                subject_id: selected,
                                create: 1,
                            })
                        }
                    >
                        CONTINUAR
                    </button>
                    {errors.subject_id && (
                        <p className="col-span-full text-danger">
                            {errors.subject_id}
                        </p>
                    )}
                </section>
            )}
            {subject && (
                <>
                    <section className="mt-6 rounded border border-border bg-surface p-5">
                        <h2 className="font-extrabold">
                            Presentación de navegación
                        </h2>
                        <p className="mt-2 text-sm">
                            Visitantes:{' '}
                            {defaultItem
                                ? defaultItem.name
                                : 'Ficha del producto'}
                            <br />
                            Usuarios con sesión:{' '}
                            {defaultItem?.default_scope === 'ALL'
                                ? defaultItem.name
                                : 'Ficha del producto'}
                        </p>
                        {defaultItem && (
                            <button
                                className="mt-4 underline"
                                onClick={() =>
                                    router.post(`${baseUrl}/default-product`)
                                }
                            >
                                VOLVER A FICHA DEL PRODUCTO
                            </button>
                        )}
                    </section>
                    <div className="mt-7 flex flex-wrap items-center justify-between gap-3">
                        <h2 className="text-xl font-extrabold">Landings</h2>
                        <button className={button} onClick={() => begin(null)}>
                            CREAR LANDING
                        </button>
                    </div>
                    <div className="mt-4 space-y-4">
                        {presentations.map((item) => (
                            <article
                                key={item.id}
                                className="min-w-0 rounded border border-border bg-surface p-5"
                            >
                                <h3 className="text-lg font-extrabold">
                                    {item.name}
                                </h3>
                                <p className="mt-1 text-sm break-all text-muted-foreground">
                                    {item.status} · {item.default_scope} · /l/
                                    {item.slug}
                                    {item.campaign_key
                                        ? ` · ${item.campaign_key}`
                                        : ''}
                                </p>
                                <div className="mt-4 flex flex-wrap gap-4 text-sm font-bold">
                                    <button
                                        className="underline"
                                        onClick={() => begin(item)}
                                    >
                                        Editar
                                    </button>
                                    <button
                                        className="underline"
                                        onClick={() => {
                                            setPreview(item);
                                            setWidth(390);
                                        }}
                                    >
                                        Preview
                                    </button>
                                    <button
                                        className="underline"
                                        onClick={() => {
                                            setClone(item);
                                            setDuplicate({
                                                name: `${item.name} — copia`,
                                                slug: `${item.slug}-copia`,
                                                campaign_key: String(
                                                    item.campaign_key ?? '',
                                                ),
                                            });
                                        }}
                                    >
                                        DUPLICAR
                                    </button>
                                    {item.status === 'PUBLISHED' ? (
                                        <>
                                            <Link
                                                href={`/l/${item.slug}`}
                                                className="underline"
                                            >
                                                Vista pública
                                            </Link>
                                            <button
                                                className="underline"
                                                onClick={() =>
                                                    router.post(
                                                        `${baseUrl}/${item.slug}/unpublish`,
                                                    )
                                                }
                                            >
                                                DESPUBLICAR
                                            </button>
                                            <button
                                                className="underline"
                                                onClick={() =>
                                                    router.post(
                                                        `${baseUrl}/${item.slug}/default`,
                                                        {
                                                            default_scope:
                                                                'GUESTS',
                                                        },
                                                    )
                                                }
                                            >
                                                USAR PARA VISITANTES
                                            </button>
                                            <button
                                                className="underline"
                                                onClick={() =>
                                                    router.post(
                                                        `${baseUrl}/${item.slug}/default`,
                                                        {
                                                            default_scope:
                                                                'ALL',
                                                        },
                                                    )
                                                }
                                            >
                                                USAR PARA TODOS
                                            </button>
                                        </>
                                    ) : (
                                        <button
                                            className="underline"
                                            onClick={() => publish(item)}
                                        >
                                            PUBLICAR
                                        </button>
                                    )}
                                    {item.status !== 'ARCHIVED' && (
                                        <button
                                            className="underline"
                                            onClick={() =>
                                                router.post(
                                                    `${baseUrl}/${item.slug}/archive`,
                                                )
                                            }
                                        >
                                            ARCHIVAR
                                        </button>
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>
                    {publishWarning && (
                        <p role="alert" className="mt-4 text-danger">
                            {publishWarning}
                        </p>
                    )}
                    {pageErrors.presentation && (
                        <p role="alert" className="mt-4 text-danger">
                            {pageErrors.presentation}
                        </p>
                    )}
                    {open && (
                        <form
                            className="mt-8 grid max-w-4xl min-w-0 gap-4 rounded border border-border bg-surface p-4 sm:grid-cols-2 sm:p-6"
                            onSubmit={(e) => {
                                e.preventDefault();
                                if (saving) return;
                                const payload: Record<string, unknown> = {
                                    ...Object.fromEntries(
                                        Object.entries(form).map(
                                            ([key, value]) => [
                                                key,
                                                value.trim() || null,
                                            ],
                                        ),
                                    ),
                                    vsl: video,
                                };
                                if (offer && formSubject) {
                                    payload.subject_type = formSubject.type;
                                    payload.subject_id = formSubject.id;
                                }
                                if (!offer)
                                    for (const key of [
                                        'hero_layout',
                                        'density',
                                        'template_key',
                                        'marketing_theme',
                                        'media_mode',
                                        'headline_emphasis',
                                        'final_cta_description',
                                    ])
                                        delete payload[key];
                                const submittedBaseline = signature(
                                    form,
                                    video,
                                    formSubject,
                                );
                                const submittedSlug = form.slug.trim();
                                setSaving(true);
                                setSaved(false);
                                setSaveErrors({});
                                setPublishWarning('');
                                const options = {
                                    preserveState: true,
                                    preserveScroll: true,
                                    onSuccess: (page: {
                                        props: Record<string, unknown>;
                                    }) => {
                                        const items = page.props
                                            .presentations as Presentation[];
                                        const persisted = items.find((item) =>
                                            editing
                                                ? item.id === editing.id
                                                : item.slug === submittedSlug,
                                        );
                                        if (!persisted) {
                                            setSaveErrors({
                                                presentation:
                                                    'No se pudo confirmar la versión guardada. Recarga la lista antes de continuar.',
                                            });
                                            return;
                                        }
                                        setEditing(persisted);
                                        setBaseline(submittedBaseline);
                                        setSaved(true);
                                        setPreview(persisted);
                                        setPreviewRevision(
                                            (revision) => revision + 1,
                                        );
                                    },
                                    onError: (
                                        errors: Record<string, string>,
                                    ) => {
                                        setSaveErrors(errors);
                                        requestAnimationFrame(() => {
                                            const summary =
                                                document.getElementById(
                                                    'landing-save-errors',
                                                );
                                            summary?.scrollIntoView({
                                                block: 'center',
                                                behavior: 'smooth',
                                            });
                                            const field =
                                                document.querySelector<HTMLElement>(
                                                    `[name="${Object.keys(errors)[0]}"]`,
                                                );
                                            if (field)
                                                field.focus({
                                                    preventScroll: true,
                                                });
                                            else
                                                summary?.focus({
                                                    preventScroll: true,
                                                });
                                        });
                                    },
                                    onFinish: () => setSaving(false),
                                };
                                editing
                                    ? router.put(
                                          `${baseUrl}/${editing.slug}`,
                                          payload as Parameters<
                                              typeof router.put
                                          >[1],
                                          options,
                                      )
                                    : router.post(
                                          baseUrl,
                                          payload as Parameters<
                                              typeof router.post
                                          >[1],
                                          options,
                                      );
                            }}
                        >
                            <h2 className="col-span-full text-xl font-extrabold">
                                {editing ? 'Editar landing' : 'Nueva landing'}
                            </h2>
                            <div
                                className="col-span-full flex flex-wrap items-center gap-3 text-sm font-bold"
                                aria-live="polite"
                            >
                                <span>
                                    {editing?.status ?? 'DRAFT'} · Audience:{' '}
                                    {form.default_scope}
                                </span>
                                <span
                                    className={
                                        dirty
                                            ? 'rounded border border-amber-500 bg-amber-50 px-3 py-2 text-amber-900'
                                            : 'text-muted-foreground'
                                    }
                                >
                                    {dirty
                                        ? '● CAMBIOS SIN GUARDAR'
                                        : saved
                                          ? '✓ CAMBIOS GUARDADOS'
                                          : editing
                                            ? 'VERSIÓN GUARDADA'
                                            : 'BORRADOR SIN GUARDAR'}
                                </span>
                                {saved && !dirty && (
                                    <span role="status">
                                        ✓ LANDING GUARDADA
                                    </span>
                                )}
                            </div>
                            {!!Object.keys(saveErrors).length && (
                                <div
                                    id="landing-save-errors"
                                    role="alert"
                                    tabIndex={-1}
                                    className="col-span-full rounded border border-danger p-4 text-danger"
                                >
                                    <strong>NO SE PUDO GUARDAR</strong>
                                    <p>
                                        Hay {Object.keys(saveErrors).length}{' '}
                                        campos que requieren atención.
                                    </p>
                                    <ul className="mt-2 list-disc pl-5">
                                        {Object.entries(saveErrors).map(
                                            ([key, message]) => (
                                                <li key={key}>
                                                    {fieldLabels[key] ?? key}:{' '}
                                                    {message}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </div>
                            )}
                            {offer && formSubject && (
                                <LandingJsonControls
                                    key={`${editing?.id ?? 'new'}:${formSubject.type}:${formSubject.id}`}
                                    brief={formSubject.aiBrief}
                                    form={form}
                                    title={formSubject.title}
                                    fallbackHeadline={
                                        formSubject.defaults.headline
                                    }
                                    hasImage={
                                        !!(
                                            form.hero_path ||
                                            formSubject.defaults.hero_path
                                        )
                                    }
                                    hasVideo={!!video}
                                    onApply={(patch) => {
                                        setForm((current) => ({
                                            ...current,
                                            ...patch,
                                        }));
                                        setSaveErrors({});
                                        setSaved(false);
                                    }}
                                />
                            )}
                            <div className="col-span-full">
                                <button
                                    disabled={saving}
                                    className={`${button} disabled:opacity-50`}
                                >
                                    {saving
                                        ? 'GUARDANDO…'
                                        : 'GUARDAR Y PREVISUALIZAR'}
                                </button>
                            </div>
                            {section('Landing')}
                            {field('name', 'Nombre interno')}
                            {field('slug', 'Slug único')}
                            <p className="col-span-full text-sm">
                                <strong>Producto:</strong> {subject.type}:{' '}
                                {subject.title}
                            </p>
                            {offer && (
                                <div className="col-span-full grid gap-3 sm:grid-cols-2">
                                    <label className="text-sm font-bold">
                                        Cambiar tipo
                                        <select
                                            aria-label="Cambiar tipo"
                                            className={control}
                                            value={type}
                                            onChange={(e) => {
                                                setType(
                                                    e.target
                                                        .value as SubjectType,
                                                );
                                                setSelected('');
                                            }}
                                        >
                                            <option value="BENEFIT">
                                                Benefit
                                            </option>
                                            <option value="EXPERIENCE">
                                                Experience
                                            </option>
                                            <option value="CHALLENGE">
                                                Challenge
                                            </option>
                                        </select>
                                    </label>
                                    <label className="text-sm font-bold">
                                        Cambiar producto
                                        <select
                                            aria-label="Cambiar producto"
                                            className={control}
                                            value={String(
                                                formSubject?.type === type
                                                    ? formSubject.id
                                                    : '',
                                            )}
                                            onChange={async (e) => {
                                                const id = e.target.value;
                                                if (!id) return;
                                                setSelectionError('');
                                                try {
                                                    const response =
                                                        await fetch(
                                                            `/admin/landings/subject?subject_type=${type}&subject_id=${id}`,
                                                            {
                                                                headers: {
                                                                    Accept: 'application/json',
                                                                },
                                                                credentials:
                                                                    'same-origin',
                                                            },
                                                        );
                                                    if (!response.ok)
                                                        throw new Error();
                                                    const context =
                                                        (await response.json()) as Subject;
                                                    setFormSubject(context);
                                                    setSelected(id);
                                                } catch {
                                                    setSelectionError(
                                                        'No se pudo cargar el producto. Intenta nuevamente.',
                                                    );
                                                }
                                            }}
                                        >
                                            <option value="">
                                                Seleccionar…
                                            </option>
                                            {subjectChoices[type]?.map(
                                                (item) => (
                                                    <option
                                                        key={item.id}
                                                        value={item.id}
                                                    >
                                                        {item.title}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                    </label>
                                    {selectionError && (
                                        <p
                                            role="alert"
                                            className="col-span-full text-danger"
                                        >
                                            {selectionError}
                                        </p>
                                    )}
                                    <p className="col-span-full text-sm text-muted-foreground">
                                        Cambiar el producto conserva el mensaje
                                        editorial. Revisa los datos del producto
                                        antes de guardar.
                                    </p>
                                </div>
                            )}
                            {offer && (
                                <>
                                    <label className="text-sm font-bold">
                                        Template
                                        <select
                                            className={control}
                                            disabled
                                            value="OFFER_CONVERSION"
                                        >
                                            <option>OFFER_CONVERSION</option>
                                        </select>
                                    </label>
                                    <label className="text-sm font-bold">
                                        Theme
                                        <select
                                            name="marketing_theme"
                                            className={control}
                                            value={form.marketing_theme}
                                            onChange={(e) =>
                                                setForm((f) => ({
                                                    ...f,
                                                    marketing_theme:
                                                        e.target.value,
                                                }))
                                            }
                                        >
                                            <option value="LIGHT_CONVERSION">
                                                Light Conversion
                                            </option>
                                            <option value="DARK_CONVERSION">
                                                Dark Conversion
                                            </option>
                                        </select>
                                        {errors.marketing_theme && (
                                            <span className="text-danger">
                                                {errors.marketing_theme}
                                            </span>
                                        )}
                                    </label>
                                    <div className="col-span-full rounded border border-border bg-background p-4">
                                        <h3 className="font-bold">
                                            Datos del producto · solo lectura
                                        </h3>
                                        <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                                            {(formSubject?.truth ?? []).map(
                                                (fact, i) => (
                                                    <div key={i}>
                                                        <dt className="font-bold">
                                                            {fact.label}
                                                        </dt>
                                                        <dd className="break-words text-muted-foreground">
                                                            {fact.value}
                                                        </dd>
                                                    </div>
                                                ),
                                            )}
                                        </dl>
                                    </div>
                                </>
                            )}
                            {section('COPY DE CAPTACIÓN')}
                            {field(
                                'acquisition_headline',
                                'Headline al crear cuenta',
                            )}
                            {field(
                                'acquisition_supporting_copy',
                                'Texto de apoyo',
                                true,
                            )}
                            <p className="text-sm text-muted-foreground">
                                {form.acquisition_headline ||
                                    formSubject?.defaults.acquisition_headline}
                                <br />
                                {form.acquisition_supporting_copy ||
                                    formSubject?.defaults
                                        .acquisition_supporting_copy}
                            </p>
                            {section('Mensaje')}
                            <p
                                data-editorial-help
                                className="col-span-full text-sm text-muted-foreground"
                            >
                                Los importes, cantidades y condiciones de acceso
                                pertenecen al producto.
                            </p>
                            {field('eyebrow', 'Eyebrow')}
                            {field('headline', 'Headline')}
                            {offer &&
                                field(
                                    'headline_emphasis',
                                    'Destacar (texto contenido en el headline)',
                                )}
                            {field(
                                'subheadline',
                                'Descripción editorial',
                                true,
                            )}
                            {field(
                                'reward_display_override',
                                'Copy complementario del valor',
                            )}
                            {offer && (
                                <>
                                    {(['hero_layout', 'density'] as const).map(
                                        (key) => (
                                            <label
                                                key={key}
                                                className="flex flex-col gap-1 text-sm font-bold"
                                            >
                                                {key === 'hero_layout'
                                                    ? 'Hero'
                                                    : 'Densidad'}
                                                <select
                                                    name={key}
                                                    aria-invalid={!!errors[key]}
                                                    className={`${control} ${errors[key] ? 'border-danger' : ''}`}
                                                    value={form[key]}
                                                    onChange={(event) =>
                                                        setForm((f) => ({
                                                            ...f,
                                                            [key]: event.target
                                                                .value,
                                                        }))
                                                    }
                                                >
                                                    {(key === 'hero_layout'
                                                        ? [
                                                              'OFFER_FIRST',
                                                              'MEDIA_LED',
                                                          ]
                                                        : [
                                                              'STANDARD',
                                                              'COMPACT',
                                                          ]
                                                    ).map((value) => (
                                                        <option key={value}>
                                                            {value}
                                                        </option>
                                                    ))}
                                                </select>
                                                {errors[key] && (
                                                    <span className="text-danger">
                                                        {errors[key]}
                                                    </span>
                                                )}
                                            </label>
                                        ),
                                    )}
                                    <p className="col-span-full text-sm text-muted-foreground">
                                        Los campos editoriales vacíos usan el
                                        texto del producto. La descripción de
                                        cierre vacía se omite. El texto guardado
                                        se muestra sin sustituciones
                                        automáticas.
                                    </p>
                                </>
                            )}
                            {section('Media')}
                            {offer && (
                                <fieldset className="col-span-full flex flex-wrap gap-5">
                                    <legend className="sr-only">Media</legend>
                                    {[
                                        ['IMAGE', 'Imagen'],
                                        ['VSL', 'Video'],
                                        ['NONE', 'Sin media'],
                                    ].map(([value, label]) => (
                                        <label
                                            key={value}
                                            className="flex gap-2"
                                        >
                                            <input
                                                type="radio"
                                                name="media_mode"
                                                aria-invalid={
                                                    !!errors.media_mode
                                                }
                                                checked={
                                                    form.media_mode === value
                                                }
                                                onChange={() =>
                                                    setForm((f) => ({
                                                        ...f,
                                                        media_mode: value,
                                                    }))
                                                }
                                            />
                                            {label}
                                        </label>
                                    ))}
                                    {errors.media_mode && (
                                        <span className="text-danger">
                                            {errors.media_mode}
                                        </span>
                                    )}
                                </fieldset>
                            )}
                            {(!offer || form.media_mode === 'IMAGE') && (
                                <>
                                    {currentMediaUrl ? (
                                        <figure className="col-span-full">
                                            <img
                                                src={String(currentMediaUrl)}
                                                alt={
                                                    form.hero_alt ||
                                                    'Imagen actual del producto'
                                                }
                                                className="max-h-40 max-w-xs rounded object-contain"
                                            />
                                            <figcaption className="mt-2 text-sm text-muted-foreground">
                                                Imagen actual · Cambia la
                                                referencia del visual para
                                                reemplazarla.
                                            </figcaption>
                                        </figure>
                                    ) : (
                                        <p className="col-span-full text-sm text-muted-foreground">
                                            Guarda la referencia del visual para
                                            comprobar la imagen actual.
                                        </p>
                                    )}
                                    {field(
                                        'hero_path',
                                        'Referencia del visual',
                                    )}
                                    {field(
                                        'hero_alt',
                                        'Descripción del visual',
                                    )}
                                </>
                            )}
                            {(!offer || form.media_mode === 'VSL') && (
                                <LandingVideoFields
                                    value={video}
                                    onChange={setVideo}
                                    errors={errors}
                                />
                            )}
                            {section('Atribución')}
                            {field('campaign_key', 'Campaign key (opcional)')}
                            <label className="text-sm font-bold">
                                Audiencia al publicar
                                <select
                                    name="default_scope"
                                    aria-invalid={!!errors.default_scope}
                                    className={`${control} ${errors.default_scope ? 'border-danger' : ''}`}
                                    value={form.default_scope}
                                    onChange={(e) =>
                                        setForm((f) => ({
                                            ...f,
                                            default_scope: e.target.value,
                                        }))
                                    }
                                >
                                    <option value="NONE">
                                        NONE · Solo enlace directo
                                    </option>
                                    <option value="GUESTS">
                                        GUESTS · Visitantes
                                    </option>
                                    <option value="ALL">ALL · Todos</option>
                                </select>
                            </label>
                            {errors.default_scope && (
                                <p className="text-danger">
                                    {errors.default_scope}
                                </p>
                            )}
                            <p className="col-span-full text-sm text-muted-foreground">
                                Un borrador no cambia la navegación. La
                                audiencia elegida se aplica al publicar.
                                Campaign key identifica adquisición; no activa
                                una campaña económica.
                            </p>
                            {section('Cierre')}
                            {field('final_cta_headline', 'Titular final')}
                            {offer &&
                                field(
                                    'final_cta_description',
                                    'Texto de apoyo final',
                                    true,
                                )}
                            {section('Publicación')}
                            <p className="col-span-full">
                                Estado: {editing?.status ?? 'DRAFT'}. Guarda los
                                cambios antes de abrir el preview o publicar.
                            </p>
                            <div className="col-span-full flex flex-wrap gap-4">
                                <button
                                    disabled={saving}
                                    className={`${button} disabled:opacity-50`}
                                >
                                    {saving
                                        ? 'GUARDANDO…'
                                        : 'GUARDAR Y PREVISUALIZAR'}
                                </button>
                                {editing && (
                                    <button
                                        type="button"
                                        className="underline"
                                        onClick={() => {
                                            setPreview(editing);
                                            setWidth(390);
                                        }}
                                    >
                                        PREVIEW GUARDADO
                                    </button>
                                )}
                                <button
                                    type="button"
                                    className="underline"
                                    onClick={() => setOpen(false)}
                                >
                                    CANCELAR
                                </button>
                            </div>
                        </form>
                    )}
                    {clone && (
                        <form
                            className="mt-8 grid max-w-3xl gap-4 rounded border border-border bg-surface p-5"
                            onSubmit={(e) => {
                                e.preventDefault();
                                router.post(
                                    `${baseUrl}/${clone.slug}/duplicate`,
                                    duplicate,
                                    { onSuccess: () => setClone(null) },
                                );
                            }}
                        >
                            <h2 className="text-xl font-bold">
                                Duplicar landing
                            </h2>
                            <p className="text-sm">
                                Mismo producto y presentación. La copia se
                                guarda como DRAFT.
                            </p>
                            {(['name', 'slug', 'campaign_key'] as const).map(
                                (key) => (
                                    <label
                                        key={key}
                                        className="text-sm font-bold"
                                    >
                                        {key === 'name'
                                            ? 'Nuevo nombre'
                                            : key === 'slug'
                                              ? 'Nuevo slug único'
                                              : 'Campaign key (copiado; editable)'}
                                        <input
                                            name={`duplicate_${key}`}
                                            className={control}
                                            value={duplicate[key]}
                                            onChange={(e) =>
                                                setDuplicate((f) => ({
                                                    ...f,
                                                    [key]: e.target.value,
                                                }))
                                            }
                                        />
                                        {errors[key] && (
                                            <span className="text-danger">
                                                {errors[key]}
                                            </span>
                                        )}
                                    </label>
                                ),
                            )}
                            <div className="flex gap-4">
                                <button className={button}>
                                    GUARDAR COPIA
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setClone(null)}
                                    className="underline"
                                >
                                    CANCELAR
                                </button>
                            </div>
                        </form>
                    )}
                    {preview && (
                        <section className="mt-8 min-w-0 rounded border border-border bg-surface p-3">
                            <div className="flex flex-wrap items-center gap-4">
                                <h2 className="font-extrabold">
                                    {previewDirty
                                        ? 'PREVIEW · VERSIÓN GUARDADA'
                                        : 'PREVIEW'}
                                    : {preview.name}
                                </h2>
                                {[390, 430, 1440].map((size) => (
                                    <button
                                        key={size}
                                        aria-pressed={width === size}
                                        className="rounded border border-border px-3 py-2"
                                        onClick={() => setWidth(size)}
                                    >
                                        {size === 1440 ? 'Desktop' : size}
                                    </button>
                                ))}
                                <button
                                    className="underline"
                                    onClick={() => setPreview(null)}
                                >
                                    CERRAR
                                </button>
                            </div>
                            <p className="my-3 text-sm text-muted-foreground">
                                {previewDirty &&
                                    'Tienes cambios sin guardar que todavía no aparecen aquí. '}
                                Presentación guardada. Acciones de negocio
                                desactivadas.
                            </p>
                            <div className="w-full overflow-x-auto">
                                <iframe
                                    title={`Preview ${preview.name}`}
                                    key={`${preview.id}:${previewRevision}`}
                                    src={`${baseUrl}/${preview.slug}/preview?revision=${previewRevision}`}
                                    style={{
                                        width,
                                        height: 780,
                                        boxSizing: 'content-box',
                                    }}
                                    className="block max-w-none border border-border bg-background"
                                />
                            </div>
                        </section>
                    )}
                </>
            )}
        </AdminLayout>
    );
}
