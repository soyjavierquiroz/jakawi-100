# AI Product Brief V1

Version: **`jakawi-product-brief-v1`**. Supported domains: **Benefit / Experience / Challenge**. **LIVE** on production `44636ae1c41f5828c702c052b99f3e3b7c778263`. A deterministic, public-safe, single-product output for an external GPT configured once with the [complete JAKAWI copywriter prompt](AI_LANDING_COPYWRITER.md).

## Purpose and source

JAKAWI exports domain truth. It does not generate copy, execute prompts, persist briefs, call AI providers or create Landings automatically. Copying does not change the subject, form, campaign, audience, domain, publication or Landing. Unlock and batch are excluded.

The [builder](../../app/app/Services/LandingAiBriefBuilder.php) receives the current subject and existing Admin/domain presenter projection from [LandingPresentationEditor](../../app/app/Services/LandingPresentationEditor.php). It reuses Benefit locations/access, Experience partner/access projections, Challenge qualification/selection and ChallengeDeliveryPresenter verification boundary. Small explicit model allowlists supplement dates, prices, terms, limits and all upcoming sessions. It never serializes whole models or viewer state. The brief follows a product change in the existing editor.

Common keys, in stable order: `brief_version`, `domain`, `title`, `partner`, `city`, `summary`, `description`, `demo`, `demo_disclosure`, `commercial_truth`, `marketing_context`. Unknown/empty facts are omitted recursively; false and zero remain. Arrays preserve their defined order; dates are ISO 8601 with timezone `America/La_Paz`, the timezone used by the existing product presenters. Clipboard output is valid JSON, UTF-8, with two-space indentation, without fences or surrounding instructions.

`summary` and `description` are only stored public product text, never presenter-generated marketing fallback. A single shared city is emitted at top level; multiple cities remain per location/session. `demo` uses the existing Benefit Product Detail DEMO suffix policy, with its exact disclosure. Other domains only emit `demo: true` when their stored title explicitly carries that marker; no invented false/disclosure policy.

`marketing_context` states the conversion goal, Spanish language and city-first context. It contains no creative recommendation, headline, angle or visual style.

## Benefit example

Illustrative fixture using the acceptance product title. Actual export reads the selected product's current facts; these example dates/locations are not hardcoded.

```json
{
  "brief_version": "jakawi-product-brief-v1",
  "domain": "Benefit",
  "title": "20% en peinado de evento — DEMO",
  "partner": "Luna Lateral — DEMO",
  "city": "Cochabamba",
  "summary": "Un peinado para tu evento.",
  "description": "Servicio de peinado.",
  "demo": true,
  "demo_disclosure": "DEMO DE PRODUCTO · oferta simulada, no canjeable",
  "commercial_truth": {
    "benefit_type": "percentage",
    "value": { "description": "20% en peinado de evento — DEMO" },
    "membership_required": true,
    "access_requirement": "Necesitas una membresía JAKAWI activa para canjear este beneficio.",
    "available": true,
    "validity": {
      "starts_at": "2026-10-01T08:00:00-04:00",
      "ends_at": "2026-12-31T19:00:00-04:00",
      "timezone": "America/La_Paz"
    },
    "locations": [
      { "name": "Estudio", "city": "Cochabamba", "area": "Cala Cala", "address": "Calle Aroma 123" }
    ],
    "usage_limit": { "redemptions_per_member": 1 },
    "requirements": ["Necesitas una membresía JAKAWI activa para canjear este beneficio."],
    "conditions": ["No acumulable. Reserva previa."]
  },
  "marketing_context": {
    "primary_goal": "Conseguir que la persona reclame o active esta oferta específica",
    "landing_language": "es",
    "city_first": true
  }
}
```

**Numeric value boundary:** the current Benefit model has no contractual numeric amount field. The authoritative 20% remains in the exact product title and `value.description`; the builder does not parse it into a newly inferred `amount`. `benefit_type` preserves the canonical stored type. `estimated_savings` is never emitted or used to calculate savings. Reward-grant-only access uses the existing prize requirement; it does not assert ordinary public membership suffices. `available` is the existing domain availability check, separate from DEMO disclosure and per-location redemption validation.

## Experience example

Illustrative stored product facts:

```json
{
  "brief_version": "jakawi-product-brief-v1",
  "domain": "Experience",
  "title": "Taller de cerámica",
  "partner": "Estudio creativo",
  "city": "Cochabamba",
  "description": "Modela una pieza propia.",
  "commercial_truth": {
    "experience_type": "workshop",
    "duration_minutes": 120,
    "prices": { "currency": "BOB", "regular_price": "90.00", "member_price": "70.00" },
    "membership_required": true,
    "access_requirement": "La reserva en JAKAWI requiere una cuenta y una membresía activa.",
    "reservation_mode": "internal",
    "available_sessions": 1,
    "sessions": [
      {
        "starts_at": "2026-11-01T15:00:00-04:00",
        "ends_at": "2026-11-01T17:00:00-04:00",
        "timezone": "America/La_Paz",
        "venue": "Estudio creativo",
        "address": "Avenida Central",
        "city": "Cochabamba",
        "capacity": 20
      }
    ],
    "conditions": ["Materiales incluidos. Reserva previa."]
  },
  "marketing_context": {
    "primary_goal": "Conseguir que la persona reclame o active esta oferta específica",
    "landing_language": "es",
    "city_first": true
  }
}
```

Prices retain stored decimal strings and currency; zero is not omitted. Sessions use the existing upcoming scope (scheduled and future), including all sessions rather than the Landing presenter's six-row preview. Capacity means configured total, never remaining places. `reservation_mode` maps `jakawi` to `internal`, partner booking methods to `external`, and `none` to `informational`. External URLs/phones are never exported. External membership requirements are unknown and omitted. Included/not included or operational facts remain verbatim in stored `conditions`/description; no invented lists extracted from prose.

## Challenge example

Illustrative stored rules, rendered with existing domain semantics:

```json
{
  "brief_version": "jakawi-product-brief-v1",
  "domain": "Challenge",
  "title": "Reto creativo",
  "description": "Comparte tu ciudad.",
  "commercial_truth": {
    "objective": "Publica una foto original.",
    "participation_eligibility": "Personas con una cuenta JAKAWI.",
    "membership_required": false,
    "access_requirement": "Puedes participar gratis; necesitas membresía activa para recibir el premio.",
    "platforms": ["instagram"],
    "evidence_type": "Enlace de una publicación social.",
    "evidence_requirement": "Publica una foto original.",
    "qualification": "Consigue al menos 20 likes en una publicación válida.",
    "validity": { "ends_at": "2026-11-01T19:00:00-04:00", "timezone": "America/La_Paz" },
    "entry_limit_per_person": 2,
    "selection": "Los primeros 10 participantes verificados reciben el premio.",
    "winner_limit": 10,
    "reward": "Un taller creativo",
    "reward_access_requirement": "Membresía JAKAWI activa para recibir el premio.",
    "review_required": true,
    "verification_boundary": "Confirma tu correo antes de enviar evidencia o recibir una recompensa."
  },
  "marketing_context": {
    "primary_goal": "Conseguir que la persona reclame o active esta oferta específica",
    "landing_language": "es",
    "city_first": true
  }
}
```

Membership for participation and reward are distinct. Platforms, hashtags and public required mentions appear only for social evidence. Reward uses the stored manual description, JP amount or linked Benefit title, never reward IDs. Verification is stated in the existing commercial language, without customer email addresses or implementation enums.

## Safety exclusions

No database IDs, UUIDs, public IDs, primary keys, slugs, model classes, storage paths, secrets, HMAC, admin/signed URLs, private review notes, emails, phones, users/customers, viewer state, booking URLs, campaign or audience. Explicit allowlists exclude operational/private columns; defense in depth also omits a public text field containing an email, phone, URL or UUID rather than exporting contact data or rewriting commercial facts. This is an output presenter, not a generalized anonymization system.

## Operator workflow

JAKAWI → **COPIAR BRIEF PARA IA** → external GPT → `jakawi-landing-v1` → **IMPORTAR JSON** → review → media → preview → publish. Saving remains an explicit Admin action. Batch: **FUTURE**. Welcome Courtesy: **FUTURE / OFF**.

Configure the GPT once with [AI_LANDING_COPYWRITER.md](AI_LANDING_COPYWRITER.md). Each time, select/open one product's Landing editor and copy its brief. One click copies the JSON and shows **✓ BRIEF COPIADO**. Microcopy: “Pégalo en tu copywriter JAKAWI y vuelve con un jakawi-landing-v1 para importar.” Clipboard failure opens a read-only manual-copy fallback. No mandatory preview dialog. Paste only the brief to the external GPT, then import its JSON using [Landing JSON V1](LANDING_JSON_V1.md). Manually composed JSON remains supported. No automated QA invokes external AI.

Live release evidence: [production release](../../artifacts/ai-product-brief-v1/production-release.json) and [Admin smoke](../../artifacts/ai-product-brief-v1/live/admin-browser.json). The docs-only status commit does not rebuild or redeploy application images.
