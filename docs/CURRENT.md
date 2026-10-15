# JAKAWI — estado actual de producción

**STATUS: CURRENT / AUTHORITATIVE.** Cierre Gold Legacy: 2026-10-15 (America/La_Paz). **GOLD PASS: 13/13 casos heredados resueltos.** Código productivo `89dcf27dc3fbb2eb55be42512fc2012b7658ceb6`; anterior `95433f4a56675d46c858b8ebf002e1762130a57d`. Rollback exacto app/web/worker protegido en `backups/gold-stabilization-20261015/legacy-test-cleanup`; sin migraciones. [Gold report](../artifacts/gold-stabilization-20261015/REPORT.md) y [release del cierre](../artifacts/gold-stabilization-20261015/legacy-test-cleanup-release.json). El commit documental posterior no requiere redeploy.

Una única suite completa final: **701 PASS, 0 FAIL, 10.517 aserciones**. Correcciones de fixtures/expectativas y dos bugs preexistentes de Campaign (unicidad con ID vacío en PostgreSQL y participant_types enviado a la tabla incorrecta). Sólo cambia ese controlador en runtime; permisos, pivote, economía y contratos comerciales conservados. TypeScript/build/manifest y smoke candidato/LIVE PASS; 17 rutas READ ONLY y 34 vistas de navegador por entorno, sin escrituras QA.

PRELAUNCH_MODE=true; raíz Coming Soon 200, rutas públicas directas con PrelaunchNotice y noindex. CRM activo; Meta provider/browser/CAPI OFF. Salud local/pública 200, app healthy, web/worker running. Existe inventario comercial real; el reset del 13 de octubre y las secciones de releases anteriores que siguen son historial, no referencias LIVE actuales. Las correcciones de la primera estabilización se conservan. No se repitieron auditorías GOLD ni se hicieron refactors, migraciones o cambios de infraestructura/comerciales.

## Arquitectura actual auditada

[Marketing System Audit V1](architecture/MARKETING_SYSTEM_AUDIT.md) coteja código en `9c1708f`: plantilla única configurable OFFER_CONVERSION para Benefit/Experience/Challenge; Unlock separado; Publisher existente; Landing-first según viewer y Product Detail funcional. AcquisitionProvider pide nombre/correo/WhatsApp sin contraseña de registro. Challenge final opt-in con PARTICIPATE autoriza join tras auth; signup genérico/GET no participan. Activation sin elección está eliminada del flujo Challenge, pero conserva JSX inalcanzable; otros dominios aún tienen excepciones. Delivery comparte shell fija branded, marca enlazada y navegación posterior; semántica de color LIVE sin cambiar composición V3.

Landing JSON Import/Export V1 está **LIVE**, schema `jakawi-landing-v1`, en el Publisher existente para Benefit/Experience/Challenge. Import parcial estricto sólo editorial, revisión y guardado humano; sin AI backend ni cambios de publicación, dominio o Unlock. [Contrato y prompt externo](marketing/LANDING_JSON_V1.md). [Guard de assets](operations/PRODUCTION_ASSET_GUARD.md): rechaza URLs reales loopback/Vite; conserva texto y validación WebAuthn sin destino de red. AI Product Brief V1 está **LIVE**, `jakawi-product-brief-v1`: COPIAR BRIEF PARA IA → external GPT → IMPORTAR JSON → CAMBIOS SIN GUARDAR → GUARDAR Y PREVISUALIZAR → PUBLICAR. [Contrato del brief](marketing/AI_PRODUCT_BRIEF_V1.md). Sin AI backend; batch: FUTURE.

Publisher Workflow UX V1 está **LIVE**: Import != save; el Preview V1 renderiza la versión guardada y se refresca tras persistir. Errores explícitos preservan el formulario; PUBLICAR bloqueado mientras haya cambios sin guardar. [Workflow](marketing/PUBLISHER_WORKFLOW_UX_V1.md). Publisher UX V2 también está **LIVE**: [workspace desktop](marketing/PUBLISHER_WORKSPACE_UX_V2.md).

Gaps vigentes: copy de adquisición guardado pero no consumido por modal y acceso por correo encolado sólo para signup con Landing. Verification Continuity ya resolvió Benefit propio y retorno contextual para Experience interno/Unlock; sus fronteras de seguridad se preservan. Welcome Courtesy sigue FUTURE / OFF / NOT IMPLEMENTED. Las secciones de releases abajo son evidencia histórica ordenada, no múltiples SHAs vigentes ni checks nuevos. El audit no acredita migraciones aplicadas ni salud productiva.

## Runtime y producción

Laravel 13 monolítico, PHP 8.4 Docker, React 19, TypeScript, Inertia 3, Tailwind 4, shadcn/ui y PostgreSQL 16. Producción: https://jakawi.com; health: https://jakawi.com/up.

CRM: https://crm.jakawi.com, WordPress + FluentCRM en runtime del host CyberPanel, **no Docker**. La prohibición de usar/inspeccionar PHP del host aplica a este trabajo y a las herramientas de desarrollo de JAKAWI; no cambia el runtime que sirve WordPress.

**PHP DEL HOST: NUNCA USAR NI INSPECCIONAR.** Laravel y Composer usan Docker PHP 8.4. Frontend exige rebuild/recreación coherente de `app` + `web`; `social-worker` usa la misma imagen `jakawi-app` y debe acompañar cambios que le afecten. Producción directa: no hay entorno staging/preview operativo.

CampaignTest queda resuelto: guest redirige a login; autenticado no Admin recibe 403. Creación/edición y duplicados comprobados contra PostgreSQL aislado; ya no hay una excepción vigente de baseline para este test.

CRM sync: `CRM_SYNC_ENABLED=true`. Meta efectivo OFF: `META_PROVIDER_ENABLED=false`, `META_BROWSER_ENABLED=false`, `META_CAPI_ENABLED=false` (defaults false cuando no hay override). Consumer automations 1–4 DRAFT/INACTIVE; Partner Applicant V1 PUBLISHED/ACTIVE. Detalle en [CRM](CRM_FOUNDATION_V1.md).

El piloto comercial aún no comenzó. Hace falta inventario real: [Manual de Inventario](operations/MANUAL_DE_INVENTARIO.md). El [Manual de Funciones](product/MANUAL_DE_FUNCIONES.md) es la referencia de uso para producto, soporte y comercial; este documento es la referencia del estado vigente.

## Actores

JAKAWI cura oferta local: **Partner → Locations, Benefits y Experiences**.

- **Visitor:** descubre catálogo y enlaces de referido; no canjea ni participa.
- **Free User:** tiene cuenta/perfil y explora; no inicia Redemption normal sin Membership activa; un grant de Challenge válido permite su Benefit exclusivo sin esa membresía.
- **Member:** Membership activa habilita Redemptions, referidos y participación en Unlocks cuando su elegibilidad lo permite.
- **Partner:** entidad de oferta, no un rol User. Tiene Locations, Benefits y presencia en Experiences/Unlocks.
- **Partner User:** pivote `partner_user`, roles `owner`, `manager`, `staff`. Opera sólo el Partner asignado. Regla piloto: cualquier usuario autorizado del Partner puede validar cualquier Location de ese Partner si conoce su PIN; no existe permiso por Location.
- **Promoter / Affiliate / Creator:** `ProgramEnrollment`, no identidad ni rol. Pueden coexistir en un User. Promoter registra ventas manuales propias; Affiliate y Creator comparten enlaces, ven resultados agregados y solicitan payout.
- **Admin:** `users.is_admin`; rutas/UI para catálogo, Partner users, Memberships, programas, rewards/payouts, Campaigns, Unlocks, Reputation, ajustes/export y métricas.

## Membership

Una Membership es activa por estado `active` y rango de fechas. Configuración actual: `jakawi_annual`, Bs100, 365 días; cada fila conserva `amount_paid`.

| Fuente | Regla |
| --- | --- |
| `purchase` | Compra confirmada; hoy venta manual de efectivo. Puede registrar Conversion y reward. |
| `admin_grant` | `amount_paid=0`, motivo obligatorio y `AuditLog`. No es venta, revenue, conversión pagada ni recovery. |

La migración `2026_09_28_000001_add_source_to_memberships_table.php` añade `source`. Cancelar conserva el registro.

## Benefits y Redemptions

**Discover → Benefit detail → Use benefit → código/QR temporal → Partner validation → confirmed Redemption → confirmed savings.** El servidor exige Membership activa para Benefit normal, o grant válido para social_challenge_grant, además de Benefit publicado/disponible, Location elegible y límites.

Estados: `pending`, `confirmed`, `expired`, `cancelled`. El código de seis caracteres/QR firmado expira según `jakawi.redemption.code_ttl_minutes` (10 por defecto). La expiración se aplica al consultar/iniciar/validar por controller/service; este SHA no registra un comando programado redemptions:expire-pending. La validación requiere contexto Partner y PIN de seis dígitos de Location. La confirmación es idempotente; sólo `confirmed` registra ahorro, historial y payback.

## Experiences

**Regla operativa del piloto: reserva externa** (WhatsApp, URL, teléfono o externo). El código actual también admite reservation_method=jakawi y publicación: no hay bloqueo server-side de ese modo en este SHA. No inferir operación comercial interna del catálogo a partir de esa capacidad.

Existen rutas/UI y backend de ExperienceSession/ExperienceReservation, respuesta Partner y check-in para jakawi. Requieren Membership activa, Experience publicada, sesión upcoming y Partner receptor publicado/asociado; no garantizan cupos en tiempo real (el servicio no aplica capacity como límite). La política de inventario del piloto usa reserva externa; no hay pago interno ni integración de disponibilidad externa en tiempo real.

## Attribution, rewards y payouts

`/r/{CODE}` guarda `AttributionTouch`; primer referente válido dentro de la ventana configurable crea la relación. UTM es evidencia de marketing. Una venta manual conserva el origen y puede acreditar al Promoter cobrador sin cambiar el referido.

Para `membership_purchased` se resuelve **un solo beneficiario**: Promoter vendedor activo → Creator activo → Affiliate activo → Member activo → Partner publicado de adquisición → ninguno. No hay doble comisión. Campaign económica puede asociarse por `utm_campaign` sin cambiar la ventana referral. `campaign_key` de LandingPresentation es sólo contexto de adquisición y no activa esa Campaign.

`RewardTransaction` es el ledger. CASH y JP son distintos. Rewards nacen `pending`; Admin las pasa a `available`. Refund conserva audit y cancela la conversión/reward aplicable.

Promoter: `pending → available → payout externo → paid`. `RewardPayout` registra solicitud, referencia externa, rechazo/pago y usa locks/pivote para evitar doble inclusión. Payout/listado/disponibilidad Promoter se filtran por `participant_type`, evitando CASH de otro programa del mismo User. Affiliate y Creator solicitan payout propio; no hay transferencia automática.

## JP

**JP ≠ dinero; no tiene equivalencia con Bs.** `RewardTransaction(reward_type=JP)` es el ledger. Reglas Member de referido son JP fijos enteros. El saldo disponible descuenta holds activos. `JpHold`: `HELD` reserva, `RELEASED` devuelve, `FORFEITED` pierde. Fulfillment puede crear bonus JP.

No existe catálogo completo de uso, conversión JP↔BOB, cash-out, drops, quests ni niveles adicionales.

## Desbloqueos V1

Un Unlock es demanda colectiva, no Benefit/Experience/Campaign. Interest no progresa; Commitment sí, contra `minimum_commitments` y `maximum_capacity`. Se configura elegibilidad Free/Member y garantía base JP ajustada por reputación.

Lifecycle: borrador/revisión/aprobación/programación/activo; `INTERESTED` o `COMMITTED`; meta y confirmación; `CONFIRMED`; `FULFILLED`, `CANCELLED_ON_TIME`, `EXPIRED`, `NO_SHOW` o `REMOVED`. Cancelación válida libera hold; confirmación expirada libera y es neutral; no-show forfeits hold; fulfillment libera y puede dar bonus una vez. Scheduler procesa deadlines. Admin puede cancelar, extender, cerrar, retirar, liberar excepcionalmente y aplicar la corrección permitida, con motivo/audit. Partner prepara/envía Unlocks y valida fulfillment en su contexto. Compartir conserva attribution, pero no da JP. Secretos de supply pueden permanecer server-side hasta meta.

## Reputation V1

`reputation_events` / `ReputationService`: `UNLOCK_FULFILLED`, `VALID_CANCELLATION`, `NO_SHOW`, `CONFIRMATION_EXPIRED`; estados `NEW`, `STANDARD`, `TRUSTED`, `RESTRICTED`.

`config/reputation.php` manda: por defecto Trusted=5 fulfillments; tras el último no-show, Standard=3 fulfillments y Trusted=5. Factores de depósito: New 1×, Standard 1×, Trusted 0.5×, Restricted 1.5×; mínimo 0/máximo 10.000 configurables. `CONFIRMATION_EXPIRED` se registra pero es neutral. Admin ve/corrige con motivo y audit, sin reescribir el original.

## Control de Piloto

`PilotDashboardMetrics` separa actividad del período, cohorte adquirida y snapshot. Cohorte: `MembershipPurchase` confirmadas/pagadas del período.

- **Conversion:** miembros pagados de cohorte / registros del período.
- **Activation:** compradores con primer Redemption confirmado / compradores.
- **Recurrence:** activados con segundo Redemption confirmado / activados.
- **Payback/Recovery:** ahorro confirmado acumulado >= `amount_paid`; grants Bs0 no entran porque no son compras.
- **Time to first value:** `Membership.starts_at` → primer Redemption confirmado.

Actividad de período cuenta confirmaciones dentro de fechas; cohortes miran uso hasta el corte; snapshot cuenta activas sin uso al corte.

## Fuera del MVP / futuro

No operativos: pagos internos de Experiences, operación comercial de booking interno durante piloto, availability en tiempo real, marketplace abierto, Partner self-service completo, permisos por Location, geofencing, app nativa, recomendaciones/gamificación avanzadas, proveedor automatizado de payouts y personalización avanzada.

**Desbloqueos Comerciales — STATUS: STRATEGIC DESIGN / NOT IMPLEMENTED.** No existen depósitos monetarios, pricing escalonado, compra colectiva, settlement comercial, proveedor oculto comercial ni saldo final operativo.

## Dominios incorporados al producto actual

Challenges separan evidencia, calificación, selección y entrega del premio; participar no implica ganar. Cities resuelve ciudad activa y las superficies de interés/solicitud donde corresponde. Discovery agrega Benefits, Experiences, Unlocks y Challenges disponibles por ciudad, con ranking y afinidad limitados ya implementados.

LandingPresentation presenta un Benefit, Experience, Unlock o Challenge para adquisición. Product Detail sigue siendo la superficie operativa; `/l/{slug}` es marketing, `/go/{slug}` redirect externo/configuración y `/d/{slug}` Unlock nativo. Ninguna landing cambia reglas ni economía del subject.

Growth Analytics conserva los nueve eventos canónicos y contexto de AttributionTouch en JAKAWI; Meta Pixel+CAPI ya está implementado pero apagado. CRM es una proyección independiente para contactos, segmentación, email y automations; FluentCRM no es un almacén de analytics. Véanse [arquitectura](ARCHITECTURE.md), [growth](growth-measurement-v1.md) y [CRM](CRM_FOUNDATION_V1.md).

## Preparación operativa V1.1

El piloto sigue **NOT STARTED**, con **REAL INVENTORY** como bloqueador primario. [PILOT_READINESS](operations/PILOT_READINESS.md) es el gate vigente; [RUNBOOKS](operations/RUNBOOKS.md) organiza recuperación y [GLOSSARY](GLOSSARY.md) fija términos. La revisión documental previa no ejecutó QA de producto ni revalidación live de CRM/cron/automations: conserva el estado suministrado, CRM_SYNC_ENABLED=true, Partner Applicant ACTIVE, consumidor 1–4 INACTIVE y Meta OFF.


## Marketing Landing Admin V1 — implementación local para revisión

Una sola plantilla persistida `OFFER_CONVERSION`, adapters Benefit/Experience/Challenge y temas explícitos DARK_CONVERSION/LIGHT_CONVERSION. Se extendió la administración LandingPresentation existente: selector de subject, defaults editoriales, resumen autoritativo de sólo lectura, énfasis seguro, cierre, media IMAGE/VSL/NONE, campaign_key/audiencia, create/edit/duplicate DRAFT, preview real 390/430/Desktop y publish/unpublish. La migración reversible preserva los tratamientos legacy y configuración media; la entrega original no la ejecutó contra producción; referencia histórica, no inventario actual de migraciones aplicadas.

Dominio conserva economía, reglas, elegibilidad y acciones. No hay CTA arbitraria, HTML/CSS/HEX, drag/drop ni orden libre. Unlock conserva su renderer/Admin y restricciones existentes; no tiene adapter Offer Conversion V1. Audiencia, `/l/{slug}`, rutas nativas, SEO y campaign_key guardado mantienen su semántica. Preview es Admin/noindex y no muta negocio ni atribución. **Benefit guest/location continuity: RESOLVED** en producción (`60feb98`). El flujo guest → auth → ubicación → membresía si corresponde → confirmación → canje conserva Benefit e intención (`8d91303`). El registro no exige sucursal y el Benefit normal no bloquea por correo no verificado; la verificación de premios Challenge se conserva. Este bloqueo específico está cerrado. Inventario comercial confirmado y demás requisitos de onboarding siguen pendientes.

Alcance: validación aislada y commit local; sin push, migración productiva ni deploy. Ver [arquitectura](ARCHITECTURE.md#marketing-landing-admin-v1), [diseño](DESIGN-SYSTEM.md#marketing-landing-admin-v1) y [manual](product/MANUAL_DE_FUNCIONES.md#marketing-landing-admin-v1).

## Landing-first Product Journey V1 — LIVE (2026-10-12)

Release `e848b9f4833b514ea64b1b337bdd426235273100`, desde producción `8d913038984ae5eb6d40f3ba7477beae5d928ece`: un build productivo, backup PostgreSQL validado y sólo la migración aditiva `2026_10_12_000003_add_acquisition_copy_to_landing_presentations`. App/web/worker recreados coherentemente; DB y demás contenedores preservados.

Landing publicada aplicable es la presentación persuasiva principal: guest resuelve `GUESTS > ALL`; cuentas gratuitas y miembros activos resuelven `ALL`. Sin Landing aplicable, Product Detail es el fallback funcional mínimo. Auth y el retorno explícito de membresía conservan Landing/subject y sucursal/fecha. Para servir guest/free/member con la misma presentación, la política normal es `ALL`; no existen audiencias nuevas. [Contrato](ARCHITECTURE.md#acquisition-funnel-v1).

**Inventario histórico anterior al reset, ya eliminado:** únicamente las representantes DEMO 8 / `qa-calibration-benefit-value`, 10 / `qa-calibration-experience-desire` y 12 / `qa-calibration-challenge-action` están PUBLISHED / ALL. Inventario de esa release antes del reset: **3 PUBLISHED, 5 DRAFT, 5 ARCHIVED**. Son escenarios sintéticos no comerciales, para revisión pre-launch; no constituyen inventario real de lanzamiento. Unlock permanece sin publicar.

Smoke productivo 390 PASS: guest y autenticado Landing-first en Café/Sunset/Reto Cena, Café con tres sucursales después de auth y contexto conservado hacia membresía, Experience con selección existente y Challenge sin bypass de verificación. Fallback: `/beneficios/demo-beneficio-04`. Cero mutaciones nuevas de dominio/membresía y sesiones temporales revocadas. `/up` local/público 200; app/DB healthy, web/worker y scheduler activos; failed jobs 3 históricos / 0 nuevos, CRM TRUE, Meta OFF. V3 LIVE + FROZEN. [Evidencia de release](../artifacts/landing-first-v1/production-release.json).

Revisión visual 390: sin overflow ni shell duplicado; login visible y CTA del modal coral con texto blanco. El CTA principal de las Landings conserva texto oscuro sobre coral en V3: pendiente de revisión humana, sin modificación de código durante este release.

## Micro opt-in + Delivery V1 — LIVE (2026-10-12)

Landing → modal nombre / correo / WhatsApp → sesión existente → requisitos y acción explícita → Delivery con datos de dominio. Contraseña permanente diferida mediante reset seguro por correo no bloqueante; sin auth temporal ni verificación genérica bloqueante. Colisiones de identidad requieren login/recuperación. Challenge conserva verificación contextual; membresía, economía, Growth, CRM y JP no cambian. Welcome Courtesy permanece FUTURE / OFF / NOT IMPLEMENTED en ROADMAP. Esta entrega ejecutó `df0476f38f469cd063e5b97bee2d19dfa4f17a84`; la release vigente está indicada al inicio de este documento. V3 permanece congelada. Un build desde export limpio; sin migración, seed ni cambios a los tres demos. Smoke productivo 390 PASS, sin signup ni mutaciones de dominio; sesión QA existente continuó a ubicación y fue revocada. Delivery autorizado existente y protección Unlock PASS. `/up` local/público 200; app/DB healthy, web/worker/scheduler activos, failed jobs 3 históricos / 0 nuevos, CRM TRUE y Meta OFF. Hubo rollback preventivo por un gate operativo que no seguía el 302 nativo; corregido el gate, las mismas imágenes quedaron LIVE sin rebuild. [Evidencia](../artifacts/micro-opt-in-delivery-v1/INDEX.md).

## Conversion Handoff + Full-page Delivery V1 — LIVE (2026-10-12)

Modal guest sólo para nombre/correo/WhatsApp, progreso 33/66/100 y copy de la promesa; sale a Activación en página completa. Autenticados omiten identidad. La selección determina el CTA; el commit de dominio lleva a Delivery de página completa. Reservas externas tienen Delivery informativo independiente, con fecha elegida y estado pendiente de gestionar con el partner, sin simular una reserva. Cuenta/seguridad son secundarios después de instrucciones, nunca el éxito principal. Reservas pendientes/externas conservan estado veraz; verificación Challenge, membresía y source Unlock permanecen autoritativos. V3 conserva composición y recibe sólo handoff y texto blanco del CTA. Welcome Courtesy sigue FUTURE / OFF / NOT IMPLEMENTED. Validación previa aislada: 13 tests / 337 assertions, QA móvil y Activación/Delivery a 1440. Release productiva `b3c00e371a6c5f7d509d96f67e07103f0176a051`, desde la base real `df0476f38f469cd063e5b97bee2d19dfa4f17a84`: un build desde export Git limpio, imágenes inmutables app/web y worker compartiendo app; rollback anterior protegido sin rebuild. Sin nuevas migraciones, seed, recreación de DB ni cambios en publicación. Smoke productivo a 390 PASS: progreso 33/66/100, identidad QA existente hacia Activación full-page, fecha seleccionada con CTA blanco y verdadero, autenticados sin opt-in en Sunset/Café/Reto, verificación Challenge preservada, fallback disponible y Delivery externa explícitamente pendiente. No se envió signup ni se ejecutaron conversiones; los contratos de resultados internos se comprobaron con componentes desplegados y outcome Benefit existente autorizado, sin fabricar resultados Challenge. Cero redemptions/reservas/participaciones/unlock commits/membresías nuevas. v2proof conserva container ID y arranque, healthy; `/up` local/público 200/200, app/DB healthy, web/worker/scheduler activos, failed jobs 3 históricos / 0 nuevos, CRM TRUE y Meta provider/browser/CAPI OFF. Revisión menor de copy: Café sin membresía muestra «CONTINUAR» con sucursal elegida visible, mientras conserva el requisito de membresía. No hubo rollback de esta release. [Evidencia](../artifacts/conversion-handoff-v1/INDEX.md).

## Delivery / Conversion V1.1 — LIVE (2026-10-13 UTC)

Challenge permite join explícito sin email verificado; el final opt-in con intended_action=PARTICIPATE puede ejecutar ese join después de auth. No hay participación por signup genérico o GET. Delivery prioriza logro, misión, instrucciones, fecha límite, acción, premio y estado antes de seguridad/cuenta. Email se exige al enviar evidencia social y antes de seleccionar/otorgar recompensa, incluido el reto manual. Confirmar email vuelve a la Delivery del mismo reto con participación propia; sin participación no la crea. Sin schema ni nuevo player: no se muestra media instructiva cuando el dominio no la provee. Micro Opt-in y composición Offer V3 congelados; sólo se integra el label autenticado «ENTRAR AL RETO». Release productiva `f2b488b7e634782d3aa21dae987596d96b30d28c`, desde `b3c00e`, con un build limpio e imágenes inmutables; sin migraciones ni cambios de publicación. Smoke de entrada con identidad QA no verificada por inspección read-only del runtime; Delivery a 390 con la misma imagen/compilados y participación aislada existente, sin fabricar una en producción. Benefit confirmado existente y Experience externa pendiente PASS. Cero nuevas conversiones, evidencia o premios; DB y v2proof sin recreación. Welcome Courtesy: FUTURE / OFF / NOT IMPLEMENTED. [QA y release](../artifacts/delivery-v1-1/INDEX.md).

## Challenge direct join — LIVE

Activación requiere elección operativa real. Challenge actual: CTA explícito → join → Delivery; el final guest «QUIERO PARTICIPAR» conserva `intended_action=PARTICIPATE`, validado tras autenticar. Signup genérico y GET no participan. Sunset fecha y Café sucursal preservados; Delivery V1.1 y Unlock sin cambio. Release `7cdc8c12e71b23e6c6b99db522e2e6274be6b87f`, un build limpio y cero mutaciones nuevas en producción; DB/v2proof intactos, CRM TRUE y Meta OFF. [Validación y release](../artifacts/challenge-direct-join/INDEX.md).


## Verification Continuity V1 — LIVE

Release de 2026-10-13 (America/La_Paz): `715af599073e359509ef7c68e188d2aba4b4da20`, desde runtime real `7cdc8c12e71b23e6c6b99db522e2e6274be6b87f`. Delta: dos commits documentales y el fix de continuidad; sin migraciones nuevas/pendientes ni cambios Docker/Compose/config. Un build limpio; rollback exacto de 7cdc protegido; sin backup, migrate, seed ni rollback ejecutado. Benefit Delivery propia admite owner sin verificar; Experience interno y Unlock comprueban `hasVerifiedEmail()` explícitamente antes de mutaciones protegidas y mantienen retorno contextual sin replay. Challenge join sin verificación y evidencia/premio protegidos, sin cambios. `User` sigue sin `MustVerifyEmail`; no reinterpretación global. Account access y verificación siguen separados.

Gates live: `/up` local/público 200/200; Café/Sunset/Reto Cena y fallback 200; assets compilados, manifest PASS, public/hot ausente, sin dev refs. Smoke read-only: canje existente con estado de correo proyectado en memoria y no-owner denegado; Experience interno proyectado sobre Landing/sesión publicada, con retorno contextual; handoff externo sin verificación; guard Unlock y source protegido. Límites del inventario real: Landing interna Experience DRAFT; no Unlock activo con Landing publicada. No se publicó ni creó inventario para QA; los flujos contextuales completos se validaron antes en datos aislados. Cero mutaciones nuevas de redemptions/reservas/participaciones/evidencia/rewards/Unlock/membresías/JP/outcomes Growth. App/DB healthy, web/worker running, cron activo; V2PROOF y DB intactos; failed jobs 3 históricos/0 nuevos; CRM TRUE, Meta provider/browser/CAPI OFF. [Evidencia](../artifacts/verification-continuity-v1/production-release.json). El [audit de marketing](architecture/MARKETING_SYSTEM_AUDIT.md) permanece autoritativo. Welcome Courtesy: FUTURE / OFF / NOT IMPLEMENTED. Las secciones de releases anteriores son historia, no el runtime vigente.


## Branded Delivery + Semantic Color V1 — LIVE

2026-10-13 (America/La_Paz): release `79f2ed7dfd9f792e94bc659136a02e809699fbaa`, desde `715af599073e359509ef7c68e188d2aba4b4da20` con rollback protegido. Shell fija compartida, logo enlazado al inicio, instrucciones/acciones antes de cuenta/seguridad y navegación final a Mi JAKAWI/Explorar. PWA flotante omitida durante Delivery para no obstruir QR. Coral = acción; amarillo = oportunidad/recompensa/deadline; verde = éxito/valor verdadero; ink/cream = estructura. Publisher, Admin, plantilla Offer V3, schema y JP/security sin cambios.

Un build productivo, `/up` 200/200 y tres Landings DEMO 200 con assets compilados/manifest PASS/sin hot ni dev refs. Smoke 390 read-only del runtime: Reto Cena, Sunset Landing/handoff externo pendiente, Café Landing y Delivery confirmada existente. QR de Café revisado con vencimiento proyectado exclusivamente en memoria porque el código pendiente real expiró; no guardado ni canje. Cero nuevas conversiones, evidencia, rewards, Unlock, membresías o Growth outcomes. App/DB healthy, web/worker running, V2PROOF/DB intactos y cron activo; failed jobs 3 históricos/0 nuevos, CRM TRUE y Meta OFF. [Evidencia LIVE](../artifacts/brand-unify-delivery-v1/production-release.json). Welcome Courtesy: **FUTURE / OFF / NOT IMPLEMENTED**. Actualización documental sin rebuild/redeploy.

## Partner Public Profile + Acquisition V1 — LIVE

Canonical `jakawi.com/{slug}`, technical `/p/{slug}` → 301; existing Partner/account pivot and persisted slug reused, no migration. Guest profile context survives native inventory route → Landing → Micro Opt-in → new signup; first valid referral touch wins. Existing login and authenticated visit never acquire/reassign. Guest signup/login visible, browsing free; authenticated account block hidden. Existing `membership_purchased` reward infrastructure unchanged. Direct prelaunch profile access + shared notice + noindex, with no Coming Soon links. Production catalog remains empty; no production Partner created. Controlled-real-inventory wording is now LIVE in the shared notice. [Contract](PARTNER-ACQUISITION.md), [validation](../artifacts/partner-public-profile-v1/validation.md). Deployment desde Git export limpio de `70b5978`, un único build productivo; sólo app/web/social-worker recreados. DB y v2proof intactos/healthy, health local/public 200/200, failed jobs 0, scheduler activo, CRM TRUE y Meta OFF. [Evidencia live](../artifacts/partner-public-profile-v1/production-release.json). Inventario real PENDING. Welcome Courtesy FUTURE / OFF. Followup documental sin rebuild/redeploy.

Real-inventory Notice V1 está LIVE y actualiza el texto del aviso compartido para el laboratorio con Partners/ubicaciones/precios reales y cumplimiento controlado para invitados, sin lanzamiento público ni cambio de condiciones de dominio. Inventario real **PENDING** hasta la primera carga deliberada. Welcome Courtesy **FUTURE / OFF**. Contrato vigente del aviso: [Prelaunch Mode](operations/PRELAUNCH_MODE_V1.md).

Release Notice V1: un build productivo desde Git export limpio, sólo app/web/social-worker recreados, sin migraciones ni mutaciones de dominio. Cleanup seguro alcanzó 22.05 GiB y se detuvo; rollback actual protegido. Root/Admin sin aviso; público no raíz con texto nuevo, aviso único y noindex,nofollow. Requisito de copy previo a inventario real **COMPLETADO**; carga real sigue **PENDING**. [Evidencia](../artifacts/prelaunch-real-inventory-notice-v1/production-release.json). Followup documental sin rebuild/redeploy.
