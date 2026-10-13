# Landing JSON V1 — canonical external editorial contract

**LIVE** on current production `44636ae1c41f5828c702c052b99f3e3b7c778263`; originally deployed on `19c946870a6a54981acc8b41f4ef0b56188226fe`. Version: **`jakawi-landing-v1`**. Applies to the existing Benefit, Experience and Challenge Landing editor. Unlock is excluded. OfferConversionTemplate V3 stays LIVE + FROZEN. This adds convenience to the existing Publisher, not another publishing system.

## Purpose and workflow

Normal production workflow: JAKAWI → COPIAR BRIEF PARA IA → external GPT configured once with the [master prompt](AI_LANDING_COPYWRITER.md) → jakawi-landing-v1 → IMPORTAR JSON → UNSAVED EDITOR → review/media → GUARDAR Y PREVISUALIZAR → saved Preview → PUBLICAR. Operators normally paste only the [product brief](AI_PRODUCT_BRIEF_V1.md), without manually composing JSON. Manual JSON and partial imports remain supported. No AI API, server upload, automatic save on import or publication. Select an existing product in JAKAWI first; importing cannot select or change it.

In the editor, IMPORTAR JSON opens a paste dialog. VALIDAR Y APLICAR validates the entire candidate before changing React form state. Errors list incompatible paths; no field changes on failure. CANCELAR discards the import. A differing nonempty `subject_hint` shows both titles before applying; APLICAR DE TODAS FORMAS permits it without changing the subject. Matching ignores case, accents and repeated whitespace.

EXPORTAR JSON exports **current unsaved form state**, with two-space indentation and the key order shown below. COPIAR JSON copies it; DESCARGAR .JSON downloads a client-side Blob. No backend export file is created. Roundtrip: export → manually change editorial fields → import exported JSON restores those fields. Audience, campaign, subject and attached media sources remain unchanged.

## Allowed fields, exact enums and limits

Only `schema_version` is required. All other keys, including groups, are optional. All leaf values are strings; group values are objects. No nulls or arrays. Unknown keys at any depth reject the **entire import**, even when accompanied by valid keys.

| JSON path | Existing form/storage field | Values / maximum characters |
| --- | --- | --- |
| schema_version | Contract only | Exactly `jakawi-landing-v1` |
| subject_hint | Informational only | Plain text, 255; never selects a subject |
| internal_name | name | Nonempty string, 255 |
| slug | slug | Nonempty letters/numbers/underscore/hyphen, 255; uniqueness checked on Save |
| template | template_key | `OFFER_CONVERSION` |
| presentation.theme | marketing_theme | `LIGHT_CONVERSION`, `DARK_CONVERSION` |
| presentation.hero_layout | hero_layout | `OFFER_FIRST`, `MEDIA_LED` |
| presentation.density | density | `STANDARD`, `COMPACT` |
| hero.eyebrow | eyebrow | Plain text, 100 |
| hero.headline | headline | Plain text, 180 |
| hero.headline_emphasis | headline_emphasis | Plain text, 180; substring of effective headline |
| hero.supporting_copy | subheadline | Plain text, 1000 |
| value.supporting_copy | reward_display_override | Plain text, 180; complementary editorial copy, never a reward override |
| acquisition.headline | acquisition_headline | Plain text, 180 |
| acquisition.supporting_copy | acquisition_supporting_copy | Plain text, 1000 |
| closing.headline | final_cta_headline | Plain text, 180 |
| closing.supporting_copy | final_cta_description | Plain text, 1000 |
| media.mode | media_mode | `IMAGE`, `VSL`, `NONE` |
| media.alt | hero_alt | Plain text, 255 |

### Missing versus empty

Missing key: leave the current field unchanged. Present optional text key with `""`: explicitly clear the editable override. Empty name, slug or enum is invalid. Empty editorial fields retain existing renderer semantics: product fallback where applicable; empty closing description is omitted. Export emits every allowed form field, including empty overrides. It does not export effective domain-derived fallback values instead of those overrides.

Validation uses the merged candidate: omitted headline/emphasis still participates in the existing substring rule; a cleared headline uses the selected product's default headline. Text uses the existing Admin HTML/Markdown rejection patterns. Server Save remains authoritative for commercial claims, slug uniqueness and configured VSL. Import is a convenience, not a security boundary or a promise that Save will accept commercial claims. Existing server validation remains authoritative. Workflow UX V1 adds clear error summaries and saved Preview refresh; see [Publisher workflow](PUBLISHER_WORKFLOW_UX_V1.md).

## Forbidden and domain safety

Every unlisted key is forbidden, including `subject_id`, `subject_type`, `benefit_id`, `experience_id`, `challenge_id`, `unlock_id`, IDs, timestamps, `editorial_version`, `status`, `publish_scope`, `default_scope`, `audience`, `campaign_key`.

Domain stays authoritative for prices/member prices/percentages/amounts/rewards, dates, sessions, locations/addresses, partners/providers, availability/capacity, membership/access/eligibility, requirements/rules/conditions, redemption/reservation semantics, Challenge mechanics, Unlock disclosure and economic Campaign. Do not put these facts in JSON as configuration or invent them in copy. `value.supporting_copy` is marketing annotation only.

No action label/kind/URL/payload/POST endpoint, CTA override, arbitrary CSS/colors, HTML/Markdown, React sections, asset IDs, image/upload paths, VSL source URL (YouTube/MP4/Bunny) or media source configuration. For example `price` and `membership_required` receive domain-specific errors; `cta_url` is not permitted; `background_color` receives a color/style error.

`media.mode` and `media.alt` are allowed. Human/Admin attaches media separately. Import preserves existing image path and complete VSL configuration, including legacy settings, regardless of mode changes. Missing media can generate a reminder; selecting VSL without a configured video still fails the existing Save validation.

## Complete valid example

```json
{
  "schema_version": "jakawi-landing-v1",
  "subject_hint": "Plan en tu ciudad",
  "internal_name": "Plan editorial",
  "slug": "plan-editorial",
  "template": "OFFER_CONVERSION",
  "presentation": {
    "theme": "LIGHT_CONVERSION",
    "hero_layout": "OFFER_FIRST",
    "density": "STANDARD"
  },
  "hero": {
    "eyebrow": "Descubre JAKAWI",
    "headline": "Vive tu ciudad",
    "headline_emphasis": "tu ciudad",
    "supporting_copy": "Encuentra tu próximo plan y conoce los detalles del producto."
  },
  "value": {
    "supporting_copy": "Explora lo que hace especial este plan."
  },
  "acquisition": {
    "headline": "Comienza con JAKAWI",
    "supporting_copy": "Crea tu cuenta para continuar."
  },
  "closing": {
    "headline": "Tu próximo plan empieza aquí",
    "supporting_copy": ""
  },
  "media": {
    "mode": "NONE",
    "alt": ""
  }
}
```

## Partial valid example

```json
{
  "schema_version": "jakawi-landing-v1",
  "hero": {
    "headline": "Descubre tu próximo plan",
    "headline_emphasis": "próximo plan",
    "supporting_copy": "Explora los detalles y decide cómo continuar."
  }
}
```

The rest of the form is untouched. When replacing a headline, include a compatible emphasis or `""` if the current emphasis would no longer match.

## Invalid example

```json
{
  "schema_version": "jakawi-landing-v1",
  "hero": { "eyebrow": "Descubre" },
  "price": 50,
  "cta_url": "https://example.com/action",
  "membership_required": false,
  "background_color": "#ff00ff"
}
```

All four forbidden keys are reported. Even the valid eyebrow is **not applied**.

## External GPT configuration

Configure the GPT once with the complete [JAKAWI copywriter master prompt](AI_LANDING_COPYWRITER.md). For each product, paste only the JSON from COPIAR BRIEF PARA IA. A reference Landing, exported example and repeated schema paste are not required. JAKAWI does not call or run the GPT.

## Current limitation and roadmap

Acquisition copy imports/exports the existing persisted `acquisition_headline` and `acquisition_supporting_copy`. **Micro Opt-in does not yet consume them.** Wiring them is a separate next task, outside V1.

V1: single-Landing JSON import/export. AI Product Brief V1: COPIAR BRIEF PARA IA derived from domain truth, **LIVE** for Benefit / Experience / Challenge; output-only, no AI calls. Future only after individual V1 survives real usage: `jakawi-landing-batch-v1` for batch imports/draft creation. No arrays, bulk creation, product lookup or multi-product assignment now. Welcome Courtesy: **FUTURE / OFF**.

See the authoritative [Marketing System Audit](../architecture/MARKETING_SYSTEM_AUDIT.md), [parser/exporter](../../app/resources/js/lib/landing-json.ts), [editor controls](../../app/resources/js/components/marketing/LandingJsonControls.tsx) and [focused tests](../../app/tests/marketing/landing-json.test.mjs).
