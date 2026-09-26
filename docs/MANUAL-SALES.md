# Manual Membership Sales V1

## Dominio y permisos

`ProgramEnrollment` concede acceso operativo por programa, sin convertir el programa en un rol de `User`. Una inscripción `PROMOTER` operativamente activa (estado `active` y dentro de vigencia) permite ver y registrar únicamente las ventas propias. Un usuario puede conservar a la vez membresía y cualquier número de inscripciones de programa. Admin sigue determinado por `users.is_admin`.

Admin gestiona Promotores desde UI: busca o crea la identidad sin contraseña compartida, reutiliza Fortify para que una cuenta nueva defina su contraseña, enrola, activa/desactiva, configura vigencia y entrega código/enlace de referido. Desactivar no borra ventas, recompensas ni historial. La regla CASH individual opcional usa `RewardRule.beneficiary_user_id` y precede a la regla de programa y global.

`MembershipPurchase` es el registro comercial neutral: guarda la referencia JAKAWI inmutable, recibo manual, canal, monto, moneda, duración configurada, beneficiario, registrador, cobrador, estado y vínculos a la membresía y conversión. `manual_cash` confirma de inmediato; QR reutilizará la misma entidad cuando exista confirmación server-side.

## Cliente, activación y atribución

El promotor puede seleccionar una cuenta existente o crear una nueva por nombre y correo. Nunca define ni ve una contraseña: se guarda un secreto aleatorio no compartido y se reutiliza el enlace normal Fortify de restablecimiento para que el cliente defina la suya.

`MembershipPurchaseService` realiza en una transacción la compra confirmada, `MembershipService`, `ConversionRecorder`, evaluación de recompensa y audit logs. La llave UUID de idempotencia y los índices únicos impiden duplicados por reintento. La referencia interna tiene formato `MS-YYYYMMDD-XXXXXXXX` y se genera aleatoriamente con índice único.

La conversión `membership_purchased` mantiene el snapshot de marketing existente (relación de referido y UTM). Para efectivo manual agrega `credited_seller_user_id`: el promotor cobrador recibe el crédito comercial sin sobrescribir `ReferralRelationship`.

## Recompensas, devolución y auditoría

`RewardRule` resuelve una única regla CASH activa por conversión: individual, luego tipo de programa y finalmente global; prioridad decide dentro de cada nivel. Soporta cálculo `FIXED` o `PERCENTAGE`. Si no hay regla, la venta continúa. `RewardTransaction` es el ledger y tiene unicidad por conversión y regla; inicia `pending`.

Sólo Admin puede reembolsar y debe indicar motivo. El reembolso conserva compra, membresía, conversión y recompensa: cancela la membresía creada, marca la conversión `refunded`, la recompensa `cancelled` y deja audit logs. No borra historial de canjes.

## Operación y futuro QR

Promotor dispone de Mis ventas, Nueva venta y detalle propio. Admin dispone de ventas, activación manual, reembolso y reglas básicas de recompensa. QR futuro sólo cambia la confirmación de pago: seguirá `MembershipPurchase → MembershipPurchaseService → Conversion → reward evaluation`. La entidad ya tiene `payment_channel` y `status` de texto, suficientes para la fundación; no se añadieron columnas de proveedor ni migraciones sin contrato real. La forma de persistir una referencia externa queda pendiente. Véase [PAYMENTS.md](PAYMENTS.md).
