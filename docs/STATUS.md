# JAKAWI Current Status

Updated: 2026-09-21

Production:
[https://jakawi.com](https://jakawi.com)

Phase:
Merchant/Benefit catalog deployed

Working:

- HTTPS
- PWA
- authentication
- PostgreSQL
- Merchant model/admin
- Benefit model/admin
- public benefit catalog
- benefit detail
- persistent merchant/benefit images
- admin authorization

Manual QA:

- Android PWA verified
- iOS PWA physical-device verification pending

Runtime production commit:
`af97e4796ac41c59d114fed5a344584e927b2252`

Next:

1. Create/promote first JAKAWI admin
2. Load first real merchant/benefit
3. Membership
4. Redemption
5. Savings

Known technical debt:

- replaced uploads may leave orphan files
- no historical slug redirects
- iOS PWA physical QA pending

Technical note:

Repository HEAD can move forward with documentation while the production runtime remains built from `af97e4796ac41c59d114fed5a344584e927b2252`.
