# JAKAWI Current Status

Updated: 2026-09-25

## Production

- Offline and in maintenance, awaiting the approved V2 RC cutover.
- `app` and `web` are stopped; production DB may remain running.
- V2 RC has made no production migration, write, deployment, or environment change.

## Completed

- V2.1 — Partner and Location foundation, including independent Locations and
  removal of Merchant runtime concepts.
- V2.2 — Benefit foundation with publication/availability and all/selected
  Partner Location scope.
- V2.3 — Membership and location-aware Redemption foundation, snapshots,
  idempotency, limits, and confirmed-only ROI.
- V2.4 — Experiences, multi-Partner roles, Sessions, optional Locations, and
  external-only reservation metadata.
- V2.5 — First-party, privacy-constrained analytics foundation.
- V2 — Member membership/benefits/experiences/profile personalization;
  Partner Content Studio, reservation confirmation/check-in and KPI; Admin
  review; isolated preview/test operations and V2 Catalog Importer.

## Next

Approved production cutover using the exact RC commit. It is not executed by
this repository task.

# V2.7B Demo Catalog V2

Deterministic fictional QA catalog with local SVG demo media, seeded and cleared through dedicated commands. It does not use or relax the importer V2 `demo-*` protection. Not deployed.
# V2.7A Catalog Importer V2

Catalog Importer V2 provides validated, transactional, idempotent supply CSV imports with dry-run by default. It has not been deployed to production.
