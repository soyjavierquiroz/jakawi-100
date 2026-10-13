# Publisher workspace UX V2 validation

Base: clean fetched `origin/main` `cfd64f165a99cfcd4e25c2565d6a0252d0ad1e87`. Production application stays `642dbd7c5ea6ed1d605fdba546cf19476b865013`. NO DEPLOY.

Scope: layout/workflow polish of the current Admin. Desktop editor/Preview 45/55; sticky toolbar and saved-state Preview; actual 390 default canvas, 430 and fitted 1440 option; mobile stacking; compact AI workflow; product truth collapsed with full facts; prominent current media; low-frequency subject/template controls in Advanced. Existing save/import/export/lifecycle routes and persistence preserved. No DB migration, schema/model/Publisher/Preview/template/media/AI architecture change.

QA uses the existing isolated `jakawi-test-app-test-1` Docker PHP 8.4 and `db-test` / `jakawi_test`, guarded V1 router and asset proxy, with a seeded Arcilla DRAFT. No production data requests or writes. No host PHP invoked or inspected. The first build attempt stopped before module compilation because copied test-container files were owned by root. Corrected ownership in that isolated container. Initial compiled QA identified a mobile sticky-toolbar overlap; restricted stickiness to desktop and added a zero-mutation assertion for viewport controls. One final frontend compilation and manifest validation followed that correction. The isolated router has no local manifest/version; the proxy serves the final built assets, as in the V1 harness.

Result: **PASS — PUBLISHER UX V2 READY FOR DEPLOY**. No deployment.

Evidence:

- [Focused frontend tests](frontend-tests.txt): **PASS**, 13 JSON/field-label tests.
- [TypeScript](typescript.txt): **PASS**, `tsc --noEmit --project app/tsconfig.json`.
- [Final frontend build](frontend-build.txt): **PASS**, Docker-routed Wayfinder, frontend compilation and manifest dependency guard (103 pages).
- [Browser report](browser-validation.json): **PASS**, 11 focused checks with real Laravel HTTP save/validation redirects, direct isolated persistence inspection and actual saved Preview. Desktop split/default/sticky/AI/product facts/media, import/dirty/save/refresh, publication guard, same-slug refresh, failed validation preservation, new DRAFT, duplicate/archive, mobile stacking and no horizontal overflow.
- [Final compiled smoke](final-smoke.json): **PASS**, final named-Preview label, desktop/media screenshots, mobile controls fit with zero writes. The comprehensive browser regression precedes this display-only label restoration; this final smoke uses the final compilation.
- [Desktop](saved-desktop.png), [mobile editor](mobile-editor.png), [mobile Preview](mobile-preview.png), [desktop media](media-desktop.png), [failed save](failed-save-desktop.png).
- [Docs checker](docs-validation.txt): **PASS**; [diff check](diff-check.txt): **PASS**.

Repeat: run the existing guarded seed/router/proxy described in [V1 validation](../publisher-workflow-ux-v1/validation.md). Set `PUBLISHER_BROWSER_MODULE` to Puppeteer Core, `PUBLISHER_CHROMIUM` to Chromium, then run `node app/tests/marketing/publisher-workspace-browser.mjs`. The canonical import fixture is [import.json](import.json). Stop the servers and remove isolated fixtures after QA.

Both local harness servers stopped and isolated fixtures removed after QA. No broad backend suite or funnel retest. Read-only authoritative product truth remains available; Preview still renders saved state, and publication is separate with the existing dirty guard.
