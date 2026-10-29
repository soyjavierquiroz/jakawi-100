# Global identity v1 — focused validation

- PostgreSQL 16 in the dedicated internal `jakawi-identity-test` network, isolated from production services.
- `bin/jakawi-identity-test php vendor/bin/phpunit tests/Feature/Identity tests/Feature/Auth`: **52 tests, 229 assertions PASS**.
- `bin/jakawi-identity-test php tests/identity/concurrency.php`: **PASS**, three independent processes retrying one handoff produce one account/result; three independent handoffs for one email produce one account plus two AUTH_REQUIRED results.
- Covers additive legacy backfill preserving user/password/admin/membership values, unique/immutable/nonreusable UUIDs, server signatures/nonces, missing or mismatched email proof, idempotency and changed-body conflicts, existing-account local authentication/explicit consent, wrong account, stale authentication, handoff expiration, pending and completed 2FA, normalized email uniqueness, privilege injection, stable lookup, exclusive credentials and idempotent revocation.
- Production verification and pilot are recorded separately by the COM release operation after deployment. No production login/password reset or synthetic activation evidence is used for the pilot.
