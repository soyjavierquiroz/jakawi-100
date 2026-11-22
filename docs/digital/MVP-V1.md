# JAKAWI Digital MVP V1

DigitalProduct is independent of Benefits, Experiences, Challenges and Unlocks.
The migration creates one internal Partner `jakawi-digital` without a Location.
Admin currently manages only that Partner's digital products; the FK supports
other Partners without granting staff economic permissions.

## Milestones

1. Catalog: `/productos-digitales`, `/productos-digitales/{slug}`, a global
   section/search in `/explorar`, and `/admin/productos-digitales`.
2. Sales: `/admin/digital/cobro`, `/admin/digital/pedidos`, authenticated
   checkout, and `/mi-jakawi/productos-digitales`.

An order has one product, one buyer and an immutable snapshot of price,
currency, product, membership eligibility, terms, recipient account and PDF
version. Price selection uses `User::activeMembership()` (including pioneer
policy); access does not depend on later membership renewal.

## Collection and review

Collection is disabled until Admin supplies an exclusive digital bank account,
holder, bank, currency and an own variable-amount QR. Known membership statement
accounts are rejected. The administrator must verify exclusivity with the bank;
a checkbox cannot prove it. No live bank information is provided by this release.
The digital circuit neither calls the membership reconciler nor activates a
membership, rewards, affiliate attribution, CRM or CLUB events.

A buyer's receipt only creates an under-review payment attempt. Confirmation
requires an administrator's independent bank verification, reference, date,
exact amount/currency/account and private PDF bank evidence. This is a human
bank review, not an automated guarantee that an uploaded statement is genuine.
One bank reference per account may be consumed once, including after refund.
Unique constraints, user/order row locks and transactional decisions prevent
concurrent duplicates. Rejected attempts remain in history. Pending price
quotes expire after 24 hours; an already submitted review is retained.
Account/QR changes invalidate receipt submission against the old version;
support must review already-paid cases using the historical recipient snapshot.

## Access and support

Products, receipts, QR and bank statements use `digital-private`, under the
persistent COM storage mount, outside the public web root. Marketing images use
the existing media pipeline. PDFs are immutable versions and are retained when
products are edited. No provider links, public paid-file URLs or SSO are added.

Payment confirmation and delivery readiness are separate. A confirmed order
gets one access grant. Missing/corrupt files mark delivery failed while retaining
the confirmed payment; Admin can restore the exact version and retry. Downloads
verify buyer, confirmed order, active grant, delivery state and file hash. The
`digital_download_requested` event records an authorized response, not proof
that the browser saved every byte. Full refunds require actual bank repayment
and a refund reference before recording, revoke future downloads, and retain
the financial history. Partial refunds are outside V1. Previously downloaded
files cannot be recalled. Incidents are recorded in AuditLog.

PDF integrity errors should be repaired by restoring the purchased version;
do not silently replace an order's accepted content. Retention/cleanup of paid
versions and banking evidence requires a separately approved policy; no automatic
delete task is introduced in V1.

## PRELAUNCH and activation

Keep PRELAUNCH_MODE=true. HTTP, services and model writes guard commercial
operations and delivery/retry. There is no activation job in V1; future jobs
must call the same guarded service. Editorial Admin and public published catalog
remain available. Do not load or publish commercial products without approved
materials/prices/licences. QA fixtures use an isolated test DB and fictitious
bank/PDFs only; never run `tests/marketing/digital-qa.php` in production.

First sale requires approved product/licence/PDF/marketing, public and optional
member price, terms/refund/support policy, exclusive account and validated QR,
and separate commercial authorization to remove PRELAUNCH.

## Measurement and release

AnalyticsEvent records digital product views, checkout start, pending order,
confirmed purchase, access grant and download requests. Financial totals come
from digital payments and refunds, separated by currency; totals after refunds
exclude neither bank fees nor licence costs and are not net profit.

Use only `/opt/sites/jakawi.com/release-control.py`, based on the reviewed
`docker/new-server/digital-release-control.py`. It requires QA/browser PASS,
backs up immutable images/database/config, applies exactly the two additive
2026_11_22 migrations, verifies old schema/data and unchanged protected services,
and requires PRELAUNCH. New tables are empty in LIVE; only the internal Partner
is created. Rollback restores previous COM images/config, retaining additive
schema and Partner. Never restore the DB dump over subsequent user writes.
Fast-forward remote main only after LIVE PASS.
