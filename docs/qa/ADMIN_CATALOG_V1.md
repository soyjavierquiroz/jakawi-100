# Catálogo administrativo JAKAWI — 404 y UX

Fecha: 14 de octubre de 2026 (America/La_Paz). Proyecto `/home/jakawi.com`.

## Corrección del 404

**Causa raíz verificada:** el enlace de Experiences del listado compartido construía `/admin/experiences/1/edit` con el ID. El modelo `Experience::getRouteKeyName()` utiliza `slug`; Laravel buscaba el slug `1` y devolvía 404 durante el binding, antes de ejecutar el formulario. No había un scope draft ni un rechazo de permisos causando ese fallo. Published tenía el mismo defecto de enlace. Benefits ya utilizaba slug.

La ruta efectiva de Experience ID 1 es:

`/admin/experiences/toctoccafe-descubre-el-cafe-boliviano/edit`

El catálogo construye enlaces mediante las rutas nombradas de Laravel. El listado compartido heredado también reconoce el slug de Experiences. Se preservan `auth`, verificación y `EnsureAdmin`; usuarios no administradores reciben 403 y visitantes van al login. No se cambió el binding global ni se añadió una ruta numérica alternativa.

El formulario conserva las asignaciones Partner/rol del pivote y las Locations seleccionadas de Benefits. Para editar envía POST con `_method=put`; usa JSON sin forzar multipart y deja a Inertia detectar archivos automáticamente. Esto evita tanto el problema de PUT multipart en PHP como cambios de saltos de línea en textos comerciales.

**Prueba real:** sesión autenticada del Admin existente, Chromium, listado → Editar en «Descubre el café boliviano» → formulario válido → Guardar → listado. Guardado idempotente con los valores comerciales actuales. La persistencia de modificaciones válidas se verificó con datos aislados, incluyendo ID 1 y texto multilínea. No se publicó ni duplicó el producto.

Se verificó además un comportamiento público anterior que mostraba los drafts como fichas «no disponibles». Ahora draft devuelve 404 en la ruta nativa y no puede escapar por una landing publicada desincronizada. El preview Admin sigue disponible. Se conserva el comportamiento previo de paused y las reglas de disponibilidad, membresía y reserva.

## UX administrativa

Benefits, Experiences y Challenges utilizan `AdminCatalog` y `admin/catalog/index.tsx`:

- Desktop: filas alineadas con Producto, Partner, Valor/Precio, Estado, Actualizado y Acciones.
- Mobile: tarjetas sin tabla horizontal, imagen pequeña con fallback, nombres, estados legibles y acciones de al menos 44 px.
- Partners reales desde relaciones, sin duplicar nombres; deduplicación visual de Partners con varios roles. «Sin Partner asignado» cuando corresponde.
- Experiences: precio regular y de miembros sólo si existen, respetando moneda. Benefits: tipo declarado y ahorro estimado cuando existe; el modelo no define un precio de venta. Challenges: JP, premio manual o beneficio según su recompensa real.
- Vigencia disponible, fecha de actualización en America/La_Paz, estado de landing y revisión de Challenges.
- Editar, preview existente, enlace al Partner y página pública sólo si pasa las comprobaciones de estado/disponibilidad aplicables. Pre-lanzamiento señalado explícitamente; Published no se presenta como disponibilidad comercial automática.
- Búsqueda literal por título o nombre de Partner, filtros por estado y Partner, actualización descendente con desempate por ID, paginación de 20 registros, estados vacío/sin coincidencias y contexto de filtros al editar/volver/guardar. Cola de Challenges pendientes de revisión conservada.
- Carga anticipada de Partners y landings, sin consultas por cada fila. Sin migraciones ni framework administrativo paralelo.

## Archivos principales

- `app/app/Services/AdminCatalog.php`: consultas, filtros, paginación, datos comerciales y acciones.
- `app/app/Http/Controllers/AdminController.php` y `AdminChallengeController.php`: listados compartidos y regreso con contexto.
- `app/resources/js/pages/admin/catalog/index.tsx`: interfaz responsive compartida.
- `app/resources/js/pages/admin/resources/index.tsx`, `resources/form.tsx` y `social-challenges/form.tsx`: enlaces, guardado y preservación de relaciones/contexto.
- `PublicController.php`, `LandingPresentationController.php`, `ResolveProductLanding.php`: privacidad pública del draft.
- `app/tests/Feature/AdminCatalogTest.php` y pruebas públicas existentes: regresión del binding, autorización, persistencia, previews, filtros, estados y nueva regla pública de drafts.

## Calidad

| Validación | Resultado |
| --- | --- |
| Suite Laravel completa del cambio principal | 662 PASS, 10 fallos preexistentes, 9857 assertions |
| Reproducción del baseline `c37f3ef` en las suites que fallaban | Los mismos 10 fallos; 51 PASS |
| Suite relacionada tras corregir transporte del formulario | 123 PASS, 2472 assertions |
| Pruebas específicas finales del catálogo | 4 PASS, 198 assertions |
| TypeScript y Wayfinder dentro de imagen PHP 8.4 limpia | PASS |
| Build app/web y manifest de 105 páginas Inertia | PASS |
| Lint de catálogo y formulario modificados | PASS |
| Lint global | 114 advertencias idénticas al baseline, normalizando números de línea |
| Formato global | Mismos 157 archivos pendientes que el baseline |
| Guards de imágenes y HTML contra manifest | PASS, sin hot/dev assets y app/web coherentes |
| Chromium candidato y LIVE | 375/390/1440; listados con datos y vacíos, búsqueda, Editar/Volver, previews; fixtures de estados e imágenes |

Los 10 fallos preexistentes pertenecen a AdminV2 (2), ApplicationV2Http, Campaign, DemoCatalogV2, LandingPresentation, PublicV2Http, UnlockCore y UnlockMarketingLanding (2). Incluyen expectativas antiguas sobre redirecciones/permisos, validación de Partners y revelación/copy de Unlocks. La evidencia conserva el detalle; no se desactivaron controles ni se ampliaron cambios para corregir esas suites ajenas.

## Incidente detectado y resuelto durante QA LIVE

El primer corte `59acfde` abrió y guardó correctamente ID 1, pero la comparación de datos detectó que el multipart forzado había normalizado LF a CRLF en `description` y `terms`, actualizando su timestamp. El gate activó rollback de app/web/worker a las imágenes originales. Se restauraron únicamente esos dos textos y `updated_at` desde el backup previo validado, comprobando que la diferencia era exclusivamente de saltos de línea. **Todos los hashes originales volvieron a coincidir** antes de continuar.

El commit `4d5cdac` elimina el multipart forzado y añade cobertura de textos multilínea. La validación final exige `application/json`, igualdad exacta de los textos enviados, regreso al listado y coincidencia de todos los hashes tras el guardado. Se conserva evidencia del primer intento y su restauración.

## Publicación y preservación

- Rama inicial: `main`, HEAD `c37f3ef1d3b11f331d4c667ee84a4c7015d70f31`.
- Runtime anterior app/web: `baead48dc695215eb5eb4384c69e96f281fce03a`.
- Commits de implementación: `59acfdebde14519e0225273f56dc7b2e7f0ec4e7` y `4d5cdac923c066d338fa310748e99d563998f104`.
- **Deploy final PASS. LIVE app/web/social-worker: `4d5cdac923c066d338fa310748e99d563998f104`**, imágenes verificadas por labels. Los commits de implementación están sincronizados con `origin/main`; el commit posterior de este informe sólo añade documentación y evidencia.
- Build desde exportación Git limpia. Corte coordinado app/web/worker. `/up` local y público 200; Partners públicos existentes 200; URL nativa de Experience draft y landing DRAFT 404. Tres previews Admin 200.
- Rollback disponible en `backups/admin-catalog-4d5cdac/rollback.yaml`, con imágenes anteriores protegidas mediante tags. Comando exacto en el artefacto `rollback-command.txt`. Se probó el rollback durante el primer intento. No requiere restaurar toda la base ni ejecutar migraciones.
- Backup custom PostgreSQL validado mediante `pg_restore --list`, con tamaño y SHA-256 registrados; permisos 0600. El backup no se incorpora a Git.
- **Preservación final:** 1 Experience, 2 Benefits, 0 Challenges, 5 Landings, 3 Partners y sus relaciones/sesiones conservan exactamente los hashes previos. ID 1 sigue draft, Bs 75; su landing sigue DRAFT. Sin duplicados, cambios comerciales ni cambios de membresía/reservas.
- `PRELAUNCH_MODE=true`, `.env`, `PrelaunchNotice`, imágenes y código del carrusel conservados. Chromium comprobó avance del carrusel móvil con teclado. Ningún contenedor ajeno al release cambió; 899 archivos de artefactos anteriores conservaron sus hashes, incluidas las cuatro carpetas inicialmente untracked.
- La sesión temporal de QA del Admin se retiró tras las pruebas; no se creó ni modificó ninguna cuenta de usuario.

## Evidencia

- [Resultado de despliegue](../../artifacts/admin-catalog-v1/deploy.json), [Chromium LIVE](../../artifacts/admin-catalog-v1/live-browser.json), [calidad](../../artifacts/admin-catalog-v1/quality.json).
- [404 original](../../artifacts/admin-catalog-v1/original-404.json), [primer rollback](../../artifacts/admin-catalog-v1/deploy-first-attempt.json), [restauración](../../artifacts/admin-catalog-v1/text-restoration.json).
- [Datos anteriores](../../artifacts/admin-catalog-v1/data-before.json) y [posteriores](../../artifacts/admin-catalog-v1/data-after.json), [backup validado](../../artifacts/admin-catalog-v1/backup.json).
- [Experiencias desktop](../../artifacts/admin-catalog-v1/live-experiences-1440.png), [mobile 375](../../artifacts/admin-catalog-v1/live-experiences-375.png), [mobile 390](../../artifacts/admin-catalog-v1/live-experiences-390.png), [formulario ID 1](../../artifacts/admin-catalog-v1/live-experience-1-edit.png).
- [Benefits desktop](../../artifacts/admin-catalog-v1/live-benefits-1440.png), [mobile](../../artifacts/admin-catalog-v1/live-benefits-390.png), [Challenges vacío](../../artifacts/admin-catalog-v1/live-retos-390.png), [sin coincidencias](../../artifacts/admin-catalog-v1/live-no-results.png).
- [Fixtures de estados, Partner ausente e imagen fallida](../../artifacts/admin-catalog-v1/fixtures-states-390.png). Estas fixtures sólo se inyectaron en el servidor local de QA; no son registros de producción.
