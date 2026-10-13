# Landing JSON V1 validation — 2026-10-13

Baseline: clean `origin/main` `cd70ed913566b4110a21d5e82273958bf8ebc894`. No deployment or production records accessed.

- Focused frontend tests: `cd app && bash tests/marketing/run-landing-json-tests.sh` — 12/12 PASS.
- TypeScript: `cd app && ./node_modules/.bin/tsc --noEmit` — PASS after restoring installed dependencies with `npm ci --no-audit --no-fund`; lockfile unchanged.
- Final frontend build: `npm run build` — PASS; manifest validates all 103 Inertia entries and assets. Wayfinder's PHP command used a temporary PATH shim invoking Docker PHP 8.4 in the isolated test container, never host PHP. One final frontend build; no SSR/backend suite.
- Documentation: `python3 bin/check-docs.py` — PASS.
- Whitespace: `git diff --check` — PASS.

[Desktop browser report](browser-validation.json) and [export dialog screenshot](admin-export-desktop.png) cover one existing Admin editor flow with the final compiled frontend. Laravel generated real Admin props from a DRAFT Benefit/Landing and admin factory fixtures inside `db-test` / `jakawi_test`. The fixtures were enclosed in a transaction and rolled back after rendering. Isolated PHP commands and migrations used `./bin/jakawi-test`. The browser served these props and built assets from a localhost fixture server; it never accessed production. No Save/Preview/Publish was requested; mutation requests were intercepted and the passing report requires zero. This QA validates the actual editor UI/state and JSON roundtrip; it does not claim a backend persistence retest.

No DB model, migration, Publisher, template engine, AI service, Unlock expansion, AI brief or batch implementation. Acquisition copy remains a separate Micro Opt-in wiring task. Welcome Courtesy remains FUTURE / OFF.

## Production release

Landing JSON V1 is **LIVE** on `19c946870a6a54981acc8b41f4ef0b56188226fe`, after one new clean production build with the semantic guard correction. [Production release evidence](production-release.json) and [LIVE Admin smoke](live/admin-browser.json). Guard behavior and exact original bundle classification: [Production Asset Guard](../../docs/operations/PRODUCTION_ASSET_GUARD.md). No migrations, persisted test draft, domain mutations or cleanup. The documentation followup does not rebuild or redeploy.
