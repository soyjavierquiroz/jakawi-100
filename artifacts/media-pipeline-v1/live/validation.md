# Media Pipeline V1 — LIVE

Application deployed: `dbbaadcd0ab4ee8accb2a5367d2d3104ba80f878`; actual previous runtime: `e71fbd8dda5d4f4fbc26a58b4d7495f369daa638`. One clean production build; only app, web and social-worker recreated. Exact previous app/web images are protected with rollback-media-e71fbd8 tags.

[Release report](production-release.json), [runtime](runtime-final.json), [readiness](readiness.json), [media runtime](media-status-final.json), [artifact guard](live-runtime-guard.txt), [GC dry-run](gc-dry-run.txt), [scheduler](scheduler.txt), [domain counts](state-final-health.json).

Media Pipeline and Media GC are LIVE. Device upload → normalized WebP master → private MinIO → signed imgproxy delivery. Runtime responsive widths: 320/480/640/960/1600; no stored width duplicates. Safe replacement preserves old media until committed references change. Temporary encrypted user-bound uploads expire after 24h. Daily GC at 03:20 America/La_Paz, without overlap; normal orphan grace 48h from first unreferenced detection, with immediate authoritative reference recheck. health/ excluded.

MinIO before/after: one object, 41 bytes, health/imgproxy-auth-test.txt, identical ETag. Dry-run scanned zero managed objects; zero deletes, bytes reclaimed and errors. No manual destructive GC, production QA uploads, migrations, seeds, backups, catalog records, new users or domain mutations.

Coming Soon, Prelaunch notice, Landing JSON schema, acquisition/config contracts and environment file are unchanged. Source hashes, local/public 200s and authenticated Admin 200 read-only render verified. App/db healthy; web/worker running; v2proof and imgproxy unchanged; scheduler active; failed jobs zero; CRM true; Meta off.

Documentation status changes only; no rebuild or redeploy afterward.
