# JAKAWI — Prelaunch Mode V1

Estado: **LIVE**, 2026-10-13 (America/La_Paz), aplicación `b386cf4882b86269fe6d1d5c2cc563cd1d64f0c7`. Producción: `PRELAUNCH_MODE=true`.

## Prelaunch Mode V1

`PRELAUNCH_MODE=true` activa la presencia oficial estática Coming Soon en `/`, sin navegación de producto, consultas de catálogo, waitlist ni fecha de lanzamiento. La raíz tiene `index,follow`, canonical `https://jakawi.com/`, título, descripción y OpenGraph. El valor por defecto en código y `.env.example` es `false`.

Las rutas directas públicas de producto, Marketing, Explore, Activation y Delivery conservan sus funcionalidades y URLs. Comparten `PrelaunchNotice`: **MODO PRE-LANZAMIENTO — JAKAWI está en pruebas. Algunas experiencias, beneficios y premios pueden estar disponibles únicamente para participantes invitados. Revisa las condiciones de cada propuesta.** El aviso es permanente, sticky y ocupa espacio en el documento; los modales siguen por encima. Las páginas públicas no raíz reciben `noindex,nofollow` tanto en el HTML inicial como en Inertia Head. Admin, previews Admin y auth conservan sus layouts y políticas existentes, sin aviso. No cambia Landing-first, fallback, acquisition ni semántica de dominio.

Un único booleano runtime en `config('jakawi.prelaunch_mode')`, compartido por Inertia como `prelaunchMode`, controla la función. `prelaunchPublic` es el alcance derivado de las rutas existentes, no otra configuración. Cambiar a `PRELAUNCH_MODE=false` y recrear app/social-worker restaura Home, navegación y SEO anterior sin reescribir código; web no cambia assets. No hay ajustes DB, schema, migraciones ni CMS nuevos.

Catálogo productivo: **clean slate**, cero registros comerciales/historial; lugares reales y ofertas posibles para evaluación siguen pendientes. Una ubicación real no acredita una oferta comercial activa. No crear inventario para smoke productivo. Marketing engine, Publisher, funnels y Delivery preservados. Welcome Courtesy: **FUTURE / OFF**.

Validación: [evidencia focalizada](../../artifacts/prelaunch-mode-v1/validation.md). Estado de despliegue: [CURRENT](../CURRENT.md).

[Release productivo](../../artifacts/prelaunch-mode-v1/production-release.json): un build, rollback exacto protegido, sin migraciones ni mutaciones de dominio, sólo app/web/social-worker recreados. 390/1440 y Admin live PASS, URLs DEMO 404, catálogo cero, salud 200/200, DB/v2proof sin recreación, failed jobs 0, CRM TRUE, Meta OFF. Followup documental sin rebuild/redeploy.

## Real-inventory notice V1 — validated copy contract, pending deployment

El PRE-LAUNCH LAB puede contener Partners reales, ubicaciones reales, precios/valores comerciales reales y cumplimiento real controlado de Benefits, Experiences y Challenges para participantes seleccionados o invitados. Eso no abre el lanzamiento público: cada propuesta conserva sus condiciones de dominio como autoridad sobre disponibilidad, elegibilidad, precios y cumplimiento. El aviso no declara que todos los negocios son ficticios, todas las ofertas son demo o ninguna oferta puede cumplirse.

Sólo cambia el texto del componente compartido; ubicación, sticky/z-index, alcance de rutas, visibilidad por PRELAUNCH_MODE y robots permanecen intactos. Coming Soon y Admin no muestran aviso. Partner, Landing, Product Detail, Activation, Delivery y rutas públicas directas lo conservan según el alcance existente; prelaunch no raíz sigue noindex,nofollow. El flag false lo oculta.

Inventario real productivo **PENDING** hasta cargar deliberadamente el primer Partner; esta actualización no crea Partners, catálogo, usuarios, referidos, recompensas ni membresías y no añade migraciones. Welcome Courtesy **FUTURE / OFF**. [Validación y release](../../artifacts/prelaunch-real-inventory-notice-v1/validation.md).
