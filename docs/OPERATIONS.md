# Operations

## Production and Testing Safety

### Production

All production Laravel commands pass through the production app container:

```bash
docker compose exec app php artisan <command>
```

Production is currently in maintenance and its `web` and `app` services must
remain stopped. Do not run `artisan up`, migrations, `migrate:fresh`,
`pg_restore`, or destructive tests against production.

### Testing

All tests and test database commands use the isolated wrapper, never host PHP
or Composer:

```bash
./bin/jakawi-test
./bin/jakawi-test php artisan test
./bin/jakawi-test php artisan migrate:fresh --seed
```

The wrapper uses Compose project `jakawi-test`, `app-test`, `db-test`, and the
database `jakawi_test`. It rejects a command unless `APP_ENV=testing`,
`DB_HOST=db-test`, and `DB_DATABASE=jakawi_test`. `--env=testing` on the
production app container is not isolation.

To stop only testing containers use `./bin/jakawi-test down`. To remove the
isolated test containers and its test volume use `./bin/jakawi-test clean`.
Neither command addresses production services or volumes.

Never run `migrate:fresh`, `db:wipe`, or destructive tests against the
production app container. Never run `php artisan` or `composer` on the host.

## Service Status

```bash
docker compose ps
docker stats --no-stream
```

## Logs

```bash
docker compose logs --tail=100 app
docker compose logs --tail=100 web
docker compose logs --tail=100 db
```

## Health Checks

```bash
curl -i http://127.0.0.1:8080/up
curl -i https://jakawi.com/up
```

## Laravel Checks

```bash
docker compose exec app php artisan about
docker compose exec app php artisan migrate:status
```

## Fictional QA Demo Catalog

The demo catalog contains **FICTIONAL QA DATA** only. It is idempotent and does
not configure redemption PINs, so it supports catalog navigation without
enabling redemptions.

```bash
docker compose exec app \
php artisan jakawi:seed-demo-catalog
```

To remove only records whose slugs start with `demo-`:

```bash
docker compose exec app \
php artisan jakawi:clear-demo-catalog
```

## Real Catalog CSV Import

The importer accepts a UTF-8, comma-separated CSV with one benefit per row.
Merchant fields may be repeated for its benefits. See
`docs/examples/catalog-import-example.csv` for a fictional example.

Generate a template:

```bash
docker compose exec app \
php artisan jakawi:catalog-import-template
```

Always run the dry run first; it validates the whole CSV and reports creates and
updates without writing to the database:

```bash
docker compose exec app \
php artisan jakawi:import-catalog \
storage/app/imports/catalog.csv
```

Only apply after a clean dry run:

```bash
docker compose exec app \
php artisan jakawi:import-catalog \
storage/app/imports/catalog.csv \
--apply
```

The import is transactional, rejects `demo-` slugs, and never imports PINs or
images. Existing merchant PINs and uploaded image paths are preserved.

## Disk Checks

```bash
df -h /
docker system df
```

## Basic Diagnosis

### web

Check whether nginx is running and responding through Docker.

```bash
docker compose ps web
docker compose logs --tail=100 web
curl -i http://127.0.0.1:8080/up
```

### app

Check whether Laravel and PHP-FPM are available.

```bash
docker compose ps app
docker compose logs --tail=100 app
docker compose exec app php artisan about
```

### db

Check whether PostgreSQL is running and migrations are visible to Laravel.

```bash
docker compose ps db
docker compose logs --tail=100 db
docker compose exec app php artisan migrate:status
```

Never suggest deleting volumes as a first troubleshooting step.
