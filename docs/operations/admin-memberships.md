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

## Plan Pionero

`memberships.type` distingue `standard` (valor predeterminado para compras actuales
y futuras) de `pioneer`. El estado sigue siendo `active`/`cancelled`. La asignación
Pionero es explícita; no depende de la antigüedad de la cuenta.

La fecha original `ends_at` se conserva para auditoría. `effectiveEndsAt()`,
`active()`, `unexpired()`, `expired()` e `isActive()` aplican la vigencia efectiva.
Un Pionero activo disfruta beneficios desde `starts_at`; sin lanzamiento oficial
no vence por la fecha histórica. La cancelación y el inicio futuro siguen bloqueando
el acceso. No se modifican pagos ni se emiten eventos comerciales al asignar el tipo.

`JAKAWI_OFFICIAL_LAUNCH_DATE` se deja sin definir. Cuando exista autorización para
establecer el lanzamiento, configurar su fecha `YYYY-MM-DD` en todos los runtimes COM
y regenerar sus caches de configuración. El vencimiento se calcula sumando **15 meses
calendario**, sin desbordamiento (si no existe el día, último día del mes destino),
hasta **23:59:59.999999 America/La_Paz**, inclusive, de ese día. Se convierte a UTC
para comparar instantes. No depende de `PRELAUNCH_MODE`. Cambiar la fecha requiere
una operación administrativa explícita; esta entrega no establece ninguna.

En listado/detalle, Pionero sin lanzamiento muestra «15 meses después del lanzamiento»;
la fecha estándar original sólo figura en Historial del detalle. La asignación puntual
a membresías 1 y 2 se registra en `audit_logs` sin reprocesar sus pagos originales.
