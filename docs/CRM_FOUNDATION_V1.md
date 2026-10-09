# CRM Integration Foundation V1 — implementation, unreleased

Laravel uses its own `crm_deliveries`, independently of analytics, Meta and `growth_provider_deliveries`. `CrmContactProjectionService` is the central contract builder. Eligible domain observers register after-commit callbacks; signup signals inside its transaction after attribution association. Outbox errors are contained after domain commit and logged with a fixed code only. No synchronous CRM HTTP happens in domain requests.

## Configuration

`config/crm.php` and `.env.example` document CRM_SYNC_ENABLED=false, CRM_PROVIDER=fluentcrm, CRM_BRIDGE_URL, CRM_BRIDGE_SECRET, CRM_CONNECT_TIMEOUT=3, CRM_TIMEOUT=8. Enqueue and dispatch require explicit true, supported provider, HTTPS exact bridge path and a secret of at least 32 bytes. No real configuration or secret was installed.

Bridge repository: `/home/crm.jakawi.com/public_html/wp-content/plugins/jakawi-fluentcrm-bridge`. Remote: https://github.com/soyjavierquiroz/jakawi-fluentcrm-bridge.git. Legacy repository, endpoint and table remain untouched.

## Projection

Signup, partner application with a valid submitted email, authenticated program application, membership request/activation/state change, confirmed benefit redemption, experience reservation, challenge participation, committed unlock, profile city/name/email/preference updates. Partner phone-only applications succeed without deliveries. A partner contact email different from the signed-in user's email is treated as a guest contact. Partners list requires an existing product partner relation; submitting an application alone never grants it.

FIRST and LATEST each select one historical AttributionTouch using occurred_at and ID as deterministic tie breaker, without changing campaign/conversion semantics. Each carries provider, all five standard UTM components (source/medium/campaign/content/term), campaign_key, landing slug and touch_at. Source tags derive only from FIRST, never LATEST. NONE produces no organic/provider source tag. The bridge freezes the complete first snapshot, orders last snapshots by touch clock, and uses event clocks for domain state and consent; activity is maximum timestamp. Historical activity tags remain additive across reordered deliveries.

## Consent

Register and partner forms show the exact Spanish single-opt-in checkbox, checked in the new-form UI. Missing/unchecked backend input means false. Consent is nullable until recorded; no legacy migration is required. Profile shows NULL as unchecked with explicit explanatory copy and persists only on save. Ordinary events use UNCHANGED; explicit changes use GRANT/REVOKE. No double opt-in or confirmation step. The bridge preserves provider suppressions.

## Delivery and links

Payload uses Laravel encrypted:array at rest, hidden from serialization; payload never includes bridge credentials. Scheduler `crm:dispatch` every minute uses existing scheduler, no worker/container. Up to 100 deliveries in a 40-second budget, bounded 3-second connection/8-second HTTP defaults (hard caps 5/10), PostgreSQL FOR UPDATE SKIP LOCKED through the HTTP call, and rollback on process death.

Six attempts, delays 1m/5m/15m/1h/6h; network, 429 and 5xx retry. Retry-After is bounded between 1m and 6h. Other responses, including 400/409/422 and 401/403, become DEAD. No redirect following. Successful response must contain OK and a positive provider_contact_id before creating a durable link. Unique provider/user and provider/contact ID prevent merges. A link discovered before a delivery's first attempt is copied into its stored payload; once attempted, event ID and body remain fixed, timestamp/signature refresh.

`crm_contact_links.last_known_email` is server-side domain data; it is hidden from model serialization. Deleting a user cascades their link. CRM deliveries have no user foreign key, so encrypted history survives deletion; no automatic CRM purge is implemented.

## Controlled rollout (not performed)

Review changes against the configured remote. Run migrations on an approved release. Activation is technical only. Explicit schema provisioning uses the nonce-protected manage_options POST action at Tools → Jakawi CRM Bridge; verify diagnostics and required transactional FluentCRM storage/hooks. Configure matching random secrets externally; enable both sides only after controlled validation. Monitor sanitized status counts and DEAD codes. No backfill, merge or legacy migration happens automatically. Disabling flags stops new deliveries and dispatch.

## Definitive contract

CRM_SCHEMA_VERSION = 1: 3 lists, 15 tags, 32 fields. Exact contract is documented in the new bridge README and Contract.php. JAKAWI retains event history; FluentCRM gets marketing projection only. No click IDs, metadata, fingerprints, query strings or Growth page-view/CTA deliveries.

signup_at uses user creation time. last_activity_type is canonical and last_activity_at uses domain event time; the bridge applies a monotonic maximum and preserves the associated type. lead_source/lead_form are first-write stable surface keys. Membership request adds membership-requested; activation removes requested/expired and adds active; expiry adds expired/removes active. Cancelled or pending memberships are not mislabeled expired.

Frontend source was unchanged during contract finalization; no new Docker/Vite build. Tests use Docker PHP 8.4 and isolated PostgreSQL only. No live mutation, provisioning, secrets, activation/deactivation, production migration, deploy, commit or push.

## Files changed — JAKAWI

Modified under /home/jakawi.com:

- `app/.env.example`
- `app/app/Actions/Fortify/CreateNewUser.php`
- `app/app/Http/Controllers/PartnerApplicationController.php`
- `app/app/Http/Controllers/Settings/ProfileController.php`
- `app/app/Http/Requests/Settings/ProfileUpdateRequest.php`
- `app/app/Models/PartnerApplication.php`
- `app/app/Models/User.php`
- `app/app/Providers/AppServiceProvider.php`
- `app/app/Services/PartnerApplicationService.php`
- `app/resources/js/pages/auth/register.tsx`
- `app/resources/js/pages/cities/partner-application.tsx`
- `app/resources/js/pages/settings/profile.tsx`
- `app/resources/js/types/auth.ts`
- `app/routes/console.php`

Created under /home/jakawi.com:

- `app/app/Models/CrmContactLink.php`
- `app/app/Models/CrmDelivery.php`
- `app/app/Observers/CrmOutcomeObserver.php`
- `app/app/Services/Crm/CrmBridgeClient.php`
- `app/app/Services/Crm/CrmConfiguration.php`
- `app/app/Services/Crm/CrmContactProjectionService.php`
- `app/app/Services/Crm/CrmDeliveryDispatcher.php`
- `app/config/crm.php`
- `app/database/migrations/2026_10_09_180000_create_crm_foundation.php`
- `app/tests/Feature/CrmFoundationTest.php`
- `docs/CRM_FOUNDATION_V1.md`

## Files created — plugin

Under /home/crm.jakawi.com/public_html/wp-content/plugins/jakawi-fluentcrm-bridge:

- `.gitignore`
- `CHANGELOG.md`
- `README.md`
- `includes/Adapter.php`
- `includes/BridgeError.php`
- `includes/Contract.php`
- `includes/EventStore.php`
- `includes/SchemaManager.php`
- `jakawi-fluentcrm-bridge.php`
- `tests/fixtures.php`
- `tests/run.php`

## Contract finalization validation — 2026-10-09

Docker PHP 8.4.26 only. Bridge: 112 assertions passed, all eight PHP files passed syntax checks. Laravel CRM focused: 19 tests / 114 assertions passed. Exactly one full Laravel suite: 524 passed, 1 failed, 6660 assertions, 131.12 seconds. The sole failure is the allowed CampaignTest::test_admin_can_create_campaign_and_non_admin_cannot baseline (403 versus expected redirect). No additional regression. Existing test container and isolated jakawi_test PostgreSQL were used, with HTTP fakes; no CRM runtime in Docker.

Both repositories passed git diff --check, including whitespace checks for untracked files. No frontend source changed during this task; no Docker/Vite build. Live mutations: NONE. No provisioning, contacts, real secrets, webhook, plugin activation/deactivation, sync enablement, production migration, deploy, commit or push. Ready to version: SÍ.
