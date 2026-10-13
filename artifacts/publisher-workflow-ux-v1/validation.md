# Publisher workflow UX V1 validation

Base: `af87e1f6ef11d8fad54b9aca6b5858968d6ee8e2`, clean fetched `origin/main`. At implementation validation, production application remained `44636ae1c41f5828c702c052b99f3e3b7c778263`. NO DEPLOY. No production records read or written for QA. Docker PHP 8.4 / `db-test` / `jakawi_test` only; no host PHP invocation or inspection.

## Root cause — reproduced before changes

[Actual request/response evidence](reproduction.json), from the original compiled frontend and authenticated Laravel Admin in isolated DRAFT Experience state:

- Save does execute: real PUT → 303 → redirected Admin GET 200. The latter carries Inertia validation errors or updated persisted presentation props; redirect status alone does not prove successful persistence.
- Structurally valid JSON is not a promise of server acceptance. `Descubre una forma de crear con arcilla.` triggers the existing commercial editorial validator's `una` pattern. The server returns a real `subheadline` error and leaves DB unchanged. That rule is retained. The UI previously showed only a small field error, with no top-level failure/success distinction.
- Accepted copy (`Descubre tu forma…`) persists imported name, slug and all editorial/control values. No backend loss or self-slug uniqueness bug was found. Success called `setOpen(false)`. Opening CREAR LANDING afterward initializes a new blank form, explaining the repeated name/slug prompt in that reproduced path; there is no evidence of a server-required-name error for nonempty imported values.
- Preview keeps a local `Presentation` object and an iframe keyed only by slug. Save neither replaced that object nor remounted the iframe. Even after the accepted update, it displayed `PREVIOUS SAVED COPY` from the earlier saved presentation. This is demonstrably stale frontend state, not an intentional domain-title fallback. Existing Experience Offer Conversion renders the saved editorial headline; domain title remains in its independent product context.

The full human production import/request was not supplied; these are demonstrated causes in the exact existing import/save/preview paths, rather than guessed contents of that request.

## Validation

- Focused Docker backend: `LandingPublisherWorkflowTest` — 2 passed, 121 assertions. All 17 mapped fields → persistence → Admin reload → saved Preview; own unchanged slug allowed; collision explicit and no write; actual editorial rejection unchanged.
- JSON focused tests: `bash app/tests/marketing/run-landing-json-tests.sh` — 12 passed.
- TypeScript: `app/node_modules/.bin/tsc --noEmit --project app/tsconfig.json` — PASS.
- ONE final frontend validation build: `npm run build` — PASS, 103-page manifest and all assets/dependencies present. Wayfinder's PHP invocation routed to Docker through a PATH shim. No host PHP.
- [Desktop browser flow](browser-validation.json), final compiled assets with real authenticated Laravel HTTP requests, session validation redirects and direct isolated DB inspection. No mocked Save/Preview responses. Valid import, no auto-persistence, dirty state, saved-only Preview, publication block, successful PUT, 17 persisted fields, same-slug iframe refresh, editor reload, collision preservation, first-error focus, actual editorial rejection, neutral help versus red error, current image and new-DRAFT POST.
- [Saved screenshot](saved-desktop.png), [failed-save screenshot](failed-save-desktop.png), [canonical import fixture](import.json).
- Markdown relative-link checker and documented JSON parsing: PASS. `git diff --check`: PASS.
- All isolated fixtures removed after QA. No funnel retest or broad backend suite. No deployment.

## Repeat the browser flow

Use the existing isolated `jakawi-test` Docker PHP container with current backend source and migrated `jakawi_test`. Run [seed](../../app/tests/marketing/publisher-workflow-seed.php) in that container only. The [guarded router](../../app/tests/marketing/publisher-workflow-router.php) boots the real Laravel HTTP kernel, injects the isolated Admin and allows read-only fixture inspection. Serve it with Docker PHP at internal port 8099, `APP_URL=http://127.0.0.1:8098`, `SESSION_SECURE_COOKIE=false`. Both PHP helpers refuse any environment/database/host other than testing/jakawi_test/db-test. They are test harnesses, never production routes.

Run [local proxy](../../app/tests/marketing/publisher-workflow-proxy.mjs) from repo root with `PUBLISHER_TEST_HOST` set to the isolated container's internal IP. It serves the final built assets and relays requests/responses to Laravel; initial HTML embeds actual Inertia props. It serves a synthetic SVG for the fixture asset only. Then run [browser script](../../app/tests/marketing/publisher-workflow-browser.mjs) with `PUBLISHER_BROWSER_MODULE` pointing to Puppeteer Core and `PUBLISHER_CHROMIUM` pointing to Chromium. `PUBLISHER_ORIGIN` defaults to `http://127.0.0.1:8098`, `PUBLISHER_ARTIFACTS` defaults to this directory. Fixtures must start in the seeded state. Stop both harness servers and remove the isolated fixtures afterward.

## Scope

Import does not save. Success is server-confirmed. Failed saves preserve the entire current form and saved Preview. Publication with unsaved changes is blocked, never automatic. Media uses the existing MediaUrl resolver and reference input. No Landing JSON/schema/DB migrations, domain-truth changes, Publisher rebuild, template/adapter changes, AI integration, acquisition/Delivery changes or new preview engine. Publisher UX V2 remains optional future polish.

## Production release

Publisher Workflow UX V1 is now **LIVE** on `0a0d4b6939ae914ffe9face7436d099e1cfac6a4`, from actual runtime `44636ae1c41f5828c702c052b99f3e3b7c778263`. [Release evidence](production-release.json), [real deployed Admin smoke](live/admin-browser.json). One production build; protected exact rollback images; no migrations, domain mutations or published-record edits. The temporary DEMO DRAFT was removed after one successful Save, one reload and one safe collision. Docs-only status followup; no rebuild/redeploy. The implementation validation above is historical pre-deploy evidence.
