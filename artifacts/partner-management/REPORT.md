# Gestión Partner COM

Entrada: `/admin/soporte?partner=1`, motivo y «Gestionar Partner». Se mantiene «Ver como Partner» de sólo lectura y «Ver como usuario».

Reutiliza el Portal Partner y los editores existentes: perfil/presentación, logo/portada/galería, sedes y horarios; promociones/beneficios, experiencias/sesiones, retos y desbloqueos. Los nuevos enlaces del perfil reutilizan el formulario y las validaciones del catálogo, con lista cerrada de campos. El estado de publicación del comercio/sede, PIN, códigos de referido y controles internos no son editables allí. Nuevas sedes nacen en borrador.

Las promociones comerciales son beneficios; los retos y desbloqueos ofrecen los flujos promocionales ya existentes. El motor de campañas internas/landings y su publicación administrativa no pertenece al Portal Partner. No se añade un motor de campañas, pagos ni economía. Los contenidos se envían a revisión por el flujo original; no se aprueban ni publican desde soporte.

La autenticación y las atribuciones normales de autor mantienen al administrador real. La asociación existente owner/manager sólo resuelve la autorización del Partner. Cada petición revalida actor, vencimiento y vínculo de sesión. Las lecturas conservan transacciones READ ONLY; sólo métodos/rutas editoriales enumerados pueden confirmar escrituras. Auditoría normal ampliada con actor, sesión, Partner, ruta, registro y valores anteriores/nuevos; la transacción incluye los registros de auditoría. Una experiencia compartida con otros Partners no puede editarse desde este modo.

No se prueban escrituras ni sesiones de soporte en LIVE. Pruebas con PostgreSQL y cuentas sintéticas; evidencia de pruebas e interfaz 390/1440 en este directorio. El despliegue sólo sustituye servicios COM y añade una columna de modo a soporte.
