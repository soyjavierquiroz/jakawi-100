/** Single-landing editorial interchange. Never accepts domain or publication state. */
export const LANDING_JSON_VERSION = 'jakawi-landing-v1';
const fields = {
    internal_name: ['name', 255, true],
    slug: ['slug', 255, true],
    template: ['template_key', ['OFFER_CONVERSION']],
    'presentation.theme': [
        'marketing_theme',
        ['LIGHT_CONVERSION', 'DARK_CONVERSION'],
    ],
    'presentation.hero_layout': ['hero_layout', ['OFFER_FIRST', 'MEDIA_LED']],
    'presentation.density': ['density', ['STANDARD', 'COMPACT']],
    'hero.eyebrow': ['eyebrow', 100],
    'hero.headline': ['headline', 180],
    'hero.headline_emphasis': ['headline_emphasis', 180],
    'hero.supporting_copy': ['subheadline', 1000],
    'value.supporting_copy': ['reward_display_override', 180],
    'acquisition.headline': ['acquisition_headline', 180],
    'acquisition.supporting_copy': ['acquisition_supporting_copy', 1000],
    'closing.headline': ['final_cta_headline', 180],
    'closing.supporting_copy': ['final_cta_description', 1000],
    'media.mode': ['media_mode', ['IMAGE', 'VSL', 'NONE']],
    'media.alt': ['hero_alt', 255],
} as const;
const groups = [
    'presentation',
    'hero',
    'value',
    'acquisition',
    'closing',
    'media',
];
const plain = /[<>`]|\*\*|__|!?\[[^\]]*\]\(|^\s*#+\s/mu;
const object = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null && !Array.isArray(value);
function forbidden(key: string) {
    if (
        /price|precio|date|fecha|membership|eligib|reward|rules|conditions|capacity|availability|location|session|requirement/.test(
            key,
        )
    )
        return 'pertenece al dominio y no puede importarse';
    if (/color|css/.test(key))
        return 'colores y estilos arbitrarios no permitidos';
    return 'no permitido en jakawi-landing-v1';
}
export type LandingJsonResult =
    | { ok: true; patch: Record<string, string>; subjectHint?: string }
    | { ok: false; errors: string[] };
export function subjectHintMismatch(
    hint: string | undefined,
    title: string,
): boolean {
    const normalize = (s: string) =>
        s
            .normalize('NFD')
            .replace(/\p{M}/gu, '')
            .trim()
            .replace(/\s+/g, ' ')
            .toLocaleLowerCase('es');
    return !!hint?.trim() && normalize(hint) !== normalize(title);
}
export function parseLandingJson(
    text: string,
    current: Record<string, string>,
    fallbackHeadline = '',
): LandingJsonResult {
    let data: unknown;
    try {
        data = JSON.parse(text);
    } catch {
        return { ok: false, errors: ['JSON inválido: revisa la sintaxis.'] };
    }
    if (!object(data))
        return {
            ok: false,
            errors: ['La raíz debe ser un objeto JSON, no una lista.'],
        };
    const errors: string[] = [];
    const patch: Record<string, string> = {};
    if (data.schema_version !== LANDING_JSON_VERSION)
        errors.push(`schema_version: debe ser ${LANDING_JSON_VERSION}`);
    if (
        'subject_hint' in data &&
        (typeof data.subject_hint !== 'string' ||
            [...data.subject_hint].length > 255 ||
            plain.test(data.subject_hint))
    )
        errors.push(
            'subject_hint: debe ser texto plano de hasta 255 caracteres',
        );
    const visit = (obj: Record<string, unknown>, prefix = '') => {
        for (const [key, value] of Object.entries(obj)) {
            const path = prefix ? `${prefix}.${key}` : key;
            if (!prefix && (key === 'schema_version' || key === 'subject_hint'))
                continue;
            if (!prefix && groups.includes(key)) {
                if (!object(value)) errors.push(`${path}: debe ser un objeto`);
                else visit(value, key);
                continue;
            }
            if (
                (!prefix && key.includes('.')) ||
                !Object.hasOwn(fields, path)
            ) {
                errors.push(`${path}: ${forbidden(key)}`);
                continue;
            }
            const rule = fields[path as keyof typeof fields];
            if (typeof value !== 'string') {
                errors.push(`${path}: debe ser un string (usa "" para vaciar)`);
                continue;
            }
            if (typeof rule[1] !== 'number') {
                if (!(rule[1] as readonly string[]).includes(value))
                    errors.push(
                        `${path}: valores permitidos: ${rule[1].join(', ')}`,
                    );
            } else {
                if ([...value].length > rule[1])
                    errors.push(`${path}: máximo ${rule[1]} caracteres`);
                if ('2' in rule && rule[2] && !value.trim())
                    errors.push(`${path}: no puede estar vacío`);
                if (path === 'slug' && !/^[\p{L}\p{M}\p{N}_-]+$/u.test(value))
                    errors.push(
                        'slug: solo letras, números, guiones y guiones bajos',
                    );
                if (
                    path !== 'internal_name' &&
                    path !== 'slug' &&
                    plain.test(value)
                )
                    errors.push(
                        `${path}: usa texto plano, sin HTML ni Markdown`,
                    );
            }
            patch[rule[0]] = value;
        }
    };
    visit(data);
    const merged = { ...current, ...patch };
    if (
        merged.headline_emphasis?.trim() &&
        !(merged.headline?.trim() || fallbackHeadline).includes(
            merged.headline_emphasis.trim(),
        )
    )
        errors.push(
            'hero.headline_emphasis: el texto destacado debe estar contenido en el headline efectivo',
        );
    return errors.length
        ? { ok: false, errors }
        : {
              ok: true,
              patch,
              subjectHint: data.subject_hint as string | undefined,
          };
}
/** Stable order; strings reflect unsaved form overrides, not projected domain defaults. */
export function exportLandingJson(
    form: Record<string, string>,
    title: string,
): string {
    const data: Record<string, unknown> = {
        schema_version: LANDING_JSON_VERSION,
        subject_hint: title,
    };
    for (const [path, rule] of Object.entries(fields)) {
        const [group, key] = path.split('.');
        const value = form[rule[0]] ?? '';
        if (!key) data[group] = value;
        else {
            data[group] ??= {};
            (data[group] as Record<string, string>)[key] = value;
        }
    }
    return JSON.stringify(data, null, 2);
}
