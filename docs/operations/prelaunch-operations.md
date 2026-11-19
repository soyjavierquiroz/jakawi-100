# PRELAUNCH operational policy

Source baseline: `ab29f7ce173e30037476cf6d63f0a79dc3377dcf`, verified against the running COM application. `main` (`1b135e2`) and the existing feature checkout (`c863327`) are ancestors of that baseline. Work takes place in a separate worktree; the existing dirty checkout is preserved.

`jakawi.prelaunch_mode` is the single switch. No role, admin, queue or service bypass is provided. It does not change publication, prices, roles, commercial configuration or historical records. With the switch off, the original per-product authorization and validation still execute.

- HTTP policy blocks commercial controller actions before payload side effects. Internal COM purchase endpoints use the same policy. JSON gets HTTP 423 and `PRELAUNCH_OPERATION_BLOCKED`; Inertia mutations get a 303 validation redirect; blocked GET requests receive an Inertia page.
- Domain services reject reservations, attendance, redemptions, Unlock participation/fulfillment, Challenge evaluation/rewards, purchases, activation, ledger changes, payouts, bank imports and commercial dispatch. Eloquent operational records also reject saving/deleting as defense in depth. Application code using bulk SQL must continue to go through guarded services.
- Scheduled Unlock/Challenge processing, receipt extraction, reconciliation and commercial transaction delivery are no-ops. Existing business records and transactional outbox entries are retained. Known account/identity attribution notifications may still be delivered; unknown and commercial transaction events are blocked. Authentication/identity transport, CRM and Meta dispatchers are not reconfigured.
- Editorial Partner/Location/product drafts, proposal submission/review, previews, history, dashboard and KPIs remain available. Challenge operational opening and Unlock scheduling/activation are blocked; editorial approvals remain available.
- Reading an expired redemption renders its expired state without updating the historical row while PRELAUNCH is on.
- External reservation redirects through COM are blocked. Contact links and static bank QR/image reads remain reads; COM cannot prevent a person independently contacting or paying a third party outside COM. No external system is modified.

This change does not implement the next Partner delivery: capacity enforcement, check-in time-window fixes, redemption result UI, Unlock operational UI and proposal versioning remain separate work. Existing ExperiencePublication capacity/operational blockers are preserved; PRELAUNCH off is not certification that legacy reservations enforce capacity.

Validation uses only `bin/jakawi-test` with its isolated PostgreSQL database; no business transactions or migrations run in LIVE. New Inertia assets are compiled from this worktree with the unchanged lockfile. The release layers reviewed source/assets over immutable verified LIVE images, preserving runtime dependencies; it does not build from `/opt/sites/jakawi.com/app`.

The focused test run may set `JAKAWI_TEST_DOCKERFILE=docker/prelaunch-test.Dockerfile` when invoking `bin/jakawi-test`. This keeps the normal isolation/locking checks, pins the existing test-dependency image, verifies its lock hash, removes inherited application source and copies this checkout. The default test Dockerfile remains available.

## Canonical release integration

There is no separate prelaunch release controller or temporary deployment wrapper. `docker/new-server/payments-admin-release-control.py` is the existing versioned source of the canonical `/opt/sites/jakawi.com/release-control.py`, parameterized for this reviewed release. After validation, preserve the prior canonical file privately and install the reviewed source at the same canonical path. Execute only that path with explicit source/evidence/tag/baseline/remote-reference environment variables.

The existing prepare/deploy/verify sequence remains the release mechanism. Preparation checks the clean source, pinned images, source ancestry, PRELAUNCH on and DEMO off, captures business/schema fingerprints and backs up the old images/configuration/database. Deployment requires validation and isolated-browser PASS and only recreates the four COM services. Verification precedes the added `promote` action, which fast-forwards main without force. The added `rollback` action restores only previous COM images/configuration, never a database dump or schema. Its function is tested with local Git repositories and intercepted Docker commands; no production rollback is used as a test.
