# Política de despliegue vigente

**CURRENT — referencia de política, no autorización de deploy.** El procedimiento canónico de ejecución/backups/rollback está en [OPERATIONS.md](OPERATIONS.md); estado en [CURRENT.md](CURRENT.md).

Producción directa en https://jakawi.com, salud `/up`; sin staging/preview operativo. PHP del host nunca se usa ni inspecciona; comandos Laravel/Composer sólo Docker PHP 8.4.

Frontend requiere rebuild/recreación coherente `app` + `web`. `social-worker` comparte imagen `jakawi-app`: recrearlo cuando los cambios de esa imagen le afectan. Antes de release, backup custom PostgreSQL en `/home/jakawi.com/backups`, no owner/no ACL y validación pg_restore --list. Nunca migrate:fresh/db:wipe/seed/reset producción.

Revisar disco (`df -h /`), Git, servicios y rollback antes de ejecutar procedimiento autorizado. No borrar containerd, imágenes activas ni general-prune indiscriminado. Detalle de imagen temporal de verificación y limpieza explícita en operaciones.

El procedimiento anterior permanece en Git como histórico; este documento enlaza el runbook vigente para evitar instrucciones operativas duplicadas.
