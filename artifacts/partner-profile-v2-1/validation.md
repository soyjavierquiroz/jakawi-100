# Partner Profile V2.1 — validation

Previous HEAD: `9b521d56598de3427076aafb946dfe9b15980708`. Implementation/commit/push only: **NO DEPLOY**. Docker PHP 8.4 through `./bin/jakawi-test`; no host PHP/Composer/Artisan. No production Partner, social, media or account mutation.

## Focused refinement

All five social fields already exist in Partner schema/model/Admin and in the public projection: `instagram`, `tiktok`, `facebook`, `website`, `whatsapp`. No migration or new field. Public links appear only for usable stored values, ordered Instagram → TikTok → Facebook → Website → WhatsApp. They are compact and secondary near the existing hero description, use existing Lucide icons, open externally with `target="_blank" rel="noopener noreferrer"`, and omit unsafe protocols/credentials. Stored WhatsApp phone values become wa.me links without inventing a country code. No feeds, embeds, API calls, social model or CMS.

Production Dossis 43 read-only inspection confirms Instagram, Facebook and TikTok already stored. Website and WhatsApp are null; those optional fields need Admin entry if links are desired. Its existing logo is not overwritten or transformed during implementation.

`logo_path` remains PROFILE IMAGE; `cover_path` remains HERO. Public avatar becomes 112×112, circular, contained, with fixed geometry and existing initials fallback. The only green touches are a small decorative identity dot and the existing hero location icon; coral actions/yellow opportunities stay unchanged.

The existing member profile's react-easy-crop UI/export is extracted into **one shared AvatarCropDialog**, used by member profile and Partner logo upload. Partner mode supports a round mask, drag/reposition, zoom including full-logo contain, live preview and confirm. Export is square PNG (transparent padding for horizontal logos), capped at 1024 pixels without enlarging selected source pixels; shared MediaUploadService normalizes it to an immutable WebP master on the configured disk (private MinIO in production). No circular physical encoding, new crop engine or huge original upload. Low-resolution sources remain small.

Existing Admin can select a new file or reopen the saved master through a small admin-only, ID-bound `logo-source` read action. It streams the existing configured storage object without writing; no arbitrary object path or public master endpoint. This avoids CORS restrictions and recropping a thumbnail. Cancel leaves the old reference intact; applying the crop only stages an upload receipt, and the existing Partner form saves it. Failed receipt/replacement leaves old media/reference safe. Cover retains the generic uploader and existing behavior; no enforced logo crop, ratio change or gallery.

## Validation

- [Focused backend](backend-tests.txt): **25 passed / 381 assertions**. PartnerIdentityRefinementTest, PartnerPublicProfileTest, PartnerAcquisitionTest and MemberProfileTest. Social projection, square normalized logo, immutable replacement/old master retention, failure preservation, unchanged cover, admin-only original read/access protection and existing profile/acquisition regressions. No broad suite.
- [TypeScript](typescript.txt): PASS, `tsc --noEmit`; empty successful log.
- [ONE final validation build](frontend-build.txt): PASS; 104 Inertia pages, manifest/assets/dependencies present, hot absent. Development server used for interaction checks before this one build; final browser validation uses compiled assets.
- [Browser report](browser-validation.json): PASS, zero page errors. Instagram-only, all five socials, empty socials, unsafe URLs, external link attributes and WhatsApp mapping. Circular contained avatar/initials fallback and no overflow at [390](partner-390.png) / [1440](partner-1440.png).
- Actual isolated Admin login → reopen old master/cancel → select 1600×400 PNG → circular mask → zoom via keyboard → drag → show entire logo → [live preview](admin-crop-fit-1440.png) → upload receipt → save/reload. Preview preserves both color markers at the source's brand edges. Stored output **1024×1024 WebP**, new immutable key, old master still exists; cover unchanged. [Zoom/reposition](admin-crop-1440.png).
- [390 Admin crop](admin-crop-390.png): responsive dialog. A 200×50 source yields **206×206** square output with padding, without forced 800/1024 upscaling. Staging the replacement does not change the saved reference.
- Existing guest acquisition block remains; authenticated fixture hides it. PrelaunchNotice, robots wrapper, Coming Soon, attribution/new-user primitives and native Offer rendering are unchanged. No acquisition/referral/commission changes; no production QA account or object upload.
- [Production safety read](production-read-check.json): existing Dossis social/logo fields unchanged by implementation. [Preserved source](preserved-contracts.json). [Fixture cleanup](fixture-cleanup.json): zero remaining QA Partners/users; final temporary upload objects removed; test servers stopped.
- [Docs](docs-validation.txt) and [diff](diff-check.txt): PASS.

## Acceptance

| Area | Result |
| --- | --- |
| Social | Existing fields YES; migration NO; all five supported; empty links HIDDEN |
| Logo | PROFILE IMAGE; crop 1:1; CIRCULAR presentation; zoom/reposition YES; existing crop primitive REUSED |
| Media | Shared pipeline YES; immutable replacement YES; failure preserves old logo YES; cover changed NO |
| Green | Subtle existing semantic green YES; coral/yellow PRESERVED |
| Regression | Acquisition PASS; Prelaunch UNCHANGED; Partner V2 hero/cover PRESERVED |
| QA | Focused tests, TypeScript, real Admin crop, 390, 1440, one final build, diff/docs PASS |
| Git | Previous HEAD above; commit/push SHA reported in final answer; NO DEPLOY |
| Decision | PARTNER PROFILE V2.1 READY FOR DEPLOY |
| Resultado | JAKAWI PARTNER PROFILE V2.1 READY |
