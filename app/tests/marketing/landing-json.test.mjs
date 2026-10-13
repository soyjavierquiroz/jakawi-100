import test from 'node:test';
import assert from 'node:assert/strict';
const { parseLandingJson, exportLandingJson, subjectHintMismatch } =
    await import(process.env.LANDING_JSON_MODULE);
const form = {
    name: 'Draft',
    slug: 'draft',
    template_key: 'OFFER_CONVERSION',
    marketing_theme: 'LIGHT_CONVERSION',
    hero_layout: 'OFFER_FIRST',
    density: 'STANDARD',
    eyebrow: 'Descubre',
    headline: 'Vive tu ciudad',
    headline_emphasis: 'ciudad',
    subheadline: 'Encuentra tu próximo plan',
    reward_display_override: '',
    acquisition_headline: 'Comienza aquí',
    acquisition_supporting_copy: 'Crea tu cuenta',
    final_cta_headline: 'Vamos',
    final_cta_description: '',
    media_mode: 'IMAGE',
    hero_alt: 'Vista del plan',
    default_scope: 'ALL',
    campaign_key: 'campaign',
    status: 'DRAFT',
    subject_id: '99',
    price: '50',
    hero_path: '/stored.jpg',
    vsl: 'stored video',
};
const parse = (data, current = form) =>
    parseLandingJson(
        JSON.stringify({ schema_version: 'jakawi-landing-v1', ...data }),
        current,
        'Título del producto',
    );
test('full JSON maps exactly the seventeen existing editorial fields', () => {
    const result = parseLandingJson(exportLandingJson(form, 'Producto'), {});
    assert(result.ok);
    assert.equal(Object.keys(result.patch).length, 17);
    for (const [key, value] of Object.entries(result.patch))
        assert.equal(value, form[key]);
});
test('partial import changes only supplied fields', () => {
    const result = parse({ hero: { supporting_copy: 'Nuevo mensaje' } });
    assert(result.ok);
    assert.deepEqual(result.patch, { subheadline: 'Nuevo mensaje' });
});
test('missing fields preserve form and media sources', () => {
    const result = parse({
        presentation: { theme: 'DARK_CONVERSION' },
        media: { mode: 'NONE' },
    });
    assert(result.ok);
    const merged = { ...form, ...result.patch };
    assert.equal(merged.headline, form.headline);
    assert.equal(merged.hero_path, form.hero_path);
    assert.equal(merged.vsl, form.vsl);
});
test('empty optional string clears; required strings and null reject', () => {
    const result = parse({
        closing: { supporting_copy: '' },
        hero: { eyebrow: '' },
    });
    assert(result.ok);
    assert.equal(result.patch.eyebrow, '');
    for (const data of [
        { internal_name: '' },
        { slug: '' },
        { hero: { headline: null } },
    ])
        assert.equal(parse(data).ok, false);
});
test('unknown nested and top-level fields reject entire import without mutation', () => {
    assert.equal(parse({ 'hero.headline': 'ciudad' }).ok, false);
    const before = structuredClone(form);
    const result = parse({
        hero: { headline: 'ciudad nueva', invented: 'x' },
        unknown: true,
    });
    assert(!result.ok);
    assert.equal('patch' in result, false);
    assert.equal(result.errors.length, 2);
    assert.deepEqual(form, before);
});
test('domain, action, publication, subject and arbitrary style keys reject', () => {
    for (const key of [
        'price',
        'member_price',
        'validity',
        'prices',
        'dates',
        'membership_required',
        'eligibility',
        'reward',
        'rules',
        'subject_id',
        'subject_type',
        'benefit_id',
        'experience_id',
        'challenge_id',
        'unlock_id',
        'cta_url',
        'action',
        'background_color',
        'status',
        'audience',
        'default_scope',
        'publish_scope',
        'campaign_key',
        'editorial_version',
        'hero_path',
        'vsl',
    ])
        assert.equal(parse({ [key]: 'x' }).ok, false, key);
});
test('enums, template, JSON and object shapes are strict', () => {
    for (const data of [
        { template: 'OTHER' },
        { presentation: { theme: 'LIGHT' } },
        { presentation: { hero_layout: '' } },
        { presentation: { density: 'SMALL' } },
        { media: { mode: 'VIDEO' } },
        { hero: [] },
        { schema_version: 'v2' },
    ])
        assert.equal(parse(data).ok, false);
    for (const text of ['{', '[]', 'null', '{}'])
        assert.equal(parseLandingJson(text, form).ok, false);
});
test('subject hint warns before apply, informational only, normalized match allowed', () => {
    const result = parse({ subject_hint: 'Otro producto' });
    assert(result.ok);
    assert.deepEqual(result.patch, {});
    assert(subjectHintMismatch(result.subjectHint, 'Producto actual'));
    assert(!subjectHintMismatch('  CAFÉ   Local ', 'cafe local'));
    assert(!subjectHintMismatch('', 'Producto'));
});
test('export uses current unsaved state and excludes all other state', () => {
    const data = JSON.parse(
        exportLandingJson(
            { ...form, acquisition_headline: 'Sin guardar' },
            'Producto',
        ),
    );
    assert.equal(data.schema_version, 'jakawi-landing-v1');
    assert.equal(data.acquisition.headline, 'Sin guardar');
    assert.deepEqual(Object.keys(data), [
        'schema_version',
        'subject_hint',
        'internal_name',
        'slug',
        'template',
        'presentation',
        'hero',
        'value',
        'acquisition',
        'closing',
        'media',
    ]);
    assert.deepEqual(Object.keys(data.media), ['mode', 'alt']);
    for (const key of [
        'default_scope',
        'campaign_key',
        'status',
        'subject_id',
        'price',
        'hero_path',
        'vsl',
    ])
        assert(!Object.hasOwn(data, key));
});
test('export/manual change/import restores semantic editorial equivalence', () => {
    const result = parseLandingJson(exportLandingJson(form, 'Producto'), {
        ...form,
        headline: 'Changed',
        headline_emphasis: '',
        media_mode: 'NONE',
    });
    assert(result.ok);
    const restored = { ...form, headline: 'Changed', ...result.patch };
    assert.equal(
        exportLandingJson(restored, 'Producto'),
        exportLandingJson(form, 'Producto'),
    );
});
test('import contract has no save or publish path and does not mutate input', () => {
    const before = structuredClone(form);
    const result = parse({ acquisition: { headline: 'Empieza' } });
    assert(result.ok);
    assert.deepEqual(form, before);
    assert.deepEqual(Object.keys(result.patch), ['acquisition_headline']);
});
test('existing plain text, length and effective emphasis constraints apply to merged candidate', () => {
    for (const headline of [
        '<b>ciudad</b>',
        '**ciudad**',
        '# ciudad',
        'ciudad'.repeat(40),
        'Nuevo mensaje',
    ])
        assert(!parse({ hero: { headline } }).ok);
    assert(!parse({ hero: { headline_emphasis: 'ausente' } }).ok);
    assert(parse({ hero: { headline: '', headline_emphasis: 'producto' } }).ok);
    assert(
        parse({ hero: { headline: 'Nuevo mensaje', headline_emphasis: '' } })
            .ok,
    );
});

test('Admin error summaries use human labels including value copy and nested/unknown controls', async () => {
    const { landingEditorFieldLabel } = await import(process.env.LANDING_EDITOR_LABELS_MODULE);
    assert.equal(landingEditorFieldLabel('reward_display_override'), 'Copy complementario del valor');
    assert.equal(landingEditorFieldLabel('subheadline'), 'Descripción editorial');
    assert.equal(landingEditorFieldLabel('final_cta_description'), 'Texto de apoyo final');
    assert.equal(landingEditorFieldLabel('acquisition_headline'), 'Headline al crear cuenta');
    assert.equal(landingEditorFieldLabel('vsl.source.url'), 'Configuración de video');
    assert.equal(landingEditorFieldLabel('unknown_internal_field'), 'Configuración de la landing');
});
