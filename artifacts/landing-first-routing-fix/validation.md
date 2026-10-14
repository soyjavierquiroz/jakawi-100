# Landing-first routing fix

The Partner surface bypassed the existing presentation contract twice: `PublicController` forced Benefit/Experience native URLs and omitted Challenge destination URLs; `partners/show.tsx` independently reconstructed all product links from slugs. Cards now consume `ProductLandingResolver::navigationUrl()` for all three supported subjects. No Dossis redirect, stored Partner URL, new resolver, schema, publication or domain-action change.

Direct generic URLs already use `ResolveProductLanding`: an applicable published default redirects with query context; otherwise the original detail renders. This mechanism is retained. Guest GUESTS/ALL and authenticated ALL semantics remain unchanged. Unlock is unchanged.

CTA behavior is retained: guest Micro Opt-in/login → saved Landing continuation; authenticated Benefit POST `redemptions.start` or activation-stage choices. Neither action runs presentation resolution. Partner ownership is retained for new signup and is not assigned on existing login.

Validation uses Docker PHP 8.4 through `./bin/jakawi-test`; no host PHP. Focused Benefit action/continuity, Landing acquisition, Partner acquisition/profile, Experience marketing and Challenge presentation tests. Added assertions cover both viewers' direct Partner targets, query-preserving generic redirects for all three subjects, real generic fallback, draft/archive/NONE, audience mismatch, guest priority and actual signup → activation → Benefit action in the isolated database. Production's one-default constraint prevents simultaneous GUESTS+ALL rows; priority is exercised only after temporarily dropping that index inside the rolled-back test transaction. Production schema remains untouched.

Two existing stale expectations were corrected: Experience membership CTA already says ACTIVAR MEMBRESÍA; Challenge direct URLs already redirect for guest GUESTS/ALL and authenticated ALL. The initial selected classes also exposed an unrelated existing Unlock video expectation; that test is excluded from this routing release's final scope. No Unlock implementation changed.

TypeScript, one final frontend validation build, manifest checks, Markdown links and `git diff --check` pass. Browser/release evidence is added after release without redeployment.
