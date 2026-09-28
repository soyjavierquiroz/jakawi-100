# JAKAWI — Launch Readiness V1

**STATUS: ARCHIVED SNAPSHOT (2026-09-27).** Sus comprobaciones de ese día no sustituyen el estado actual: [CURRENT.md](CURRENT.md) establece *implementation ready / functional environment verification pending* para el código al 2026-09-28.

Audited on 2026-09-27 against production commit `d0238f5bb9780a1e70b69473f02efedfec2d7a95`. This is an operational checklist; no production records were created or changed.

## READY

- Public Home, Explore, login, registration, one published Partner, Benefit, and Experience returned HTTP 200. Built frontend assets returned HTTP 200; no Vite mismatch observed.
- Guest and registered-user routes exist for discovery, referral context, membership view/profile, benefits/redemption, experiences/reservations, and Unlock participation. Member savings, referral, JP balance and confirmation/fulfillment states are implemented.
- Partner portal is scoped by partner membership. It includes redemption validation, reservation/check-in, content proposal/review, acquisition/performance data, Unlock proposal/monitoring/fulfillment and partner payout request.
- Admin routes are protected and cover partners, locations, benefits, experiences, memberships/manual sales/refunds, promoters, affiliates, creators, campaigns, payouts, reward rules, adjustments, exports and Unlock operations. Refund and payout paid/reject actions are Admin-only.
- Manual cash membership sale, activation, internal reference and seller credit are available to active promoters; refunds remain Admin-only.
- Affiliate and Creator dashboards, referral links, aggregated attribution metrics, commissions and manual payout request flows exist. Admin records paid/rejected payouts with an external reference/reason.
- Scheduler: exactly one JAKAWI cron entry runs every minute; `bin/jakawi-schedule` is executable and uses non-blocking `flock`; recent log entries show successful `unlocks:expire` executions.
- Media: imgproxy is running; an existing signed production image returned `200 image/webp`. Published assets have fallback-capable image components. Original-object credentials are not exposed by this check.
- Backup: the approved containerized `pg_dump` and non-destructive `pg_restore --list` procedure is documented in `docs/OPERATIONS.md`. The newest non-empty custom backup was successfully listed (481 entries).
- Security/privacy spot check: Admin and program middleware protect privileged routes; partner paths additionally check the exact partner relation; public Unlock serialization removes secret fields before goal reached; CSV export is Admin-only and excludes contact/IP/user-agent fields; self-referral is rejected; production QR is disabled (not fake). Partner check-in sends only the check-in code, abbreviated display name (first name plus last initial), validation state and reservation context.
- Analytics persistence is present for discovery, benefit/redemption, experience reserve click and referral. The Unlock lifecycle taxonomy and persistence exist; no Unlock activity exists yet.

## BLOCKERS

1. No active or scheduled Unlock exists in production. The public Unlock journey, JP guarantee, confirmation and fulfillment cannot be operated or verified with real launch supply.
2. No active Promoter, Affiliate or Creator enrollment exists. Those three operational journeys cannot be used by any production operator.
3. No active reward rule exists (including no Partner rule). Commission/reward generation cannot be launched for Promoter, Affiliate, Creator or Partner acquisition.

## CONFIGURATION REQUIRED

- Membership: **READY** — BOB 100, 365 days, `jakawi_annual`.
- Affiliate payout minimum: **READY** — BOB 100.
- Partner payout minimum: **READY** — BOB 100.
- Attribution window: **READY (default)** — 30 days in the Admin workflow; no overriding `app_settings` value exists.
- Unlock JP commitments: **READY** — `UNLOCK_JP_COMMITMENTS_ENABLED=true`; create an approved zero/positive-JP Unlock only after the operator has reviewed its guarantee and fulfillment plan. Do not create JP test movements.
- Scheduler: **READY** — cron/minute, wrapper and recent successful log verified.
- QR payments: **DEFERRED** — `QR_PAYMENT_ENABLED=false`, driver `disabled`; no fake QR is enabled.
- Before enabling program-led acquisition: enroll approved operators in Admin and create/activate the applicable CASH reward rule(s). Confirm campaign dates/participants/rule if campaigns are part of launch.

## CONTENT REQUIRED

- Current published supply: 13 Partners, 18 Locations, 30 usable Benefits (all with effective published location coverage), 8 published Experiences and 12 future scheduled sessions.
- Current campaign/Unlock supply: 0 campaigns, 0 active/scheduled Unlocks.
- All 13 published Partners, 30 published Benefits and 8 published Experiences have configured media. Verify each commercial offer, hours, contact and fulfillment owner before public announcement; do not manufacture inventory.

## DAY-OF-LAUNCH CHECKLIST

- Confirm the three blockers above are either completed or explicitly removed from the launch proposition.
- Confirm the on-duty Admin, each live Partner owner/manager and each live Promoter/Affiliate/Creator can sign in with their assigned account.
- Verify one approved membership activation, one benefit redemption at the operating location, and one experience fulfillment only with real authorized users/transactions.
- For each live Unlock, review deadline, eligibility, JP deposit/bonus, target, capacity, confirmation window, fulfillment owner and no-show handling.
- Recheck `docker compose ps`, local/public `/up`, scheduler log freshness, public Home/Explore and one signed existing image.
- Verify latest backup is non-empty and `pg_restore --list`-valid; record its path/checksum in the release log. Do not restore production.

## KNOWN DEBT

- **A — Launch blocker in the present scope:** the three production readiness gaps listed in BLOCKERS.
- **B — Important after launch:** real QR payment provider remains unavailable; contextual paywall branch is not deployed. The isolated baseline reproduces two test-only failures: `CampaignTest::test_admin_can_create_campaign_and_non_admin_cannot` expects a login redirect for an authenticated non-admin but receives the current correct `403`; `PublicV2HttpTest::test_benefit_listing_filter_and_detail_follow_availability` expects one detail location but receives zero. Public production Partner/Benefit/Experience routes were independently HTTP-200 checked, so neither is classified as a production Campaign/Public blocker.
- **C — Deferred by design:** Reward Catalog / JP spending beyond Unlock guarantees, advanced notifications, advanced reputation, waitlist automation, automated payout provider and social APIs. None is a blocker for the current manual-MVP workflow.

## POST-LAUNCH WATCHLIST

- Monitor scheduler failures/overlap, failed jobs, Unlock deadline transitions and JP hold/release/forfeit audit records.
- Monitor redemption expiry/confirmation, experience reservations/check-ins, attribution conversion/reward reconciliation and payout requests awaiting Admin action.
- Watch media rendering/imgproxy errors and preserve the backup validation cadence.
- Review analytics volume for `signup_completed`, membership conversion and Unlock lifecycle events after live use; absence today is expected because there is no live Unlock/campaign activity.
