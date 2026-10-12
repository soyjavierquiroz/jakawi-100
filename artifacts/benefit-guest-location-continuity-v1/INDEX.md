# Benefit guest/location continuity V1

Decision: BENEFIT CONTINUITY READY FOR DEPLOY. Implementation only; production remains on `3b2692270b5eb45016fbc77ac7a8e8f6e377c24a`.

## Flow and root cause

The guest CTA previously posted without location context; the server continuation stored only Benefit identity and returned to a closed Product Detail. The flow now uses the existing server session continuation and a canonical protected GET `/beneficios/{slug}/canjear` to resume the same Product Detail confirmation. Only the existing POST creates a pending redemption; partner validation still confirms it through the existing service.

Guests choose a location before auth. Legacy guest POSTs without a choice resume the selector after auth. One usable location is resolved deterministically, multiple locations require an explicit selection, and zero usable locations expose an unavailable state. Usable locations come from existing Benefit rules and require a redemption PIN. The UI never receives the PIN/hash.

Benefit identity and action live in the server session; selected `location_id` is validated and carried into the generated canonical GET. Login, signup, verification, and the membership gate retain this destination/context. No caller-provided return URL is accepted. Restored context is a choice, never authorization: final POST and the unchanged redemption service recheck current availability, location applicability, membership, limits, and existing grant rules. Pending idempotency and per-user transaction locking remain unchanged.

The existing `verified` declaration was ineffective because User does not implement MustVerifyEmail. A Benefit-only middleware now enforces `hasVerifiedEmail()` on the two redemption endpoints and preserves a generated same-origin GET through email verification. No global User/auth contract was changed.

## Validation

- Docker PHP 8.4 via `./bin/jakawi-test`: 82 related regression tests PASS; final focused continuity suite 10 tests PASS (157 assertions). Eight continuity tests overlap the regression run; 84 distinct targeted tests covered.
- TypeScript: PASS (`tsc --noEmit`).
- Browser: 16 local cases PASS, including both actual curated demo Benefits at 390 / 1440 with guest, member, and restored intent; single/zero location, non-member, and unverified UI cases. No horizontal overflow or JS errors.
- Browser uses actual demo domain props captured read-only, isolated auth projections, and intercepted submissions. Real auth, verification, successful domain writes, duplicate guards, and rejection paths are covered in the isolated backend tests; no production redemption was created.
- `git diff --check`: PASS. No production deploy, migration, or commercial-demo data writes.
- V3 template, Marketing Admin, Experience/Challenge flows, Growth taxonomy, CRM, JP, and economics are unchanged.

## Demo evidence

Café `demo-beneficio-01`: 3 usable locations. Cena `demo-beneficio-04`: 2 usable locations. These remain synthetic, non-contractual and non-redeemable commercial demos; real inventory remains PENDING CONFIRMATION.

| Demo | 390 | 1440 |
|---|---|---|
| Café | [Confirmation](demo-beneficio-01-member-390.png) | [Confirmation](demo-beneficio-01-member-1440.png) |
| Cena | [Confirmation](demo-beneficio-04-member-390.png) | [Confirmation](demo-beneficio-04-member-1440.png) |

[Browser assertions](browser-validation.json)

Benefit continuity is ready for deployment review; the production blocker remains until this release is deployed and smoked. This does not establish commercial inventory or launch readiness.
