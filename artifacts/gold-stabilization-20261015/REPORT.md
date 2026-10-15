# JAKAWI — Gold stabilization

Fecha: 2026-10-15 (America/La_Paz).

## A. Resultado

**GOLD PASS — cierre de los 13 casos heredados completo.** La primera estabilización desplegó `9af13f8` → `95433f4`; Legacy Test Cleanup despliega `95433f4` → `89dcf27dc3fbb2eb55be42512fc2012b7658ceb6` para corregir dos defectos localizados de Campaign. Rollback exacto de app/web/worker disponible y no ejecutado; sin migraciones. [Release inicial](production-release.json), [release del cierre](legacy-test-cleanup-release.json) y [validación](validation.json).

## B. Seguridad

- Confirmado: POST de canje firmado sin límite de intentos, a diferencia del formulario manual. Se añade throttle 20/minuto manteniendo firma/auth/verified/Partner; la prueba aislada verifica 429 en el intento 21. No cambia la confirmación ni la economía.
- Dependencias afectadas corregidas: concurrently 10.0.5→10.0.6, shell-quote 1.9.0→1.12.0, source-map-js 1.2.1→1.2.2 y override puntual tinypool 2.1.0→2.1.2. Vite, vite-plus, Laravel y React conservan sus versiones; el override evita cambiar la integración de build. npm audit completo final: cero avisos; Composer audit locked/no-dev: cero avisos. No se acredita explotación remota de JAKAWI por paquetes de tooling. Fuentes: [shell-quote](https://github.com/advisories/GHSA-pqg4-j6r4-53mv), [source-map-js](https://github.com/advisories/GHSA-68fv-2mgg-jv7q), [tinypool](https://github.com/advisories/GHSA-5gmw-xhrv-c9v3), [tinypool run options](https://github.com/advisories/GHSA-85c8-ppgw-ccpr).
- Revisión dirigida: middleware Admin/Partner, pertenencia en Content Studio, User fillable, rutas mutables web/CSRF, guards de redemption/grant y Media Pipeline. Upload auth + throttle 30/minuto; MIME/tamaño/dimensiones, normalización WebP, tokens cifrados con propietario/vencimiento, escritura privada y reemplazo sin borrar el original preservados. No se subieron assets QA a producción.
- Configuración efectiva LIVE: debug OFF; sesión Secure/HttpOnly/SameSite=lax; PRELAUNCH ON; CRM ON; Meta provider/browser/CAPI OFF. Headers públicos comprobados: SAMEORIGIN, nosniff y strict-origin-when-cross-origin. Sin rotación ni exposición de secretos. No se confirmó P0 ni una nueva escalada de privilegios en las superficies revisadas; esto no es un pentest exhaustivo.

## C. Estabilidad

Admin Benefits consultaba pertenencia de Locations aun con estructura inválida: un string/array anidado podía producir 500. El after-validator respeta errores previos y devuelve 422 sin guardar; prueba con tres entradas inválidas y conservación del Benefit. Se preservan selección válida y restricción al Partner. No se retiró código muerto sin evidencia suficiente. Ninguna regresión funcional nueva detectada en los gates finales. El cierre de tests destapó dos defectos preexistentes en Campaign: la unicidad excluía un ID vacío en PostgreSQL y se intentaba persistir participant_types en campaigns. Se corrigieron sólo la regla de unicidad y la separación de datos del pivote, sin cambiar reglas comerciales.

## D. Performance

Discovery y el inventario del Partner resolvían Landings con una consulta por tarjeta. Se precarga la relación con seis columnas; ProductLandingResolver conserva PUBLISHED, alcance guest/auth, orden y fallback nativo. Medición aislada con ocho Benefits: **8 consultas de Landing antes → 1 después**, validada con query log PostgreSQL. Sin caché compartida, índices nuevos ni porcentajes de latencia inventados. La medición no incluye todos los tipos ni representa un benchmark productivo.

## E. Validación

- **Cierre Legacy: 13/13 casos resueltos; una única suite completa final, 701 PASS / 0 FAIL y 10.517 aserciones.** Ningún test eliminado, omitido o desactivado. TypeScript/build/manifest y smoke candidato/LIVE PASS para el fix de Campaign; detalle del cierre en J.
- Histórico de la primera pasada, suite integrada aislada inicial: 687 PASS, 14 FAIL, 10.165 aserciones. Una fixture nueva inválida se corrigió; 12 fallos se reprodujeron sobre el SHA LIVE anterior (77 tests: 65 PASS/12 FAIL). El restante es una aserción heredada que exige IDs diferentes entre tablas Benefit/Experience, dependiente de las secuencias; no es una identidad de dominio válida.
- Primera pasada, validación focalizada: **45 PASS, 0 FAIL, 690 aserciones**, incluyendo nuevas regresiones, Discovery, autorización Partner, adquisición, Media y formularios Admin. En esa pasada no se repitió la suite completa tras corregir la fixture; el cierre Legacy sí la ejecutó y pasó.
- TypeScript PASS; compilación frontend final PASS; manifest PASS para 109 páginas; PHP lint y diff check PASS. Un intento previo falló por incompatibilidad del parche de vite-plus y fue retirado; una sola compilación frontend completada. App/web finales reutilizan ese build y contienen assets idénticos.
- Candidato y LIVE: 16 rutas bajo transacciones READ ONLY y 32 vistas de navegador cada uno, a 390/1440 px; sin page errors. LIVE también controla fallos de carga JS/CSS. Incluye `/dossis43`, `/elrepollo`, `/armagedon-for-gamers`, Explorer, fichas nativas Benefit/Experience, Landing aplicable y crear/editar Admin de Benefits/Experiences/Partners/Locations. Sin Challenge público actual para añadir un gate nativo real.
- `/up` real local/público 200; raíz HTTP Coming Soon 200 con assets compilados. PrelaunchNotice único en público y ausente en root/Admin; robots root index/follow, público noindex/nofollow. Los renders de catálogo/Admin usan READ ONLY para evitar registros de prueba y atribución/analytics de QA.
- Snapshots antes/después idénticos para 14 tablas de catálogo, Landings, usuarios, ownership, membresías, canjes y rewards. Ningún registro, compra, canje, upload o usuario QA productivo. Backup custom PostgreSQL validado con pg_restore --list.

## F. Documentación

Actualizados [CURRENT](../../docs/CURRENT.md), [ARCHITECTURE](../../docs/ARCHITECTURE.md), [OPERATIONS](../../docs/OPERATIONS.md) y [LANDING_FIRST_ROUTING](../../docs/marketing/LANDING_FIRST_ROUTING.md): runtime real, resolución por lote, controles, validación frontend Docker y rollback. Checker documental: 74 documentos, 476 targets, cero rotos. Contratos de producto/media/acquisition/economía conservados.

## G. Git

Commits funcionales: `8468c57`, `95433f4` y cierre Legacy `89dcf27`; documentación/evidencia compacta en el commit de cierre de esta pasada. Rama de trabajo `release/gold-stabilization-20261015`; `main` integrada por fast-forward y sincronizada con origin/main al cierre. El commit documental posterior no modifica el runtime funcional `89dcf27`. El cierre Legacy se trabaja directamente en main, sobre el historial ya integrado.

Los 17 archivos de trabajo preexistente coincidían byte a byte con LIVE `9af13f8`: se conservaron adicionalmente en el stash `gold-preserve-preexisting-live-9af13f8`, y sus commits existentes se integraron sin reescritura. Ese trabajo ya está en el historial; no hay que aplicar el stash. Artefactos históricos untracked preservados y excluidos del release. Backups, logs, HTML autenticado y material operativo privado no se publican. El remoto sólo configura QA documental, sin deploy automático adicional.

## H. Pendientes reales (priorizados)

1. **RESUELTOS:** los 12 fallos heredados y la aserción inestable de ExploreDiscoveryTest. Campaign incluía dos bugs reales además de la expectativa de autorización obsoleta. La matriz de los 13 métodos y sus contratos está en [validación](validation.json), sección legacy_test_cleanup.cases.
2. Fuera del alcance del cierre Legacy y sin cambios: las queries por Partner en métricas Admin y por Benefit en disponibilidad del perfil público registradas en la primera pasada. No bloquean la suite y no se reauditaron ni optimizaron aquí.

## I. Producción

App healthy, web/worker running; DB healthy e imgproxy running. Sólo app/web/social-worker recreados. PRELAUNCH=true, PrelaunchNotice intacto, CRM=true, Meta=OFF. Sin nuevas regresiones conocidas en smoke; suite heredada cerrada con 701 tests PASS. Sin cambios de precios, condiciones, comisiones, ownership, adquisición, datos editoriales o infraestructura; v2proof no utilizado.

Rollback actual a `95433f4`: imágenes anteriores exactas protegidas por tags y `backups/gold-stabilization-20261015/legacy-test-cleanup/rollback.yaml`; comando en [OPERATIONS](../../docs/OPERATIONS.md). Backup privado validado en la misma carpeta. No requiere restaurar DB para este release sin migraciones. Material operativo preservado para revisión/recuperación.

## J. Cierre Legacy Test Cleanup

13/13 casos corregidos en diez archivos de tests, sin reducir el número de métodos. Fixtures Media decodificables y disco fake correcto; canonical/301 de Partner; draft/hidden privados; Unlock/VSL requiere meta más participante elegible, sin bypass Admin; CTA actual de membresía; identidad Explorer por tipo+ID. Se mantiene la cobertura negativa de acceso, privacidad, uploads inválidos y canjes no permitidos.

Campaign recibió el único cambio de runtime (seis líneas del controlador): regla Unique con ignore del modelo sólo cuando existe y exclusión de participant_types de la fila Campaign; el pivote conserva su persistencia validada. Tests PostgreSQL cubren permisos, crear, duplicado, editar con el mismo código y rechazar el código de otra campaña. No cambian recompensas, comisiones, fechas comerciales ni atribución.

Tests afectados ejecutados durante la corrección; después una sola suite completa: 701 PASS, 10.517 aserciones. Build único por el cambio real de runtime, TypeScript PASS y manifest 109 páginas. Candidato/LIVE: 17 rutas READ ONLY (incluido Admin Campaigns) y 34 vistas de navegador por entorno, sin errores; snapshots de 16 tablas idénticos, incluido Campaign/pivote. `/up` local/público 200, Coming Soon y PrelaunchNotice preservados. Sin datos QA ni migraciones productivas; rollback no ejecutado. No se repitieron las auditorías GOLD ni se abordó otro trabajo. Código `89dcf27`, main sincronizada con origin/main al cierre.
