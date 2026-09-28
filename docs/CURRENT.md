# JAKAWI — estado de producto actual

**STATUS: IMPLEMENTED / PILOT READY**
Referencia: **2026-09-28**. La implementación está lista; la verificación funcional completa requiere PHP >= 8.4.1 y no es ejecutable aquí por incompatibilidad PHP ya conocida.

## Actores

JAKAWI cura oferta local: **Partner → Locations, Benefits y Experiences**.

- **Visitor:** descubre catálogo y enlaces de referido; no canjea ni participa.
- **Free User:** tiene cuenta/perfil y explora; no inicia Redemption sin Membership activa.
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

**Discover → Benefit detail → Use benefit → código/QR temporal → Partner validation → confirmed Redemption → confirmed savings.** El servidor exige Membership activa, Benefit publicado/disponible, Location elegible y límites.

Estados: `pending`, `confirmed`, `expired`, `cancelled`. El código de seis caracteres/QR firmado expira según `jakawi.redemption.code_ttl_minutes` (10 por defecto). `redemptions:expire-pending` corre cada minuto y la pantalla también aplica expiración. La validación requiere contexto Partner y PIN de seis dígitos de Location. La confirmación es idempotente; sólo `confirmed` registra ahorro, historial y payback.

## Experiences

**PILOT RULE: reserva externa.** Una Experience pública no puede publicarse con `reservation_method=jakawi`; el servidor lo bloquea. Mecanismos operativos: WhatsApp, URL, teléfono o externo.

Existen `ExperienceSession`, `ExperienceReservation`, respuesta Partner y check-in para `jakawi`, pero son arquitectura/backend preparada: no son booking público operativo durante piloto. No hay pago interno ni availability en tiempo real.

## Attribution, rewards y payouts

`/r/{CODE}` guarda `AttributionTouch`; primer referente válido dentro de la ventana configurable crea la relación. UTM es evidencia de marketing. Una venta manual conserva el origen y puede acreditar al Promoter cobrador sin cambiar el referido.

Para `membership_purchased` se resuelve **un solo beneficiario**: Promoter vendedor activo → Creator activo → Affiliate activo → Member activo → Partner publicado de adquisición → ninguno. No hay doble comisión. Campaign puede asociarse por `utm_campaign` sin cambiar la ventana referral.

`RewardTransaction` es el ledger. CASH y JP son distintos. Rewards nacen `pending`; Admin las pasa a `available`. Refund conserva audit y cancela la conversión/reward aplicable.

Promoter: `pending → available → payout externo → paid`. `RewardPayout` registra solicitud, referencia externa, rechazo/pago y usa locks/pivote para evitar doble inclusión. Payout/listado/disponibilidad Promoter se filtran por `participant_type`, evitando CASH de otro programa del mismo User. Affiliate y Creator solicitan payout propio; no hay transferencia automática.

## JP

**JP ≠ dinero; no tiene equivalencia con Bs.** `RewardTransaction(reward_type=JP)` es el ledger. Reglas Member de referido son JP fijos enteros. El saldo disponible descuenta holds activos. `JpHold`: `HELD` reserva, `RELEASED` devuelve, `FORFEITED` pierde. Fulfillment puede crear bonus JP.

No existe catálogo completo de uso, conversión JP↔BOB, cash-out, drops, quests ni niveles adicionales.

## Desbloqueos V1

Un Unlock es demanda colectiva, no Benefit/Experience/Campaign. Interest no progresa; Commitment sí, contra `minimum_commitments` y `maximum_capacity`. Se configura elegibilidad Free/Member y garantía base JP ajustada por reputación.

Lifecycle: borrador/revisión/aprobación/programación/activo; `interested` o `committed`; meta y confirmación; `confirmed`; `fulfilled`, `cancelled_on_time`, `expired`, `no_show` o `removed`. Cancelación válida libera hold; confirmación expirada libera y es neutral; no-show forfeits hold; fulfillment libera y puede dar bonus una vez. Scheduler procesa deadlines. Admin puede cancelar, extender, cerrar, retirar, liberar excepcionalmente y aplicar la corrección permitida, con motivo/audit. Partner prepara/envía Unlocks y valida fulfillment en su contexto. Compartir conserva attribution, pero no da JP. Secretos de supply pueden permanecer server-side hasta meta.

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

No operativos: pagos internos de Experiences, booking público interno durante piloto, availability en tiempo real, marketplace abierto, Partner self-service completo, permisos por Location, geofencing, app nativa, recomendaciones/gamificación avanzadas, proveedor automatizado de payouts y personalización avanzada.

**Desbloqueos Comerciales — STATUS: STRATEGIC DESIGN / NOT IMPLEMENTED.** No existen depósitos monetarios, pricing escalonado, compra colectiva, settlement comercial, proveedor oculto comercial ni saldo final operativo.
