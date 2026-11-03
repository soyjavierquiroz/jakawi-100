# Gestión Partner COM

Entrada: `/admin/soporte?partner=1`, motivo y «Gestionar Partner». Se mantiene «Ver como Partner» de sólo lectura y «Ver como usuario».

Reutiliza el Portal Partner y los editores existentes: perfil/presentación, logo/portada/galería, sedes y horarios; promociones/beneficios, experiencias/sesiones, retos y desbloqueos. Los nuevos enlaces del perfil reutilizan el formulario y las validaciones del catálogo, con lista cerrada de campos. El estado de publicación del comercio/sede, PIN, códigos de referido y controles internos no son editables allí. Nuevas sedes nacen en borrador.

Las promociones comerciales son beneficios; los retos y desbloqueos ofrecen los flujos promocionales ya existentes. El motor de campañas internas/landings y su publicación administrativa no pertenece al Portal Partner. No se añade un motor de campañas, pagos ni economía. Los contenidos se envían a revisión por el flujo original; no se aprueban ni publican desde soporte.

La autenticación y las atribuciones normales de autor mantienen al administrador real. La asociación existente owner/manager sólo resuelve la autorización del Partner. Cada petición revalida actor, vencimiento y vínculo de sesión. Las lecturas conservan transacciones READ ONLY; sólo métodos/rutas editoriales enumerados pueden confirmar escrituras. Auditoría normal ampliada con actor, sesión, Partner, ruta, registro y valores anteriores/nuevos; la transacción incluye los registros de auditoría. Una experiencia compartida con otros Partners no puede editarse desde este modo.

No se prueban escrituras ni sesiones de soporte en LIVE. Pruebas con PostgreSQL y cuentas sintéticas; evidencia de pruebas e interfaz 390/1440 en este directorio. El despliegue sólo sustituye servicios COM y añade una columna de modo a soporte.

Resultado final: 23 pruebas, 321 aserciones, sin fallos; TypeScript y build/manifest correctos. Navegador 390/1440 PASS: guardado real de perfil y promoción en PostgreSQL aislado, aislamiento entre Partners, barra con actor real y retorno a administración. Se validó la propuesta promocional de desbloqueo y su envío a revisión en las pruebas HTTP. Las pruebas iniciales detectaron una ruta incorrecta de contraseña en el test y una regresión en indicadores del modo de lectura; ambas están corregidas en la ejecución final.

LIVE: `f4ebbba4fa4db62dd5af97311a61e6edb9cb2bd6`, COM app/web/worker/scheduler, salud 200. Otros 39 contenedores sin cambios; datos comerciales preservados. No se iniciaron sesiones ni operaciones comerciales de prueba en LIVE. `LIVE.json`, `tests-final.log`, `frontend-final.log`, `browser.json` y capturas 390/1440 contienen la evidencia. El commit posterior de evidencia sólo documenta esta implementación LIVE y ajusta el aislamiento del navegador de prueba.
