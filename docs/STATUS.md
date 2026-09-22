# JAKAWI Current Status

Updated: 2026-09-22

Production:
[https://jakawi.com](https://jakawi.com)

Phase:
Redemption v1 deployed

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

Manual QA:

- Android PWA PASS
- Merchant/Benefit PASS
- Membership PASS
- Redemption production QA PENDING
- iOS PWA physical QA pending

Runtime production commit:
`ee0369b0cdc5cba556eb3c2ff085e27b8c99f53c`

Next:

1. Configure PIN on real merchant
2. Perform first real redemption
3. Validate savings in Mi JAKAWI

Known technical debt:

- Feature suite can show two unrelated 429 failures due rate limiter contamination
- replaced uploads may leave orphan files
- historical slug redirects not implemented
- iOS PWA physical QA pending
- validator throttle currently sees a shared Docker proxy IP because OpenLiteSpeed does not forward `X-Forwarded-For`; correct before significant traffic

Technical note:

Repository HEAD can move forward with documentation while the production runtime remains built from `ee0369b0cdc5cba556eb3c2ff085e27b8c99f53c`.
