# Administración de membresías COM

`/admin/memberships` consulta registros `Membership`, incluidos los vencidos,
programados y cancelados. No convierte cuentas sin membresía en miembros inactivos.
Tener una relación Partner no excluye una membresía personal.

Los pagos bancarios y ventas asociados se consultan por `membership_id` en los
registros existentes. Sólo los estados `approved` y `confirmed`, respectivamente,
aportan un importe confirmado. Si existe más de un cobro confirmado, el listado
no calcula un total: el detalle permite revisar cada registro. Un importe histórico
`amount_paid` sin pago asociado se muestra separado y no acredita un cobro.
Las cuentas de custodia `@purchases.invalid` se presentan como identidad pendiente,
sin utilizar su nombre/correo técnico como identidad del comprador.

## Nuevas membresías

Usar Pagos y conciliación (`/admin/conciliacion-membresias`) o Ventas en efectivo
(`/admin/sales`). Se conservan los servicios de cobro, identidad y eventos existentes.
El detalle de membresía enlaza a la compra o pago de origen; no hay un nuevo ledger.

## Excepciones históricas

Se conserva `POST /admin/memberships`, sin acceso de activación en el listado.
Exige la sesión de un administrador, CSRF, su contraseña actual (`password`),
`confirm=1` y `reason` obligatorio (10–2000 caracteres). También exige `user_id`,
`amount_paid` explícito (0–99999999.99), `payment_method` y `payment_reference`.
`starts_at` y `notes` son opcionales. No hay importe ni medio predeterminados.
No admite cuentas de custodia. Una membresía activa previa rechaza la operación.

La operación es excepcional y requiere autorización administrativa. No registra
un cobro ni genera una venta ficticia. La referencia identifica el antecedente de
la excepción; el importe declarado no aparece como cobro confirmado sin evidencia
asociada. La activación y `membership_exception_activated` en `audit_logs` se
confirman en una transacción, con actor, motivo y estado resultante.

`DELETE /admin/memberships/{membership}` conserva su ruta y exige los mismos
`password`, `confirm` y `reason`. Se ofrece dentro de un desplegable en el detalle.
Conserva el registro, fechas y cobros; no emite reembolsos. Registra
`membership_exception_cancelled`, actor, motivo y estados anterior/posterior.
Para devolver una venta se debe usar la operación comercial existente.

## Verificación

`AdminRealMembershipsTest` cubre estados, titulares Partner, filtros, búsqueda,
paginación, pagos bancarios/efectivo, custodia, permisos y operaciones excepcionales.
Ejecutar mediante `bin/jakawi-test`, exclusivamente en PostgreSQL aislado.
