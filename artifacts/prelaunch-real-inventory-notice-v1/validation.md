# Real-inventory prelaunch notice V1 validation

Base: clean fetched `origin/main` `dc3f2bf84b84b0cd54e1a25c2c1b65df503efb60`. Actual app/web/social-worker runtime confirmed `70b597868511d95da1581c16d10e49a6877ad43d`, all images matching. Production inventory empty, prelaunch/CRM true, Meta OFF. Docker PHP 8.4 only; no host PHP use or inspection. No production Partner, catalog, user, referral, ownership, reward, membership or migration mutations.

## Scope and contract

Only production code change: shared [PrelaunchNotice](../../app/resources/js/components/prelaunch-notice.tsx) text. Existing sticky placement, spacing, styling, z-index, routes, layouts, visibility and robots remain untouched. No feature, entitlement, CMS, database or domain change.

Canonical copy:

> MODO PRE-LANZAMIENTO
>
> JAKAWI está en pruebas. Algunas experiencias, beneficios y premios pueden estar disponibles únicamente para participantes invitados. Revisa las condiciones de cada propuesta.

The prelaunch lab may contain real Partners, locations, commercial prices/values and controlled real fulfillment for invited/selected participants. Each proposal's existing domain conditions remain authoritative. This does not announce public launch or label all offers unavailable/fictional/demo. Production real inventory stays PENDING until deliberately loading the first Partner. Welcome Courtesy FUTURE / OFF.

## Deep safe cleanup

[Exact cleanup result](cleanup-result.json): initial available **16,187,133,952 bytes / 15.075443 GiB**. Unused build cache pruned; obsolete stopped `jakawi-json-candidate` and `jakawi-vsl-dev-demo` removed without volumes. Only the unused, unprotected app/web `release-448d7d2…` images removed, after checking every container reference and rollback tags. Cleanup stopped immediately at **23,674,617,856 bytes / 22.048706 GiB** free, reclaiming **7,487,483,904 bytes / 6.973263 GiB** actual filesystem space.

No application build during cleanup. Current containers/images, all protected rollback images, volumes/PostgreSQL data, backups, v2proof and operational evidence preserved. No networks removed. Test image preparation began only after target was reached. Exact current runtime rollback additionally tagged `jakawi-app:rollback-notice-70b5978`, `jakawi-web:rollback-notice-70b5978`; no rollback rebuild.

## Focused validation

- [Backend](backend-tests.txt): **PrelaunchModeTest — 4 passed, 133 assertions**, Docker isolated `jakawi_test` only. Covers root exclusion/indexability, Partner/public and representative Landing scope, Admin exclusion, and disabling the flag. No broad suite.
- [Component checks](components.txt): **2 passed**, exact new copy, one notice, no absolute commercially-unavailable wording, unchanged sticky/z-index behavior and Coming Soon exclusion.
- TypeScript PASS. [ONE final frontend validation build](validation-build.txt) PASS: 104 Inertia pages, compiled assets/dependencies present, semantic dev-runtime guard. Wayfinder PHP routed to Docker via PATH shim; no host PHP.
- [390 browser validation](browser-validation.json), final compiled assets + real isolated Laravel HTTP kernel: Partner, representative Landing, Product Detail, Activation and QR Delivery all show exact new copy once and noindex,nofollow. Notice remains sticky/readable with no overflow. Inventory CTA, modal input/submit/close controls and generated redemption QR are unobscured. No form submit/signup/domain action. [Partner](partner-390.png), [Landing modal](landing-modal-390.png), [Delivery QR](delivery-qr-390.png).
- PRELAUNCH_MODE=false hides notice and prelaunch noindex on Partner. Root Coming Soon hides notice and retains index,follow. [Admin quick smoke](admin-1440.png) 200, no notice. Browser errors/mutations zero.
- [Isolated fixture cleanup](fixture-cleanup.json): zero QA Partners/users remaining, test QR/membership removed. Temporary PHP/proxy servers stopped.
- Docs checker and git diff --check PASS. Production code/layout/policy untouched beyond notice text.

## Repeat visual QA

After the focused test wrapper migrates isolated `jakawi_test`, run the guarded [fixture/router](../../app/tests/marketing/prelaunch-real-inventory-qa.php) inside Docker PHP only. It refuses any other environment/DB/host. It seeds DOSIS 43 — QA from the existing Partner profile fixture and one isolated pending redemption for QR inspection. Test identity injection applies only to isolated Admin/Activation/Delivery GETs; `?normal=1` changes the in-memory flag only in this test router. Never production.

Serve the router on Docker internal port 8099 with APP_URL=http://127.0.0.1:8098 and SESSION_SECURE_COOKIE=false. Use the existing [local asset proxy](../../app/tests/marketing/partner-profile-proxy.mjs), PARTNER_TEST_HOST set to that isolated container IP. Run [browser script](../../app/tests/marketing/prelaunch-real-inventory-browser.mjs) with NOTICE_BROWSER_MODULE pointing to Puppeteer Core, NOTICE_CHROMIUM to Chromium and optional NOTICE_ARTIFACTS output. Execute the guarded fixture with `clean` afterward and stop both servers.

## Production release — LIVE

Deployed application `e71fbd8dda5d4f4fbc26a58b4d7495f369daa638` directly from actual `70b597868511d95da1581c16d10e49a6877ad43d`, one clean-export production build. App/web image guards and candidate root/public/Admin renders passed before cutover. Readiness passed in 2.50 seconds after one transient HTTP 000; bounded 120-second / 2-second poll preserved. Only app/web/social-worker recreated; DB/v2proof container IDs unchanged and healthy. No migration, Partner/catalog creation, signup or economic mutation.

Live HTTPS root: Coming Soon, no notice, index,follow. Live HTTPS empty Benefit listing: exact new copy once, noindex,nofollow, readable at 390 with no overflow. Read-only authenticated existing Admin rendered with deployed assets: no notice, no catalog. Unknown root and technical Partner paths 404. Public Partner/Landing/Activation/Delivery copy/scope established by exact deployed source hashes and prior isolated browser checks, without production fixtures. [Release evidence](production-release.json), [live browser](live/browser-validation.json).

Scheduler active, failed jobs 0, CRM TRUE, Meta OFF, health 200/200. Real inventory PENDING; Welcome Courtesy FUTURE / OFF. Notice prerequisite for controlled real inventory completed. Docs status followup only, no rebuild/redeploy.
