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
- visual QA pending

Manual QA:

- Catalog PASS
- Membership PASS
- Redemption PASS
- Savings PASS
- MVP experience PASS
- populated catalog PASS
- demo image visual QA pending
- Android PWA PASS
- iOS physical PWA QA pending

Runtime production commit:
`3721843253709652cfca9221a84dad15057468f4`

Next:

1. Visual QA demo images on mobile
2. Fix only obvious visual issues
3. Prepare real merchant onboarding/content

Known technical debt:

- Feature suite can show two unrelated 429 failures due rate limiter contamination
- replaced uploads may leave orphan files
- historical slug redirects not implemented
- iOS PWA physical QA pending
- validator throttle currently sees a shared Docker proxy IP because OpenLiteSpeed does not forward `X-Forwarded-For`; correct before significant traffic

Technical note:

Repository HEAD can move forward with documentation while the production runtime remains built from `3721843253709652cfca9221a84dad15057468f4`.
