# Affiliate Program V1

## Alcance

Affiliate es un `User` con una inscripción operativamente activa
`ProgramEnrollment(AFFILIATE)`. No es un rol ni una nueva identidad: una misma
persona puede además ser Member o Promoter. El programa funciona sin QR y usa
las compras manuales confirmadas ya existentes.

## Attribution y beneficiario

`/r/{CODE}` registra un `AttributionTouch`; el registro aplica la política de
primer referente válido descrita en [ATTRIBUTION.md](ATTRIBUTION.md). El origen
de marketing y el cobro de comisión son distintos.

Para cada `membership_purchased` hay como máximo un beneficiario de adquisición:

1. un `credited_seller_user_id` que sea Promoter activo gana la venta manual;
2. si no existe, el primer referente válido que sea Affiliate activo puede
   ganar;
3. en otro caso no hay recompensa.

Por ello una venta manual de Promoter conserva la atribución del Affiliate,
pero no crea una segunda comisión. `RewardBeneficiaryResolver` determina quién
recibe; `RewardResolver` elige una sola regla y calcula cuánto.

## Reglas y ledger

Las reglas usan `RewardRule`: `participant_type=AFFILIATE`, evento
`membership_purchased`, tipo `CASH`, cálculo `FIXED` o `PERCENTAGE`. La
precedencia es individual (`beneficiary_user_id`), programa Affiliate y global;
no se apilan. Sin regla, la conversión sigue siendo válida y no produce reward.

`RewardTransaction` es el único ledger. Nace `pending`; Admin puede moverla a
`available`. Una devolución marca la conversión como `refunded` y la reward como
`cancelled`; nunca se borra historial.

Payout/PAID se difiere a **Affiliate Payout V1**: el esquema actual no guarda
referencia de pago, fecha de pago y administrador pagador de forma auditable.
No se automatizan transferencias ni se inventan cuentas bancarias o lotes.

## Métricas

- **Clicks:** aperturas válidas de `/r/{CODE}`: `AttributionTouch` con ese
  referente. No se inventa fingerprint/deduplicación.
- **Registros:** `ReferralRelationship` activa del Affiliate.
- **Compras y ventas atribuidas:** `Conversion` confirmada
  `membership_purchased` vinculada a la relación del Affiliate. Una conversión
  reembolsada deja de contar en los totales operativos, preservando su audit.
- **Comisiones:** sumas del ledger por estado; datos financieros, no analytics.

El dashboard sólo enseña agregados y referencias/montos mínimos de conversiones;
nunca correo, teléfono ni perfiles de clientes.

## Permisos y auditoría

El Affiliate sólo ve sus propios resultados y genera enlaces con
`utm_campaign`/`utm_content` saneados. No puede activar membresías, crear ventas
manuales, editar reglas, modificar atribución ni cambiar estados financieros.
Admin enrola, activa/desactiva, controla vigencia/código, reglas y PENDING →
AVAILABLE. Esas acciones y los cambios de código/regla quedan en `AuditLog`.
