# Architecture

JAKAWI is a Laravel monolith with an Inertia, React, and TypeScript frontend,
PostgreSQL, and Docker. TLS terminates at OpenLiteSpeed, which proxies to
Docker nginx and Laravel/PHP-FPM.

```text
Internet HTTPS -> OpenLiteSpeed -> nginx Docker -> Laravel/PHP-FPM -> PostgreSQL
```

## V2.6 domain

V2 starts from a fresh schema. There is no `Merchant`, `merchants`,
`merchant_id`, Booking, or compatibility adapter in the runtime domain.

- A `Partner` is the organization or person providing value. It has many
  `Location` records, many `Benefit` records, and a many-to-many relationship
  with `Experience` records that includes an editorial role and sort order.
- A `Location` is a place, not a Partner. It may belong to a Partner or be
  independent. It has location-specific contacts, publication state, optional
  maps/contact links, and the sole redemption PIN field:
  `locations.redemption_pin_hash`.
- A `Benefit` belongs to one Partner. Its location scope is either all
  published Locations of that Partner or selected, validated published Partner
  Locations through `benefit_location`. Independent Locations cannot be used
  for a Benefit.
- A `Membership` belongs to a User. Active access requires active status and a
  current date range; cancellation preserves history.
- A `Redemption` belongs to a User and Membership, and starts only for an
  active Membership, an available Benefit, an eligible published Location, and
  a configured Location PIN. It stores Partner, Location, Benefit, and savings
  snapshots. Pending codes are reusable until their ten-minute TTL; confirmation
  is idempotent and limits count confirmed Redemptions only. ROI uses confirmed
  savings only.
- An `Experience` has zero or more Partners and zero or more
  `ExperienceSession` records. A Session has an optional Location, which may be
  Partner-owned, unrelated, independent, or null. Reservations are external
  redirects only; there is no Booking, payment, capacity decrement, or internal
  reservation record.

Public controllers expose only published/available records according to the
domain rules. Partner legal/contact/internal fields and Location manager/PIN
fields are not serialized into public Inertia props. `/mi-jakawi`, redemption
start, and an individual redemption require authentication; redemption details
also require ownership. `/admin` is protected server-side by `is_admin`.

## Analytics

`AnalyticsEvent` is append-only and can carry nullable User, visitor, Partner,
Location, Benefit, Experience, Session, and Redemption context. The controlled
first-party taxonomy is: `home_view`, `partner_view`, `location_view`,
`benefit_view`, `experience_view`, `redeem_started`, `redeem_confirmed`,
`experience_reserve_click`, `maps_click`, and `whatsapp_click`.

`jakawi_visitor_id` is a random UUID cookie, HttpOnly and SameSite=Lax, with a
365-day lifetime and Secure in HTTPS production. An authenticated event may
retain both User and visitor context; JAKAWI performs no fingerprinting or
identity stitching. The tracker whitelists metadata and never stores request
bodies, query strings, email, phone/WhatsApp, PIN, code, IP, user-agent, URL,
destination, or payment data. There is no generic public tracking endpoint or
analytics dashboard.

## Security and uploads

Location PIN input is exactly six digits, is stored with `Hash::make`, verified
with `Hash::check`, never prefilled, and a blank Admin update preserves its
existing hash. Partners do not own a PIN.

Admin uploads accept JPEG, PNG, and WebP only, at most 5 MB. Laravel image/MIME
validation and controlled public-disk paths use generated filenames rather than
the original filename; replacing an upload removes the prior managed file.

## Isolation

All Laravel test and test-database commands use `./bin/jakawi-test`. It uses
the physically separate `app-test` and `db-test` services and `jakawi_test`.
Production remains offline in maintenance while V2 is prepared; its app and web
services are not used for testing.
