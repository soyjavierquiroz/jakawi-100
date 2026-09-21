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
