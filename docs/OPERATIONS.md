# Operaciones

Este runbook describe el stack actual. No autoriza despliegues, cambios de datos ni cambios de infraestructura por sí mismo.

## Producción

`jakawi.com` llega por OpenLiteSpeed al servicio Docker `web` en `127.0.0.1:8080`. `web` (nginx) reenvía PHP a `app` (Laravel/PHP-FPM 8.4). `db` es PostgreSQL 16 sin puerto publicado. `img.jakawi.com` llega por OpenLiteSpeed a `imgproxy` en `127.0.0.1:8082`. Los servicios del compose productivo son `web`, `app`, `db` e `imgproxy`.

No usar PHP ni Composer del host. Todo comando Laravel/Composer relevante se ejecuta dentro de la imagen PHP 8.4, por ejemplo:

```bash
docker compose exec app php artisan <command>
```

## Despliegue manual seguro

El despliegue es manual. Antes de actuar, revisar estado Git y la configuración aprobada. Para cambios que incluyan frontend, construir y recrear **`app` y `web` juntos**; no existe un despliegue seguro de sólo uno, porque ambos contienen partes del mismo build Vite. Luego verificar el estado de servicios y las rutas `/up` local y pública:

```bash
docker compose ps
curl -f http://127.0.0.1:8080/up
curl -f https://jakawi.com/up
```

Una migración normal, si ha sido aprobada como parte del release, se ejecuta sólo desde `app` con `--force`. Está prohibido `migrate:fresh` contra producción. No borrar volúmenes, no hacer `docker compose down -v` como operación normal y no regenerar `.env` durante un deploy.

Después de cambiar `.env`, recrear cada servicio que consume ese entorno y comprobar la configuración efectiva dentro del contenedor. Esto es especialmente importante para `app` e `imgproxy`; editar el archivo no modifica un contenedor ya creado.

## Pagos QR

El release actual debe conservar `QR_PAYMENT_ENABLED=false` y
`QR_PAYMENT_DRIVER=disabled`. `fake` sirve exclusivamente para pruebas y
desarrollo; la aplicación rechaza esa configuración en producción. No hay QR
visible, endpoint de pago ni webhook que operar aún. Véase [PAYMENTS.md](PAYMENTS.md).

## Pruebas aisladas

Todas las pruebas y comandos contra base de test pasan por:

```bash
./bin/jakawi-test
./bin/jakawi-test php artisan test
./bin/jakawi-test npm run types:check
```

El wrapper crea/usa `jakawi-test`, `app-test`, `db-test`, `jakawi_test` y el volumen `jakawi-test_jakawi_test_pgdata`; rechaza un entorno, host o base que no sean los esperados. Para detenerlo: `./bin/jakawi-test down`. `./bin/jakawi-test clean` borra únicamente el volumen de test aislado. No hay flujo preview operativo documentado para producción: `compose.preview.yaml` queda fuera de este runbook y no debe usarse como sustituto de tests ni de deploy.

## Verificación de media

Confirmar que `imgproxy` está en ejecución con `docker compose ps`, que `https://img.jakawi.com` se enruta por OpenLiteSpeed a 8082 y que una URL firmada de una key existente devuelve WebP. Validar además que el original no sea accesible públicamente desde MinIO. No usar el host API S3 detrás del proxy Cloudflare: `myminioback.kuruk.in` debe ser DNS-direct para preservar SigV4.

## Backups y rollback

El backup debe proteger la base PostgreSQL y, cuando aplique, el inventario de object keys/media; ejecutar únicamente el procedimiento de backup aprobado para el host, no uno inventado. Antes de un cambio riesgoso, confirmar una restauración conocida y el commit de retorno.

Un rollback conservador vuelve al último commit conocido bueno, reconstruye/recrea coherentemente los servicios afectados y repite los health checks. Evaluar las migraciones antes de revertir código: no borrar datos ni volúmenes para “hacer coincidir” una versión. Mantener `app` y `web` sincronizados también durante rollback.

## Production PostgreSQL backup — approved procedure

Antes de una migración o release, desde `/home/jakawi.com`, identificar el
servicio PostgreSQL real con `docker compose config --services`. Crear sin
borrar contenido previo `/home/jakawi.com/backups`, generar un timestamp y
usar `pg_dump` **dentro** de ese contenedor para la base `jakawi`:

```bash
STAMP=$(date +%Y%m%d-%H%M%S)
BACKUP="/home/jakawi.com/backups/jakawi-pre-attribution-v1-${STAMP}.dump"
docker compose exec -T "$DB_SERVICE" sh -lc \
  'pg_dump -U "$POSTGRES_USER" -d jakawi --format=custom --no-owner --no-acl' \
  > "$BACKUP"
```

Validar que el archivo no esté vacío, mostrar su tamaño y comprobar su formato
sin restaurarlo: `docker compose exec -T "$DB_SERVICE" pg_restore --list <
"$BACKUP" > /tmp/jakawi-backup-list.txt`. Registrar la ruta,
tamaño, resultado y, recomendado, `sha256sum`. Nunca usar `pg_dump` del host,
`migrate:fresh`, ni una restauración destructiva de prueba contra producción.
