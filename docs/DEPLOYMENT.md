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

### Readiness antes de los gates de cutover

HTTP `000` durante el arranque de contenedores es un estado transitorio de
«todavía no listo», no un criterio de rollback inmediato. Antes del cutover debe
pasar el guard del manifest de producción. Tras recrear coherentemente app, web
y worker, confirmar que siguen running y realizar una única ventana de polling
acotada a **120 segundos**, cada **2 segundos**. Los gates comienzan sólo cuando
app está healthy, web está running y `/up` local devuelve HTTP 200.

Después de readiness, comprobar `/up` público, las rutas nativas de Challenge,
Experience y Benefit y las landings requeridas. Reintentar una petición que
reciba `000` hasta tres veces, con dos segundos entre intentos; una excepción de
manifest o HTTP 500 en una ruta nativa requiere rollback coherente. Si se agota
la ventana de readiness, capturar estado, health, logs recientes de app/web/worker,
error de curl y listener local antes de rollback. No reiniciar repetidamente ni
reconstruir para resolver la espera de arranque.
