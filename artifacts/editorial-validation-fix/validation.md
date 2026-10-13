# Editorial validation false-positive fix

Status: LIVE on production `642dbd7c5ea6ed1d605fdba546cf19476b865013`. Implementation validation before deployment: Base: `2fee8a859b4e2fd30395285f248acd85dafa36bc`, clean fetched `origin/main`. At that implementation validation, production application remained `0a0d4b6939ae914ffe9face7436d099e1cfac6a4`.

## Exact cause

`AdminLandingPresentationController::save` used a `$editorial` closure on `headline`, `subheadline`, `reward_display_override`, `final_cta_headline` and `final_cta_description`. It rejected digits, `%`, or word-boundary matches for `gratis`, `gratuito`, `sin membresía`, `sin límites`, `precio`, `cupos`, `garantizado`, `cero`, `uno`, `una`, `dos` through `diez`, `cien`, `mil`, `doble`, case-insensitively. Existing identical persisted values, the exact domain title in headline, and the exact domain short description in subheadline bypassed the check. Acquisition copy did not use it.

All three supplied Arcilla paragraphs contain `una`, which triggered the commercial-truth error. `Materiales incluidos` was not a trigger. Normalized paragraph lengths: description 98 / max 1000, value supporting copy 156 / max 180, closing description 103 / max 1000. These were false positives, not length violations or attempts to persist domain fields. The root cause was reported before changing logic.

The Admin summary also lacked a label for `reward_display_override` and fell back to the raw internal key. Default server length messages could name that field technically too.

## Bounded change

Removed the semantic keyword/number closure and its bindings. Editorial prose retains nullable/string, canonical length and plain-text constraints. Enums, effective headline emphasis, slug uniqueness, existing forbidden actions/URLs/styles, VSL controls and read-only domain projection are retained. The importer/schema, adapters, DB and templates are unchanged; no warning/moderation engine added.

The existing field-label map now covers all editor controls and has human fallbacks for nested video/unknown errors, extracted as `landingEditorFieldLabel`. Server length errors use human attributes and specific Spanish character-limit messages. Shared neutral help says: “Los datos comerciales y condiciones se toman automáticamente del producto.” Actual errors remain field-specific and red.

## Focused validation

- Docker PHP 8.4 / db-test / jakawi_test only. No host PHP used or inspected. No production requests or record changes.
- `LandingPublisherWorkflowTest`: **3 passed / 155 assertions**. Existing 17-field persistence/Preview integrity; exact Arcilla paragraphs save and project verbatim; product attributes unchanged; 181-character value copy returns `Copy complementario del valor no debe superar 180 caracteres.`; HTML, invalid template/theme, CTA URL/action, structural prices and invalid emphasis remain rejected.
- `bash app/tests/marketing/run-landing-json-tests.sh`: **13 passed**. Explicit `price`, `member_price`, `membership_required`, `validity`, `reward`, CTA/action and unknown-field rejection retained; enum/shape/length/plain-text/emphasis constraints retained; human labels and safe nested/unknown fallbacks covered.
- TypeScript: **PASS**.
- [One desktop Admin smoke](browser-validation.json): **PASS**, real authenticated Laravel HTTP requests and direct isolated DB inspection with final compiled assets. [Fixture](arcilla.json) uses the supplied three editorial paragraphs in the canonical JSON contract. Import → dirty → Save → server-confirmed success; no error summary; all three fields persisted exactly; refreshed Preview contains `DEJA EL SCROLL. ENSÚCIATE LAS MANOS.` and all three paragraphs. ONE safe slug-collision save verifies human label/message, red real error, focus, full form preservation, unchanged persisted Preview and neutral help. Exactly two PUT requests, no publish.
- [Saved screenshot](saved-desktop.png), [collision screenshot](failed-save-desktop.png).
- ONE final frontend validation build: **PASS**, manifest 103 pages, all compiled dependencies present. Wayfinder PHP routed into Docker through the existing PATH shim. No validation rebuild or deployment.
- Docs checker: **PASS**; `git diff --check`: **PASS**.
- Isolated fixtures removed and browser harness stopped after QA. No broad suite or funnel retest.

Docs describe structural domain protection and editable editorial prose. The pre-deploy validation above is historical.

## Production release — LIVE

[Release evidence](production-release.json) and [desktop live smoke](live/admin-browser.json): one clean production build of `642dbd7c5ea6ed1d605fdba546cf19476b865013`; protected rollback `0a0d4b6939ae914ffe9face7436d099e1cfac6a4`; no pending/run migrations. Only app/web/social-worker recreated. App/DB healthy, web/worker running, V2PROOF unchanged/healthy, cron active; /up local/public and Café/Sunset/Reto 200; assets/manifest/dev-runtime guards PASS. Failed jobs 3 historical / 0 new; CRM TRUE, Meta OFF.

Existing Arcilla DRAFT id 15: approved exact editorial paragraphs imported with existing name/slug preserved; JSON applied/dirty/saved feedback PASS; exactly one editorial PUT, no publication or audience/campaign changes. Refreshed saved Preview renders headline and all three paragraphs. Structural price/membership/CTA URL/action JSON atomically rejected client-side without Save; specific errors red, shared help neutral. Domain counts and the full product projection remain unchanged. [Saved](live/saved-desktop.png) and [structural rejection](live/structural-rejection-desktop.png) screenshots. Local-only allowlisted harness used existing Admin identity and production assets/kernel with isolated session; stopped after smoke.

Editorial Validation Fix, Publisher Workflow UX V1, JSON V1 and AI Product Brief V1: LIVE. Batch: FUTURE. Welcome Courtesy: FUTURE / OFF. Docs-only followup: no rebuild or redeploy.
