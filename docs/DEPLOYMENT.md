# Deployment

Current deployment is manual.

```bash
cd /home/jakawi.com

git status
git fetch origin
git pull --ff-only origin main

docker compose build
docker compose up -d

docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize

docker compose ps

curl -f http://127.0.0.1:8080/up
curl -f https://jakawi.com/up
```

## Rules

- Never use `docker compose down -v` as a normal operation.
- Never delete the PostgreSQL volume as a normal operation.
- Never regenerate `.env` during deploy.
- OpenLiteSpeed changes require a backup first.
- Do not invent or assume CI/CD; deployment is currently manual.

## Conservative Rollback

1. Identify the last known good commit.
2. Confirm the working tree state with `git status`.
3. Checkout or reset to the known good commit only when intentionally rolling back application code.
4. Rebuild and restart with the normal Docker Compose deployment commands.
5. Run health checks against `http://127.0.0.1:8080/up` and `https://jakawi.com/up`.

Do not delete volumes during rollback unless there is a separate, explicit data recovery plan.
