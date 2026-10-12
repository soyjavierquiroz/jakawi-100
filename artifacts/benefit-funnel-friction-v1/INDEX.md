# Benefit funnel friction V1

Implementation and local validation, 2026-10-12. No production deployment or production data changes.

## Before and after

Before: the guest CTA opened redemption confirmation and required a location before authentication. The Benefit-specific verification middleware and frontend verification branch then interrupted the continuation. Benefit and Experience also nested JourneyLayout inside ConsumerLayout, repeating navigation/footer chrome.

After: guest CTA → existing registration/login → canonical Benefit redemption intent → location selection when needed → membership conversion when needed → confirmation → existing domain redemption. Registration requires no location. A valid explicit preselection survives authentication through the existing server-side continuation. A sole usable location resolves automatically; zero usable locations remains unavailable.

Normal Benefit redemption no longer requires verified email. Authentication, membership, validity, applicability, limits and duplicate protection remain authoritative. Challenge-grant Benefits retain their explicit verification requirement. Verification infrastructure remains available and its view now uses Spanish copy; it was not removed globally.

Native Benefit, Experience and Challenge details use ConsumerLayout once. Its mobile BottomNav replaces the redundant PublicFooter. Challenge contextual back navigation and content heading remain. MarketingLayout, PublicFooter itself, Marketing Admin and OfferConversionTemplate V3 are unchanged. V3 remains FROZEN.

## Backend evidence

Docker PHP 8.4 through `./bin/jakawi-test`, isolated database:

- BenefitRedemptionContinuityTest, RedemptionHttpTest, MembershipRedemptionTest, PublicJourneyWiringTest and MembershipConversionJourneyTest: 48 PASS / 845 assertions.
- Final BenefitRedemptionContinuityTest and EmailVerificationTest: 17 PASS / 183 assertions. Ten continuity tests overlap the earlier run; total distinct coverage is 55 tests.
- Coverage includes registration without a location, unverified login/member continuation, valid preserved selection, foreign/unusable locations, zero/one/multiple locations, expired/inactive subjects, membership continuity, duplicate/limit behavior and unsafe return context. Challenge-grant verification is explicitly retained.
- No success outcome is recorded for authentication, location selection or membership navigation. Growth taxonomy and redemption services are unchanged.

## Browser evidence

[Machine-readable result](browser-validation.json): 5 PASS at 390 × 900.

The browser renders the current frontend with current demo domain props captured read-only. Auth responses are isolated fixtures; real registration/login/session/domain behavior is covered by the Docker backend tests above. No production account or redemption was created. Global header counts exclude the Challenge content heading inside its main content.

| Case | Result | Evidence |
| --- | --- | --- |
| Café, 3 locations: guest → registration → same Benefit → blank required selector → membership action | PASS | [390 screenshot](demo-beneficio-01-returned-390.png) |
| Cena, 2 locations: guest → login → same Benefit → selector | PASS | [390 screenshot](demo-beneficio-04-returned-390.png) |
| Unverified member with valid preselected location → activation confirmation | PASS | Machine-readable result |
| Experience native shell | One visible global header, no PublicFooter, one BottomNav, no overflow | [390 screenshot](experience-shell-390.png) |
| Challenge native shell | One visible global header, no PublicFooter, one BottomNav, no overflow | [390 screenshot](challenge-shell-390.png) |

Benefit also has one visible global header, one BottomNav and no duplicate footer. Guest auth requests contain no location requirement. No verification dead end or browser exception occurred.

## Final validation

PASS: one `docker build -f docker/Dockerfile --target build -t jakawi-frontend-check .`, including Docker PHP Wayfinder generation, TypeScript, frontend compilation, manifest and production hot-file guards. No production build or deployment.

PASS: `git diff --check` and artifact Markdown links. Commit message: `fix(benefits): streamline guest redemption funnel`.

Decision: BENEFIT FUNNEL V1 READY FOR DEPLOY. Deployment remains a separate task.
