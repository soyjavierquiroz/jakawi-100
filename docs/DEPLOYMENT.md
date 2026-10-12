# Política de despliegue vigente

**CURRENT — referencia de política, no autorización de deploy.** El procedimiento canónico de ejecución/backups/rollback está en [OPERATIONS.md](OPERATIONS.md); estado en [CURRENT.md](CURRENT.md).

Producción directa en https://jakawi.com, salud `/up`; sin staging/preview operativo. PHP del host nunca se usa ni inspecciona; comandos Laravel/Composer sólo Docker PHP 8.4.

Frontend requiere rebuild/recreación coherente `app` + `web`. `social-worker` comparte imagen `jakawi-app`: recrearlo cuando los cambios de esa imagen le afectan. Antes de release, backup custom PostgreSQL en `/home/jakawi.com/backups`, no owner/no ACL y validación pg_restore --list. Nunca migrate:fresh/db:wipe/seed/reset producción.

Revisar disco (`df -h /`), Git, servicios y rollback antes de ejecutar procedimiento autorizado. No borrar containerd, imágenes activas ni general-prune indiscriminado. Detalle de imagen temporal de verificación y limpieza explícita en operaciones.

El procedimiento anterior permanece en Git como histórico; este documento enlaza el runbook vigente para evitar instrucciones operativas duplicadas.

### Guard del manifest de frontend

Antes del cutover, el manifest de producción debe pasar `npm run check:manifest`
(desde `app/`). `npm run build` ejecuta este guard automáticamente y detiene el
build Docker si falta una entrada de página Inertia, un asset o una dependencia.
Las entradas se derivan de los mismos directorios y extensiones del resolver
React (`resources/js/{pages,Pages}/**/*.{tsx,jsx}`); se conservan como entradas
explícitas aunque otra página las importe estáticamente.
