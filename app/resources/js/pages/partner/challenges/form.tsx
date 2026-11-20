import { ImageUpload } from '@/components/media/image-upload';
import {
    EditorialState,
    Field,
    primaryAction,
    ProposalSection,
    reviewStates,
    secondaryAction,
    type Partner,
} from '@/components/partner/proposal-fields';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import PartnerHeading from '../layout';
type Option = { id: number; name?: string; title?: string };
type Challenge = Record<string, any>;
const defaults = {
    title: '',
    description: '',
    city_id: '',
    starts_at: '',
    ends_at: '',
    allowed_platforms: '',
    required_hashtags: '',
    required_mentions: '',
    published_from: '',
    published_until: '',
    evidence_type: 'SOCIAL_POST',
    qualification_type: 'VALID_EVIDENCE',
    evaluation_mode: 'CONTINUOUS',
    qualification_metric: '',
    qualification_target: '',
    selection_type: 'ALL_QUALIFIED',
    selection_metric: '',
    winner_limit: '',
    review_mode: 'AUTOMATIC',
    participation_eligibility: 'ALL_USERS',
    reward_eligibility: 'ALL_USERS',
    max_entries_per_user: '',
    reward_type: 'MANUAL_PRIZE',
    benefit_id: '',
    manual_prize_description: '',
    reward_jp_amount: '',
    instructions: '',
    marketing_hook: '',
    marketing_headline: '',
    marketing_subheadline: '',
    marketing_reward_copy: '',
    hero_alt: '',
    ranking_visibility: 'PUBLIC',
    ranking_refresh_interval_minutes: 30,
};
export default function ChallengeForm({
    partner,
    challenge,
    editable,
    heroUrl,
    cities,
    benefits,
}: {
    partner: Partner;
    challenge: Challenge | null;
    editable: boolean;
    heroUrl: string | null;
    cities: Option[];
    benefits: Option[];
}) {
    const base = `/partner/${partner.slug}/retos`;
    const initial: Record<string, any> = Object.fromEntries(
        Object.entries(defaults).map(([key, value]) => [
            key,
            challenge?.[key] ?? value,
        ]),
    );
    for (const key of [
        'allowed_platforms',
        'required_hashtags',
        'required_mentions',
    ])
        initial[key] = (challenge?.[key] ?? []).join(', ');
    for (const key of [
        'starts_at',
        'ends_at',
        'published_from',
        'published_until',
    ])
        initial[key] = challenge?.[key]?.slice(0, 16) ?? '';
    const form = useForm(initial);
    const image = useForm({ image_upload: '', image_remove: false });
    const send = useForm({});
    const [imageBusy, setImageBusy] = useState(false);
    const field = (
        key: string,
        label: string,
        help?: string,
        multiline = false,
        type = 'text',
        required = false,
        min?: number,
    ) => (
        <Field
            name={key}
            label={label}
            help={help}
            error={form.errors[key]}
            value={form.data[key]}
            onChange={(v) => form.setData(key, v)}
            multiline={multiline}
            type={type}
            required={required}
            min={min}
        />
    );
    const select = (
        key: string,
        label: string,
        options: [string, string][],
        help?: string,
    ) => (
        <Field
            name={key}
            label={label}
            value={form.data[key]}
            onChange={(v) => form.setData(key, v)}
            options={options}
            error={form.errors[key]}
            help={help}
        />
    );
    const listError = (key: string) =>
        Object.entries(form.errors)
            .filter(([name]) => name.startsWith(key + '.'))
            .map(([name, error]) => (
                <p key={name} role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            ));
    const payload = (data: Record<string, any>) => {
        const value = { ...data };
        for (const key of [
            'city_id',
            'qualification_metric',
            'qualification_target',
            'selection_metric',
            'max_entries_per_user',
            'starts_at',
            'ends_at',
            'published_from',
            'published_until',
        ])
            value[key] = data[key] || null;
        for (const key of [
            'allowed_platforms',
            'required_hashtags',
            'required_mentions',
        ])
            value[key] = data[key]
                .split(',')
                .map((s: string) => s.trim())
                .filter(Boolean);
        value.winner_limit =
            data.selection_type === 'ALL_QUALIFIED'
                ? null
                : data.winner_limit || null;
        value.benefit_id =
            data.reward_type === 'BENEFIT' ? data.benefit_id || null : null;
        value.manual_prize_description =
            data.reward_type === 'MANUAL_PRIZE'
                ? data.manual_prize_description
                : null;
        if (data.reward_type === 'JP')
            value.reward_jp_amount = data.reward_jp_amount || null;
        else delete value.reward_jp_amount;
        return value;
    };
    return (
        <PartnerHeading
            title={challenge ? 'Propuesta de reto' : 'Nuevo reto'}
            partner={partner}
        >
            <div className="max-w-4xl space-y-5">
                <Link
                    className="inline-block py-2 text-sm underline"
                    href={base}
                >
                    Volver a retos
                </Link>
                <EditorialState
                    label={
                        reviewStates[challenge?.review_status ?? 'DRAFT'] ??
                        challenge?.review_status
                    }
                    editable={editable}
                    notes={challenge?.review_comment}
                />
                {challenge && (
                    <p className="text-sm text-muted-foreground">
                        Estado operativo:{' '}
                        {(
                            {
                                draft: 'Borrador',
                                open: 'Abierto o programado',
                                closed: 'Cerrado',
                                cancelled: 'Cancelado',
                            } as Record<string, string>
                        )[challenge.status] ?? challenge.status}
                        . La aprobación editorial no abre el reto.
                    </p>
                )}
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform(payload);
                        challenge
                            ? form.put(`${base}/${challenge.slug}`)
                            : form.post(base);
                    }}
                >
                    <fieldset
                        disabled={!editable || form.processing}
                        className="min-w-0 space-y-5"
                    >
                        <ProposalSection
                            title="1. Información del reto"
                            description="Prepara una propuesta. JAKAWI conserva la aprobación, apertura y cierre del reto."
                        >
                            {field(
                                'title',
                                'Título',
                                'Nombre breve que describa el reto.',
                                false,
                                'text',
                                true,
                            )}
                            {select('city_id', 'Ciudad', [
                                ['', 'Nacional'],
                                ...cities.map(
                                    (c) =>
                                        [String(c.id), c.name ?? ''] as [
                                            string,
                                            string,
                                        ],
                                ),
                            ])}
                            <div className="sm:col-span-2">
                                {field(
                                    'description',
                                    'Descripción',
                                    'Explica el reto y sus condiciones principales.',
                                    true,
                                )}
                            </div>
                            <details className="space-y-4 rounded-md border border-border p-4 sm:col-span-2">
                                <summary className="cursor-pointer py-1 text-sm font-medium">
                                    Presentación opcional
                                </summary>
                                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                    {field(
                                        'marketing_hook',
                                        'Invitación breve',
                                        'Hasta 180 caracteres, sin cifras.',
                                    )}
                                    {field(
                                        'marketing_headline',
                                        'Título de presentación',
                                        'Hasta 180 caracteres, sin cifras.',
                                    )}
                                    {field(
                                        'marketing_subheadline',
                                        'Texto de apoyo',
                                        'Hasta 1000 caracteres, sin cifras.',
                                        true,
                                    )}
                                    {field(
                                        'marketing_reward_copy',
                                        'Texto de apoyo del premio',
                                        'Hasta 180 caracteres, sin cifras.',
                                    )}
                                    {field(
                                        'hero_alt',
                                        'Descripción de la imagen',
                                        'Describe el visual para quienes no puedan verlo.',
                                    )}
                                </div>
                            </details>
                        </ProposalSection>
                        <ProposalSection title="2. Participación e instrucciones">
                            {field(
                                'instructions',
                                'Instrucciones para participar',
                                'Detalla los pasos y qué se considerará una participación válida.',
                                true,
                            )}
                            {select(
                                'qualification_type',
                                'Cómo se cumple la meta',
                                [
                                    ['VALID_EVIDENCE', 'Cumplir las reglas'],
                                    [
                                        'METRIC_THRESHOLD',
                                        'Alcanzar una métrica',
                                    ],
                                    ['MANUAL', 'Confirmación de JAKAWI'],
                                ],
                                'La meta determina cuándo una participación califica. JAKAWI gestiona su comprobación.',
                            )}
                            {form.data.qualification_type ===
                                'METRIC_THRESHOLD' && (
                                <>
                                    {select(
                                        'qualification_metric',
                                        'Métrica de la meta',
                                        [
                                            ['', 'Elige una'],
                                            ['views', 'Vistas'],
                                            ['likes', 'Me gusta'],
                                            ['comments', 'Comentarios'],
                                        ],
                                    )}
                                    {field(
                                        'qualification_target',
                                        'Meta mínima',
                                        'Cantidad positiva que debe alcanzar la publicación.',
                                        false,
                                        'number',
                                        true,
                                        1,
                                    )}
                                </>
                            )}
                            <div className="space-y-3 sm:col-span-2">
                                <h3 className="font-semibold">
                                    Selección propuesta
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    Proponer una mecánica no selecciona
                                    ganadores ni entrega premios. Esas acciones
                                    corresponden a JAKAWI.
                                </p>
                            </div>
                            {select(
                                'selection_type',
                                'Quiénes podrían ganar',
                                [
                                    ['ALL_QUALIFIED', 'Todos los que cumplan'],
                                    [
                                        'FIRST_N',
                                        'Primeras participaciones verificadas',
                                    ],
                                    [
                                        'TOP_N',
                                        'Mejores participaciones según ranking',
                                    ],
                                    ['MANUAL', 'Selección de JAKAWI'],
                                ],
                                'Primeras y mejores participaciones requieren indicar cuántos ganadores habrá.',
                            )}
                            {form.data.selection_type !== 'ALL_QUALIFIED' &&
                                field(
                                    'winner_limit',
                                    'Cantidad de ganadores',
                                    'En selección manual también se requiere una cantidad.',
                                    false,
                                    'number',
                                    true,
                                    1,
                                )}
                            {form.data.selection_type === 'TOP_N' && (
                                <>
                                    {select(
                                        'selection_metric',
                                        'Métrica del ranking',
                                        [
                                            ['', 'Elige una'],
                                            ['views', 'Vistas'],
                                            ['likes', 'Me gusta'],
                                            ['comments', 'Comentarios'],
                                        ],
                                    )}
                                    {select(
                                        'ranking_visibility',
                                        'Visibilidad del ranking',
                                        [
                                            ['PUBLIC', 'Público'],
                                            [
                                                'PARTICIPANTS_ONLY',
                                                'Solo participantes',
                                            ],
                                            ['NONE', 'Sin ranking'],
                                        ],
                                    )}
                                    {field(
                                        'ranking_refresh_interval_minutes',
                                        'Intervalo del ranking (minutos)',
                                        'El mínimo existente es 15 minutos.',
                                        false,
                                        'number',
                                        false,
                                        15,
                                    )}
                                </>
                            )}
                            {select(
                                'evaluation_mode',
                                'Cuándo se evalúa',
                                [
                                    ['CONTINUOUS', 'Durante el reto'],
                                    ['AT_CLOSE', 'Al cierre'],
                                ],
                                'El ranking de mejores participaciones se evalúa al cierre.',
                            )}
                            {select(
                                'review_mode',
                                'Revisión de ganadores',
                                [
                                    ['AUTOMATIC', 'Automática según reglas'],
                                    ['REQUIRED', 'Revisión de JAKAWI'],
                                ],
                                'La selección manual requiere revisión de JAKAWI.',
                            )}
                        </ProposalSection>
                        <ProposalSection title="3. Evidencia y plataformas">
                            {select(
                                'evidence_type',
                                'Tipo de evidencia',
                                [
                                    ['SOCIAL_POST', 'Publicación en redes'],
                                    ['MANUAL', 'Presencial o manual'],
                                ],
                                'La evidencia manual requiere elegir Confirmación de JAKAWI como meta.',
                            )}
                            {form.data.evidence_type === 'SOCIAL_POST' && (
                                <>
                                    <div>
                                        {field(
                                            'allowed_platforms',
                                            'Plataformas permitidas',
                                            'Separa los identificadores existentes con comas; por ejemplo, instagram o tiktok.',
                                        )}
                                        {listError('allowed_platforms')}
                                    </div>
                                    <div>
                                        {field(
                                            'required_hashtags',
                                            'Etiquetas requeridas',
                                            'Separa las etiquetas con comas.',
                                        )}
                                        {listError('required_hashtags')}
                                    </div>
                                    <div>
                                        {field(
                                            'required_mentions',
                                            'Menciones requeridas',
                                            'Separa las cuentas con comas.',
                                        )}
                                        {listError('required_mentions')}
                                    </div>
                                    {field(
                                        'max_entries_per_user',
                                        'Máximo de publicaciones por persona',
                                        'Opcional; debe ser positivo si se define.',
                                        false,
                                        'number',
                                        false,
                                        1,
                                    )}
                                    {field(
                                        'published_from',
                                        'Publicación válida desde (UTC)',
                                        'Opcional. Fecha mínima del contenido presentado.',
                                        false,
                                        'datetime-local',
                                    )}
                                    {field(
                                        'published_until',
                                        'Publicación válida hasta (UTC)',
                                        'Si se define un intervalo, el fin debe ser posterior al inicio.',
                                        false,
                                        'datetime-local',
                                    )}
                                </>
                            )}
                        </ProposalSection>
                        <ProposalSection
                            title="4. Fechas y elegibilidad"
                            description="Las fechas se muestran y guardan en UTC, zona horaria de la aplicación. No abren el reto automáticamente desde este portal."
                        >
                            {field(
                                'starts_at',
                                'Inicio del reto (UTC)',
                                'Opcional mientras preparas el borrador.',
                                false,
                                'datetime-local',
                            )}
                            {field(
                                'ends_at',
                                'Cierre del reto (UTC)',
                                'Si defines ambas fechas, el cierre debe ser posterior al inicio.',
                                false,
                                'datetime-local',
                            )}
                            {select(
                                'participation_eligibility',
                                'Quién puede participar',
                                [
                                    ['ALL_USERS', 'Cualquier usuario'],
                                    ['ACTIVE_MEMBERS', 'Solo miembros activos'],
                                ],
                            )}
                            {select(
                                'reward_eligibility',
                                'Quién puede recibir el premio',
                                [
                                    ['ALL_USERS', 'Cualquier usuario'],
                                    ['ACTIVE_MEMBERS', 'Solo miembros activos'],
                                ],
                                'Participar y ser elegible para el premio son condiciones diferentes.',
                            )}
                        </ProposalSection>
                        <ProposalSection
                            title="5. Premio propuesto"
                            description="JAKAWI revisa el premio y conserva su aprobación y entrega."
                        >
                            {select('reward_type', 'Tipo de premio', [
                                ['BENEFIT', 'Beneficio propio'],
                                ['MANUAL_PRIZE', 'Premio manual'],
                                ['JP', 'Puntos JP'],
                            ])}
                            {form.data.reward_type === 'BENEFIT' &&
                                select(
                                    'benefit_id',
                                    'Beneficio propio elegible',
                                    [
                                        ['', 'Elige uno'],
                                        ...benefits.map(
                                            (b) =>
                                                [
                                                    String(b.id),
                                                    b.title ?? '',
                                                ] as [string, string],
                                        ),
                                    ],
                                    'Solo se muestran beneficios de este Partner configurados para recompensas de retos.',
                                )}
                            {form.data.reward_type === 'MANUAL_PRIZE' &&
                                field(
                                    'manual_prize_description',
                                    'Descripción del premio',
                                    'Describe qué se propone entregar y sus condiciones.',
                                    true,
                                    'text',
                                    true,
                                )}
                            {form.data.reward_type === 'JP' &&
                                field(
                                    'reward_jp_amount',
                                    'Cantidad de JP propuesta',
                                    'Cantidad positiva. Guardarla no genera puntos ni premios.',
                                    false,
                                    'number',
                                    true,
                                    1,
                                )}
                            {form.data.reward_type === 'BENEFIT' &&
                                benefits.length === 0 && (
                                    <p className="text-sm text-muted-foreground sm:col-span-2">
                                        No hay beneficios propios elegibles.
                                        Contacta a JAKAWI para revisar la
                                        propuesta.
                                    </p>
                                )}
                        </ProposalSection>
                        {editable && (
                            <button
                                className={primaryAction}
                                disabled={form.processing}
                            >
                                Guardar borrador
                            </button>
                        )}
                    </fieldset>
                </form>
                <section className="space-y-4 rounded-md border border-border bg-surface p-4 sm:p-6">
                    <h2 className="text-xl font-semibold">
                        6. Revisión y envío
                    </h2>
                    {challenge ? (
                        <>
                            <a
                                className={secondaryAction}
                                href={`${base}/${challenge.slug}/preview`}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Previsualizar reto
                            </a>
                            <p className="text-sm text-muted-foreground">
                                La previsualización usa el contenido guardado y
                                no acepta participaciones.
                            </p>
                            {editable && (
                                <form
                                    className="space-y-4"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        image.post(
                                            `${base}/${challenge.slug}/imagen`,
                                            { onSuccess: () => image.reset() },
                                        );
                                    }}
                                >
                                    <ImageUpload
                                        name="image"
                                        label="Imagen del reto"
                                        currentUrl={heroUrl}
                                        error={
                                            image.errors.image_upload ||
                                            (
                                                image.errors as Record<
                                                    string,
                                                    string
                                                >
                                            ).image
                                        }
                                        disabled={image.processing || imageBusy}
                                        onBusy={setImageBusy}
                                        onChange={(token, remove) =>
                                            image.setData({
                                                image_upload: token,
                                                image_remove: remove,
                                            })
                                        }
                                    />
                                    <button
                                        className={secondaryAction}
                                        disabled={
                                            image.processing ||
                                            imageBusy ||
                                            (!image.data.image_upload &&
                                                !image.data.image_remove)
                                        }
                                    >
                                        Guardar imagen
                                    </button>
                                </form>
                            )}
                            {!editable && heroUrl && (
                                <img
                                    src={heroUrl}
                                    alt={
                                        challenge.hero_alt || 'Imagen del reto'
                                    }
                                    className="max-h-72 w-full rounded-md object-contain"
                                />
                            )}
                            {editable && (
                                <>
                                    <p className="text-sm">
                                        {form.isDirty || image.isDirty
                                            ? 'Guarda los cambios de contenido e imagen antes de enviar.'
                                            : 'Revisa la propuesta guardada antes de enviarla a JAKAWI.'}
                                    </p>
                                    <button
                                        className={primaryAction}
                                        disabled={
                                            form.isDirty ||
                                            image.isDirty ||
                                            form.processing ||
                                            image.processing ||
                                            imageBusy ||
                                            send.processing
                                        }
                                        onClick={() =>
                                            send.post(
                                                `${base}/${challenge.slug}/enviar`,
                                            )
                                        }
                                    >
                                        Enviar a revisión
                                    </button>
                                </>
                            )}
                        </>
                    ) : (
                        <p className="text-sm">
                            Guarda el borrador para habilitar la imagen, la
                            previsualización y el envío a revisión.
                        </p>
                    )}
                </section>
            </div>
        </PartnerHeading>
    );
}
