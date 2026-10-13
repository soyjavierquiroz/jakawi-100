# Prelaunch Mode V1 — validation

Baseline: clean origin/main `5eb59b8`; actual app/web/social-worker runtime `ba25043f2cb35b7e7feae12a4f234792cbf84c66`, newer than the supplied expected `642dbd7c5ea6ed1d605fdba546cf19476b865013`. Only actual runtime → current repository delta is reconciled; no intermediate releases. No schema migrations or production catalog creation.

PHP/Wayfinder executed exclusively in Docker PHP 8.4. Backend tests use the guarded `bin/jakawi-test` wrapper and isolated `db-test` / `jakawi_test`; no broad suite. Four tests / 112 assertions cover static empty-catalog Coming Soon, no Discovery calls, initial root SEO, direct Landing/native fallback/Explore policy, Admin/saved Preview/auth exclusion and flag OFF restoration. Two frontend render tests cover exact copy, root without navigation/links/forms, root robots/canonical, and permanent truthful sticky notice.

- [Backend tests](backend-tests.txt)
- [Frontend component tests](frontend-tests.txt)
- [TypeScript](typescript.txt)
- [One final frontend validation build](frontend-build.txt): 104-page manifest, dependencies and semantic dev-runtime guard.
- [Browser QA](browser-validation.json): actual isolated Laravel HTML and final compiled assets, no production catalog fixtures or domain actions. Opening acquisition emits its existing isolated CTA analytics POST; no acquisition submission or domain conversion is performed.
- [390 root](root-390.png), [390 Landing](landing-390.png), [acquisition controls](acquisition-390.png), [390 Activation](activation-390.png), [1440 root](root-1440.png), [Admin](admin-1440.png).

Local browser harness refuses any DB except testing / db-test / jakawi_test. Fixtures are isolated. Root OFF and ON use the same built assets; no waitlist, launch date or fabricated launch inventory. An initial compiled browser run found Head outside Inertia in withApp. Corrected to the shared PublicModeLayout inside Inertia; one final frontend validation compilation followed that correction. A redundant build command caused by an incorrect working directory was interrupted, with no source changes; it is not a production build. Root does not include PWA prompt or theme toggle. Notice is in document flow and below acquisition modal layers. Production catalog remains intentionally empty; direct behavior is demonstrated only in isolated QA.

Candidate gates PASS: 4 backend tests / 112 assertions, 2 frontend render tests, TypeScript, final frontend validation build, browser 390/1440 including Inertia transition, docs checker and diff. Production deployment and live checks will be recorded after cutover. Documentation followup requires no rebuild/redeploy. Welcome Courtesy FUTURE / OFF.

Repeat isolated QA: `./bin/jakawi-test php tests/marketing/prelaunch-qa.php`, then run a local-only Docker PHP server with that guarded router and serve the final compiled assets through `app/tests/marketing/prelaunch-proxy.mjs` (`PRELAUNCH_TEST_HOST` = isolated container IP). Run `app/tests/marketing/prelaunch-browser.mjs` with `PRELAUNCH_BROWSER_MODULE` and `PRELAUNCH_CHROMIUM` pointing to Puppeteer Core and Chromium. The fixture includes a published Benefit and applicable location only in jakawi_test; do not invoke on production.
