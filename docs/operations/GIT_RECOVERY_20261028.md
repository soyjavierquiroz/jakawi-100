# Original-history recovery for the new server

Recovery branch: `recovery/new-server-20261028`. No deployment is performed by this change.

Base: original `main`, `fd0b5d0f27a306106db97acd919d63b55e3e86cb`, 344 reachable commits. The original history, branches and tags are preserved. This base is not claimed to be the deployed commit.

The verified deployed-source inventory contains 1,124 files. Against the base repository's `app/`, 724 are byte-identical, four differ and 396 are absent from Git. Both the recovered snapshot and the current source passed all 1,124 SHA-256 comparisons before integration. The inventory file SHA-256 is `840e9f851bac9619cdd3bcae58283d3920995637761d36f82ebfc28fc08a8b25`. Private recovery bundles and runtime configuration are excluded from this repository.

## Integrated source

- `app/app/Http/Controllers/PublicController.php`: preserve deployed places ordering, including featured sort_order and PostgreSQL null ordering/tie-breaks for newest.
- `app/resources/js/pages/explore.tsx`: preserve selected sort when clearing search and show total count with search text.
- `app/bootstrap/app.php`: preserve the additional private proxy subnet used by the new server.
- `app/bootstrap/providers.php` and `app/app/Providers/StagingIsolationProvider.php`: preserve the deployed provider registration and its staging-only HTTP/mail/S3 write restrictions. This provider is inactive outside staging; it does not constitute a complete network isolation boundary.

The other 395 deployed-only files are generated output: 198 files under public/build and 197 Wayfinder files (90 actions, 106 routes, one helper). They remain excluded from Git by the original ignore rules. Their binary provenance remains in the private verified snapshot, rather than being attributed to this commit. No generated assets, runtime caches or user storage are added. The five source files above are byte-identical to the verified deployed versions.

## Development source retained

The 196 original app files absent from the deployed snapshot are retained, including 159 test files, seven scripts, documentation and development configuration. Absence from the deployed snapshot does not demonstrate intentional historical deletion. All root docs, historical artifacts and Docker files remain from the original repository. No historical files are deleted by this recovery.

The four recovered frontend helpers already match the original repository byte for byte: inert-inertia-url-base.mjs, inertia-pages.mjs, check-vite-manifest.mjs and check-dev-runtime.mjs. No helper replacement is necessary. composer.json/lock, package.json/lock, vite.config.ts and tsconfig.json also match the verified deployed source.

## Build and architecture limits

The original Docker build uses PHP 8.4 Bookworm, Composer 2, Node 22, npm ci, Wayfinder type generation and Vite; it provides app and web targets. Original Compose describes the former single bridge network, named data volumes, local web listener and integrated imgproxy. The migrated runtime instead uses Alpine PHP, separate public image input, host-managed persistence, private/egress networks, a shared proxy and separately operated auxiliary services. The operational new-server Dockerfile consumes precompiled assets. These are architecture differences, not missing application features.

The original Docker/Compose files are retained as historical development/build source. They are not a reconciled deployment definition for the new server. No production configuration, filesystem storage paths, credentials or executable Compose override are copied into this branch. Do not run the historical Compose against production as a recovery procedure.

Complete development source is available. Reproducible build and full application startup remain pending an isolated verification of dependency/base-image availability, build-time configuration, generated routes/assets and application caches. Base image/package versions are not fully pinned. Functional equivalence must be distinguished from binary equality of compiled assets. No install, build, migration, runtime test or production change was executed to prepare this branch.
