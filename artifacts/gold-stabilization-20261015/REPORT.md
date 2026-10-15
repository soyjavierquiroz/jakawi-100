# JAKAWI — Gold stabilization

Fecha: 2026-10-15 (America/La_Paz).

## A. Resultado

**PARTIAL — correcciones verificadas y desplegadas; suite heredada pendiente.** LIVE anterior `9af13f88adea1fc416a416f36c99e4b284703042`; LIVE nuevo `95433f4a56675d46c858b8ebf002e1762130a57d`. Rollback exacto de app/web/worker disponible y no ejecutado. Una salida productiva, sin migraciones. [Release](production-release.json) y [validación](validation.json).

## B. Seguridad

- Confirmado: POST de canje firmado sin límite de intentos, a diferencia del formulario manual. Se añade throttle 20/minuto manteniendo firma/auth/verified/Partner; la prueba aislada verifica 429 en el intento 21. No cambia la confirmación ni la economía.
- Dependencias afectadas corregidas: concurrently 10.0.5→10.0.6, shell-quote 1.9.0→1.12.0, source-map-js 1.2.1→1.2.2 y override puntual tinypool 2.1.0→2.1.2. Vite, vite-plus, Laravel y React conservan sus versiones; el override evita cambiar la integración de build. npm audit completo final: cero avisos; Composer audit locked/no-dev: cero avisos. No se acredita explotación remota de JAKAWI por paquetes de tooling. Fuentes: [shell-quote](https://github.com/advisories/GHSA-pqg4-j6r4-53mv), [source-map-js](https://github.com/advisories/GHSA-68fv-2mgg-jv7q), [tinypool](https://github.com/advisories/GHSA-5gmw-xhrv-c9v3), [tinypool run options](https://github.com/advisories/GHSA-85c8-ppgw-ccpr).
- Revisión dirigida: middleware Admin/Partner, pertenencia en Content Studio, User fillable, rutas mutables web/CSRF, guards de redemption/grant y Media Pipeline. Upload auth + throttle 30/minuto; MIME/tamaño/dimensiones, normalización WebP, tokens cifrados con propietario/vencimiento, escritura privada y reemplazo sin borrar el original preservados. No se subieron assets QA a producción.
- Configuración efectiva LIVE: debug OFF; sesión Secure/HttpOnly/SameSite=lax; PRELAUNCH ON; CRM ON; Meta provider/browser/CAPI OFF. Headers públicos comprobados: SAMEORIGIN, nosniff y strict-origin-when-cross-origin. Sin rotación ni exposición de secretos. No se confirmó P0 ni una nueva escalada de privilegios en las superficies revisadas; esto no es un pentest exhaustivo.

## C. Estabilidad

Admin Benefits consultaba pertenencia de Locations aun con estructura inválida: un string/array anidado podía producir 500. El after-validator respeta errores previos y devuelve 422 sin guardar; prueba con tres entradas inválidas y conservación del Benefit. Se preservan selección válida y restricción al Partner. No se retiró código muerto sin evidencia suficiente. Ninguna regresión funcional nueva detectada en los gates finales.

## D. Performance

Discovery y el inventario del Partner resolvían Landings con una consulta por tarjeta. Se precarga la relación con seis columnas; ProductLandingResolver conserva PUBLISHED, alcance guest/auth, orden y fallback nativo. Medición aislada con ocho Benefits: **8 consultas de Landing antes → 1 después**, validada con query log PostgreSQL. Sin caché compartida, índices nuevos ni porcentajes de latencia inventados. La medición no incluye todos los tipos ni representa un benchmark productivo.

## E. Validación

- Suite integrada aislada inicial: 687 PASS, 14 FAIL, 10.165 aserciones. Una fixture nueva inválida se corrigió; 12 fallos se reprodujeron sobre el SHA LIVE anterior (77 tests: 65 PASS/12 FAIL). El restante es una aserción heredada que exige IDs diferentes entre tablas Benefit/Experience, dependiente de las secuencias; no es una identidad de dominio válida.
- Validación final focalizada: **45 PASS, 0 FAIL, 690 aserciones**, incluyendo nuevas regresiones, Discovery, autorización Partner, adquisición, Media y formularios Admin. No se repitió la suite completa tras corregir la fixture.
- TypeScript PASS; compilación frontend final PASS; manifest PASS para 109 páginas; PHP lint y diff check PASS. Un intento previo falló por incompatibilidad del parche de vite-plus y fue retirado; una sola compilación frontend completada. App/web finales reutilizan ese build y contienen assets idénticos.
- Candidato y LIVE: 16 rutas bajo transacciones READ ONLY y 32 vistas de navegador cada uno, a 390/1440 px; sin page errors. LIVE también controla fallos de carga JS/CSS. Incluye `/dossis43`, `/elrepollo`, `/armagedon-for-gamers`, Explorer, fichas nativas Benefit/Experience, Landing aplicable y crear/editar Admin de Benefits/Experiences/Partners/Locations. Sin Challenge público actual para añadir un gate nativo real.
- `/up` real local/público 200; raíz HTTP Coming Soon 200 con assets compilados. PrelaunchNotice único en público y ausente en root/Admin; robots root index/follow, público noindex/nofollow. Los renders de catálogo/Admin usan READ ONLY para evitar registros de prueba y atribución/analytics de QA.
- Snapshots antes/después idénticos para 14 tablas de catálogo, Landings, usuarios, ownership, membresías, canjes y rewards. Ningún registro, compra, canje, upload o usuario QA productivo. Backup custom PostgreSQL validado con pg_restore --list.

## F. Documentación

Actualizados [CURRENT](../../docs/CURRENT.md), [ARCHITECTURE](../../docs/ARCHITECTURE.md), [OPERATIONS](../../docs/OPERATIONS.md) y [LANDING_FIRST_ROUTING](../../docs/marketing/LANDING_FIRST_ROUTING.md): runtime real, resolución por lote, controles, validación frontend Docker y rollback. Checker documental: 74 documentos, 476 targets, cero rotos. Contratos de producto/media/acquisition/economía conservados.

## G. Git

Commits funcionales: `8468c57` y `95433f4`; documentación/evidencia compacta en el commit de cierre de esta pasada. Rama de trabajo `release/gold-stabilization-20261015`; `main` integrada por fast-forward y sincronizada con origin/main al cierre. El commit documental posterior no modifica el runtime funcional `95433f4`.

Los 17 archivos de trabajo preexistente coincidían byte a byte con LIVE `9af13f8`: se conservaron adicionalmente en el stash `gold-preserve-preexisting-live-9af13f8`, y sus commits existentes se integraron sin reescritura. Ese trabajo ya está en el historial; no hay que aplicar el stash. Artefactos históricos untracked preservados y excluidos del release. Backups, logs, HTML autenticado y material operativo privado no se publican. El remoto sólo configura QA documental, sin deploy automático adicional.

## H. Pendientes reales (priorizados)

1. P2: reconciliar los 12 fallos de contrato heredados reproducidos: AdminV2 (2), ApplicationV2Http (1), BenefitRedemptionContinuity (1), Campaign (1), DemoCatalogV2 (1), LandingPresentation/VSL (1), PublicV2Http (2), UnlockCore (1), UnlockMarketingLanding (2). No se cambió producto sano para satisfacer expectativas antiguas; requieren revisar contratos/fixtures por módulo.
2. P2: corregir la aserción de IDs entre tablas en ExploreDiscoveryTest; puede fallar según las secuencias aunque los filtros y tipos sean correctos.
3. P2: siguen queries por Partner en métricas Admin (cuatro agregados) y por Benefit en disponibilidad del perfil público. Requieren una agregación dedicada que preserve semántica; no se introdujo en esta pasada acotada ni se les atribuye mejora.

## I. Producción

App healthy, web/worker running; DB healthy e imgproxy running. Sólo app/web/social-worker recreados. PRELAUNCH=true, PrelaunchNotice intacto, CRM=true, Meta=OFF. Sin nuevas regresiones conocidas en smoke; limitación de suite heredada descrita arriba. Sin cambios de precios, condiciones, comisiones, ownership, adquisición, datos editoriales o infraestructura; v2proof no utilizado.

Rollback: imágenes anteriores exactas protegidas por tags y `backups/gold-stabilization-20261015/rollback.yaml`; comando en [OPERATIONS](../../docs/OPERATIONS.md). Backup privado validado en la misma carpeta. No requiere restaurar DB para este release sin migraciones. Material operativo preservado para revisión/recuperación.
