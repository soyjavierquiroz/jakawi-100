# Integración de Jakawi Video Player

El documento canónico de reutilización es [JAKAWI_VIDEO_PLAYER.md](JAKAWI_VIDEO_PLAYER.md): API, defaults, ejemplos TSX/JSON, proveedores, encuadres, temas, cursos, shortcodes y operación. Esta nota conserva la evidencia de la etapa de integración.

## Alcance implementado

Player nativo y wrappers `LandingVideo`/`CourseVideo`; configuración JSON nullable `landing_presentations.vsl`, migración `2026_10_11_000001_add_vsl_to_landing_presentations`. Las cuatro Marketing Landings usan el visual existente y conservan CTA/Growth. Unlock omite toda configuración antes de `revealable()`. No hay página de lecciones ni parser conectado a campos de texto.

Bunny HLS utiliza `type: 'bunny'`; Bunny MP4 directo utiliza `type: 'mp4'`. Esta precisión sustituye la descripción anterior que agrupaba ambos bajo el tipo Bunny.

## Evidencia histórica de integración

El informe previo registró 61 tests dirigidos y 17 feature tests de landings/configuración/permisos (540 assertions) en `jakawi-vsl-test`, PostgreSQL dedicado, con guard y PHPUnit directo. También registró TypeScript/build en herramientas aisladas. El wrapper Artisan advertía configuración duplicada. Estos resultados no se volvieron a ejecutar como feature tests en el cierre documental.

La afirmación anterior «No se aplicó la migración a preview ni producción; no hubo deploy ni push» describía esa etapa. No describe el runtime principal actual: el 11 de octubre de 2026 se observó la migración VSL aplicada en el contenedor principal. Commit, ramas, alcance de esa comprobación y validación nueva están en el [estado de entrega canónico](JAKAWI_VIDEO_PLAYER.md#estado-de-entrega-y-evidencia).
