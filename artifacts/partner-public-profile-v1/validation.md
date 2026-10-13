# Partner public profile + acquisition V1 validation

Base: clean fetched `origin/main` / previous HEAD `a3c730eaaf072fe70131936af261125b188991b5`. Implemented for commit/push; **NO DEPLOY**. Docker PHP 8.4 and isolated `db-test` / `jakawi_test` only. No host PHP use or inspection. No production Partner, inventory, account or economic writes.

## Existing contracts reused

- `Partner.slug` is already unique and persisted. `Partner.users()` / `User.partners()` use `partner_user` with owner/manager/staff. Partner application and acquisition ownership are separate existing contracts; the public page does not invent applicant → Partner approval or account association.
- Existing `AttributionTouch.acquisition_partner_id`, visitor cookie, server session touch IDs and `CreateNewUser::create` are sufficient for origin continuity and new-account referral creation. `acquisition_provider` remains its existing NONE/META/TIKTOK/GOOGLE advertising dimension; Partner is represented by `acquisition_partner_id`, never a new provider enum.
- Existing `AttributionService::applyFirstValidReferrer` retains an already-valid active relationship. Existing expiry/replacement policy remains unchanged, but public profile visits and existing login never invoke referral assignment.
- Signup uses first referral-bearing anonymous/session touch inside the configured attribution window (30 days default), ordered by `occurred_at`, then `id`; Partner A → B credits A. Latest Landing/UTM measurement does not change that rule. If a Partner lacks a referral code, ownership creation provisions it through existing `ReferralCodeService`. Page views never provision it.
- Economic event: confirmed manual membership purchase via `MembershipPurchaseService::confirmManualCash`, conversion type `membership_purchased`; existing beneficiary precedence, configured reward rule, generic transaction/payout infrastructure. No new commission engine, amount, percentage or ledger.

## Implementation

Canonical public `/{slug}`; `/p/{slug}` and legacy `/partners/{slug}` redirect 301. Published, non-reserved profiles only; unknown/inactive/reserved resolution is 404. Shared reserved rule derives current static registered route prefixes plus constrained root programs and protected prefixes. Model writes, Admin form validation and `PartnerLocationRules` all share it. Existing unique slug schema remains; lowercase letters/numbers/single hyphens, no slug history. Vanity route follows explicit routes.

Public projection reuses `partners/show` and existing domain queries/media. Stored brand/name/logo/cover/description/category and published locations/city/zone/address/hours/map only; no invented offers or operational data. Current public Benefits, published Experiences with upcoming sessions, approved open Challenges within publication/participation windows. Sections omitted when empty; active empty Partner profile remains visible with a truthful upcoming message. Cards use native product routes, retaining authoritative Landing-first middleware; no profile Landing or duplicated product action.

Guest CREAR MI CUENTA and visible INICIA SESIÓN reuse existing forms via a server-resolved profile auth entry, returning naturally to the profile. Neither is needed to browse or to establish downstream origin. Authenticated viewers see inventory and no account block. Existing successful login associates pending anonymous touches to the existing user **for measurement only** and clears session candidates, never creating a referral or reward. Consumed touches cannot later acquire a new account after logout. Contextual direct registration retains email/WhatsApp identity protection and existing secure-access email behavior.

Existing Admin and Partner dashboard expose PUBLIC PROFILE and COPIAR ENLACE. Partner assignment authorization unchanged. Admin Partner edit/update/referral-code bindings explicitly use IDs to match their existing index/form URLs (implicit model binding otherwise used slug and returned 404). Global Admin management remains available; no new Partner portal.

Shared PrelaunchNotice and noindex,nofollow already cover `partners.*`. Root canonical points to configured application URL (production https://jakawi.com). Coming Soon does not link Partners. **Before first real controlled inventory goes live, replace the absolute commercially-unavailable notice with truthful controlled-prelaunch availability copy.** This followup is documented; production notice is unchanged.

## Validation

- [Targeted backend](backend-tests.txt): **45 passed, 710 assertions**. `PartnerPublicProfileTest`, `PartnerAcquisitionTest`, `PartnerRewardTest`, `PartnerAccessTest`, `LandingAcquisitionTest`, `PartnerLoginTest`, `PartnerTest`. No full suite. Includes all 21 requested backend cases across new and reused tests: canonical/technical/unknown/reserved/inactive, account association, all inventory/location domains, downstream/direct signup, existing login/auth identity/ownership stability, no commissions on visit/signup and configured economic purchase, prelaunch policy. Admin ID navigation, reserved update failure, valid update persistence also pass.
- TypeScript: `app/node_modules/.bin/tsc --noEmit --project app/tsconfig.json` PASS.
- [ONE final frontend validation build](frontend-build.txt): `npm run build` PASS, 104 Inertia pages; assets/dependencies present. Wayfinder PHP invocation routed through Docker PATH shim. No host PHP.
- [Browser results](browser-validation.json): real Laravel requests, guest cookies and authentication, final compiled assets; no forced user or mocked auth. 390 guest profile checks identity, description, location, Benefits/Experiences/Challenges, native CTA route, notice, robots/canonical, visible signup/login and no overflow. [390 profile](partner-390.png), [Micro Opt-in](micro-opt-in-390.png), [authenticated](authenticated-390.png), [existing login](existing-login-390.png), [1440 regression](partner-1440.png).
- **PRIMARY** browser path never clicks profile signup: `/dosis43` → VER BENEFICIO → `/beneficios/dosis-benefit-qa` → `/l/dosis-benefit-landing-qa` → three-step Micro Opt-in → POST acquisition 204 → POST register 302. New account id 65 receives `referral_relationships.acquisition_partner_id=44`, the isolated DOSIS 43 — QA Partner. Zero rewards, redemptions, reservations or Challenge participations.
- Clean separate browser session: profile → INICIA SESIÓN → actual existing account login 302 → profile. Existing user id 64 has no referral relationship, no pending touch IDs and no rewards. Account block hidden; inventory visible. Separate direct signup id 66 also credits Partner 44 and returns to the profile. Already-authenticated profile keeps the established owner.
- 1440 guest regression: three current inventory cards, no overflow. Coming Soon remains separate and has no Partner links. Browser errors: zero. Synthetic QA SVGs represent fixture media only; production media resolution remains unchanged.
- Markdown relative-link checker PASS; `git diff --check` PASS.
- [Fixture cleanup](fixture-cleanup.json): zero remaining QA Partners/users. Both temporary HTTP/proxy servers stopped. Subsequent focused backend run rebuilt only the isolated test image and reset only `jakawi_test`.

## Final acceptance mapping

| Area | Result |
| --- | --- |
| A — Ownership | Existing `partner_user` reused YES; Partner/account tie YES; new relationship/migration NO; separate CMS NO |
| B — URLs | `/{partnerSlug}` canonical, example `/dosis43`; `/p/{slug}` 301; reserved protection YES; unknown 404; inactive NOT PUBLIC |
| C — Page | Stored identity/description/locations YES; current Benefits/Experiences/Challenges YES; truthful zero-inventory state YES |
| D — Guest | CREAR MI CUENTA VISIBLE; INICIA SESIÓN VISIBLE; account required for browsing NO |
| E — Auth | Signup/login HIDDEN; inventory VISIBLE; acquisition reassignment NO |
| F — Context | Guest Partner context YES; survives Benefit/Product, Marketing Landing and Micro Opt-in YES |
| G — Primary | New signup from Benefit Landing owns DOSIS 43 — QA; profile signup button required NO |
| H — Existing | Login acquisition created NO; existing owner changed NO; pending false acquisition retained NO |
| I — Ownership rule | First referral-bearing valid-window touch by occurred_at/id; A → B credits A; existing valid relationship retained; visits/login never replace, even if expired; arbitrary Partner IDs trusted NO |
| J — Commission | Visit/login NO; signup ownership only; later confirmed membership_purchased invokes existing rewards; ledger/payout reuse YES; new engine NO |
| K — Prelaunch | Direct vanity access YES; Coming Soon links NO; notice YES; noindex,nofollow; real-inventory notice copy followup DOCUMENTED |
| L — Management | Public URL/copy YES in existing Admin/Partner management; authorization preserved YES; new portal NO |
| M — QA | Targeted backend, TypeScript, 390 profile/primary/new/signup/existing-login/authenticated, 1440, docs, one final build and diff PASS |
| N — Production data | Partners created 0; inventory created 0; domain mutations 0; isolated QA fixture only |
| O — Architecture | Migration NO; Partner CMS NO; payout ledger NO; referral engine NO; Landing engine changed NO; template engine changed NO |
| P — Git | Previous fetched origin/main a3c730eaaf072fe70131936af261125b188991b5; new commit/push SHA reported in final response; no deploy |
| Q — Decision | PARTNER PUBLIC PROFILE + ACQUISITION READY FOR DEPLOY |
| R — Resultado | JAKAWI PARTNER PUBLIC PROFILE V1 READY |

## Repeat browser QA

After focused backend tests migrate the isolated DB, copy [guarded QA fixture/router](../../app/tests/marketing/partner-profile-qa.php) into `jakawi-test-app-test-1` and execute it in Docker PHP to seed the fixture. It refuses any environment other than testing/db-test/jakawi_test. Serve that same router with Docker PHP `-S 0.0.0.0:8099`, `APP_URL=http://127.0.0.1:8098`, `SESSION_SECURE_COOKIE=false`. No user injection or production route registration.

Run [local asset proxy](../../app/tests/marketing/partner-profile-proxy.mjs) from repository root with `PARTNER_TEST_HOST` set to that isolated container IP. Run [browser flow](../../app/tests/marketing/partner-profile-browser.mjs) with `PARTNER_BROWSER_MODULE` pointing to Puppeteer Core and `PARTNER_CHROMIUM` to Chromium. Optional `PARTNER_ORIGIN` / `PARTNER_ARTIFACTS` override local defaults. Execute the guarded PHP fixture with argument `clean` after QA and stop both servers. Never seed production.

## Production release — LIVE

Runtime `70b597868511d95da1581c16d10e49a6877ad43d` deployed directly from actual `b386cf4882b86269fe6d1d5c2cc563cd1d64f0c7`, using one clean-export production build. Rollback images protected by exact IDs; only app/web/social-worker recreated. No migrations, production Partner/catalog creation, signup or economic mutation. The implementation validation above remains historical pre-deploy evidence. [Release evidence](production-release.json), [live browser smoke](live/browser-validation.json), [live route results](http-public-final.json).

Root Coming Soon and empty Partner Admin render PASS; local/public health 200/200. Reserved registered routes retain their handlers and baseline status. `/desbloqueos` had no registered endpoint before this release and remains 404; the new shared slug rule reserves it so no Partner can claim it. Unknown root/technical Partner URLs both 404, no redirect loops. All Partner-origin acquisition/identity/economic contracts verified against exact deployed source hashes and prior isolated acceptance evidence; no fake Partner created for production smoke.

Prelaunch notice unchanged; real inventory PENDING. Before first real controlled inventory, update absolute unavailable notice wording. Welcome Courtesy FUTURE / OFF. Documentation status followup is docs-only with no rebuild/redeploy.
