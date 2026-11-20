# Partner operations V3A

Source baseline: `8bca7f503453d9443eb3eadb4c0d0d3538e9954f` (V2). This delivery does not enable commercial activity. PRELAUNCH continues to guard HTTP, service methods, operational model writes and automatic commercial processes. No migration or historical backfill is required.

## Reservation capacity

The existing request/response flow is retained: pending requests need a Partner response and do not allocate confirmed seats. Occupancy is the sum of `party_size` of confirmed reservations across the session; rejected and cancelled reservations consume zero. A pending request is not a guaranteed place or a new waiting-list product.

A request must fit against currently confirmed occupancy. Concurrent pending requests can coexist; confirmation rechecks the entire group and fails without changing its pending status if there is insufficient space. No automatic confirmation, partial group, overbooking or waiting-list promotion is introduced. Null capacity retains its existing meaning (no configured limit). Existing over-capacity history is not repaired or rewritten; further allocation must fit before confirmation.

Request, response, cancellation and check-in acquire the session row lock before the reservation row lock. Confirmation reads current persisted state inside the transaction. Same-outcome retries preserve response/cancellation/check-in timestamps. Cancellation retains its existing owner and future-session restriction and frees confirmed seats.

Session model edits serialize with these operations, reject capacity below existing confirmed attendance and reject changing the reservation Partner when any reservation exists. Model deletion and the existing Admin deletion route reject sessions with any reservation, including rejected/cancelled history. No records are deleted or reassigned by the release.

The technical ExperiencePublication blocker saying capacity is not enforced is removed because capacity is now enforced. All other publication, `operations_confirmed`, membership, eligibility and channel checks remain. This neither changes stored publication state nor authorizes a LIVE operation during PRELAUNCH.

## Check-in interval

New attendance is allowed from `starts_at` through `ends_at`, inclusive. If no explicit end exists, use `starts_at + experience.duration_minutes`, only when duration is positive. No additional advance or post-session grace is invented. Missing/invalid end and missing duration block attendance with an explanatory message. Existing attendance retries return the original record even outside the interval.

The reservation must be confirmed, its session scheduled, and its reservation Partner unchanged. Partner association authorization and signed QR routes remain. Manual lookup is Partner-scoped and read-only. Recorded attendance retains `checked_in_by_user_id`.

Session timestamp columns have no timezone and the application timezone is UTC. Existing unqualified timestamps continue to mean UTC; new explicitly offset-qualified inputs are normalized to UTC. No historical values are reinterpreted or rewritten. Display uses a valid `Location.timezone`, falling back to application UTC when absent/invalid; that fallback is shown explicitly. The server compares absolute instants independently of browser timezone.

## Redemptions

Manual and QR screens display server outcomes, validation errors and PRELAUNCH restrictions. The confirmation service returns an explicit in-memory outcome for confirmed versus already-confirmed replay, preserving its existing `confirm(): Redemption` interface. This avoids inferring outcomes from an unlocked pre-read. Partner isolation, signed QR, PIN on new confirmation, membership/grants, per-member limits and existing locking remain. Already-confirmed retries retain the existing idempotent read behavior and do not issue another redemption, analytics event or fulfillment. No additional Unlock/Challenge operation is introduced.

Errors use an allowlist of safe Spanish messages. Unknown exceptions use a generic message. Invalid/foreign manual codes share the same response and HTTP 403 contract without disclosing another Partner's data. QR responses no longer include the member's full name. PIN fields are masked and cleared after submission.

## Deferred dependencies and limits

Redemptions currently do not persist the confirming operator in a dedicated column. Full operator attribution needs a separately reviewed schema migration, service actor propagation and historical policy; this delivery does not fabricate historical attribution or run that migration. Reservation response and attendance already have operator columns.

Existing database foreign keys cascade reservation deletion. This delivery protects the existing application session deletion path and Eloquent model deletion; raw SQL/bulk query deletion and parent-entity database cascades bypass Eloquent safeguards. Strong database-level history protection requires a separately reviewed FK migration. No parent Experience deletion UI/route exists in the current application. Do not use SQL/bulk deletes for operational sessions.

Operational tests use only the isolated `jakawi_test` PostgreSQL database through `bin/jakawi-test`, including independent PHP processes contending on the same PostgreSQL row lock. LIVE verification is read-only/anonymous. Release uses exclusively `/opt/sites/jakawi.com/release-control.py`; rollback is checked via unit isolation and canonical dry-run, never by a production rollback test. No GOLD suite.
