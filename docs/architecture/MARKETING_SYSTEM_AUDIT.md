# Marketing / Acquisition / Activation / Delivery — audit V1

**CURRENT CODE / AUTHORITATIVE.** Publisher Workflow UX V1 + AI Product Brief V1 + Landing JSON V1 LIVE update, 2026-10-13 (America/La_Paz). Original static audit checkout: `9c1708f`; implementation baseline `7cdc8c1`. The original audit was documentation only. Verification Continuity V1 starts from clean `origin/main` `158769f01010e521377a47e0e3e62858fd51cdc1` and adds scoped runtime changes plus isolated validation. Current production is `0a0d4b6939ae914ffe9face7436d099e1cfac6a4` (Publisher Workflow UX V1 + AI Product Brief V1 + Landing JSON V1), deployed from actual runtime `44636ae1c41f5828c702c052b99f3e3b7c778263` with that rollback protected. Branded Delivery previously deployed from `715af599073e359509ef7c68e188d2aba4b4da20`. Verification Continuity was previously deployed from `7cdc8c12e71b23e6c6b99db522e2e6274be6b87f`. “Active” means a current route/import/caller exists, not measured production traffic. The original static audit did not inspect saved production records, applied migrations, environment overrides or current deployment; the subsequent scoped release evidence is linked below. Release artifacts are historical evidence, not a new health check.

## 1. Current architecture and verdict

**2. CONFIGURABLE SINGLE-TEMPLATE SYSTEM.** Benefit, Experience and Challenge share `OfferConversionTemplate`; `LandingTemplate` allows only `OFFER_CONVERSION`. V3 is the current composition and LIVE + FROZEN product policy; V1 remains the TypeScript contract name, not an older selectable template. No multi-template registry, section builder or persisted section schema exists. Unlock has a separate active renderer, outside Offer Conversion.

```text
DOMAIN (Benefit / Experience / Challenge / Unlock)
    ↓
LandingPresentation (polymorphic subject, editorial/media/attribution)
    ↓
PHP domain presenter → frontend domain adapter → OfferPresentationV1
    ↓
OfferConversionTemplate → /l/{slug}
    ↓
Acquisition only if guest (AcquisitionProvider: name → email → WhatsApp / login)
    ↓
Activation only if genuine choice [intended policy; exceptions below]
    ↓
Domain mutation / external Experience informational handoff
    ↓
Delivery (authorized outcome / explicit external-reservation instructions)
    ↓
JAKAWI ecosystem [shared fixed shell in Brand + Unify Delivery V1; LIVE]
```

Unlock substitutes `UnlockMarketingLandingPresenter → landing-presentations/unlock.tsx` for the Offer adapter/template. Product Detail is a **functional fallback**, with operational renderers also reused in conversion. Welcome Courtesy is **FUTURE / OFF / NOT IMPLEMENTED**; no implementation or membership bypass was found.

Primary evidence: [routes](../../app/routes/web.php), [controller](../../app/app/Http/Controllers/LandingPresentationController.php), [template enum](../../app/app/Enums/LandingTemplate.php), [contract](../../app/resources/js/components/marketing/offer/presentation.ts), [template](../../app/resources/js/components/marketing/offer/OfferConversionTemplate.tsx), [conversion page](../../app/resources/js/pages/landing-presentations/conversion.tsx), [layout selection](../../app/resources/js/app.tsx).

## 2. Landing domain inventory

| Piece | Classification | Current evidence / semantics |
| --- | --- | --- |
| `LandingPresentation` | ACTIVE / KEEP — CORE | `subject(): MorphTo`, slug route key; configuration casts; default `NONE`, editorial version 0. |
| Subject relationships | ACTIVE / KEEP — CORE | Benefit, Experience, Challenge, Unlock expose `landingPresentations(): MorphMany`; no explicit morph alias map found in providers. |
| Challenge `presentations()` alias | LEGACY / REMOVE CANDIDATE | Only declaration found in app code; current callers use `landingPresentations()`. Check external integrations before removal. |
| Status | ACTIVE | DB constraint DRAFT/PUBLISHED/ARCHIVED. Public route requires PUBLISHED; admin publish checks subject visibility (Benefit also partner). Unpublish DRAFT/NONE; archive ARCHIVED/NONE. |
| `default_scope` | ACTIVE | NONE = direct link only; GUESTS = native guest resolution; ALL = guest/authenticated resolution. It is not an access gate on direct `/l`. |
| `publish_scope` | ACTIVE | Stores intended scope for drafts/duplicates. Publishing applies it transactionally; changes lock subjects/presentations. |
| Default resolution | ACTIVE | `ProductLandingResolver::forViewer`: PUBLISHED, guest GUESTS/ALL (GUESTS ordered first), authenticated ALL. Unique partial DB index allows only one active default per subject. |
| Direct `/l/{presentation:slug}` | ACTIVE | Public, not guest middleware. Subject visibility remains authoritative. ALL canonical is landing; other scopes native canonical + noindex; preview noindex. |
| Native → Landing-first | ACTIVE | `ResolveProductLanding` on Benefit/Experience/Challenge/Unlock GET detail; preserves query attribution; listing/card destinations use resolver. No applicable landing → native renderer. |
| `campaign_key` | ACTIVE | Saved presentation, slug-like validation; attribution/Growth on public view, suppressed preview/prefetch/partial view. Incoming query does not override stored key. Not economic Campaign. |
| `is_default` | LEGACY SCHEMA, NOT LIVE FIELD | Removed by default-scope migration; occurrences remain in migrations and migration tests. Keep migration history. |

[Defaults service](../../app/app/Services/LandingPresentationDefaults.php) prevents competing defaults. Migration chain (all KEEP — CORE historical schema): `2026_10_08_000001_create_landing_presentations` → `2026_10_08_000003_add_landing_default_scope` → `2026_10_11_000001_add_vsl_to_landing_presentations` → `2026_10_12_000001_add_offer_presentation_settings` → `000002_add_offer_layout_controls` → `000003_add_acquisition_copy_to_landing_presentations`, all in `app/database/migrations`. Layout/density are nullable strings validated by Admin, unlike template/theme/media DB constraints. Challenge editorial fields also exist on the subject through `2026_10_08_000002_add_challenge_marketing_editorial`.

## 3. Template and reusable pieces

[PHP presenters](../../app/app/Services/LandingPresentationEditor.php) derive offer/rules/actions; frontend [Benefit](../../app/resources/js/pages/landing-presentations/adapters/benefit-offer-adapter.tsx), [Experience](../../app/resources/js/pages/landing-presentations/adapters/experience-offer-adapter.tsx), [Challenge](../../app/resources/js/pages/landing-presentations/adapters/challenge-offer-adapter.tsx) adapters bind actions and build the ephemeral `OfferPresentationV1`. Landing page entry files re-export these adapters. No parallel V1/V2/V3 Offer renderer files found.

Shared `offer/blocks.tsx`: `OfferHero`, `MarketingValueBlock`, `MarketingFactStrip`, `MarketingPrices`, `MarketingSteps`, `MarketingFinalCTA`, `OptionalMarketingMedia`; `MarketingPrimaryCTA` is an alias of `MarketingCTA`, not another CTA system. `composition.ts` removes repeated value/FAQ content. `primitives.tsx` supplies CTA/FAQ plus still-used older hero/benefits/social-proof primitives for Unlock, Partners and Programs. Keep them.

Template order is fixed: notice → hero/media/value/facts/prices/conditions/action → optional steps → required `renderOperationalContent` → FAQ → final CTA → optional after-final. Additional frontend slots: hero annotation/support, value supplement; action refs support Challenge sticky CTA. Slots are bound in code, not Admin-editable React or CMS sections.

Controls: LIGHT_CONVERSION/DARK_CONVERSION; OFFER_FIRST/MEDIA_LED; STANDARD/COMPACT. Null controls retain legacy domain treatment defaults (plain/editorial/compact). `editorial_version=0` preserves older copy handling; version 1 applies validated editorial projection. These compatibility branches are not proven dead without inspecting records.

Media: IMAGE uses hero path/domain image through `MediaUrl` and `JakawiImage`; VSL uses nullable JSON through `LandingVideoConfiguration → LandingVideo → JakawiVslPlayer`; NONE omits media. Sources youtube/mp4/bunny, framing embedded-vertical/native-vertical/horizontal, muted/off autoplay, poster. Offer wrappers force inherit theme. Admin disallows new arbitrary VSL colors and preserves existing custom configuration; Unlock never serializes media/poster before authorized disclosure. No new player needed.

## 4. Publisher and full field map

[Admin controller](../../app/app/Http/Controllers/AdminLandingPresentationController.php) + [form](../../app/resources/js/pages/admin/landing-presentations/index.tsx) + [editor](../../app/app/Services/LandingPresentationEditor.php). `/admin/landings` selects Benefit/Experience/Challenge; per-subject routes save/edit/duplicate/publish/unpublish/archive/default/preview. Unlock uses per-subject management, not global subject selection. Auth + verified + admin protects routes.

“Preview” below means saved landing iframe (390/430/1440) with business actions disabled; it is not a working acquisition/activation preview. Storage prefix LP = `landing_presentations`.

| Field | Storage | Used by renderer? | Previewed? | Domain truth or marketing? | Still useful? / notes |
| --- | --- | --- | --- | --- | --- |
| subject type/id | LP morph columns | Yes, selects presenter/page | Yes | Domain reference | Yes; selector existing products; no copied economics. Unlock reassignment prohibited. |
| name | LP.name | Admin / metadata | Admin label | Marketing internal | Yes; not hero copy. |
| slug | LP.slug unique | Route/links | Saved preview URL | Marketing routing | Yes. |
| status | LP.status | Public guards, Admin list | Preview can render draft | Lifecycle | Yes; actions, not free input; save prohibits status. |
| audience (`default_scope`) | LP.default_scope + LP.publish_scope | Resolver/SEO, not direct-link exclusion | Controls shown; viewer routing not simulated | Marketing routing | Yes; saved intent applied on publish. |
| template | LP.template_key | Allowlist validation | Yes | Marketing | Yes; only OFFER_CONVERSION; no meaningful template choice. Not allowed for Unlock. |
| theme | LP.marketing_theme | MarketingLayout + template | Yes | Marketing | Yes; two enums, not arbitrary colors. |
| hero layout | LP.hero_layout | Template grid/treatment | Yes | Marketing | Yes; OFFER_FIRST/MEDIA_LED. Offer domains only. |
| density | LP.density | Template + adapter details | Yes | Marketing | Yes; STANDARD/COMPACT. Offer domains only. |
| eyebrow | LP.eyebrow | Yes, version 1 projection; presenter defaults otherwise | Yes | Marketing | Yes. |
| headline | LP.headline | Yes; title/derived fallback | Yes | Marketing | Yes; plain text, protected claims. |
| headline emphasis | LP.headline_emphasis | `OfferHero` matching span | Yes | Marketing | Yes; must be contained in effective headline; not Unlock. |
| supporting copy | LP.subheadline | Hero description (version 1 verbatim) | Yes | Marketing | Yes; older Challenge presenter appends domain qualifications; version 1 replaces that copy only. |
| media mode | LP.media_mode | IMAGE/VSL/NONE | Yes | Marketing | Yes; old configs retained across mode changes. Offer only. |
| image source | LP.hero_path | MediaUrl, fallback subject image | Yes if IMAGE | Marketing visual | Yes; reference path, not structured offer. |
| image alt | LP.hero_alt | Image alt, subject fallback | Yes if image | Marketing accessibility | Yes. |
| VSL source type/url | LP.vsl.source | LandingVideo/JakawiVslPlayer | Yes, disclosure permitting | Marketing media | Yes; youtube/mp4/bunny HTTPS validated. |
| VSL framing/autoplay | LP.vsl | Player configuration | Yes | Marketing media | Yes; inputs in LandingVideoFields. |
| VSL poster | LP.vsl.poster | Player configuration | Yes if configured | Marketing media | API/storage supported; **no poster input in current LandingVideoFields**. Preserve existing value. |
| VSL mode/theme/colors | LP.vsl | Player; Offer forces inherit | Effective media only | Marketing media | Compatibility; fixed VSL mode, no new color editor. |
| reward display override | LP.reward_display_override | Complementary `rewardNote` / `valueNote` | Yes | Marketing annotation | Yes; does not replace reward/economics. |
| final headline | LP.final_cta_headline | finalCta.headline | Yes | Marketing | Yes. |
| final supporting copy | LP.final_cta_description | version 1 finalCta.description | Yes | Marketing | Yes; empty omitted; not Unlock. |
| acquisition headline | LP.acquisition_headline | **No in current micro opt-in**; projected by LandingAcquisition | Admin text sample only | Marketing | Partial; context.headline is never read by modal. |
| acquisition supporting copy | LP.acquisition_supporting_copy | **No in current micro opt-in**; projected by LandingAcquisition | Admin text sample only | Marketing | Partial; context.supportingCopy never read by modal. |
| campaign key | LP.campaign_key | Attribution/Growth, not visible copy | Stored only; no tracking on preview | Acquisition attribution | Yes; duplicate copies or edits it. |
| publish scope | LP.publish_scope | Applied to default_scope by publishing | Admin audience control | Marketing routing | Yes; implicit storage, not separate input. |
| editorial version | LP.editorial_version | Compatibility projection | Yes via effective copy | Internal migration state | Yes; automatic, prohibited user field. |

Editor forbids arbitrary action/CTA URL/kind/payload/endpoint, CSS, sections, prices, eligibility and rules. Plain text forbids HTML/Markdown. New editorial commercial claims (digits, percentages, guaranteed/free/access phrases) are restricted; changing only theme/media does not upgrade old copy version. Subject read-only summary derives Benefit value/partner/access/validity, Experience dates/place/prices/reservation, Challenge reward/deadline/qualification/selection/access.

**Dynamic Landing creation today: YES for the existing fixed structure in Benefit/Experience/Challenge. PARTIAL for fully editable structured content and the acquisition copy promise.** Create domain product → select in existing form → save → preview → publish already works by code contract. Existing PHP/adapter defaults fill FAQ/steps/conditions; no code change is needed for another ordinary product. Do not rebuild a Publisher to gain capabilities already present.

Single-Landing JSON import/export V1 and AI Product Brief V1 are **LIVE** for the existing Benefit/Experience/Challenge editor on `44636ae1c41f5828c702c052b99f3e3b7c778263`. COPIAR BRIEF PARA IA exports deterministic public-safe domain truth for an external GPT; no AI backend or provider calls. [Brief contract](../marketing/AI_PRODUCT_BRIEF_V1.md) and [release evidence](../../artifacts/ai-product-brief-v1/production-release.json). It only patches current editorial form fields; subject, domain truth, audience, campaign, publication and media sources are excluded. Canonical external contract and prompt: [Landing JSON V1](../marketing/LANDING_JSON_V1.md). [Release guard](../operations/PRODUCTION_ASSET_GUARD.md) rejects actual loopback runtime URLs and Vite refs, preserving benign WebAuthn validation text. No AI integration, new Publisher, schema change or Unlock expansion. Acquisition copy remains unconsumed by Micro Opt-in.

## 5. Domain truth → adapter → activation → delivery

| Domain | Domain truth / landing adapter | Activation and branching | Mutation / Delivery | Status |
| --- | --- | --- | --- | --- |
| Benefit | Benefit/Partner/available Locations/access/terms/limits; `BenefitMarketingLandingPresenter → BenefitOfferAdapter` | `benefits/show.tsx` continuationOnly: location selection, membership gate; one location auto-selected. Authenticated member with single location uses landingDirectAction. | `RedemptionController::start → RedemptionService`; owned `/canjes/{public_id}` → `redemptions/show.tsx`, QR/code/expiry/address/terms; confirmed savings distinct. | ACTIVE; fallback and operational view required. |
| Experience | Experience/partners/upcoming sessions/prices/reservation_method; `ExperienceMarketingLandingPresenter → ExperienceOfferAdapter` | `experiences/show.tsx` continuationOnly: date/session and membership where internal. Single-session internal member has direct POST shortcut. External or guest continuation can still display activation for one session. | `ExperienceReservationController → ExperienceReservationService` for JAKAWI; `/resultado` owned pending/confirmed result. External result validates session/targets, links to PublicController::reserve; no local reservation claimed. | ACTIVE; internal POST requires verified email, contextually in Landing Activation; external result does not. |
| Challenge | Challenge rulesContract, evidence/qualification/selection/reward/access; `ChallengeMarketingLandingPresenter → ChallengeOfferAdapter` | `requiresParticipationChoice()` always false. Explicit authenticated CTA POST joins; final guest opt-in carries PARTICIPATE and afterAuthentication joins if eligible. Activation GET redirects to landing or owned result. | `ChallengeService::join`; `/resultado` → `ChallengeShow conversionOnly → ChallengeDelivery` + `ChallengeDeliveryPresenter`; evidence/review/grant remain domain actions. | ACTIVE; no-choice confirmation interstitial unreachable today. |
| Unlock | Unlock state/target/capacity/eligibility/JP/disclosure; `UnlockMarketingLandingPresenter → UnlockLanding` (no Offer adapter) | `unlocks/show.tsx` continuationOnly action panel; JP acknowledgement if deposit; eligibility/membership; zero-deposit SUMARME shortcut for auth. | `UnlockController → UnlockParticipationService`; owned result shows commitment pending goal or authorized revealed result; retain confirm/cancel/fulfillment actions. | ACTIVE separate renderer; contextual mutation verification and JP flag apply. |

Business branching lives in PHP presenters/controllers/services, frontend domain adapters and native operational pages. `OfferConversionTemplate` does not inspect membership, participation, evidence, dates or grants: **no domain-semantics violation found in generic template**. Boundary debt: controller/frontend use `action.label` comparisons (USAR BENEFICIO/SUMARME) for direct shortcuts; acquisition provider includes domain label hacks `/café/` and `/sunset/`. These should stay out of the generic template and are candidates for future explicit action contracts, not a reason to replace it.

## 6. Acquisition reality and verification boundaries

[AcquisitionProvider](../../app/resources/js/components/marketing/acquisition-context.tsx) is the one landing identity modal, installed by MarketingLayout for all four landing pages. Local state: open, register/login, step 1–3, preparing, failure, collision, useForm processing/errors. Steps name/email/WhatsApp, progress 33/66/100; final submit prepares session via POST `/l/{slug}/acquisition`, then POST `/register`. Register transform excludes password. Login uses `/login` with email/password, with `/forgot-password` recovery. No account-ready intermediate screen or nested action modal in landing flow.

`PublicJourneyContinuation` allowlists journeys/actions/IDs/context; session carries landing ID and validated location/session when available. Acquisition endpoint requires PARTICIPATE for Challenge, prohibits intended_action for other domains. `LoginResponse`/`RegisterResponse` consume continuation and call `afterAuthentication`. Challenge alone can join on this explicitly authorized final submission; generic signup/GET/verification does not join. Authenticated users bypass modal and use direct POST if projected, otherwise activation URL. This is not an all-domain choice detector.

[CreateNewUser](../../app/app/Actions/Fortify/CreateNewUser.php) normalizes email/WhatsApp; contextual email collision or serialized phone collision switches to login/recovery, never identity-only authentication. Server generates a random 64-character credential through normal model hashing. Contextual registration queues `SecureAccountAccess` with existing reset broker token; queue failure recorded without blocking session. Account access message reads shared new-account/email-queued props.

**Cold registration remains ACTIVE / KEEP — FALLBACK:** Fortify `/register → auth/register.tsx`, still referenced by native actions, Programs/membership/public-journey config. It also uses name/email/WhatsApp and the same passwordless creator; the old password acquisition implementation is absent. However access email dispatch is conditional on landing continuation: generic cold registration has no equivalent queued SecureAccountAccess from this creator. This is a real access-continuity gap, not dead code.

**Verification Continuity V1 — P0 resolved and LIVE at `715af599`, from actual runtime `7cdc8c1`.** Boundaries are domain-specific:

| Domain | Verified email boundary | Landing return / integrity |
| --- | --- | --- |
| Benefit | Normal intent/start and owned Delivery do not require verification. Challenge-grant canje retains `EnsureBenefitRedemptionVerified`; grants/redeem rules and partner authorization/PIN remain unchanged. | `/canjes/{public_id}` requires authenticated ownership; expiry still removes usable QR. Secure account access remains secondary. |
| Experience | **YES** for internal reservation mutation. Retain the route's declared identity boundary: it creates an attendee/party-size reservation for a dated session under partner approval, rather than an informational handoff. The service locks the session and validates membership, publication, session and partner; this change adds no capacity accounting. Cancellation retains its existing route policy. | Reserve attempt shows **CONFIRMA TU CORREO PARA RESERVAR** within the same Activation, retaining validated session selection. Signed verification returns to Activation; only a subsequent explicit POST reserves. External/pending informational handoff needs no verification and creates no local reservation. |
| Challenge | **NO** for initial join; **YES** for social evidence and reward-sensitive eligibility/selection/grant/manual reward boundaries. | Existing contextual verification returns to owned Challenge Delivery. Policy and implementation unchanged. |
| Unlock | **YES** for the already declared interest/commit/cancel/confirm mutation boundary; commitment affects collective capacity, and may retain JP and open confirmation. | Same Unlock Activation and current participation state after proof; no POST replay, auto-commit, JP spend or reveal. Reveal still requires existing domain state, viewer eligibility and owned qualifying participation; restricted source projection is unchanged. |

Directed validation found that `User` does **not** implement Laravel's `MustVerifyEmail`: the generic `verified` middleware declaration alone was ineffective for authenticated users. V1 does not change that global model/interface or unrelated routes. `EnsureContextualActionVerified` explicitly checks `hasVerifiedEmail()` on the scoped Experience internal POST and Unlock mutations, preserving their declared security intent effectively. Benefit Delivery removes the misleading generic declaration. No new verification provider, tokens, table or redirect engine exists.

`PublicJourneyContinuation` retains a published Landing belonging to the routed subject and an upcoming session belonging to that Experience. Activation keeps that existing continuation. `VerifyEmailResponse` uses its existing generated GET destination; no arbitrary return URL or protected POST is accepted/replayed. A foreign Landing/session is rejected. Generic `auth/verify-email.tsx` and verification.notice remain **KEEP — FALLBACK** for direct/generic auth and scoped mutations with no Landing context. Proof still uses the existing signed verification link. SecureAccountAccess remains distinct from proof of email ownership; neither silently substitutes for the other.


No password-in-registration UI, “Cuenta lista” acquisition state, old auth-modal component or guest-only `/l` middleware found. “Tu cuenta está lista” in the access email is legitimate secondary account copy, not an intermediate product screen.

## 7. Activation and stale interstitial

Shared shell: `landing-presentations/conversion.tsx`, not a shared typed activation view-model. It embeds four native pages via continuationOnly / conversionOnly. Genuine-choice predicates are distributed: locations count, session count, JP acknowledgement, and Challenge's explicit false method.

Policy “activation only for genuine choice” is **complete for current Challenge**, partial across other domains: guest Benefit/Experience/Unlock afterAuthentication generally returns `/activar`; only authenticated direct-action conditions bypass it. Benefit can still show a single auto-selected location with confirmation; Experience one-session external result uses a continuation button; Unlock zero-deposit guest return can still expose commitment panel. Preserve genuine choices, membership and monetary/JP acknowledgements; confirmation-only cases deserve review.

Stale JSX **remains YES**: `conversion.tsx` renders ENTRA AL RETO when no participation, with ChallengeShow's embedded ENTRAR AL RETO button. Currently unreachable because activation controller redirects every Challenge (`requiresParticipationChoice=false`) and result route requires participation. Do not delete the whole conversion page or `ChallengeShow`: both have active delivery/fallback consumers. Old activation URLs intentionally remain recovery routes, not dead routes to remove.

Native Benefit confirmation overlay and Experience reservation dialog are still reachable in fallback pages; full-page landing conversion disables their modal presentation. They are KEEP — FALLBACK, not abandoned nested landing flow.

## 8. Delivery capabilities / gap matrix

Brand + Unify Delivery V1 (2026-10-13, America/La_Paz), **LIVE at `79f2ed7`**: shared [Delivery + DeliveryLayout](../../app/resources/js/components/marketing/delivery.tsx) provides a fixed operational shell. Benefit enters through ConsumerLayout's redemption branch; Experience/Challenge/Unlock through conversion. Both use the same minimal linked JAKAWI header (`/`) and authenticated ecosystem block after all domain fulfillment/actions and secondary AccountAccessMessage: **SIGUE VIVIENDO TU CIUDAD**, **IR A MI JAKAWI** (`/mi-jakawi`), **EXPLORAR MÁS** (`/explorar`). Delivery suppresses the floating PWA installation prompt so it cannot obscure QR/instructions. No duplicated ConsumerLayout/MobileBottomNav, acquisition menu, registry, persisted configuration, Delivery DB model or CMS.

| Capability | Current repo contract |
| --- | --- |
| Branded shell / logo navigation | SHARED; accessible linked wordmark on mobile and desktop. |
| Outcome treatment | Shared Delivery, completed only when explicitly projected; neutral default for pending/internal/external reservation and unrevealed commitment. |
| Benefit | QR/code, expiry/timer, ownership, partner validation, address, terms and confirmed savings retained; instructions/address precede code, conditions follow it. Confirmed and expired lifecycle renderers remain active. |
| Experience | Internal confirmed reservation can use green; pending/external cannot. Session/location/status yellow; real maps/partner action coral. No reservation is invented. |
| Challenge | Activated participation green, instructions before deadline/reward/state/evidence and secondary verification; reward opportunity yellow, evidence action coral. Inventory remains secondary. |
| Unlock | Shared authorized shell; completed green only inside existing revealable/authenticated/eligible guard. Restricted source, commit, JP and authorization unchanged. |
| Account/security | Secondary, after operational fulfillment; shared AccountAccessMessage follows domain actions/verification. |
| Instructional media | Existing optional node slot retained; no caller supplies media, no new field or fake content. |

LandingPresentation/presenters/adapters remain KEEP CORE. Domain-owned instructions and projections remain specialized. Welcome Courtesy remains **FUTURE / OFF / NOT IMPLEMENTED**. The shell and semantic color contract originally deployed on `79f2ed7dfd9f792e94bc659136a02e809699fbaa` remain LIVE on current production `0a0d4b6939ae914ffe9face7436d099e1cfac6a4`.

## 9. Product Detail fallback — keep all four

| Domain | Route → controller → renderer | Other live consumers | Verdict |
| --- | --- | --- | --- |
| Benefit | `/beneficios/{slug}` → PublicController::benefit → benefits/show | RedemptionController::intent; conversion embeds with redemptionIntent/continuationOnly | KEEP — FALLBACK and operational core. |
| Experience | `/experiencias/{slug}` → PublicController::experience → experiences/show | conversion activation/result; external `/reservar` action | KEEP — FALLBACK and operational core. |
| Challenge | `/retos/{slug}` → ChallengeController::show → social-challenges/show | landing adapter embedded operational content; conversion delivery | KEEP — FALLBACK and operational core. |
| Unlock | `/d/{slug}` → UnlockController::show → unlocks/show | conversion activation/result; JourneyLayout inside native view | KEEP — FALLBACK and operational core. |

Landing middleware redirects only when published default applies. No complete Product Detail renderer is safe to delete. Local duplicated headings/copy can be consolidated later without removing fallback behavior.

## 10. Evidence-based inventory / remove candidates

| Code / path (relative to repository) | Why candidate / classification | Reference evidence | Removal risk |
| --- | --- | --- | --- |
| `app/resources/js/pages/landing-presentations/conversion.tsx` Challenge no-participation heading branch | DEAD under current domain; removed confirmation-only interstitial JSX | Challenge::requiresParticipationChoice false; activation redirects; result ownership guard | Low for branch, high for whole file; retain recovery route and delivery branch. |
| `app/resources/js/layouts/marketing-layout.tsx` inner acquisition logo branch | DEAD conditional inside `!acquisition` header | Outer !acquisition makes inner acquisition ? img unreachable | Low; no change authorized here. |
| `app/app/Models/Challenge.php::presentations()` | LEGACY alias | Repository search found declaration only; current relationships use landingPresentations | Low/medium, external callers unknown. |
| `app/resources/js/layouts/auth/auth-card-layout.tsx`, `auth-split-layout.tsx` | Unreferenced alternate starter layouts | Import search: only self declarations; auth-layout imports auth-simple-layout | Low after confirming no external/manual entrypoint. |
| `app/resources/js/layouts/app/app-header-layout.tsx`, `components/nav-footer.tsx` | Unreferenced starter layout/navigation | Import search: no consumers; app-layout uses app-sidebar-layout | Low/medium; inspect their children before cascading deletion. |
| `app/resources/js/components/marketing/offer/presentation.ts` value.tone / compactLabel | Unconsumed contract fields | Adapters set tone; MarketingValueBlock reads neither tone nor compactLabel | Medium: preferably consume semantic tone in future; not delete shared contract blindly. |
| `app/app/Support/PublicJourneyConfig.php`, `Http/Controllers/PublicJourneyLandingController.php`, `config/public_journeys.php`, `resources/js/pages/public-journeys/show.tsx` | LEGACY/parallel config-driven marketing infrastructure; production-unused by checked-in configuration | web.php calls registerRoutes; landings/redirects arrays populated only APP_ENV=testing; analytics route/controller/tests remain | Medium/high: explicit tests/callers and possible config override/cache; retirement decision before removal. |

No standalone old Offer renderer or acquisition modal was found. Older primitives are **actively used**, not remove candidates. `MarketingPrimaryCTA` aliases the shared CTA. Challenge sticky hero/body/final actions reuse its action object, not a separate CTA engine. Benefit/Experience modal states are functional fallback code. Challenge native and conversion delivery duplicate instructions but both branches have callers; unify future content, do not classify native branch dead.

`editorial_version=0`, nullable Offer controls and Unlock custom media compatibility are **LEGACY / UNKNOWN data usage**, not safe removals without read-only record inventory in a separately authorized cleanup. `is_default` migrations/tests stay. No unrouted audit-scope controller was confirmed dead: public journey controller has dynamic route registration plus analytics endpoint; landing/admin/domain controllers all have routes. No abandoned experimental marketing component was confirmed beyond the candidates above.

Feature flags: `unlocks.jp_commitments_enabled` is **ACTIVE** in presenter, UnlockStatusService and UnlockParticipationService; production default false, overrides uninspected. Its “Slice 3 once confirmation exists” comment is stale relative to current confirm/fulfillment methods, but flag is not dead. Meta/CRM flags are outside template architecture and must remain operational controls. No Courtesy flag found. CSS Challenge polish classes and `data-demo-reward` are emitted by active adapter; do not remove by version-like names.

Static evidence procedure: `rg` import/symbol/route searches over app/resources/js, app/app, app/routes, app/config, migrations/tests; TS component import-candidate scan followed by explicit symbol search; inspected Inertia dynamic page names/layout selection. A lack of direct import is insufficient for an Inertia page or route controller. No runtime traffic census claimed.

### KEEP — CORE (do not rebuild)

- LandingPresentation + schema, defaults service/resolver/middleware and saved campaign attribution.
- Existing Marketing Admin controller/form/editor, publication guards, duplicate/preview and audience semantics.
- Domain presenters/adapters, OfferPresentationV1 and frozen OfferConversionTemplate/blocks/composition.
- MarketingAction/CTA/FAQ primitives and delegated Growth click instrumentation.
- LandingVideo/JakawiVslPlayer/MediaUrl/JakawiImage and Unlock disclosure boundaries.
- AcquisitionProvider + LandingAcquisition + PublicJourneyContinuation + Fortify response integration.
- Domain mutations/services/ownership checks; ChallengeDeliveryPresenter/ChallengeDelivery; Delivery and account access area.

### KEEP — FALLBACK

All four Product Detail renderers/routes and their operational modal states; cold registration/login/reset/verification pages; ConsumerLayout/JourneyLayout/PublicFooter/MobileBottomNav; recovery activation URLs; Partner validation and confirmed/expired redemption states.

## 11. Marketing gap matrix

| Capability | Exists | Partial | Missing | Recommended next step |
| --- | --- | --- | --- | --- |
| New ordinary Benefit/Experience/Challenge landing without code | Yes | — | — | Use current product + landing forms, saved preview, publish. |
| Global Publisher subject selector / lifecycle / duplicate | Yes | — | — | Do not rebuild. |
| Hero/closing editorial copy + safe emphasis | Yes | — | — | Preserve protected domain claims and version migration. |
| Theme/layout/density/optional media/VSL | Yes | — | — | Keep fixed controls, no arbitrary colors. |
| Derived domain price/reward/access/date/location/rules | Yes | — | — | Keep truth in domain; Benefit estimated_savings is not an offer amount. |
| Editable acquisition headline/supporting copy reaches actual opt-in | — | Yes | Consumption | Wire existing persisted fields deliberately into frozen opt-in contract in future. |
| Admin preview of acquisition/activation/delivery states | — | Saved landing only | Journey preview | Add bounded preview if product requires it; never execute domain mutations. |
| Marketing-specific FAQ / benefits / steps / proof content | — | Derived defaults, frontend slots | Editorial schema/Admin fields | Add only optional structured editorial content that is actually required; never override conditions/rules. |
| Structured contractual Benefit value | — | Title and terms, estimated_savings informational | Explicit offer fact model | Define domain contract first if richer offer facts are required; no economic override in marketing. |
| Unlock in global Offer publisher | — | Separate active per-subject Admin/renderer | Offer adapter / global selector | Optional P2 scope, preserve disclosure; not needed for the three-domain workflow. |

**Domain-derived:** title/provider, value/reward, sessions/date/time/place/address/prices, membership/access, limits/deadlines, qualifications/selections, rules/terms, availability/action, Unlock progress/disclosure.

**Editable:** all fields in section 4; effective hero/closing copy is editable today. **Hardcoded:** template order, chapter headings, CTA labels/action explanations, FAQ question patterns and derived answers, steps labels/instructions scaffolding, demo annotations/sticky policy, micro-opt-in step headings and domain nickname regexes. **Missing:** consumption of acquisition copy, optional independent editorial structured sections, full journey preview; neither a new engine nor a new Publisher is missing.

## 12. Color / brand audit

[theme.css](../../app/resources/css/theme.css) defines semantic palette equivalents: CORAL `--brand` #ff5a4f; YELLOW `--reward` #ffc83d; GREEN `--success` #24b47e; INK `--ink` #111111; CREAM `--cream` #fff8f2. Marketing aliases: --marketing-action/emphasis = brand, --marketing-reward = reward, --marketing-value = success, with full light/dark surface rebinding. `app.css` exposes Tailwind aliases and dark variant isolation.

Brand + Unify Delivery V1 consumes existing `value.tone`: `reward` is a yellow opportunity surface with ink text; `offer` remains informational/neutral. There is no new tone enum. Benefit title/copy does not establish gratuity or realized savings; its domain-derived validity receives yellow. Experience dates receive yellow and member price receives green only when the presenter confirms both authoritative prices and member price is strictly below regular price (including authoritative zero); no savings amount is calculated. Challenge reward opportunity receives yellow, never a won-prize claim. V3 section order, hero/media layout, steps, FAQ and CTA architecture remain frozen.

**Fixed semantic contract:** coral = action (existing approved contrast treatment, white labels); yellow = opportunity/reward/deadline (ink labels); green = real success/confirmed value (ink or approved contrast); ink/cream = instruction/structure/warm surfaces. Delivery's green status is explicit, not implied by rendering a result. Pending reservation and future reward stay neutral/yellow.

Landing visual meaning = **domain-derived facts + existing editorial fields + fixed semantic template rules**. Marketing does not choose colors manually. No arbitrary Admin colors, new fields, Delivery editor, new CMS/model, replacement Publisher or template engine. Ephemeral price/fact presentation hints are derived in code and never persisted. No global token was retired; unconsumed compactLabel remains separate debt.

## 13. Documentation contradictions and corrections

| Source reviewed | Finding against code | Resolution in this audit |
| --- | --- | --- |
| README / docs index | Entrypoints lacked current funnel audit; index dated older review | Link authoritative audit; no new runtime claim. |
| ARCHITECTURE themes | “No Landing Engine” could imply no meaningful template | Clarify configurable single template, no builder/multiple-template engine. |
| ARCHITECTURE Acquisition | “Challenge no participa automáticamente” / “ninguna vuelta auth participa” contradicted explicit PARTICIPATE afterAuthentication | Specify narrow authorized post-auth join; generic signup/GET do not join. |
| ARCHITECTURE registration | Legacy registration described outside change, but shared creator now random-password globally | Document active passwordless cold fallback and contextual-only access email. |
| ARCHITECTURE / CURRENT Admin | “No se ejecutó contra producción” written as current fact; latest code/docs describe later releases | Historical migration delivery scope only; applied DB state not rechecked here. |
| ARCHITECTURE / CURRENT earlier LIVE SHA paragraphs | Older releases b3c00e/f2b488b/e848b9f appear alongside latest reference | Mark release sections historical; baseline/current production claim stays sourced to previous release evidence, not this audit. |
| CURRENT Delivery V1.1 | No automatic participation wording obsolete for explicit acquisition intent | Correct exception; mark history precedence. |
| Manual audience/acquisition | Acquisition fields described as effective copy; modal ignores them | Mark saved/projected/Admin sample only, actual modal hardcoded. |
| Manual permissions | Broad auth+verified wording obscures no-verification Challenge join | Explicit domain boundaries are now documented in section 6; Benefit Delivery is auth + ownership, Experience internal and Unlock mutations use scoped verification checks. |
| GLOSSARY | Missing LandingPresentation, activation vs membership activation, Delivery semantics | Add current terms and canonical audit link. |
| ROADMAP | Courtesy already accurately FUTURE/OFF | Preserve; add ranked current-system work reference. |
| ADR-001 | Product Detail vs marketing accurate but primary landing/fallback preference implicit | Make viewer-aware primary/fallback explicit. |
| DESIGN-SYSTEM | Historical neutral value/reward and partial branding finding | Current contract now documents consumed tone and shared shell LIVE. |
| VSL alpha / older artifacts | Alpha “not imported” and historical screenshots include old acquisition/activation | Historical alpha/iteration scope, not current player/flow. Add scope notice; preserve evidence. |
| ADR-002 / ADR-005 / ADR-006; operations RUNBOOKS; VSL_INTEGRATION; CONVERSION | Campaign/key separation, provider/runtime boundaries, player and economic conversion remain valid | No runtime reinterpretation; architecture audit governs current funnel. |

Older artifact screenshots/releases are intentionally retained as evidence, not rewritten to look current. Current-doc additions explicitly defer to this code audit when earlier iteration prose disagrees. No guest-only direct landing restriction is present in code; GUESTS remains a valid default audience. No current registration password or “Cuenta lista” screen found. Generic verification remains reachable at the boundaries documented above.

## 14. Next work map (maximum eight ranked items)

| Priority | Gap / debt | Smallest next work |
| --- | --- | --- |
| P0 RESOLVED / LIVE | Verification Continuity V1: Benefit owned Delivery; contextual Experience/Unlock mutation verification | Focused backend and 390px isolated browser validation; preserve grant/reward/disclosure guards. |
| P1 | Saved acquisition copy unused | Consume existing fields or explicitly retire their promise; no replacement Publisher. |
| P1 RESOLVED / LIVE | Brand + Unify Delivery V1 | Fixed shared linked shell and ecosystem navigation; domain truth/security preserved. |
| P1 | Choice-only activation not universal | Explicit domain choice/action projection; bypass confirmation-only continuation when safe, preserve membership/JP consent. |
| P1 | Cold signup gets random password without contextual access email | Reuse secure access path for generic signup; keep login/collision recovery. |
| P2 | Dead interstitial/layout branches and test-only config landing infrastructure | Remove proven dead branches first; decide config subsystem retirement separately with tests/config inventory. |
| P2 | Optional structured marketing editorial content / bounded journey previews | Extend existing form/adapter only for accepted missing content; keep truth derived. |
| P2 PARTIALLY RESOLVED | Semantic tone consumed; duplicated domain delivery projections remain | Existing tone/date/member-value rules implemented; no Unlock Offer integration. |

**Brand/Unify Delivery is LIVE**, following the deployed P0 continuity fix. Publisher is already substantially built; the most visible missing structure is consistent post-conversion ecosystem navigation. Small proven dead branches can be cleaned independently, but broad legacy retirement must not delay or erase working fallbacks.

Cleanup order: confirm data-dependent legacy usage → remove unreachable Challenge/MarketingLayout branches and unused starter layouts → decide test-only config subsystem retirement → unify duplicated domain projections → retire compatibility only after record audit. Preserve migration history, generic auth/verification, fallbacks and operational states throughout.

Build order: P0 verification/continuity fix LIVE → shared branded Delivery/navigation → connect acquisition copy + generic access email → normalize choice-only handoff → optional structured editorial/preview/semantic improvements. Further runtime changes remain outside this continuity task.

**Explicitly do not rebuild:** Landing model/default routing, Marketing Publisher CRUD/lifecycle/preview, Offer template engine, domain adapters/rules/services, identity flow, native VSL, QR redemption, Challenge verification/reward pipeline. Do not add Courtesy, arbitrary colors, a Delivery CMS or another CTA/auth-modal system as part of cleanup.

## 15. Validation

`git diff --check`: PASS. `python3 bin/check-docs.py`: PASS (60 Markdown files, 370 relative targets, 0 broken). Original static audit validation only; it did not execute frontend/browser/PHP/Docker builds or deploy. Check outcomes are recorded in the commit handoff, not historical test assertions reused as fresh verification.


Verification Continuity V1 pre-release validation (2026-10-12, America/La_Paz; undeployed at that time): 35 focused backend tests passed (521 assertions), plus the tightened grant boundary recheck; TypeScript `tsc --noEmit` passed; one final frontend `npm run build` and manifest validation passed. PHP executed only in Docker 8.4; tests use `./bin/jakawi-test`. Minimal browser QA used isolated `jakawi_test`, the final compiled frontend and 390px only: guest acquisition → unverified Benefit owner → safe redemption → Delivery/QR; selected internal Experience → contextual verification + notification, no reservation; Unlock → contextual state without commit/JP/restricted source. No browser verification was faked. Existing signed-proof backend tests cover successful/invalid verification, same context and no automatic mutation. Challenge has unchanged code/policy and its focused delivery tests pass. See [browser evidence](../../artifacts/verification-continuity-v1/browser-validation.json). Welcome Courtesy remains FUTURE / OFF.


Production release 2026-10-13 (America/La_Paz): **LIVE**, SHA `715af599073e359509ef7c68e188d2aba4b4da20`; actual previous runtime/rollback `7cdc8c12e71b23e6c6b99db522e2e6274be6b87f`, not f2b488. Three-commit delta introduces no migration or runtime/config change outside scoped verification code. Production migration status has no pending entries; no backup/migrate/seed. One clean production build; compiled assets/manifest/no-hot/no-dev-ref gates PASS. Read-only candidate/live smoke preserves domain counts and Growth outcomes. Inventory limits: no published internal Experience Landing or active published Unlock; Experience internal context and email ownership state were projected only in memory, and Unlock guard/source checked without mutation. Full contextual flows were already validated in isolated QA. `/up` 200/200, representative Landings/fallback 200; DB/V2PROOF unchanged, healthy; CRM TRUE, Meta OFF, failed jobs 3 historical/0 new. [Production evidence](../../artifacts/verification-continuity-v1/production-release.json). No rebuild/redeploy for the documentation update. Welcome Courtesy: FUTURE / OFF / NOT IMPLEMENTED.


Brand + Unify Delivery V1 candidate validation (2026-10-13, America/La_Paz): **PASS / NOT DEPLOYED**. 25 targeted Docker PHP 8.4 backend tests (281 assertions), 10 frontend component/composition contracts, TypeScript and final frontend build/103-page manifest. Minimal isolated browser QA: Challenge Delivery 390, Sunset Landing + pending/external Delivery, Café Landing/QR Delivery and one 1440 Delivery. Final screenshots confirm no floating install obstruction, correct Challenge action hierarchy, truthful pending state and bottom navigation. Contrast coral/white 4.60:1, yellow/ink 12.21:1, green/ink 7.11:1, visible focus. Initial compiled QA required two visual corrections; a single final build followed those corrections. No deploy, production mutations, Admin/schema changes or broad funnel retest. [Candidate evidence](../../artifacts/brand-unify-delivery-v1/INDEX.md). Welcome Courtesy remains FUTURE / OFF.


Branded Delivery + Semantic Color V1 production release (2026-10-13, America/La_Paz): **LIVE**, `79f2ed7dfd9f792e94bc659136a02e809699fbaa`, actual previous/rollback `715af599073e359509ef7c68e188d2aba4b4da20`. One production build from clean Git export; no migrations, backup, seed or DB recreation. App/web/worker only recreated; immutable images and rollback tags verified. Readiness accepted transient 000 then 200 after 2.34s. Local/public `/up` 200/200; Café/Sunset/Reto Cena Landings 200; compiled HTML and manifest PASS, hot/dev refs absent. Read-only runtime browser smoke at 390 confirms brand/links, semantic accents, fulfillment-first hierarchy and bottom ecosystem navigation without duplicate mobile nav. Sunset actual external state remains pending; Café actual confirmed Delivery plus QR expiry projected only in memory because no pending code is currently valid. No new domain/Growth outcomes; failed jobs 3 historical/0 new. DB/V2PROOF unchanged and healthy; app healthy, web/worker running, scheduler active, CRM TRUE, Meta provider/browser/CAPI OFF. Publisher/Admin/template V3 and Unlock source/JP guards unchanged. [Production evidence](../../artifacts/brand-unify-delivery-v1/production-release.json). Docs-only followup, no rebuild/redeploy. Welcome Courtesy: **FUTURE / OFF / NOT IMPLEMENTED**.

Publisher Workflow UX V1 production release (2026-10-13, America/La_Paz): **LIVE**, `0a0d4b6939ae914ffe9face7436d099e1cfac6a4`, actual previous application / protected rollback `44636ae1c41f5828c702c052b99f3e3b7c778263`. One clean production build; app/web/social-worker only recreated. No migrations, domain mutations, published-record changes, template or preview-engine rebuild. Import != save. COPIAR BRIEF PARA IA → external GPT → IMPORTAR JSON → CAMBIOS SIN GUARDAR → GUARDAR Y PREVISUALIZAR → PUBLICAR. Saved Preview refreshes after successful persistence; validation preserves the form and unsaved publication is blocked. Real deployed DRAFT smoke PASS; temporary editorial DRAFT removed. [Release evidence](../../artifacts/publisher-workflow-ux-v1/production-release.json). JSON V1 and AI Product Brief V1 remain LIVE. Batch: FUTURE. Welcome Courtesy: FUTURE / OFF. Docs-only followup, no rebuild/redeploy.
