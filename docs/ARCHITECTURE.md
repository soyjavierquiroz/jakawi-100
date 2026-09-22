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

No DB enums, PostGIS, Maps API, public routes, Admin UI, Experiences, or
Redemption workflow are part of this foundation.

## Test and production isolation

All tests and test database commands go through `./bin/jakawi-test`. It uses
the isolated `app-test` and `db-test` Docker services and database
`jakawi_test`; it refuses production-shaped database settings. Production stays
offline in maintenance while V2 is rebuilt.
