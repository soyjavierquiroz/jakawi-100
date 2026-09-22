# JAKAWI Current Status

Updated: 2026-09-22

Production:
[https://jakawi.com](https://jakawi.com)

Phase:
Catalog, Membership, Redemption, Savings and MVP experience in production

Working:

- HTTPS
- PWA
- authentication
- PostgreSQL
- Merchant
- Benefit
- Membership
- Mi JAKAWI
- Redemption lifecycle
- temporary redemption codes
- merchant PIN validation
- redemption limits
- estimated savings snapshots
- admin redemption history
- redemption creation and validator runtime fixes
- MVP experience polish
- Demo catalog loaded: 12 fictional merchants and 30 fictional benefits
- 5 featured fictional benefits
- idempotent demo dataset with all records prefixed `demo-`
- safe demo catalog clear command available
- demo merchants intentionally have no redemption PIN
- fictional QA data only

Demo catalog images:

- 12 generated merchant logos
- 12 generated merchant covers
- 30 generated benefit images
- deterministic local SVG assets
- no external image dependencies
- demo assets stored under public demo paths
- visual QA PASS

Catalog onboarding:

- bulk CSV importer deployed
- dry-run by default
- transactional apply
- idempotent merchant/benefit import by slug
- existing PINs/uploads preserved
- demo slugs rejected
- production template available
- no real catalog CSV applied yet

Manual QA:

- Catalog PASS
- Membership PASS
- Redemption PASS
- Savings PASS
- MVP experience PASS
- populated catalog PASS
- demo image visual QA PASS
- Android PWA PASS
- iOS physical PWA QA pending

Runtime production commit:
`65e60ccc8d250fd717a175304316fe2a1e6a8618`

Next:

1. Prepare first real merchant CSV
2. Run dry-run
3. Correct validation errors
4. Apply first real merchant batch
5. Upload/adjust real merchant images and PINs as needed

Known technical debt:

- Feature suite can show two unrelated 429 failures due rate limiter contamination
- replaced uploads may leave orphan files
- historical slug redirects not implemented
- iOS PWA physical QA pending
- validator throttle currently sees a shared Docker proxy IP because OpenLiteSpeed does not forward `X-Forwarded-For`; correct before significant traffic

Technical note:

Repository HEAD can move forward with documentation while the production runtime remains built from `65e60ccc8d250fd717a175304316fe2a1e6a8618`.
