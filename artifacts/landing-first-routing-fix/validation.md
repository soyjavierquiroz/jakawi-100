# Landing-first routing fix

The Partner surface bypassed the existing presentation contract twice: `PublicController` forced Benefit/Experience native URLs and omitted Challenge destination URLs; `partners/show.tsx` independently reconstructed all product links from slugs. Cards now consume `ProductLandingResolver::navigationUrl()` for all three supported subjects. No Dossis redirect, stored Partner URL, new resolver, schema, publication or domain-action change.

Direct generic URLs already use `ResolveProductLanding`: an applicable published default redirects with query context; otherwise the original detail renders. This mechanism is retained. Guest GUESTS/ALL and authenticated ALL semantics remain unchanged. Unlock is unchanged.

CTA behavior is retained: guest Micro Opt-in/login → saved Landing continuation; authenticated Benefit POST `redemptions.start` or activation-stage choices. Neither action runs presentation resolution. Partner ownership is retained for new signup and is not assigned on existing login.

Validation uses Docker PHP 8.4 through `./bin/jakawi-test`; no host PHP. Focused Benefit action/continuity, Landing acquisition, Partner acquisition/profile, Experience marketing and Challenge presentation tests. Added assertions cover both viewers' direct Partner targets, query-preserving generic redirects for all three subjects, real generic fallback, draft/archive/NONE, audience mismatch, guest priority and actual signup → activation → Benefit action in the isolated database. Production's one-default constraint prevents simultaneous GUESTS+ALL rows; priority is exercised only after temporarily dropping that index inside the rolled-back test transaction. Production schema remains untouched.

Two existing stale expectations were corrected: Experience membership CTA already says ACTIVAR MEMBRESÍA; Challenge direct URLs already redirect for guest GUESTS/ALL and authenticated ALL. The initial selected classes also exposed an unrelated existing Unlock video expectation; that test is excluded from this routing release's final scope. No Unlock implementation changed.

TypeScript, one final frontend validation build, manifest checks, Markdown links and `git diff --check` pass. Browser/release evidence is added after release without redeployment.

## Released runtime and live QA

Implementation `f9b6c0e450528b11dc4c30548c639ba8e1cda622` is LIVE, replacing `b008a236fa68752e0f911109f3adf099799ebd6d`. One production build; app/web/social-worker recreated; exact previous images protected for rollback. No pending/run migrations, product edits, publication changes, user creation, ownership assignment or redemption. Other runtime container IDs remain unchanged; PRELAUNCH_MODE/environment unchanged. [Release evidence](production-release.json), [readiness](readiness.json).

**Production differs from the supplied premise:** Landing 1 (`dossis43-parada-estrategica`) is ARCHIVED/NONE. Landing 2 (`dossis-43-cafe-y-antojo`) is PUBLISHED/ALL. The resolver correctly selects Landing 2 for both audiences. No data was changed to force the archived slug. The old Landing URL remains 404; the Benefit generic URL redirects to the current published Landing and preserves `utm_source`/`ref`. [Route evidence](live-routes.json).

[Candidate browser](candidate-browser.json) and [live browser](live-browser.json) passed actual Partner card clicks → applicable Landing as the first product screen, without a generic sheet or loop. Guest live QA used actual public HTTPS GETs, with Purpose: prefetch to suppress acquisition touches, and opened Micro Opt-in without submission. Authenticated QA used actual deployed kernel responses in READ ONLY transactions with an existing identity, real deployed assets, and actual React clicks; the canonical Benefit POST was intercepted before transmission. This proves action selection without creating/redeeming a consumer. The deliberate blocked request's network cancellation is recorded separately in the browser report. Full signup/action/Delivery execution is covered only in isolated tests.

Screenshots: [guest Landing](live-guest-landing.png), [authenticated Landing](live-auth-landing.png), [guest acquisition](live-guest-acquisition.png). Production reads/writes comparison: all domain counts/configuration unchanged; two ordinary analytics events came from public HTTP QA. No acquisition touches or ownership were added. Coming Soon root remains index,follow; Landing keeps PrelaunchNotice and noindex,nofollow.

Final focused coverage: 69 passing cases in [focused run](focused-tests.txt), followed by the corrected priority/fallback fixture's [successful rerun](priority-test.txt), for 70 verified cases. Docs-only evidence commit after release; no redeployment.
