# Partner Public Profile V2 — validation

Previous HEAD / fetched origin/main: `9857357e23c541608a40b3a8e3c3a346100634f7`. Presentation implementation only; no deployment. Docker PHP 8.4 through `./bin/jakawi-test` and the existing Docker build shim; no host PHP, Composer or Artisan. Browser fixtures use only `testing / db-test / jakawi_test`, real Laravel sessions/authentication and final compiled frontend assets.

## Product and architecture

One shared `partners/show` conversion/brand template. Mobile leads with cover → contained profile logo → identity/headline → actions. Desktop uses a 53/47 editorial split. Existing description's first sentence supplies headline styling; remaining stored copy stays supporting content. No fabricated or persisted headline, Partner-specific content, new schema, CMS, page builder, adapter or template engine.

Logo uses the existing signed MediaUrl/imgproxy pipeline with the new `partner_logo` **fit** preset, avoiding the prior thumbnail's destructive fill crop. Cover remains `partner_cover` with responsive srcset/sizes and object-cover; no photo overlay. Missing logo renders initials, missing cover renders JAKAWI semantic color/typography graphics. Failed image requests retain the existing JakawiImage fallback. Partner has no gallery relation, so gallery is omitted.

Hero scrolls to active inventory, with location secondary. Zero inventory gets a place CTA and compact editorial message. Benefits, Experiences and Challenges share large-media cards with type, stored title/supporting copy, authoritative estimated savings/prices/date/reward and canonical native CTAs. Estimated value, opportunity and rewards use yellow; no estimated savings styled as confirmed green. Landing resolution remains downstream.

Location is an editorial destination section using existing names/city/zone/address/reference/hours/phone. Existing `/lugares/{slug}/mapa` navigation is preserved. Guest register/login use unchanged `/p/{slug}/auth/{method}` entries below inventory/location; authenticated users have neither action. No acquisition logic, first-touch ownership, existing-user protection or commission changes.

## Validation evidence

- [Focused backend](backend-tests.txt): **27 passed / 653 assertions**; `PartnerPublicProfileTest`, `PartnerAcquisitionTest`, `LandingAcquisitionTest`. Includes signed logo fit vs cover fill and responsive sizes, missing media projection, all active inventory, expired/paused filtering, native Benefit route/downstream Landing, first-touch new signup, existing owner/login protections and economic regression. No broad suite.
- [TypeScript](typescript.txt): `app/node_modules/.bin/tsc --noEmit --project app/tsconfig.json` PASS (empty log).
- [Final validation build](frontend-build.txt): PASS; manifest validates **104 Inertia pages**, all assets/dependencies present. Candidate builds supported visual review; the final build includes the fallback contrast correction and was used for final browser QA.
- [Browser report](browser-validation.json): 390 with horizontal logo + cover + all three offers; logo object-contain / cover object-cover, loaded media, mobile order, prominent media, canonical CTA URLs, hero scroll with sticky header clearance, map route and account below offers/location. Signup/login visible for guest and absent for authenticated. No horizontal overflow or browser errors.
- Actual primary flow: Partner → Benefit → applicable Landing → three-step Micro Opt-in → NEW signup. Partner owns new relationship; no profile signup click, rewards, redemptions, reservations or Challenge participations. Separate existing login consumes candidate without reassignment; direct signup retains existing auth primitives.
- [390 rich profile](partner-390.png), [authenticated](authenticated-390.png), [existing login](existing-login-390.png), [Micro Opt-in](micro-opt-in-390.png).
- [390 empty profile](empty-390.png): no-media/no-inventory fixture, branded fallback + initials, no broken image elements, compact empty state under 120px, location followed by guest account block. Long stored description also validated without overflow; existing bottom navigation stays in place.
- [430 regression](partner-430.png): PASS; no overflow.
- [1440](partner-1440.png): PASS; cover >500px and copy >550px, editorial split, three dominant Offer cards, no overflow. Screenshots visually reviewed.
- [Preserved source contracts](preserved-contracts.json): PrelaunchNotice copy/component, robots wrapper, consumer navigation, attribution service, new-user action and Coming Soon are byte-for-byte unchanged from previous HEAD. Browser confirms notice, `noindex,nofollow`, canonical URL and isolated Coming Soon.
- [Docs checker](docs-validation.txt): architecture and validation docs PASS (44 relative targets, zero broken). [Diff check](diff-check.txt): PASS.
- [Fixture cleanup](fixture-cleanup.json): zero QA Partners/users after final run; local fixture HTTP and asset proxy servers stopped. No production domain writes or deployment.

## Acceptance

| Area | Result |
| --- | --- |
| A — Hero | CRUD-like wrapper removed; cover hero YES; profile logo YES; editorial hierarchy YES |
| B — Media | Logo PROFILE IMAGE; cover HERO; gallery NOT MODELED / OMITTED; graceful fallback YES; Media Pipeline REUSED |
| C — Inventory | Benefits / Experiences / Challenges HIGH-IMPACT CARDS; Landing-first PRESERVED; zero inventory COMPACT |
| D — Acquisition | Signup LOWER; Partner → Offer → Landing → signup PRESERVED; existing users reassigned NO; referral logic changed NO |
| E — Auth | Guest signup/login YES; authenticated signup/login HIDDEN |
| F — Location | Editorial destination; current Maps route PRESERVED |
| G — Prelaunch | Notice UNCHANGED; robots UNCHANGED; Coming Soon UNCHANGED |
| H — Architecture | Migration NO; CMS NO; template engine NO; DB fields NO; acquisition logic changed NO |
| I — QA | Focused backend, acquisition, TypeScript, 390, 430, 1440, final build, diff and docs PASS |
| J — Git | Previous HEAD above; commit/push SHA reported in final response; origin/main; NO DEPLOY |
| K — Decision | PARTNER PUBLIC PROFILE V2 READY FOR DEPLOY |
| L — Resultado | JAKAWI PARTNER PUBLIC PROFILE V2 READY |

## Production release — LIVE

Partner Public Profile V2 application `3fd45e30b4d641f9f6601f31c53f70ec57600048` is LIVE, released directly from actual runtime `dbbaadcd0ab4ee8accb2a5367d2d3104ba80f878` with one production build and protected exact rollback images. Shared conversion/brand profile; logo = profile image, cover = hero; high-impact inventory cards; compact zero-inventory state; acquisition semantics unchanged. No migration, new account, fake inventory, media upload or domain mutation from deployment/QA. Docs-only followup; no rebuild or redeploy.

[Release evidence](live/production-release.json), [readiness](live/readiness.json), [browser smoke](live/browser-validation.json), [390](live/dossis43-390.png), [1440](live/dossis43-1440.png). Partner HTML was rendered by the deployed production kernel in a READ ONLY transaction; browser loaded and hash-checked actual public compiled assets. This avoids analytics/attribution writes by QA. Public GET 200 was independently observed in the access log during concurrent external traffic: one profile visit and a Maps click added normal measurement ([audit](live/measurement-audit.json)); users, offers, ownership and economy stayed unchanged. Existing Admin media controls and authenticated Partner account-block hiding pass read-only rendering. Prelaunch/robots/Coming Soon, Media Pipeline and GC remain unchanged; app/DB/v2proof healthy, web/worker running, scheduler active, failed jobs zero, CRM true and Meta off.
