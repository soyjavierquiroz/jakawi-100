# Operations

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
