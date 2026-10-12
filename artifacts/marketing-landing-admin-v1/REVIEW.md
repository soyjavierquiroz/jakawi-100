# Marketing Landing Admin V1 — local review

Base: `5755f17` (`refactor(marketing): introduce offer conversion template`). Existing shared LandingPresentation administration is extended; no parallel Admin or builder.

## Implementation

- Persisted allowlisted `OFFER_CONVERSION`, `DARK_CONVERSION` / `LIGHT_CONVERSION`, `IMAGE` / `VSL` / `NONE`, headline emphasis and final supporting copy.
- Existing copy/media columns reused. Subject defaults and truth summary are read-only projections; economics, eligibility, rules, capacity, dates, limits and actions remain domain-owned.
- Subject selection and reassignment support Benefit/Experience/Challenge. Unlock retains its existing admin/editorial validation, renderer and disclosure boundary; no Offer adapter.
- Create/edit, explicit publish/unpublish and draft duplication with new name/unique slug. `publish_scope` stores pending audience while `default_scope` remains the published active scope.
- Admin preview uses the same public controller/adapter/template and saved configuration in an iframe whose content viewport is exactly 390/430/1440. Actions remain domain-bound and preview-disabled; no attribution/business writes.
- Reversible migration assigns current template/themes/media without rewriting legacy copy. `editorial_version=0` preserves discovery composition until an editorial/subject edit. Image/VSL configuration survives media mode changes.
- Existing Benefit guest redemption continuity (`location_id`, auth/verification) remains a commercial pilot blocker, documented without workaround.

## Evidence

Targeted Docker PHP 8.4 tests: **46 PASS / 2261 assertions** (`backend-tests.txt`), including the existing four domain/landing suites. No broad backend suite or baseline CampaignTest debt change. TypeScript and docs/diff checks pass.

Browser QA uses Vite and real page components with controller-exported fixtures from the isolated `jakawi_test` database. Authorization and persistence are exercised separately by backend HTTP tests; browser fixtures are not a production or end-to-end Admin session.

- `browser-report.json`: 33 records; no browser exceptions. Admin at 390/1440; each domain preview at 390/430/1440 on both Admin widths. Noindex, one emphasis span, same hero/final action, no media wrappers, functional FAQ, operational content, blocked mutations, no overflow and duplicate controls.
- `extra-browser-report.json`: both themes on all domains at 390/1440, no-media cases and create/defaults/media-preservation payload; no exceptions/overflow.
- `vsl-report.json`: existing Bunny VSL playing, audible, readyState 4; no player changes.
- `pixel-report.json`: Experience and Benefit pixel-identical at 390/430/1440; Challenge pixel-identical at 430/1440. The 390 comparison differs only within the asynchronous decoded VSL frame rectangle; `challenge-static-parity.json` shows zero changed pixels outside it. Layout dimensions match throughout. Player internals/source/configuration are unchanged.

Screenshots include Admin forms, saved previews, both themes, parity and VSL. No Product UI header introduced in Offer landings. Preview renders saved content; edits must be saved first.

## Delivery boundary

Migration tested only in isolated Docker testing database. No production migration, push or deploy. Frontend Docker validation is run once only after all preceding checks and the >=10 GiB disk gate; see `disk-report.json` and `frontend-build.txt` for its recorded result.

Next: architecture review, then create dynamic QA landings through Admin. Commercial Benefit redemption waits for its separate domain-flow fix.
