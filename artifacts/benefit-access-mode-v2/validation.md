# Benefit access mode V2

Implementation validation: 77 focused tests / 1527 assertions pass across Benefit, redemption continuity, Challenge grants, Landing acquisition and Partner acquisition/profile. Isolated Docker PHP 8.4 with `./bin/jakawi-test`, testing / db-test / jakawi_test. TypeScript passes. One final frontend validation build passes; 104-page manifest and semantic runtime guards pass. No host PHP.

`access_mode` VARCHAR is reused without migration: `public` still requires active membership; `authenticated` requires a signed-in account, with no membership requirement; `social_challenge_grant` retains its valid owned reward/verification requirements. Historical default `public` unchanged. Admin rejects unknown modes and labels all three choices. New mode is discoverable without exposing Challenge rewards.

Canonical Benefit activation and owned redemption Delivery are reused. Guest → Partner → Benefit → applicable Landing → acquisition/register → authenticated activation → owned Delivery → PIN confirmation is exercised in isolated tests, with zero memberships/purchases, idempotent confirmation and second successful redemption blocked. Missing PIN, invalid PIN, foreign location and non-owner access are rejected. No attribution/referral implementation changes. Welcome Courtesy FUTURE / OFF. No capacity field; Dossis target 20 is manual.

Release and production data evidence will be appended after authorized deployment. No production consumer journey is executed.


## Production release — LIVE

Application `b008a236fa68752e0f911109f3adf099799ebd6d` deployed from actual runtime
`359c98cfbe00dda393a4630c203f2892a4c2c3d1`. [Release record](live/production-release.json).
One clean production build from Git export; no migration or ceremonial backup.
17.16 GiB available before build; no cleanup. Exact app/web rollback images tagged
and [rollback configuration](live/rollback.yaml) retained. Only app/web/worker
recreated; DB, MinIO, imgproxy and v2proof untouched. PRELAUNCH_MODE unchanged.

[Health](live/health.json): /up 200/200, app/DB/v2proof healthy, web/worker running,
scheduler active, failed jobs 0. [Final production state](live/final-state.json):
1 Partner, 1 Location, 1 Benefit, 1 Landing, 2 existing users; no new consumers,
memberships, canjes, reservations, referrals, ownership, transactions, Experiences,
Challenges or Unlocks. Existing Dossis Location 1 PIN configured through
`Location::setRedemptionPin`, verified in memory, never printed/stored in plaintext.
Admin at `/admin/locations/dossis43/edit` can reset via PIN nuevo (6 dígitos),
with PIN configurado: Sí. Existing plaintext cannot be viewed (hash-only contract).

[Browser QA](live/live-browser.json): deployed production HTTP kernel in READ ONLY,
analytics disabled in the render process only, real public compiled assets (75
hash-verified), no consumer mutation. CTA analytics POST intercepted/aborted.
Partner returns 200, active card routes to canonical Benefit; [canonical redirect](live/benefit-route.json)
302 selects `/l/dossis43-parada-estrategica`, which returns 200. Guest CTA opens
Micro Opt-in step 1; stop before input/submission. Existing Admin non-member can
reach activation with no POST. Admin Benefit, Publisher, Preview and Location
controls open read-only. Public media NONE, no broken Benefit image, no overflow at
390/1440. Notice/robots correct, Coming Soon and absence of root Dossis link preserved.

Benefit 1: `published`, `authenticated`, one confirmed redemption per user, applies
only to existing Dossis Location 1, expires 2026-11-30 23:59 America/La_Paz.
Bs 35 paid directly to Dossis; no payment/purchase/JP record. Price lives in
commercial description/terms and Landing copy; estimated_savings remains NULL.
Internal name Parada Estratégica JAKAWI and JAKAWI confirmation/operational target
are retained in review_notes because Benefit has no separate internal-name field.
No invented products or speed promise; manual target 20 is absent from public
conditions and never described as enforced capacity.

Landing 1: PUBLISHED / ALL / OFFER_CONVERSION / LIGHT_CONVERSION / OFFER_FIRST /
COMPACT / NONE; empty campaign_key. Editorial copy persisted with editorial_version=1.
Canonical button labels remain QUIERO MI BENEFICIO / USAR MI BENEFICIO; the saved
acquisition headline is QUIERO MI COMBO. No custom CTA URL or routing.

[Partner 390](live/dossis43-390.png), [Landing 390](live/landing-390.png),
[Landing 1440](live/landing-1440.png), [Micro Opt-in entry](live/acquisition-entry-390.png).

Final focused redemption rerun: 17 passed / 135 assertions (new access cases plus
historical MembershipRedemptionTest); [evidence](redemption-final-tests.txt).
The initial focused set is [77 passed / 1527 assertions](tests.txt).
Docs checker and git diff --check PASS. Documentation/evidence followup only;
no second production build or redeploy.

READY FOR: new guest / existing user logged out / authenticated user / real redemption QA.
DOSSIS 43 FIRST BENEFIT LIVE — READY FOR USER-JOURNEY QA
