# Targeted media audit — before implementation

Baseline: `2bd5805f92aaab8aa9ec31476f938a059938c84c`.

| Subject | Field / form | Local file | URL/path only | Disk / DB reference | Validation / optimization | Replacement / preview |
| --- | --- | --- | --- | --- | --- | --- |
| Partner | Admin logo, cover | YES | NO | minio-media; logo_path, cover_path immutable keys | Laravel image/JPEG PNG WebP/10MB + service dimensions/50MP; raw original, imgproxy runtime WebP | Service deleted old before DB save; no local preview in admin |
| Partner Location | Admin image | YES | NO | minio-media; image_path | Same | Same; no immediate preview |
| Benefit | Admin image; Partner handler supports image but Partner form had no selector | YES (Admin) | NO | minio-media; image_path | Same | Same; no immediate preview |
| Experience | Admin/Partner image and cover | YES | NO | minio-media; image_path, cover_path | Same | Same; no immediate preview |
| Challenge | Admin/Partner separate image POST after draft exists | YES | NO | minio-media; hero_path | Same | Same unsafe service replacement; saved hero preview only |
| Unlock | DB hero_path and presenter image capable; Admin form/rules had no image control | NO | NO (no control) | hero_path nullable string; existing presenter MediaUrl | No upload validation/normalization | No upload/replacement workflow |
| Marketing Landing | Publisher hero_path text reference | NO | YES (path text) | hero_path direct string; MediaUrl chooses managed MinIO or legacy public path | String/max2048; no image processing; JSON importer excludes source | DB transaction; saved visual after reference/save, no device preview |
| User/profile | Avatar gallery/camera, existing crop UI | YES | NO | minio-media; UserProfile.avatar_path | Raster/10MB + shared validation; client crop, raw server storage | Saved DB before deleting old, but no shared-reference check; local crop preview |

Shared abstractions: MediaUploadService, MediaUrl, JakawiImage. MinIO is a separate private S3 disk with server credentials and throw-on-error. Signed imgproxy reads s3://jakawi-media keys and applies preset WebP transforms; public props already carry srcsets for catalog images. Existing migration command copies legacy local raster media (dry run by default). There was no temporary editor-upload lifecycle, authoritative reference set or media GC. No new DB media model is necessary.

VSL provider fields and configuration are independent; unchanged. Landing JSON schema retains mode/alt without arbitrary source imports. No production catalog records or storage objects were inspected/created by the implementation or QA.
