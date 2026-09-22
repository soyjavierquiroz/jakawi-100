# Architecture

JAKAWI is a Laravel monolith with an Inertia, React, and TypeScript frontend.
It stores data in PostgreSQL and runs in Docker. TLS terminates at OpenLiteSpeed,
which proxies to Docker nginx on `127.0.0.1:8080`.

```text
Internet HTTPS -> OpenLiteSpeed -> nginx Docker -> Laravel/PHP-FPM -> PostgreSQL
```

## V2 domain foundation

V2 has no legacy compatibility layer. It starts from a fresh schema and does
not retain `Merchant`, `merchants`, or `merchant_id` as active domain concepts.

### Partner

A Partner is the person or organization that provides value. It carries the
brand, legal, category, and brand-level contact information. Its administrative
fields (`legal_name`, `tax_id`, contact fields, and `internal_notes`) are not
automatically exposed by a public representation.

`Partner` has many `Location` records. Its publication state is one of
`draft`, `published`, `paused`, or `archived`; only `published` is public-ready.

### Location

A Location is where something operates or occurs. It contains location-specific
contact data, address and optional coordinates, operating hours, and a
location-only redemption PIN hash. A Location may belong to a Partner, or may
stand alone (for example an external venue, meeting point, or future Experience
location).

Partner is not Location. A missing location phone or WhatsApp never falls back
to the Partner contact; brand contact and the real contact at a place remain
distinct.

Coordinates are nullable `decimal(10,7)`. Application validation bounds
latitude to `-90..90` and longitude to `-180..180`. `opening_hours` is nullable
JSON; the MVP structure uses weekday keys and zero or more time ranges:

```json
{"monday":[["09:00","13:00"],["14:00","20:00"]],"sunday":[]}
```

### Benefit

A Benefit is an offer or advantage a member may use. It belongs to exactly one
Partner and carries editorial copy, a configured category and benefit type,
optional estimated savings as `decimal(10,2)`, optional per-member redemption
limit, publication state, and optional availability dates. The limit is only a
declared policy in V2.2; enforcement arrives with Redemption.

Publication means `status = published`. Availability additionally requires a
published Partner and a date window containing the current time. Status never
changes automatically when a date window expires.

A Benefit either dynamically applies to every published Location of its Partner
(including locations created later), or to explicit published Location records.
Explicit associations are validated to belong to the same Partner. Independent
Locations do not participate in Benefits; they are reserved for future
Experience work. An all-locations Benefit remains published even if its Partner
has no published Locations, in which case it resolves to no available locations.

### Membership and Redemption

A Membership grants a User time-bounded access. It is active only when its
status is `active` and its start/end dates contain now; expiry is derived from
dates rather than persisted as a status. Activation and cancellation are
transactional and retain the full payment and activation history.

A Redemption represents one Benefit used at one specific Partner Location. It
is started only with an active Membership, an available Benefit, a published
eligible Location, and that Location's configured redemption PIN. The temporary
six-character code has a configured ten-minute TTL. Confirmation revalidates
all of those conditions and checks the current Location PIN—Partners never own
or validate a redemption PIN.

Redemption stores partner name, location name, benefit title, and estimated
savings snapshots at start. Those facts remain historical if the live records
are renamed or deleted. Per-member Benefit limits count only confirmed
redemptions. Confirmed savings are derived from those snapshots; a Membership
can calculate its remaining payback and whether its paid amount has been met.

### Experience

An Experience is content a member discovers and lives; it is not a Benefit.
Benefits are redeemed in JAKAWI, whereas the MVP reservation for an Experience
remains external metadata (WhatsApp, URL, phone, another external destination,
or no destination). There is no internal Booking, payment, checkout, capacity
availability, or reservation record in this domain.

An Experience has zero or more Partners through `experience_partner`. The
relationship carries an editorial role (`organizer`, `host`, `venue`,
`sponsor`, `participant`, `creator`, `provider`, or `other`) and sort order. A
Partner can carry multiple legitimate roles, and an Experience may have none,
such as content organized directly by JAKAWI.

An Experience has zero or more Sessions. A Session has a required start, an
optional end after its start, a `scheduled` or `cancelled` status, and optional
informational capacity. `upcoming` means scheduled with `starts_at >= now`;
published Experiences appear in the upcoming scope only if they have one.
Sessions are not required for an Experience to be published.

`Location` represents a reusable place. A Session may reference a Partner
Location, an independent Location, a Location belonging to an unrelated
Partner, or no Location at all. No ownership cross-check is imposed. A null
Location supports online, to-be-confirmed, or informal meeting-point sessions;
`venue_label` supplies simple context. Session records never duplicate address,
map, or coordinate data.

No DB enums, PostGIS, Maps API, public routes, Admin UI, Booking, checkout, or
payments are part of this foundation.

### First-party analytics

`AnalyticsEvent` is an append-only behavioural record with an allowed event
name from `jakawi.analytics.events`, nullable foreign keys to the live domain,
small nullable JSON metadata, and `occurred_at`. Foreign keys use
`nullOnDelete`, so event history remains meaningful without duplicating domain
snapshots. Analytics deliberately has no aggregate tables, queues, dashboards,
external services, or public generic ingestion endpoint.

The first-party `jakawi_visitor_id` cookie is a random UUID, HttpOnly,
SameSite=Lax, and lasts 365 days (with Secure enabled under HTTPS production
configuration). The visitor ID is available during the request that creates it.
Authenticated events may hold both `user_id` and `visitor_id`; no identity
stitching or fingerprinting is performed.

`AnalyticsTracker` is the single normal application interface for events. Its
configured taxonomy is `home_view`, `partner_view`, `location_view`,
`benefit_view`, `experience_view`, `redeem_started`, `redeem_confirmed`,
`experience_reserve_click`, `maps_click`, and `whatsapp_click`. Metadata is
whitelisted: reservation method for experience reservation clicks, and a small
configured source for maps clicks. It never receives or records request bodies,
query strings, IP addresses, user agents, email, phone/WhatsApp, names,
addresses, PINs, redemption codes, payment references, or destinations.

Redemption lifecycle events are written in the same database transactions as
the actual pending creation and pending-to-confirmed transition. Reusing a
valid pending Redemption does not produce a second `redeem_started`; an
idempotent re-confirmation does not produce a second `redeem_confirmed`.
Analytics is the source of truth for observed behaviour only. Redemption,
especially confirmed Redemption records and their snapshots, remains the source
of truth for canjes and savings/ROI.

V2.6 will wire the view and click methods to explicit UI/controllers. There is
no Analytics UI or public event endpoint in V2.5.

## Test and production isolation

All tests and test database commands go through `./bin/jakawi-test`. It uses
the isolated `app-test` and `db-test` Docker services and database
`jakawi_test`; it refuses production-shaped database settings. Production stays
offline in maintenance while V2 is rebuilt.
