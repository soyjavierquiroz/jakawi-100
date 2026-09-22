# JAKAWI Current Status

Updated: 2026-09-22

Production:
[https://jakawi.com](https://jakawi.com)

Phase:
Redemption v1 validator hotfix deployed

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
- redemption creation runtime 500 fixed
- redemption validator 500 fixed
- authenticated navigation includes Beneficios
- admin users have Admin navigation link

Manual QA:

- Android PWA PASS
- Merchant/Benefit PASS
- Membership PASS
- Redemption production QA PENDING
- iOS PWA physical QA pending

Runtime production commit:
`9c573655a347d5917db251279988cca143a884ea`

Next:

1. Perform manual production redemption QA
2. Validate savings in Mi JAKAWI

Known technical debt:

- Feature suite can show two unrelated 429 failures due rate limiter contamination
- replaced uploads may leave orphan files
- historical slug redirects not implemented
- iOS PWA physical QA pending
- validator throttle currently sees a shared Docker proxy IP because OpenLiteSpeed does not forward `X-Forwarded-For`; correct before significant traffic

Technical note:

Repository HEAD can move forward with documentation while the production runtime remains built from `9c573655a347d5917db251279988cca143a884ea`.
