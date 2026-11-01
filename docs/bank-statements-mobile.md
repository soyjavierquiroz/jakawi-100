# Extractos desde el celular — Fase 11B

Acceso: https://jakawi.com/admin/extractos-bancarios, también desde el botón
«Extractos bancarios» de `/admin/conciliacion-membresias`. Requiere la sesión y
el rol administrador de COM existentes; no hay otra contraseña.

1. Seleccionar el PDF digital descargado de GanaMóvil.
2. Pulsar **Importar y procesar** y mantener abierta la pantalla hasta ver el resultado.

Banco, cuenta y período se leen del documento. Se reutilizan el parser probado,
el original privado, el historial y las huellas de Fase 11A, sin IA. El archivo
idéntico abre su historial anterior; los períodos solapados se indican y los
movimientos repetidos no se incorporan de nuevo. Los alias de cuenta de 11A se
reutilizan cuando están vinculados a la cuenta extraída. Un vínculo ambiguo se
rechaza para evitar repartir la misma cuenta entre identidades diferentes.

La vista de resultado conserva fecha, transacción, descripción, débito, crédito,
saldo y estado, con tarjetas en 390 px y tabla en escritorio. Incluye cantidades
y sumas de créditos y débitos, nuevos, duplicados, conciliados y sin identificar.
Las candidatas se obtienen del motor de conciliación existente, con páginas de
40 compras pendientes. «Revisar y conciliar en COM» abre el módulo de aprobación
actual. Importar o encontrar Bs 100 nunca activa una membresía. La aprobación
explícita mantiene el flujo `membership.activated` existente y las reglas de CLUB.

Solo PDF digital del formato GanaMóvil probado, máximo 10 MB. Los archivos
inválidos, escaneados o con formato desconocido muestran un error y no crean
movimientos. La validación incluye MIME, extensión, sesión admin y CSRF. El
original queda en `membership-private`, sin acceso público. El servidor procesa
sincrónicamente; el botón muestra «Subiendo y procesando PDF…» durante la espera.
No se añaden servicios, migraciones, contraseñas o integraciones bancarias.

Pruebas y capturas con datos inventados: `artifacts/phase11b/`. La muestra real
solo se valida en `db-test/jakawi_test` y permanece fuera del repositorio y LIVE.
