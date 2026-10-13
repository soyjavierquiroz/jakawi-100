# AI Product Brief V1 validation

Base: `a26b93fce18512196b03de1eff1f40d64095999a` (clean actual `origin/main`). No deploy.

- Focused backend: `./bin/jakawi-test php artisan test --filter=LandingAiBriefTest` — 7 passed, 90 assertions; isolated Docker PHP 8.4 / db-test / jakawi_test.
- TypeScript: `app/node_modules/.bin/tsc --noEmit --project app/tsconfig.json` — PASS.
- One final frontend validation build: `npm run build` — PASS; its Wayfinder PHP invocation routed to Docker PHP through a PATH shim. No host PHP executed or inspected. Manifest: 103 pages, all assets/dependencies present.
- Desktop Admin smoke: [browser report](browser-validation.json) — PASS, real clipboard plus denied-clipboard fallback. Real authenticated Laravel Admin props rendered in isolated test DB from a read-only public snapshot of current “20% en peinado de evento — DEMO”; all fixtures rolled back. Final compiled assets served locally. No production writes, no external AI, zero requests during copy. Form/campaign/audience unchanged.
- [Clipboard JSON](brief.json) and [desktop screenshot](admin-brief-desktop.png).
- Markdown checker — 64 files, 405 relative targets, zero broken. Documented JSON examples parse successfully.
- `git diff --check` — PASS.

The current acceptance product and partner are archived. The exported domain availability is false. No published applicable locations currently exist, so locations/city are omitted. The 20% is authoritative title text; no separate numeric discount exists in the domain and no amount was inferred from estimated savings. Validity, access requirement, stored description/conditions and DEMO disclosure are preserved.

The [browser script](../../app/tests/marketing/ai-product-brief-browser.mjs) runs from the repo root with `AI_BRIEF_FIXTURE_DIR` (containing an isolated Laravel `page.json`), `AI_BRIEF_BROWSER_MODULE` (Puppeteer Core module), `AI_BRIEF_CHROMIUM` and `AI_BRIEF_ARTIFACTS`. This harness does not invoke external AI or Save/Publish.
