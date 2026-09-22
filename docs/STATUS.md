# JAKAWI Current Status

Updated: 2026-09-21

Production:
[https://jakawi.com](https://jakawi.com)

Phase:
Redemption v1 in development

Working:

- HTTPS
- PWA
- authentication
- PostgreSQL
- Merchant admin
- Benefit admin
- public benefit catalog
- Membership lifecycle
- admin membership activation
- membership cancellation
- Mi JAKAWI
- benefit membership state

In development:

- Redemption lifecycle
- member redemption code page
- public merchant validator
- Mi JAKAWI redemption savings
- admin redemption traceability

Manual QA:

- Android PWA verified
- iOS PWA physical QA pending
- Membership manual production QA pending

Runtime production commit:
`c37667795aa103a8a100e5ed00e2106538486cd1`

Next:

1. Manually activate first real membership
2. Validate Mi JAKAWI
3. Complete Redemption v1 QA
4. Decide production migration/deploy window

Known technical debt:

- Feature suite can show two unrelated 429 failures due rate limiter contamination
- replaced uploads may leave orphan files
- historical slug redirects not implemented
- iOS PWA physical QA pending

Technical note:

Repository HEAD can move forward with documentation while the production runtime remains built from `c37667795aa103a8a100e5ed00e2106538486cd1`.
