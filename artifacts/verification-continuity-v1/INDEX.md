# Verification Continuity V1

Undeployed change based on clean origin/main `158769f01010e521377a47e0e3e62858fd51cdc1` (marketing architecture audit). No production database or application deployment was changed.

- Benefit: authenticated owner may view Delivery unverified. Ownership, expiration, grants, redemption rules and partner validation remain protected.
- Experience: internal reservation identity boundary explicitly enforced; contextual verification retains the selected session and returns to Activation without reserving. External result needs no verification.
- Challenge: initial join remains unverified; evidence/reward boundaries and contextual Delivery return are unchanged.
- Unlock: interest/commit/cancel/confirm require proof, return to the same Activation and participation state without replaying mutations or revealing restricted source.
- Existing `User` lacks `MustVerifyEmail`; generic middleware declarations were ineffective. The new explicit check is scoped to Experience/Unlock mutations. No global model/interface change.
- Account access and email ownership verification remain separate; generic verification fallback retained. Welcome Courtesy: FUTURE / OFF.

Validation:

- `./bin/jakawi-test php artisan test --compact --filter='VerificationContinuityTest|BenefitRedemptionContinuityTest|ChallengeDeliveryTest|EmailVerificationTest'`: 35 passed, 521 assertions.
- Tightened Benefit grant POST/verified-without-grant check: rerun only that test: PASS, 8 assertions.
- TypeScript: `app/node_modules/.bin/tsc --noEmit`, PASS.
- One final `npm run build`: PASS, manifest validates 103 Inertia pages. Build's Wayfinder PHP runs through a shim into Docker PHP 8.4, never host PHP.
- [Browser evidence](browser-validation.json): PASS, isolated test data, final compiled frontend, 390px only. Benefit acquisition and Delivery/QR; Experience session/context/notification without reservation; Unlock contextual state without commitment, JP hold or restricted source. Multiple safe Benefit fixtures belong only to this isolated QA database; no production mutations.
- Signed verification proof/return, invalid proof, foreign subject/session, malicious intended URL and no auto-reservation/commit/cancel are covered by backend tests.
- Documentation and whitespace checks: PASS.

Authoritative boundaries and policy decision: [audit](../../docs/architecture/MARKETING_SYSTEM_AUDIT.md), [current architecture](../../docs/ARCHITECTURE.md).
