# JAKAWI Current Status

Updated: 2026-09-22

Production:
[https://jakawi.com](https://jakawi.com)

Phase:
Catalog, Membership, Redemption and Savings v1 in production

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

Manual QA:

- Merchant/Benefit PASS
- Membership PASS
- Redemption PASS
- Redemption idempotency PASS
- redemption limit PASS
- Savings PASS
- Android PWA PASS
- iOS physical PWA QA pending

Runtime production commit:
`9c573655a347d5917db251279988cca143a884ea`

Next:

1. Perform iOS physical PWA QA
2. Continue with small MVP experience polish (not deployed yet)

Known technical debt:

- Feature suite can show two unrelated 429 failures due rate limiter contamination
- replaced uploads may leave orphan files
- historical slug redirects not implemented
- iOS PWA physical QA pending
- validator throttle currently sees a shared Docker proxy IP because OpenLiteSpeed does not forward `X-Forwarded-For`; correct before significant traffic

Technical note:

Repository HEAD can move forward with documentation while the production runtime remains built from `9c573655a347d5917db251279988cca143a884ea`.
