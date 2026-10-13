# Marketing / Acquisition / Activation / Delivery — audit V1

**CURRENT CODE / AUTHORITATIVE.** 2026-10-12 (America/La_Paz). Audited checkout: `9c1708f` on `main`; implementation baseline `7cdc8c1`. Documentation only; no runtime, database, deployment, browser, build or PHP execution. “Active” means a current route/import/caller exists, not measured production traffic. Saved production records, applied migrations, environment overrides and current deployment were not inspected. Release artifacts are historical evidence, not a new health check.

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
JAKAWI ecosystem [partial navigation; not unified today]
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

## 5. Domain truth → adapter → activation → delivery

| Domain | Domain truth / landing adapter | Activation and branching | Mutation / Delivery | Status |
| --- | --- | --- | --- | --- |
| Benefit | Benefit/Partner/available Locations/access/terms/limits; `BenefitMarketingLandingPresenter → BenefitOfferAdapter` | `benefits/show.tsx` continuationOnly: location selection, membership gate; one location auto-selected. Authenticated member with single location uses landingDirectAction. | `RedemptionController::start → RedemptionService`; owned `/canjes/{public_id}` → `redemptions/show.tsx`, QR/code/expiry/address/terms; confirmed savings distinct. | ACTIVE; fallback and operational view required. |
| Experience | Experience/partners/upcoming sessions/prices/reservation_method; `ExperienceMarketingLandingPresenter → ExperienceOfferAdapter` | `experiences/show.tsx` continuationOnly: date/session and membership where internal. Single-session internal member has direct POST shortcut. External or guest continuation can still display activation for one session. | `ExperienceReservationController → ExperienceReservationService` for JAKAWI; `/resultado` owned pending/confirmed result. External result validates session/targets, links to PublicController::reserve; no local reservation claimed. | ACTIVE; generic verification middleware remains on internal POST. |
| Challenge | Challenge rulesContract, evidence/qualification/selection/reward/access; `ChallengeMarketingLandingPresenter → ChallengeOfferAdapter` | `requiresParticipationChoice()` always false. Explicit authenticated CTA POST joins; final guest opt-in carries PARTICIPATE and afterAuthentication joins if eligible. Activation GET redirects to landing or owned result. | `ChallengeService::join`; `/resultado` → `ChallengeShow conversionOnly → ChallengeDelivery` + `ChallengeDeliveryPresenter`; evidence/review/grant remain domain actions. | ACTIVE; no-choice confirmation interstitial unreachable today. |
| Unlock | Unlock state/target/capacity/eligibility/JP/disclosure; `UnlockMarketingLandingPresenter → UnlockLanding` (no Offer adapter) | `unlocks/show.tsx` continuationOnly action panel; JP acknowledgement if deposit; eligibility/membership; zero-deposit SUMARME shortcut for auth. | `UnlockController → UnlockParticipationService`; owned result shows commitment pending goal or authorized revealed result; retain confirm/cancel/fulfillment actions. | ACTIVE separate renderer; verified middleware and JP flag apply. |

Business branching lives in PHP presenters/controllers/services, frontend domain adapters and native operational pages. `OfferConversionTemplate` does not inspect membership, participation, evidence, dates or grants: **no domain-semantics violation found in generic template**. Boundary debt: controller/frontend use `action.label` comparisons (USAR BENEFICIO/SUMARME) for direct shortcuts; acquisition provider includes domain label hacks `/café/` and `/sunset/`. These should stay out of the generic template and are candidates for future explicit action contracts, not a reason to replace it.

## 6. Acquisition reality and verification boundaries

[AcquisitionProvider](../../app/resources/js/components/marketing/acquisition-context.tsx) is the one landing identity modal, installed by MarketingLayout for all four landing pages. Local state: open, register/login, step 1–3, preparing, failure, collision, useForm processing/errors. Steps name/email/WhatsApp, progress 33/66/100; final submit prepares session via POST `/l/{slug}/acquisition`, then POST `/register`. Register transform excludes password. Login uses `/login` with email/password, with `/forgot-password` recovery. No account-ready intermediate screen or nested action modal in landing flow.

`PublicJourneyContinuation` allowlists journeys/actions/IDs/context; session carries landing ID and validated location/session when available. Acquisition endpoint requires PARTICIPATE for Challenge, prohibits intended_action for other domains. `LoginResponse`/`RegisterResponse` consume continuation and call `afterAuthentication`. Challenge alone can join on this explicitly authorized final submission; generic signup/GET/verification does not join. Authenticated users bypass modal and use direct POST if projected, otherwise activation URL. This is not an all-domain choice detector.

[CreateNewUser](../../app/app/Actions/Fortify/CreateNewUser.php) normalizes email/WhatsApp; contextual email collision or serialized phone collision switches to login/recovery, never identity-only authentication. Server generates a random 64-character credential through normal model hashing. Contextual registration queues `SecureAccountAccess` with existing reset broker token; queue failure recorded without blocking session. Account access message reads shared new-account/email-queued props.

**Cold registration remains ACTIVE / KEEP — FALLBACK:** Fortify `/register → auth/register.tsx`, still referenced by native actions, Programs/membership/public-journey config. It also uses name/email/WhatsApp and the same passwordless creator; the old password acquisition implementation is absent. However access email dispatch is conditional on landing continuation: generic cold registration has no equivalent queued SecureAccountAccess from this creator. This is a real access-continuity gap, not dead code.

**Verification is domain-specific, not globally non-blocking:** Challenge join requires no verification; social evidence submit and reward eligibility require verified email, enforced server-side. Delivery sends verification in context; `VerifyEmailResponse` re-evaluates an existing owned participation and returns its result. Generic `auth/verify-email.tsx` and verification.notice remain active fallbacks. Experience internal reservations, Unlock mutations and Benefit `/canjes/{public_id}` still sit in the auth+verified group. Normal Benefit start/intent explicitly remove verified and use `EnsureBenefitRedemptionVerified` (grant-only). Thus a normal unverified Benefit user may create a code then meet generic verification on its delivery route. This static inconsistency needs targeted future validation/fix; do not document universal uninterrupted delivery today.

No password-in-registration UI, “Cuenta lista” acquisition state, old auth-modal component or guest-only `/l` middleware found. “Tu cuenta está lista” in the access email is legitimate secondary account copy, not an intermediate product screen.

## 7. Activation and stale interstitial

Shared shell: `landing-presentations/conversion.tsx`, not a shared typed activation view-model. It embeds four native pages via continuationOnly / conversionOnly. Genuine-choice predicates are distributed: locations count, session count, JP acknowledgement, and Challenge's explicit false method.

Policy “activation only for genuine choice” is **complete for current Challenge**, partial across other domains: guest Benefit/Experience/Unlock afterAuthentication generally returns `/activar`; only authenticated direct-action conditions bypass it. Benefit can still show a single auto-selected location with confirmation; Experience one-session external result uses a continuation button; Unlock zero-deposit guest return can still expose commitment panel. Preserve genuine choices, membership and monetary/JP acknowledgements; confirmation-only cases deserve review.

Stale JSX **remains YES**: `conversion.tsx` renders ENTRA AL RETO when no participation, with ChallengeShow's embedded ENTRAR AL RETO button. Currently unreachable because activation controller redirects every Challenge (`requiresParticipationChoice=false`) and result route requires participation. Do not delete the whole conversion page or `ChallengeShow`: both have active delivery/fallback consumers. Old activation URLs intentionally remain recovery routes, not dead routes to remove.

Native Benefit confirmation overlay and Experience reservation dialog are still reachable in fallback pages; full-page landing conversion disables their modal presentation. They are KEEP — FALLBACK, not abandoned nested landing flow.

## 8. Delivery capabilities / gap matrix

Shared [Delivery](../../app/resources/js/components/marketing/delivery.tsx) is a section with heading/subject/success cue/children/optional instructionalMedia/account area. It is not a full layout, model, CMS or typed cross-domain delivery adapter. AccountAccessMessage is shared. Challenge alone has dedicated `ChallengeDeliveryPresenter` + `ChallengeDeliveryContent`; Experience and Unlock derive inline; Benefit uses Redemption projection and its own active pending/confirmed/expired renderer.

| Capability | Benefit `/canjes` | Experience / Challenge / Unlock `/l/.../resultado` | Exists / partial / missing; smallest future step |
| --- | --- | --- | --- |
| Shared content structure | Own QR/result page + AccountAccessMessage | Delivery reused; domain children differ | PARTIAL: fixed shell + domain content slots, preserve ownership/state. |
| JAKAWI brand header | ConsumerLayout logo/mark | Conversion header logo image | EXISTS, but inconsistent. |
| Logo navigation | Link to `/` in ConsumerLayout | Image without link | PARTIAL: shared linked header. |
| Post-conversion ecosystem navigation | Explore CTA; ConsumerLayout navigation | No shared ecosystem links | PARTIAL: explicit secondary links after delivery. |
| Mi JAKAWI | ConsumerLayout desktop/mobile nav | Absent in conversion shell | PARTIAL: existing destination can be reused (verified route). |
| Explorar más | SEGUIR EXPLORANDO / DESCUBRIENDO | No shared link; external reservation/evidence actions are operational | PARTIAL: reuse `/explorar` after primary task. |
| PublicFooter | Absent; ConsumerLayout has MobileBottomNav | Absent; conversion has neither | MISSING unified footer; reuse existing component if chosen. |
| Bottom navigation | ConsumerLayout MobileBottomNav | Absent | PARTIAL; choose deliberate delivery navigation, no CMS. |
| Instructional media | No configured delivery media | Delivery accepts node, **no caller passes it** | PARTIAL slot / MISSING content source; optional future domain field, respect Unlock disclosure. |
| Domain instructions | QR/code, address, terms, expiry/partner validation | Session/state/targets; Challenge mission/rules/evidence; Unlock terms/offers/progress/actions | EXISTS; mostly derived, labels/manual instructions still domain-local. |
| Account/security secondary area | AccountAccessMessage on pending; confirmed branch omits it | Shared account area; Challenge contextual verification secondary | PARTIAL: preserve task-first placement and access boundaries. |

Branded: **PARTIAL**. Navigation after conversion: **PARTIAL**. Biggest gaps: linked ecosystem navigation in conversion shell; consistent account/security handling; typed domain projections; optional instructional media source. Benefit confirmed/expired screens are active lifecycle states, not obsolete Delivery versions.

Smallest future shape: one fixed full-page delivery shell (brand link, result heading/status, subject/provider, instructions, primary operational action, optional domain media, secondary account/security, ecosystem links), domain-owned children/projections, existing routes/authorization. No CMS, template registry or persisted step engine required. Benefit QR/polling/expiry and Unlock disclosure stay specialized. Do not label pending reservation/participation/commitment as fulfilled reward.

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

Landings use coral CTA/emphasis, ink/cream surfaces; green appears in FAQ markers/player presentation styles, yellow in micro-opt-in progress. **Offer value/reward block is neutral:** adapter sets `value.tone` but MarketingValueBlock ignores it, so actual benefit/reward distinctions do not activate green/yellow styling there. Experience prices are also neutral. Tokens exist; the offer hierarchy underuses semantic accents rather than lacking a palette.

Delivery uses success/success-surface for result cues, coral operational CTAs and neutral surfaces. Benefit inherits product appearance/ConsumerLayout; other results force light marketing context via conversion page. Branding/navigation is not one shared branded delivery layout. No global palette token was proven dead: CSS aliases and derived var references count as usage. Unconsumed Offer tone/compactLabel are separate contract debt, not proof the global green/yellow tokens are unused.

Recommendation: improve semantic use inside existing blocks (conditional reward/opportunity vs actual success), after frozen-template change authorization; keep pending/external/unearned outcomes distinct. No arbitrary Admin colors and no player rebuild.

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
| Manual permissions | Broad auth+verified wording obscures no-verification Challenge join | Add explicit join boundary and current verified Experience/Unlock/Benefit result routes. |
| GLOSSARY | Missing LandingPresentation, activation vs membership activation, Delivery semantics | Add current terms and canonical audit link. |
| ROADMAP | Courtesy already accurately FUTURE/OFF | Preserve; add ranked current-system work reference. |
| ADR-001 | Product Detail vs marketing accurate but primary landing/fallback preference implicit | Make viewer-aware primary/fallback explicit. |
| DESIGN-SYSTEM | Semantic intent could be read as implemented value/reward coloring | Add actual unconsumed tone finding and partial Delivery branding. |
| VSL alpha / older artifacts | Alpha “not imported” and historical screenshots include old acquisition/activation | Historical alpha/iteration scope, not current player/flow. Add scope notice; preserve evidence. |
| ADR-002 / ADR-005 / ADR-006; operations RUNBOOKS; VSL_INTEGRATION; CONVERSION | Campaign/key separation, provider/runtime boundaries, player and economic conversion remain valid | No runtime reinterpretation; architecture audit governs current funnel. |

Older artifact screenshots/releases are intentionally retained as evidence, not rewritten to look current. Current-doc additions explicitly defer to this code audit when earlier iteration prose disagrees. No guest-only direct landing restriction is present in code; GUESTS remains a valid default audience. No current registration password or “Cuenta lista” screen found. Generic verification remains reachable at the boundaries documented above.

## 14. Next work map (maximum eight ranked items)

| Priority | Gap / debt | Smallest next work |
| --- | --- | --- |
| P0 | Verification/continuity mismatch at normal Benefit delivery; verified Experience/Unlock actions | Targeted future journey validation, align intended boundaries without weakening grant/reward/disclosure guards. |
| P1 | Saved acquisition copy unused | Consume existing fields or explicitly retire their promise; no replacement Publisher. |
| P1 | Delivery branding/navigation fragmented | Extend one fixed shared delivery shell with linked logo/ecosystem links and secondary account/security, preserve domain results. |
| P1 | Choice-only activation not universal | Explicit domain choice/action projection; bypass confirmation-only continuation when safe, preserve membership/JP consent. |
| P1 | Cold signup gets random password without contextual access email | Reuse secure access path for generic signup; keep login/collision recovery. |
| P2 | Dead interstitial/layout branches and test-only config landing infrastructure | Remove proven dead branches first; decide config subsystem retirement separately with tests/config inventory. |
| P2 | Optional structured marketing editorial content / bounded journey previews | Extend existing form/adapter only for accepted missing content; keep truth derived. |
| P2 | Semantic tone and duplicated domain delivery projections | Consume existing tokens and share projections; Unlock Offer integration only if explicitly desired. |

**Recommended next move: 3. BRAND/UNIFY DELIVERY FIRST**, following the P0 boundary check. Publisher is already substantially built; the most visible missing structure is consistent post-conversion ecosystem navigation. Small proven dead branches can be cleaned independently, but broad legacy retirement must not delay or erase working fallbacks.

Cleanup order: confirm data-dependent legacy usage → remove unreachable Challenge/MarketingLayout branches and unused starter layouts → decide test-only config subsystem retirement → unify duplicated domain projections → retire compatibility only after record audit. Preserve migration history, generic auth/verification, fallbacks and operational states throughout.

Build order: P0 verification/continuity check/fix → shared branded Delivery/navigation → connect acquisition copy + generic access email → normalize choice-only handoff → optional structured editorial/preview/semantic improvements. Separate authorization is required for all runtime changes.

**Explicitly do not rebuild:** Landing model/default routing, Marketing Publisher CRUD/lifecycle/preview, Offer template engine, domain adapters/rules/services, identity flow, native VSL, QR redemption, Challenge verification/reward pipeline. Do not add Courtesy, arbitrary colors, a Delivery CMS or another CTA/auth-modal system as part of cleanup.

## 15. Validation

`git diff --check`: PASS. `python3 bin/check-docs.py`: PASS (60 Markdown files, 370 relative targets, 0 broken). Static route/import/reference inspection performed; no frontend build, browser QA, PHP tests, Docker builds or deploy. Check outcomes are recorded in the commit handoff, not historical test assertions reused as fresh verification.
