# Meta Provider V1 — implemented, production OFF

Current production effective state (2026-10-09): META_PROVIDER_ENABLED=false, META_BROWSER_ENABLED=false, META_CAPI_ENABLED=false. Defaults are false. No actual Meta credentials are required to install or verify this code.
`acq=meta` selects an acquisition provider; it is not legal consent. CRM email opt-in now exists separately; it does not authorize Meta tracking. This provider foundation adds no CMP.

## Enable later

Set `META_PROVIDER_ENABLED=true` plus the desired channel flags:
`META_BROWSER_ENABLED=true` and/or `META_CAPI_ENABLED=true`.
Both use `META_PIXEL_ID` (numeric pixel/dataset identifier).
CAPI additionally requires `META_CAPI_ACCESS_TOKEN` (server secret) and
`META_GRAPH_API_VERSION` (explicit supported `vN.0` version).
`META_TEST_EVENT_CODE` is optional and server-only.
Incomplete configuration fails closed. The existing global analytics toggle is also respected. Only the pixel ID reaches Inertia.
No credentials or real Meta traffic are used by the tests.

## Canonical facts and delivery

`MetaEventMapper` owns all nine Meta names; Purchase is not mapped.
Growth writes the canonical analytics event and eligible outbox entry in the same database
transaction. A savepoint catches an outbox storage failure without losing the canonical fact
or business outcome; that failure is logged for operators. No historical event scan or
backfill creates deliveries when the provider is enabled later.

`growth:dispatch-meta` is scheduled every minute with an overlap mutex. It reads only existing
META/SERVER PENDING or due RETRY rows; a PostgreSQL `FOR UPDATE SKIP LOCKED` row lock is held
through each bounded request. A process crash rolls back the claim. If Meta accepted a request
before a crash, a subsequent retry still carries the same canonical ID for deduplication.
A run handles at most 100 rows, with a 40-second processing budget (a final request can take
up to 5 additional seconds). No additional worker/container or social queue is used.

Connection failures, 429 and 5xx retry after 1m, 5m, 15m, 1h, 6h; six total attempts maximum.
Permanent errors or exhausted attempts become DEAD. A success requires a 2xx response with
one accepted event and no error. Disabling CAPI prevents all sends, including pending rows.
The outbox retains only event FK, state/timestamps, counts, HTTP status and fixed error code.
Query `growth_provider_deliveries` by provider/status to observe delivery. No payload, raw
response, identity, token or new failed_job is stored.

## Browser contract

Inertia always refreshes `metaBrowser` from the current server touch. Canonical landing views
provide descriptors with their existing UUIDs. LandingPresentation and configured public
journey CTA clients generate one UUID and POST it to JAKAWI while mirroring that same UUID to
Pixel without waiting for analytics. Invalid UUIDs are rejected; duplicate IDs create no
second canonical event or outbox entry. Older callers may omit a client UUID and remain
CAPI-only. Server outcomes are CAPI-only in V1.

The small tracker lazily loads the library, disables autoConfig before init, initializes
once per pixel and uses trackSingle/trackSingleCustom with eventID. It emits no PageView.
It deduplicates by pixel/name/UUID in memory. SPA navigation clears the current gate;
provider changes during load cancel pending emissions. The script may remain loaded after
deactivation, but no new explicit Meta events are emitted. Preview/prefetch/partial renders
expose no browser context and create no acquisition delivery.

## Privacy and remaining validation

CAPI uses SHA-256 of `jakawi:meta:v1:visitor:` plus the canonical visitor UUID, or the same
namespace with `user:` plus an internal ID only when no visitor exists. No second identity
is stored. Email, phone, names, IP, UA, fbp and fbc are omitted. Safe custom data contains
only a known product category/internal ID and whitelisted CTA categories. Source URLs use
the configured application origin plus validated landing slugs or known route categories;
Configured public-journey/legacy landings without a canonical path use the application root;
raw request URLs/queries and mutable domain records are never used.

No application/nginx CSP was found, so this implementation adds no CSP relaxation.
Before enabling production channels, confirm the supported Graph version and automatic
tracking settings in Meta, then validate delivery/deduplication in Meta Test Events. Fake
HTTP and a fake browser adapter verify the contract locally; they cannot prove remote Meta
acceptance, attribution/match quality or deduplication. This V1 intentionally omits fbp/fbc,
advanced matching, Purchase, an outcome browser mirror and a delivery dashboard.

Canonical event mapping and membership UTM boundaries: [Growth](../../docs/growth-measurement-v1.md). Current production: [CURRENT](../../docs/CURRENT.md). Enabling channels requires a separately authorized operational change.
