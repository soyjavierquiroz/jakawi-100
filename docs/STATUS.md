# JAKAWI Current Status

Updated: 2026-09-22

## Production

- Production is offline and in maintenance during the V2 reconstruction.
- `web` and `app` remain stopped. Production `db` may remain running.
- No production migration, deployment, environment change, or write is part of
  this work.

## V2.1: Partner + Location foundation

- Isolated test infrastructure is merged into `main`.
- The V2 schema introduces `partners` and `locations` only for this domain.
- Locations can be independent of Partners and own their contacts, coordinates,
  hours, publication state, and redemption PIN hash.
- The legacy Merchant domain and its schema are removed without compatibility
  aliases or `merchant_id` fallbacks.
- Benefits, Experiences, Redemption V2, Analytics, Admin UI, Public UI,
  importer, demo data, and deployment are intentionally out of scope.

## V2.2: Benefit foundation

- Benefits belong to Partners and can apply dynamically to all published Partner
  Locations or to validated selected Partner Locations.
- Publication and availability are central model rules: availability requires a
  published Benefit, a published Partner, and a valid optional date window.
- Production remains offline in maintenance; this work uses only the isolated
  test database.
