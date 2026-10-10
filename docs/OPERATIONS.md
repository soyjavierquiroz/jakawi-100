# Operaciones

## Partner payouts

Admin → Pagos mantiene mínimos BOB independientes: `affiliate_minimum_payout` y `partner_minimum_payout`. Owner/manager solicita el total elegible de su Partner; Admin registra el pago manual con referencia externa o rechaza con motivo. Los pagos PAID son historia financiera y se corrigen sólo con ajustes operativos auditables.

Este runbook describe el stack actual. No autoriza despliegues, cambios de datos ni cambios de infraestructura por sí mismo.

## Producción

`jakawi.com` llega por OpenLiteSpeed al servicio Docker `web` en `127.0.0.1:8080`. `web` (nginx) reenvía PHP a `app` (Laravel/PHP-FPM 8.4). `db` es PostgreSQL 16 sin puerto publicado. `img.jakawi.com` llega por OpenLiteSpeed a `imgproxy` en `127.0.0.1:8082`. Los servicios del compose productivo son `web`, `app`, `social-worker`, `db` e `imgproxy`.

**PHP DEL HOST: NUNCA USAR NI INSPECCIONAR.** No usar Composer del host. Todo comando Laravel/Composer relevante se ejecuta dentro de la imagen PHP 8.4, por ejemplo:

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

Una migración normal, si ha sido aprobada como parte del release, se ejecuta sólo desde `app` con `--force`. Está prohibido `migrate:fresh`, `db:wipe`, seed/reset contra producción. No borrar volúmenes, no hacer `docker compose down -v` como operación normal y no regenerar `.env` durante un deploy.

Después de cambiar `.env`, recrear cada servicio que consume ese entorno y comprobar la configuración efectiva dentro del contenedor. Esto es especialmente importante para `app` e `imgproxy`; editar el archivo no modifica un contenedor ya creado.

## Laravel Scheduler

Producción ejecuta el scheduler mediante cron del host cada minuto:
`/home/jakawi.com/bin/jakawi-schedule` → Docker `app` (PHP 8.4) → `php artisan schedule:run`.
El wrapper usa un bloqueo no bloqueante en `/tmp/jakawi-scheduler.lock`; las
ejecuciones solapadas salen sin acumularse. Cron registra salida en
`/home/jakawi.com/storage/logs/scheduler-cron.log`.

Para verificarlo, revisar ese log tras un minuto y ejecutar manualmente:

```bash
/home/jakawi.com/bin/jakawi-schedule
```

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

El backup debe proteger la base PostgreSQL y, cuando aplique, el inventario de object keys/media; usar el procedimiento aprobado más abajo. Antes de un cambio riesgoso, confirmar una restauración conocida y el commit de retorno.

Un rollback conservador vuelve al último commit conocido bueno, reconstruye/recrea coherentemente los servicios afectados y repite los health checks. Evaluar las migraciones antes de revertir código: no borrar datos ni volúmenes para “hacer coincidir” una versión. Mantener `app` y `web` sincronizados también durante rollback.

## Production PostgreSQL backup — approved procedure

Antes de una migración o release, desde `/home/jakawi.com`, identificar el
servicio PostgreSQL real con `docker compose config --services`. Crear sin
borrar contenido previo `/home/jakawi.com/backups`, generar un timestamp y
usar `pg_dump` **dentro** de ese contenedor para la base `jakawi`:

```bash
STAMP=$(date +%Y%m%d-%H%M%S)
BACKUP="/home/jakawi.com/backups/jakawi-pre-release-${STAMP}.dump"
docker compose exec -T "$DB_SERVICE" sh -lc \
  'pg_dump -U "$POSTGRES_USER" -d jakawi --format=custom --no-owner --no-acl' \
  > "$BACKUP"
```

Validar que el archivo no esté vacío, mostrar su tamaño y comprobar su formato
sin restaurarlo: `docker compose exec -T "$DB_SERVICE" pg_restore --list <
"$BACKUP" > /tmp/jakawi-backup-list.txt`. Registrar la ruta,
tamaño, resultado y, recomendado, `sha256sum`. Nunca usar `pg_dump` del host,
`migrate:fresh`, ni una restauración destructiva de prueba contra producción.

## Ajustes administrativos y CSV V1

Los ajustes operativos son registros inmutables en `operational_adjustments`:
requieren motivo, actor, antes/después y hora. CASH y JP son unidades separadas
en el ledger; nunca se actualiza un campo de saldo de usuario. Un débito no puede
dejar saldo de ledger negativo. Los cambios de recompensa sólo permiten
`pending → available` y `available → cancelled`; `paid` nunca se reescribe y
requiere un ajuste financiero posterior. Las correcciones de atribución invalidan
la relación activa anterior y crean una nueva sin borrar AttributionTouch.

Admin puede descargar CSV de afiliados, conversiones, recompensas, payouts y
atribución. Los exports no incluyen email, teléfono, IP ni user-agent; son UTF-8,
usan escaping CSV estándar y anteponen `'` a valores que empiezan por `=`, `+`,
`-` o `@` para evitar fórmulas de hoja de cálculo. Cada descarga deja una auditoría
liviana con actor, tipo y filtros, sin guardar el archivo.

## Identificación mínima en validación Partner

La pantalla Partner de revisión de asistencia entrega únicamente el código de
asistencia, estado, contexto de la reserva y una identidad abreviada generada en
servidor: primer nombre más inicial del último apellido (por ejemplo, `Javier Q.`).
No entrega nombre completo, email, teléfono, perfil, historial de membresía,
referidos ni datos financieros. El código de asistencia es el identificador
operativo primario; la identidad abreviada sólo sirve para desambiguación humana.

## Desarrollo y verificación de frontend

La raíz comparte el host productivo: no ejecutar bootstrap/reset como si fuera una base local vacía. `./bin/jakawi-test` es el wrapper aislado para tests; nunca PHP del host. Baseline conocido: CampaignTest::admin_can_create_campaign_and_non_admin_cannot (nombre PHPUnit con prefijo test_), redirect esperado frente a 403 recibido; no clasificar como regresión nueva.

Build de verificación, sólo cuando autorizado:

```bash
docker build -f docker/Dockerfile \
  --target build \
  -t jakawi-frontend-check .
```

`jakawi-frontend-check` es imagen temporal, no despliegue. Esta consolidación documental no ejecuta tests ni builds. Producción es directa; no existe staging/preview operativo. El archivo preview heredado no constituye un entorno aprobado.

`social-worker` usa la misma imagen `jakawi-app`, consume social-interactive/social y debe acompañar cambios de imagen que le afecten; frontend exige `app` + `web` coherentes. No crear otro worker para CRM/Meta: `crm:dispatch` y `growth:dispatch-meta` ya corren cada minuto en Laravel Scheduler. CRM sync activo; Meta OFF. Runbook WordPress independiente: [CRM](CRM_FOUNDATION_V1.md).

## Disco y Docker

Los builds consumen disco significativo. Antes de un build, revisar `df -h /` y artefactos Docker mediante consultas de lectura. La cifra libre del día no es una especificación de arquitectura.

No borrar manualmente containerd, no hacer general-prune a ciegas ni eliminar imágenes activas. Para retirar una imagen temporal, identificar ID exacto y comprobar que **cero contenedores (incluidos detenidos)** la usan; sólo entonces eliminar ese artefacto explícito. No retirar cachés/volúmenes/imágenes por su nombre aproximado. No usar `docker compose down -v` en producción.

## Seguridad de base y documentación

Backups en `/home/jakawi.com/backups`: custom pg_dump dentro de PostgreSQL, `--no-owner --no-acl`; validar `pg_restore --list` sin restaurar sobre producción. Definir DB_SERVICE con el servicio real antes del ejemplo de backup. Jamás migrate:fresh, db:wipe, seed/reset producción.

Cambios sólo documentales: `git diff --check`, revisión de status/stat y enlaces relativos sin instalar dependencias; no requieren tests/build/deploy. No copiar .env, tokens, HMAC, contraseñas, salts o PII QA. Para oferta usar el [Manual de Inventario](operations/MANUAL_DE_INVENTARIO.md); para comportamiento el [Manual de Funciones](product/MANUAL_DE_FUNCIONES.md).
