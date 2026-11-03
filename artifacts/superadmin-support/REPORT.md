# COM — Ver como usuario / Partner

Herramienta: `/admin/soporte`; Dossis 43: `/admin/soporte?partner=1`.
También se enlaza desde la consulta de usuarios, detalles de membresía y Partners.

Se requiere sesión COM autenticada, política `SupportSessionPolicy::create`
(`is_admin` e `is_superadmin`), contraseña reciente y TOTP vigente si MFA está habilitado.
La reautenticación vale cinco minutos y se consume al iniciar soporte.
El nuevo permiso nace desactivado para todas las cuentas. No se asigna por email.
El solicitante se resolvió por consulta de lectura como Javier Quiroz, usuario #1,
`is_admin=true`; la concesión adicional al usuario #1 fue autorizada explícitamente en esta conversación y se audita por separado, sin atribuirla a una sesión web ficticia.

La identidad persistente de autenticación sigue siendo el administrador. Sólo se
resuelve el usuario observado durante la petición. El registro conserva UUID,
actor, destino, Partner opcional, motivo, inicio, vencimiento y fin efectivo.
El fin efectivo queda inicialmente fijado al vencimiento de 15 minutos, incluso
si se cierra el navegador; una salida anticipada registra su fecha y causa.
Se regeneran ID de sesión y CSRF al reautenticar, entrar y salir. El registro
está vinculado al administrador y al hash de la sesión, y se revisa su permiso
en cada petición. No hay cadenas de observación ni destinos administrativos.

Se muestran Perfil, Mi JAKAWI, tipo/estado real de membresía, beneficios y catálogo
público; para representantes autorizados: portal, promociones, experiencias,
reservas, retos y desbloqueos del Partner. Se conservan middleware y políticas
originales del destino. Cada lectura se ejecuta en una transacción PostgreSQL
READ ONLY y se revierte al terminar, junto con cambios incidentales en el estado
de sesión. Se suprimen analítica, atribución y generación implícita de referidos.
No se modifica lógica de membresías, pagos ni comisiones.

Sólo se permite una lista cerrada de GET/HEAD revisados. Los demás endpoints,
las escrituras, exportaciones, recibos privados, PIN, tickets, OIDC, accesos CLUB,
CRM y ajustes económicos quedan bloqueados antes del controlador. Una pantalla
bloqueada muestra una explicación y el botón para regresar. Las respuestas de
soporte no se almacenan en caché. No se crea sesión CLUB ni cambia global_subject_id.

Dossis 43 | Coffee (#1, `dossis43`) está publicado y es candidato de soporte.
Su asociación existente es usuario #168, owner, `economic_access=false`.
La política preexistente da a los owners visibilidad económica por su rol;
soporte no modifica esa política ni concede el indicador `economic_access`.
Las rutas económicas quedan fuera de la lista de pantallas de soporte.
No se crean ni reemplazan representantes.

Validación: PostgreSQL aislado, cuentas sintéticas, comprobación de administrador
normal rechazado, gratuito, Pionero, owner y manager sin permiso económico,
Partner no asociado, aislamiento entre Partners, mutaciones y GET sensibles,
escritura GET implícita rechazada por PostgreSQL, expiración, salida, restauración,
revocación y vínculo de sesión. Evidencias de ejecución en `tests-final.log`,
`frontend-final.log`, `regression-final.log` y `browser.json`. Las 13 pruebas de soporte pasaron (155 aserciones); la pasada adicional de regresión y límites de reautenticación incluye 22 casos. Las capturas 390/1440 usan sólo fixtures
locales de prueba. No se crean sesiones de soporte LIVE para probar la función.

La entrega operativa específica conserva imágenes y manifiestos anteriores,
respaldo PostgreSQL y hashes de tablas comerciales e identidad. Sólo aplica
la migración aditiva de soporte y sustituye app/web/worker/scheduler COM.
No modifica contenedores de CLUB, CRM u otros sitios. Evidencia LIVE y SHA:
`/opt/sites/jakawi.com/artifacts/superadmin-support/LIVE.json`.
