import { ListPagination, type ListPage } from '@/components/list-pagination';
import { ImageUpload } from '@/components/media/image-upload';
import { LandingPreviewPane } from '@/components/marketing/LandingPreviewPane';
import { LandingJsonControls } from '@/components/marketing/LandingJsonControls';
import { LandingVideoFields } from '@/components/media/jakawi-vsl-player/LandingVideoFields';
import type { LandingVideoConfig } from '@/components/media/jakawi-vsl-player/LandingVideo';
import { landingEditorFieldLabel } from '@/lib/landing-editor-labels';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
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
    hero_upload: '',
    hero_remove: '0',
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
const signature = (
    form: Record<string, string>,
    video: LandingVideoConfig | null,
    subject: Subject | null,
) => JSON.stringify([form, video, subject?.type, subject?.id]);
const control =
    'w-full min-w-0 scroll-mt-72 rounded border border-border bg-surface p-2 font-normal focus-visible:outline-2 focus-visible:outline-brand';
const button =
    'rounded-[var(--radius-control)] bg-brand px-4 py-3 text-sm font-extrabold text-brand-foreground';
export default function Index({
    subject,
    subjectChoices = {},
    autoCreate = false,
    autoEdit = null,
    presentations, pagination, defaultPresentation,
    baseUrl = '/admin/landings',
    productUrl = '/admin',
}: {
    subject: Subject | null;
    subjectChoices?: Partial<
        Record<SubjectType, { id: number; title: string; slug: string }[]>
    >;
    autoCreate?: boolean;
    autoEdit?: Presentation | null;
    presentations: Presentation[]; pagination: ListPage | null; defaultPresentation: Presentation | null;
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
        setPreview(item);
        setWidth(390);
        requestAnimationFrame(() =>
            document
                .getElementById('landing-workspace')
                ?.scrollIntoView({ block: 'start' }),
        );
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
    useEffect(() => {
        if (autoEdit) begin(autoEdit);
        // The selected record initializes the existing editor once on navigation.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [autoEdit?.id]);
    const field = (key: string, label: string, multiline = false) => (
        <label className="flex min-w-0 flex-col gap-1 text-sm font-bold">
            {label}
            {multiline ? (
                <textarea
                    name={key}
                    aria-invalid={!!errors[key]}
                    aria-describedby={
                        errors[key] ? `landing-error-${key}` : undefined
                    }
                    className={`${control} min-h-20 ${errors[key] ? 'border-danger' : ''}`}
                    value={form[key] ?? ''}
                    onChange={(e) =>
                        setForm((f) => ({ ...f, [key]: e.target.value }))
                    }
                />
            ) : (
                <input
                    name={key}
                    aria-invalid={!!errors[key]}
                    aria-describedby={
                        errors[key] ? `landing-error-${key}` : undefined
                    }
                    className={`${control} ${errors[key] ? 'border-danger' : ''}`}
                    value={form[key] ?? ''}
                    onChange={(e) =>
                        setForm((f) => ({ ...f, [key]: e.target.value }))
                    }
                />
            )}{' '}
            {errors[key] && (
                <span id={`landing-error-${key}`} className="text-danger">
                    {errors[key]}
                </span>
            )}
        </label>
    );
    const section = (label: string) => (
        <h3 className="col-span-full mt-3 border-b border-border pb-2 text-lg font-extrabold">
            {label}
        </h3>
    );
    const defaultItem = defaultPresentation ?? presentations.find(
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
            {!open && (!subject || offer) && (
                <section className="mt-5 grid min-w-0 gap-4 rounded border border-border bg-surface p-4 sm:grid-cols-3">
                    <h2 className="col-span-full text-xl font-extrabold">
                        Seleccionar producto
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
                            Nueva Landing
                        </button>
                    </div>
                    <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {presentations.map((item) => (
                            <article
                                key={item.id}
                                className={`min-w-0 rounded border bg-surface p-4 ${open && editing?.id === item.id ? 'border-brand ring-1 ring-brand' : 'border-border'}`}
                                aria-current={
                                    open && editing?.id === item.id
                                        ? 'true'
                                        : undefined
                                }
                            >
                                <h3 className="text-lg font-extrabold">
                                    {item.name}
                                    {open && editing?.id === item.id && (
                                        <span className="ml-3 text-xs text-brand">
                                            EDITANDO
                                        </span>
                                    )}
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
                            id="landing-workspace"
                            className="mt-8 min-w-0 scroll-mt-4"
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
                                delete payload.hero_path;
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
                                        const savedForm = {...form, media_mode: String(persisted.media_mode ?? form.media_mode), hero_path: String(persisted.hero_path ?? ''), hero_upload: '', hero_remove: '0'};
                                        setForm(savedForm);
                                        setBaseline(signature(savedForm, video, formSubject));
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
                                            if (field) {
                                                field
                                                    .closest('details')
                                                    ?.setAttribute('open', '');
                                                field.scrollIntoView({
                                                    block: 'center',
                                                });
                                                field.focus({
                                                    preventScroll: true,
                                                });
                                            } else
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
                            <header
                                data-workspace-toolbar
                                className="top-0 z-20 mb-4 rounded border border-border bg-surface p-3 shadow-sm lg:sticky"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div
                                        className="flex min-w-0 flex-wrap items-center gap-3"
                                        aria-live="polite"
                                    >
                                        <h2 className="max-w-full text-lg font-extrabold break-words">
                                            {form.name || 'Nueva landing'}
                                        </h2>
                                        <span className="rounded border border-border px-2 py-1 text-xs font-bold">
                                            {editing?.status ?? 'DRAFT'}
                                        </span>
                                        <span className="text-xs">
                                            Audience: {form.default_scope}
                                        </span>
                                        <span
                                            className={`text-xs font-bold ${dirty ? 'text-amber-800' : 'text-green-700'}`}
                                        >
                                            {dirty
                                                ? '● CAMBIOS SIN GUARDAR'
                                                : editing
                                                  ? '✓ GUARDADO'
                                                  : 'BORRADOR SIN GUARDAR'}
                                        </span>
                                        {saved && !dirty && (
                                            <span
                                                role="status"
                                                className="text-xs text-green-700"
                                            >
                                                ✓ LANDING GUARDADA
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap items-center gap-3">
                                        <button
                                            disabled={saving}
                                            className={`${button} disabled:opacity-50`}
                                        >
                                            {saving
                                                ? 'GUARDANDO…'
                                                : 'GUARDAR Y PREVISUALIZAR'}
                                        </button>
                                        {editing &&
                                            editing.status !== 'PUBLISHED' && (
                                                <button
                                                    type="button"
                                                    className="rounded border border-border px-4 py-3 text-sm font-bold"
                                                    onClick={() =>
                                                        publish(editing)
                                                    }
                                                >
                                                    PUBLICAR
                                                </button>
                                            )}
                                        <details className="relative">
                                            <summary className="cursor-pointer rounded px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-brand">
                                                Más acciones
                                            </summary>
                                            <div className="absolute right-0 z-30 mt-2 grid min-w-48 gap-3 rounded border border-border bg-surface p-4 text-sm">
                                                {editing?.status ===
                                                    'PUBLISHED' && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            router.post(
                                                                `${baseUrl}/${editing.slug}/unpublish`,
                                                            )
                                                        }
                                                    >
                                                        DESPUBLICAR
                                                    </button>
                                                )}
                                                {editing &&
                                                    editing.status !==
                                                        'ARCHIVED' && (
                                                        <button
                                                            type="button"
                                                            className="text-danger"
                                                            onClick={() =>
                                                                router.post(
                                                                    `${baseUrl}/${editing.slug}/archive`,
                                                                )
                                                            }
                                                        >
                                                            ARCHIVAR
                                                        </button>
                                                    )}
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setOpen(false)
                                                    }
                                                >
                                                    CANCELAR
                                                </button>
                                            </div>
                                        </details>
                                    </div>
                                </div>
                                <div className="mt-3 border-t border-border pt-3">
                                    <p className="mb-2 text-xs font-extrabold">
                                        1 · CREAR CON IA{' '}
                                        <span className="ml-3 font-normal text-muted-foreground">
                                            2 · EDITAR → 3 · PREVIEW → 4 ·
                                            PUBLICAR
                                        </span>
                                    </p>
                                    {offer && formSubject && (
                                        <LandingJsonControls
                                            key={`${editing?.id ?? 'new'}:${formSubject.type}:${formSubject.id}`}
                                            canImport={!!editing}
                                            brief={formSubject.aiBrief}
                                            form={form}
                                            title={formSubject.title}
                                            fallbackHeadline={
                                                formSubject.defaults.headline
                                            }
                                            hasImage={
                                                !!(
                                                    form.hero_path ||
                                                    formSubject.defaults
                                                        .hero_path
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
                                </div>
                                {publishWarning && (
                                    <p
                                        role="alert"
                                        className="mt-2 text-sm text-danger"
                                    >
                                        {publishWarning}
                                    </p>
                                )}
                            </header>
                            <div
                                data-workspace-columns
                                className="grid min-w-0 items-start gap-5 lg:grid-cols-[minmax(0,45fr)_minmax(0,55fr)]"
                            >
                                <div
                                    data-workspace-editor
                                    className="grid min-w-0 gap-3 rounded border border-border bg-surface p-4 sm:grid-cols-2"
                                >
                                    {!!Object.keys(saveErrors).length && (
                                        <div
                                            id="landing-save-errors"
                                            role="alert"
                                            tabIndex={-1}
                                            className="col-span-full rounded border border-danger p-4 text-danger"
                                        >
                                            <strong>NO SE PUDO GUARDAR</strong>
                                            <p>
                                                Hay{' '}
                                                {Object.keys(saveErrors).length}{' '}
                                                campos que requieren atención.
                                            </p>
                                            <ul className="mt-2 list-disc pl-5">
                                                {Object.entries(saveErrors).map(
                                                    ([key, message]) => (
                                                        <li key={key}>
                                                            {landingEditorFieldLabel(
                                                                key,
                                                            )}
                                                            : {message}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </div>
                                    )}
                                    {field('name', 'Nombre interno')}
                                    {field('slug', 'Slug único')}
                                    <details
                                        data-product-truth
                                        className="col-span-full rounded border border-border bg-background p-3"
                                    >
                                        <summary className="cursor-pointer text-sm font-bold focus-visible:outline-2 focus-visible:outline-brand">
                                            DATOS DEL PRODUCTO · SOLO LECTURA{' '}
                                            <span className="ml-2 font-normal text-brand">
                                                Ver datos
                                            </span>
                                        </summary>
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
                                    </details>
                                    {section('MENSAJE')}
                                    <p
                                        data-editorial-help
                                        className="col-span-full text-sm text-muted-foreground"
                                    >
                                        Los datos comerciales y condiciones se
                                        toman automáticamente del producto.
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
                                            {(
                                                [
                                                    'hero_layout',
                                                    'density',
                                                ] as const
                                            ).map((key) => (
                                                <label
                                                    key={key}
                                                    className="flex flex-col gap-1 text-sm font-bold"
                                                >
                                                    {key === 'hero_layout'
                                                        ? 'Hero'
                                                        : 'Densidad'}
                                                    <select
                                                        name={key}
                                                        aria-invalid={
                                                            !!errors[key]
                                                        }
                                                        aria-describedby={
                                                            errors[key]
                                                                ? `landing-error-${key}`
                                                                : undefined
                                                        }
                                                        className={`${control} ${errors[key] ? 'border-danger' : ''}`}
                                                        value={form[key]}
                                                        onChange={(event) =>
                                                            setForm((f) => ({
                                                                ...f,
                                                                [key]: event
                                                                    .target
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
                                            ))}
                                            <p className="col-span-full text-sm text-muted-foreground">
                                                Los campos editoriales vacíos
                                                usan el texto del producto. La
                                                descripción de cierre vacía se
                                                omite. El texto guardado se
                                                muestra sin sustituciones
                                                automáticas.
                                            </p>
                                        </>
                                    )}
                                    {section('MEDIA')}
                                    {offer && (
                                        <fieldset className="col-span-full flex flex-wrap gap-5">
                                            <legend className="sr-only">
                                                Media
                                            </legend>
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
                                                            form.media_mode ===
                                                            value
                                                        }
                                                        onChange={() =>
                                                            setForm((f) => ({
                                                                ...f,
                                                                media_mode:
                                                                    value,
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
                                    {(!offer ||
                                        form.media_mode === 'IMAGE') && (
                                        <>
                                            <div className="col-span-full"><ImageUpload key={editing?.id ?? 'new'} name="hero" label="Imagen de la Landing" currentUrl={currentMediaUrl ? String(currentMediaUrl) : null} error={errors.hero || errors.image} onChange={(token, remove) => setForm(value => ({...value, hero_upload: token, hero_remove: remove ? '1' : '0'}))} /></div>
                                            {field(
                                                'hero_alt',
                                                'Descripción del visual (alt)',
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
                                    {section('CAPTACIÓN')}
                                    {field(
                                        'acquisition_headline',
                                        'Headline al crear cuenta',
                                    )}
                                    {field(
                                        'acquisition_supporting_copy',
                                        'Texto de apoyo',
                                        true,
                                    )}
                                    {section('CIERRE')}
                                    {field(
                                        'final_cta_headline',
                                        'Titular final',
                                    )}
                                    {offer &&
                                        field(
                                            'final_cta_description',
                                            'Texto de apoyo final',
                                            true,
                                        )}
                                    {section('PUBLICACIÓN / ATRIBUCIÓN')}
                                    {field(
                                        'campaign_key',
                                        'Campaign key (opcional)',
                                    )}
                                    <label className="text-sm font-bold">
                                        Audiencia al publicar
                                        <select
                                            name="default_scope"
                                            aria-invalid={
                                                !!errors.default_scope
                                            }
                                            className={`${control} ${errors.default_scope ? 'border-danger' : ''}`}
                                            value={form.default_scope}
                                            onChange={(e) =>
                                                setForm((f) => ({
                                                    ...f,
                                                    default_scope:
                                                        e.target.value,
                                                }))
                                            }
                                        >
                                            <option value="NONE">
                                                NONE · Solo enlace directo
                                            </option>
                                            <option value="GUESTS">
                                                GUESTS · Visitantes
                                            </option>
                                            <option value="ALL">
                                                ALL · Todos
                                            </option>
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
                                        Campaign key identifica adquisición; no
                                        activa una campaña económica.
                                    </p>
                                    <details className="col-span-full rounded border border-border p-3">
                                        <summary className="cursor-pointer font-extrabold focus-visible:outline-2 focus-visible:outline-brand">
                                            AVANZADO
                                        </summary>
                                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
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
                                                                formSubject?.type ===
                                                                    type
                                                                    ? formSubject.id
                                                                    : '',
                                                            )}
                                                            onChange={async (
                                                                e,
                                                            ) => {
                                                                const id =
                                                                    e.target
                                                                        .value;
                                                                if (!id) return;
                                                                setSelectionError(
                                                                    '',
                                                                );
                                                                try {
                                                                    const response =
                                                                        await fetch(
                                                                            `/admin/landings/subject?subject_type=${type}&subject_id=${id}`,
                                                                            {
                                                                                headers:
                                                                                    {
                                                                                        Accept: 'application/json',
                                                                                    },
                                                                                credentials:
                                                                                    'same-origin',
                                                                            },
                                                                        );
                                                                    if (
                                                                        !response.ok
                                                                    )
                                                                        throw new Error();
                                                                    const context =
                                                                        (await response.json()) as Subject;
                                                                    setFormSubject(
                                                                        context,
                                                                    );
                                                                    setSelected(
                                                                        id,
                                                                    );
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
                                                            {subjectChoices[
                                                                type
                                                            ]?.map((item) => (
                                                                <option
                                                                    key={
                                                                        item.id
                                                                    }
                                                                    value={
                                                                        item.id
                                                                    }
                                                                >
                                                                    {item.title}
                                                                </option>
                                                            ))}
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
                                                        Cambiar el producto
                                                        conserva el mensaje
                                                        editorial. Revisa los
                                                        datos del producto antes
                                                        de guardar.
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
                                                            <option>
                                                                OFFER_CONVERSION
                                                            </option>
                                                        </select>
                                                    </label>
                                                    <label className="text-sm font-bold">
                                                        Theme
                                                        <select
                                                            name="marketing_theme"
                                                            className={control}
                                                            value={
                                                                form.marketing_theme
                                                            }
                                                            onChange={(e) =>
                                                                setForm(
                                                                    (f) => ({
                                                                        ...f,
                                                                        marketing_theme:
                                                                            e
                                                                                .target
                                                                                .value,
                                                                    }),
                                                                )
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
                                                                {
                                                                    errors.marketing_theme
                                                                }
                                                            </span>
                                                        )}
                                                    </label>
                                                </>
                                            )}
                                        </div>
                                    </details>
                                </div>
                                <LandingPreviewPane
                                    preview={preview}
                                    baseUrl={baseUrl}
                                    revision={previewRevision}
                                    width={width}
                                    onWidth={setWidth}
                                    dirty={previewDirty}
                                    saved={saved}
                                    saving={saving}
                                    formId="landing-workspace"
                                />
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
                    {!open && preview && (
                        <LandingPreviewPane
                            preview={preview}
                            baseUrl={baseUrl}
                            revision={previewRevision}
                            width={width}
                            onWidth={setWidth}
                            dirty={false}
                            saved={false}
                            saving={false}
                            onClose={() => setPreview(null)}
                        />
                    )}
                </>
            )}
            {pagination && <ListPagination page={pagination} />}
        </AdminLayout>
    );
}
