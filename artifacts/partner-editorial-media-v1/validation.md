# Partner Editorial Media V1 validation

Status: **READY FOR DEPLOY — NOT DEPLOYED**.

Base: `e5036461e6d258626a09efe0c882032ed78d007c`. Production application remains `22fd406eb7d8794b2c725c79b0af55b19280a16d`.

## Contract

One migration adds nullable JSON `partners.gallery_paths`; model validation permits at most three ordered, Partner-owned immutable gallery keys. Admin accepts uploads/receipts through exactly three fixed `ImageUpload` slots, rejects client-supplied gallery paths and a fourth slot, and compacts removed slots in their remaining order. Local previews precede save; form transactions preserve old references on failure. No physical object deletion occurs during save/remove.

Existing Media Pipeline normalizes private masters into `partners/{id}/gallery/{uuid}.webp`; responsive signed imgproxy variants use a 4:5 editorial preset. Source images retain their original proportions; only presentation uses object-cover. GC recognizes every array key in both collection and final deletion recheck. Referenced images, including archived Partners, remain protected; removed images use the unchanged 48h orphan-observation grace. Health prefix exclusions remain unchanged.

The shared public template omits an empty gallery; one image stays bounded, two use a balanced grid, three use three columns on desktop and stacked 4:5 frames on mobile. Section order is offers/compact empty state → editorial images → locations → guest acquisition. Logo/profile, cover/hero, socials, green accents, canonical routes, Prelaunch, SEO, authentication, Landing-first and acquisition/ownership/commission logic remain unchanged. No CMS, gallery model, reorder UI, carousel or lightbox.

## Evidence

- [Focused Docker PHP 8.4 tests](tests.txt): **35 PASS, 530 assertions**, limited to PartnerEditorialMediaTest, PartnerPublicProfileTest, PartnerIdentityRefinementTest, PartnerAcquisitionTest and MediaPipelineTest. Includes zero/one/three ordered projection, rejected fourth/external/foreign keys, real temporary upload and immutable replacement/removal, atomic failure, archived reference preservation and collection after 49h.
- [TypeScript](typescript.txt): PASS, zero diagnostics.
- [Browser report](browser-validation.json): real isolated Admin login/upload/preview/replace/save/reload/remove; public 390/1440, zero/one/two/three images, section order, 4:5 frames, no overflow, guest/auth behavior. Final run uses compiled assets.
- [390 public](partner-390.png), [1440 public](partner-1440.png), [single image desktop](partner-single-1440.png), [zero inventory mobile](partner-zero-inventory-390.png), [Admin mobile preview](admin-preview-390.png), [Admin three slots desktop](admin-three-slots-1440.png).
- [One final frontend build](build.txt): PASS; manifest verifies 104 Inertia pages and dependency assets. No second final build. Prior browser iteration used Vite development runtime only; `public/hot` removed before final build.
- [Documentation checker](docs-check.txt), [diff check](diff-check.txt), [fixture cleanup](fixture-cleanup.json).

## Production boundary

All QA data/media are guarded by `APP_ENV=testing`, `DB_HOST=db-test`, `DB_DATABASE=jakawi_test` and use isolated local test storage. No production data/media upload or mutation, no Dossis gallery/logo/cover changes, no production migration, no deploy. Existing production app/web/worker images remain unchanged. Human Admin uploads real curated images after a separately authorized deployment and migration.
