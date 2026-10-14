# MEDIA PIPELINE V1 — validation

Decision: **MEDIA PIPELINE V1 READY FOR DEPLOY**. No deploy performed.

Baseline HEAD: `2bd5805f92aaab8aa9ec31476f938a059938c84c`.

## Audit and implementation

See [targeted before audit](audit.md) and [pipeline contract](../../docs/MEDIA_PIPELINE_V1.md).

| Before | Local file | URL/path only | Reference mechanism |
| --- | --- | --- | --- |
| Partner | YES | NO | logo_path/cover_path, private MinIO immutable keys |
| Benefit | YES (Admin; Partner backend had no form selector) | NO | image_path, private MinIO key |
| Experience | YES | NO | image_path/cover_path, private MinIO keys |
| Challenge | YES | NO | hero_path, private MinIO key |
| Unlock | NO | NO (no image form control) | image-capable hero_path string |
| Landing | NO | YES (text path reference) | hero_path, shared MediaUrl with managed/legacy fallback |

Shared uploader: `ImageUpload`; backend: extended `MediaUploadService`. No new DB media model: existing path fields and one normalized master are sufficient. `MediaReferences` explicitly includes all authoritative image-bearing fields, including Unlock and UserProfile. All six target forms, Location and Partner content forms use controlled local-device selection. Existing profile crop adapter continues through the same backend. VSL providers remain unchanged.

Validation: actual JPEG/PNG/WebP MIME, 10 MiB, 24 MP, maximum 16000 pixels per side and successful decoding. Output WebP quality 82, longest side capped at 1600 without upscaling, EXIF orientation applied, metadata discarded. Raw camera files remain only request-temporary files. Private MinIO and signed imgproxy delivery preserved. Imgproxy performs runtime responsive variants: 480/960/1600 plus existing 320/640 small candidates; no redundant stored variant family.

Keys: `{kind}/{numeric-id}/{slot}/{server-uuid}.webp`, immutable and slug-independent. Temporary `tmp/{uuid}/master.webp` objects and user-bound encrypted receipts expire after 24h. New final objects are verified before transactional DB persistence; old objects are never deleted by replacement/removal. DB/upload failures preserve old media. Image removal clears references; Landing removal selects NONE to prevent inherited product media from reappearing.

GC: `media:gc --dry-run` writes/deletes nothing. Normal run is idempotent, lists only explicit managed prefixes, ignores uncertain keys, reports counters/bytes/errors and rechecks all references immediately before deletion. Normal grace is 48h from first unreferenced observation, conservatively also requiring object age >48h; private candidate markers avoid a new DB table. Temporary grace 24h. Daily at 03:20 America/La_Paz, without overlap. `health/`, specifically `health/imgproxy-auth-test.txt`, backups, v2proof, unrelated buckets and other namespaces excluded.

Landing JSON mode/alt contract preserved; source URL/path import remains forbidden. Publisher save rejects arbitrary supplied hero paths. Existing saved/product references remain compatible.

## Verification

- [Focused backend](backend-tests.txt): **39 passed, 492 assertions**. MediaPipelineTest, MediaInfrastructureTest, MemberProfileTest, MarketingLandingAdminV1Test and PartnerContentStudioTest only.
- [Landing JSON frontend](landing-json-tests.txt): **13 passed**; source-import security contract preserved.
- [TypeScript](typescript.txt): PASS.
- [Final frontend build](frontend-build.txt): PASS; 104 Inertia pages checked by manifest validation.
- [Browser QA](browser-validation.json): PASS, real local selection, immediate blob preview, temporary processing, save/reload/persistence and replacement for Partner and Landing; invalid SVG gives readable error and preserves saved reference; no page errors.
- 390px: no horizontal overflow; signed thumbnail requests 320px and landing srcset selects 480px. Browser checks positive decoded dimensions, not merely presence of an img element.
- 1440px: no horizontal overflow; representative Landing regression passed.
- [Docs check](docs-check.txt): zero broken targets.
- `git diff --check`: PASS.

Backend testing used `./bin/jakawi-test` and Docker PHP 8.4. Browser QA used the isolated `db-test` / `jakawi_test` database, test public-disk volume and a guarded test delivery adapter. Runtime production imgproxy signing/variant URLs are verified by backend tests; no production MinIO endpoint was used for QA. The adapter reads actual normalized test WebP bytes and preserves binary responses. Screenshots: [Partner 390](partner-390-preview.png), [Landing 390](landing-390-preview.png), [Landing 1440](landing-1440.png). The test-only harness is included for reproducibility; never mount it in production.

Validation incident: the initial npm build indirectly invoked host PHP through Wayfinder and failed. This violated the environment instruction; it was acknowledged and corrected with an explicit PHP PATH adapter targeting Docker PHP 8.4. The final build used Docker PHP, passed, and is the build recorded above. No host php version inspection, Composer command or Artisan command was intentionally invoked.

## Production and Git

Production records created: **0**. Production objects uploaded/deleted: **0**. Production storage was not accessed by implementation/QA; the existing health object remains untouched. No deployment. Commit message: `feat(media): unify image uploads and orphan lifecycle`; authorized destination: `origin/main`.

Result: **JAKAWI MEDIA PIPELINE V1 READY**.
