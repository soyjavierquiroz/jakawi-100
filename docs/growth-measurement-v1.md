# Growth Measurement Foundation V1

Storage: `analytics_events`, extended in place. `GrowthMeasurementService` owns canonical context and idempotency. `AnalyticsTracker` remains the legacy primitive and delegates existing landing events to growth. No provider integrations, economic conversions, or reporting UI are added.

| Event | Stage | Authoritative trigger |
| --- | --- | --- |
| landing_view | ACQUISITION | Public LandingPresentation server render |
| landing_cta_click | INTENT | Shared delegated landing listener / existing public CTA endpoint |
| signup_completed | ACCOUNT | Fortify completes User creation and attribution association |
| membership_purchase_requested | MEMBERSHIP_INTENT | MembershipPurchaseRequest creation |
| membership_activated | MEMBERSHIP_CONVERSION | Membership creation or transition to active |
| benefit_redeemed | PRODUCT_CONVERSION | Redemption transitions to confirmed |
| experience_reserved | PRODUCT_CONVERSION | ExperienceReservation creation |
| challenge_joined | PRODUCT_CONVERSION | ChallengeParticipation creation |
| unlock_committed | PRODUCT_CONVERSION | UnlockParticipation creation/transition to COMMITTED |

All new analytics rows receive a persistent UUID `event_id`; existing rows receive UUIDs in the incremental migration. Server milestones use a unique `(event_name, source_type, source_id)` identity, preserving their event ID on retries. Historical events are not inferred into domain milestones. The stage is derived from the taxonomy, not stored.

The existing HTTP-only first-party UUID visitor cookie is retained across authentication. Events also carry `user_id` when available. Domain actions performed by administrators/partners use the beneficiary's touch identity, never the operator's visitor. No historical event merge is needed.

`AttributionTouch` remains the sole attribution source. Growth resolves applicable touches within the existing configured attribution window, ordered by `occurred_at DESC, id DESC`. LandingPresentation ID, slug, acquisition subject and default scope are stored as controlled touch metadata when the landing renders. Events snapshot the landing context, campaign key and five UTM fields. Membership activation uses an outstanding purchase request's frozen touch when applicable. Native traffic can have no landing or touch.

The outcome subject identifies the actual product/domain; the landing reference separately identifies acquisition. `campaign_key` is acquisition context only. Growth never resolves economic Campaign, records Conversion, or changes ConversionRecorder.

Domain model observers capture successful creation/status changes, then write after commit. Callback context and timestamps are captured before scheduling. Rollbacks discard callbacks. Writes use a separate transaction/savepoint and failures are best effort, with no PII-bearing exception messages in growth logs. There is no durable delivery queue/outbox in V1.

Views are server-side only; React hydration adds none. Admin previews, prefetch and Inertia partial reloads do not produce acquisition views/touches. The shared per-landing click listener records primary/secondary links and explicit participation buttons exactly once per click. Repeat clicks are valid. Preview/disabled controls are excluded. Tracking uses keepalive fetch, never awaits or delays navigation, and sends no form contents.

CTA metadata uses fixed kind/location categories. Query strings and fragments are stripped before transport. Stored internal destinations are route templates, with no sensitive route parameter values; external destinations are categorical. Growth context and metadata are allowlisted. No names, contact details, social post URLs, tokens, provider payloads, IP or User-Agent identities are stored. External WhatsApp intent cannot create an internal reservation milestone.

For future CAPI mapping, event ID, canonical name, occurrence time, visitor/user, attribution and subject context are available. No provider event names or SDKs are embedded in domain flows. Browser/provider export and delivery retries remain future work.

Indexes reuse existing event/time, user/time and visitor/time indexes and add landing/time, campaign/time, attribution reference, unique event ID and unique outcome identity. Funnel queries can filter by landing, campaign key or scalar UTM fields without decoding metadata.

## Changed files

- `app/app/Actions/Fortify/CreateNewUser.php`
- `app/app/Http/Controllers/GrowthLandingCtaController.php`
- `app/app/Http/Controllers/LandingPresentationController.php`
- `app/app/Http/Controllers/PublicJourneyLandingController.php`
- `app/app/Http/Controllers/UnlockController.php`
- `app/app/Models/AnalyticsEvent.php`
- `app/app/Observers/GrowthOutcomeObserver.php`
- `app/app/Providers/AppServiceProvider.php`
- `app/app/Services/AnalyticsTracker.php`
- `app/app/Services/AttributionService.php`
- `app/app/Services/GrowthMeasurementService.php`
- `app/config/jakawi.php`
- `app/database/migrations/2026_10_09_000001_extend_analytics_events_for_growth.php`
- `app/resources/js/components/marketing/primitives.tsx`
- `app/resources/js/lib/growth-landing-analytics.ts`
- `app/resources/js/pages/landing-presentations/benefit.tsx`
- `app/resources/js/pages/landing-presentations/challenge.tsx`
- `app/resources/js/pages/landing-presentations/experience.tsx`
- `app/resources/js/pages/landing-presentations/unlock.tsx`
- `app/resources/js/pages/social-challenges/show.tsx`
- `app/routes/web.php`
- `app/tests/Feature/AnalyticsTest.php`
- `app/tests/Feature/ExperienceReservationTest.php`
- `app/tests/Feature/GrowthMeasurementTest.php`
- `app/tests/Feature/PartnerAccessTest.php`
- `app/tests/Feature/PublicCampaignJourneysTest.php`
- `docs/growth-measurement-v1.md`
