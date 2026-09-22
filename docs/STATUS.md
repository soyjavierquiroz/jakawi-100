# JAKAWI Current Status

Updated: 2026-09-22

## V1 Production

- V1 is frozen until V2 is ready.
- The production database was reset during an unsafe test attempt.
- No real customer data existed; the prior records were QA/demo data.
- Recovery is intentionally abandoned. Existing dumps are retained only as
  historical archives.
- Production remains offline in maintenance. Its `web` and `app` services are
  stopped; the production `db` service may remain running.
- No production migration, restore, deploy, environment, or OpenLiteSpeed
  change is part of this phase.

## V2 Preparation

- V2 will launch from a fresh database.
- No Domain V2 implementation is in progress in this phase.
- Physically isolated test infrastructure (`app-test` to `db-test` to
  `jakawi_test`) is being established as the prerequisite for future V2 work.
