# JAKAWI — estado actual de producción

**STATUS: CURRENT / AUTHORITATIVE.** Referencia operativa: 2026-10-09.
Código productivo de referencia: `e9d420870fa6d44802ceb52e4f253f84333b0071`.
Los estados de runtime/automations se documentan conforme al contexto de producción entregado para esta consolidación; no se ejecutaron deploys ni pruebas nuevas.

## Runtime y producción

Laravel 13 monolítico, PHP 8.4 Docker, React 19, TypeScript, Inertia 3, Tailwind 4, shadcn/ui y PostgreSQL 16. Producción: https://jakawi.com; health: https://jakawi.com/up.

CRM: https://crm.jakawi.com, WordPress + FluentCRM en runtime del host CyberPanel, **no Docker**. La prohibición de usar/inspeccionar PHP del host aplica a este trabajo y a las herramientas de desarrollo de JAKAWI; no cambia el runtime que sirve WordPress.

**PHP DEL HOST: NUNCA USAR NI INSPECCIONAR.** Laravel y Composer usan Docker PHP 8.4. Frontend exige rebuild/recreación coherente de `app` + `web`; `social-worker` usa la misma imagen `jakawi-app` y debe acompañar cambios que le afecten. Producción directa: no hay entorno staging/preview operativo.

Baseline conocido: `CampaignTest::admin_can_create_campaign_and_non_admin_cannot` (`test_admin_can_create_campaign_and_non_admin_cannot` en PHPUnit), redirect esperado vs 403. Es un mismatch ya conocido, no una nueva regresión; esta consolidación no ejecuta tests.

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

El piloto sigue **NOT STARTED**, con **REAL INVENTORY** como bloqueador primario. [PILOT_READINESS](operations/PILOT_READINESS.md) es el gate vigente; [RUNBOOKS](operations/RUNBOOKS.md) organiza recuperación y [GLOSSARY](GLOSSARY.md) fija términos. Esta revisión documental no ejecutó QA de producto ni revalidación live de CRM/cron/automations: conserva el estado suministrado, CRM_SYNC_ENABLED=true, Partner Applicant ACTIVE, consumidor 1–4 INACTIVE y Meta OFF.
