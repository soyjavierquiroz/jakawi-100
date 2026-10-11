# VSL isolated development

Original preparation location: /home/jakawi-vsl-dev. Launchers resolve the clone root from their own location; no server path is required. Baseline 16909608893ee1e955c03ed52516198a1b44ba5b.
Never use the inherited compose.yaml, preview Compose, bin/jakawi-test or production launchers from this clone. Always select exactly one standalone VSL Compose file and its dedicated env file. No migrations or startup have been executed during preparation.

Projects: jakawi-vsl-dev and jakawi-vsl-test. Each has its own internal bridge network, pgdata and uploads volumes. PostgreSQL base image is shared read-only; application image tags are exclusive. No source bind mounts, workers, scheduler or provider credentials. Env files are ignored, mode 0600 and excluded from build context.

Read-only checks:

```bash
cd /path/to/jakawi-vsl-dev
python3 bin/check-vsl-isolation.py dev
VSL_ENV_CONFIRM=jakawi-vsl-test ./bin/jakawi-vsl-test --check
```

After separate authorization for building/startup:

```bash
docker compose --project-name jakawi-vsl-dev --env-file .env.vsl-dev -f compose.vsl-dev.yaml build
docker compose --project-name jakawi-vsl-dev --env-file .env.vsl-dev -f compose.vsl-dev.yaml up -d db app web
```

Database migrations need an explicit subsequent decision. Browser navigation requires a migrated synthetic development database. Test launcher only accepts its fixed PHPUnit command; tests may migrate the separate test database via RefreshDatabase. No arbitrary shell, reset, clean or down mode is provided.

Frontend tooling uses PHP 8.4 + Node 22, locked Composer/npm installs and Wayfinder generation inside the image, without source mounts. After build authorization:

```bash
docker compose --project-name jakawi-vsl-dev --env-file .env.vsl-dev -f compose.vsl-dev.yaml --profile checks build frontend-check
docker compose --project-name jakawi-vsl-dev --env-file .env.vsl-dev -f compose.vsl-dev.yaml --profile checks run --rm frontend-check npm run types:check
docker compose --project-name jakawi-vsl-dev --env-file .env.vsl-dev -f compose.vsl-dev.yaml --profile checks run --rm frontend-check npm run check
docker compose --project-name jakawi-vsl-dev --env-file .env.vsl-dev -f compose.vsl-dev.yaml --profile checks run --rm frontend-check npm run build
VSL_ENV_CONFIRM=jakawi-vsl-test ./bin/jakawi-vsl-test
```

Runtime networks block outbound access, including external video. A later scoped networking decision is needed for YouTube/Bunny acceptance. Dependency installs and asset/font generation during image build require network access. Disk and Node/Vite compatibility remain unverified until an authorized build. Port 8093 was free at preparation; recheck before startup. No environment values should be printed or committed. The infrastructure is local-only and must not be deployed to production.

## External machine handoff

Do not build/start on PameServer: available RAM was insufficient in Phase 1B. Use a dedicated Linux development host with Docker Compose v2, Python 3, Git, Bash and flock; PHP runs only in Docker. Recheck memory/swap, disk/inodes and port availability before building. Do not run Docker cleanup on a shared host.

After manual branch publication, clone with independent history:

```bash
git clone --branch feat/jakawi-vsl-v1 --single-branch git@github.com:soyjavierquiroz/jakawi-100.git jakawi-vsl-dev
cd jakawi-vsl-dev
git merge-base --is-ancestor 16909608893ee1e955c03ed52516198a1b44ba5b HEAD
```

Verify HEAD against the handoff commit recorded by the sender. Never copy server env files. Generate fresh local-only values below from the clone root; this refuses to overwrite files and prints no secrets:

```bash
python3 - <<'PY'
from pathlib import Path
import base64, os, secrets
for mode in ('dev', 'test'):
    assert not Path(f'.env.vsl-{mode}').exists(), 'Environment already exists'
common = dict(APP_DEBUG='false', APP_URL='http://127.0.0.1:8093',
    DB_CONNECTION='pgsql', DB_PORT='5432', DB_URL='', MEDIA_DISK='public',
    FILESYSTEM_DISK='public', MAIL_MAILER='array', QUEUE_CONNECTION='sync',
    CACHE_STORE='array', SESSION_DRIVER='file', SESSION_SECURE_COOKIE='false',
    LOG_CHANNEL='stderr', CRM_SYNC_ENABLED='false', META_PROVIDER_ENABLED='false',
    META_BROWSER_ENABLED='false', META_CAPI_ENABLED='false', QR_PAYMENT_ENABLED='false',
    QR_PAYMENT_DRIVER='disabled', SHARECONTEST_URL='http://127.0.0.1:9',
    SHARECONTEST_TOKEN='', CRM_BRIDGE_URL='', IMGPROXY_URL='')
for mode in ('dev', 'test'):
    db = 'jakawi_vsl_' + mode
    password = secrets.token_hex(24)
    env = common | dict(APP_ENV='local' if mode == 'dev' else 'testing',
        APP_KEY='base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
        DB_HOST='db' if mode == 'dev' else 'db-test', DB_DATABASE=db,
        DB_USERNAME=db + '_user', DB_PASSWORD=password, POSTGRES_DB=db,
        POSTGRES_USER=db + '_user', POSTGRES_PASSWORD=password)
    fd = os.open(f'.env.vsl-{mode}', os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(fd, 'w') as file:
        file.write(''.join(f'{key}={value}\n' for key, value in env.items()))
PY
python3 bin/check-vsl-isolation.py dev
VSL_ENV_CONFIRM=jakawi-vsl-test ./bin/jakawi-vsl-test --check
git check-ignore .env.vsl-dev .env.vsl-test
```

Resource/project names currently support ONE VSL clone per Docker daemon. Check no jakawi-vsl-dev/test resources already exist before starting. Port 8093, APP_URL and guard must be changed together if needed, preserving loopback binding. Project/image/volume/network names and guard allowlists must be changed together for concurrent clones. Do not override these casually with COMPOSE_FILE or project variables.

Runtime CPU/memory/PID limits are not yet specified; select them for the external host before startup. Such limits do not constrain image builds. Build sequentially using COMPOSE_PARALLEL_LIMIT=1 and build only required services:

```bash
COMPOSE_PARALLEL_LIMIT=1 docker compose -p jakawi-vsl-dev --env-file .env.vsl-dev -f compose.vsl-dev.yaml build app
COMPOSE_PARALLEL_LIMIT=1 docker compose -p jakawi-vsl-dev --env-file .env.vsl-dev -f compose.vsl-dev.yaml build web
docker compose -p jakawi-vsl-dev --env-file .env.vsl-dev -f compose.vsl-dev.yaml up -d db app web
```

Before development migrations, rerun the guard, inspect the db container mount (must be jakawi-vsl-dev-pgdata), and verify actual Laravel DB config with an assertion, without displaying credentials:

```bash
docker compose -p jakawi-vsl-dev --env-file .env.vsl-dev -f compose.vsl-dev.yaml exec -T app php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $c=config("database.connections.pgsql"); if(!app()->environment("local") || config("database.default")!=="pgsql" || $c["host"]!=="db" || $c["database"]!=="jakawi_vsl_dev" || $c["username"]!=="jakawi_vsl_dev_user" || !empty($c["url"])) exit(78);'
docker compose -p jakawi-vsl-dev --env-file .env.vsl-dev -f compose.vsl-dev.yaml exec -T db sh -c 'test "$POSTGRES_DB" = jakawi_vsl_dev && test "$POSTGRES_USER" = jakawi_vsl_dev_user && psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc "SELECT current_database(), current_user"'
docker compose -p jakawi-vsl-dev --env-file .env.vsl-dev -f compose.vsl-dev.yaml exec -T app php artisan migrate
```

Run migration only after all checks pass. Never import production datasets. PHPUnit factories provide synthetic landing fixtures within the test database. Persistent browser fixtures require review of an existing synthetic seeder or a later dedicated fixture task; do not run generic db:seed blindly. Run PHPUnit and frontend checks using the earlier commands. No baseline is claimed until those execute.

Open http://127.0.0.1:8093 on that machine. For a remote dedicated machine, use `ssh -N -L 8093:127.0.0.1:8093 user@development-host` from the browser workstation. Verify HTTP, asset requests and the four landing types when synthetic fixtures exist. Browser provider access can use workstation networking without adding backend external networks. Font downloads during a runtime `npm run build` may fail on the internal network; the assets image build already has build-time network. Resolve any tooling egress requirement separately with a scoped proxy/allowlist, without relaxing app/db networks.

## Smart Player snapshot

The external source path is /opt/projects/experience-platform/src/components/media/smart-video-player. The owner must prepare the snapshot on that source server; do not fetch automatically.

Include all TS/TSX and transitive local hooks/utilities/contracts, relevant styles/tokens, playback icons and their licenses, YouTube URL normalizer, tests/mocks/fixtures, package.json and lockfile, relevant TS/build/test config, repository identity and full commit reference. Include written ownership/license authorization to reuse and modify in JAKAWI. Exclude env files, credentials, customer data, vendor/node_modules and unrelated proprietary code.

The owner stages only reviewed files in a clean directory named smart-video-player-snapshot, then packages it:

```bash
tar -czf smart-video-player-snapshot.tar.gz smart-video-player-snapshot
sha256sum smart-video-player-snapshot.tar.gz > smart-video-player-snapshot.tar.gz.sha256
```

Send archive privately; send the expected hash through an independently authenticated channel. Receiver runs `sha256sum -c smart-video-player-snapshot.tar.gz.sha256`, then `tar -tzf smart-video-player-snapshot.tar.gz` and rejects absolute paths, traversal or unsafe links before extraction. Review contents for secrets and import/license boundaries before any Git staging. Prefer a scoped private archive or a separately approved private source repository. SHA-256 verifies integrity, not ownership or licensing.
