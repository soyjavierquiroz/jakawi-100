# Manual Membership Sales V1

**STATUS: IMPLEMENTED.** Un Promoter activo puede registrar únicamente sus ventas de efectivo, usando cliente existente o identidad nueva sin manejar su contraseña. `MembershipPurchaseService` realiza compra confirmada, Membership `source=purchase`, Conversion, evaluación de reward y audit en una transacción idempotente.

La venta conserva attribution y acredita al Promoter sin doble comisión. `RewardTransaction` CASH nace `pending`; Admin puede hacerla `available` y registrar payout externo hasta `paid`. Refund requiere motivo, conserva historia y cancela Membership/Conversion/reward aplicables. QR de pago continúa futuro; no hay checkout QR operativo.
