# Glúteo Perfecto: presentación pública Próximamente

Producto #1 y Landing #24 mantienen sus identidades y Partner #29. Se usan los estados de publicación existentes para el catálogo y la URL pública; no hay migraciones. El bloque hero de la Landing conserva una marca editorial `availability: COMING_SOON`. Esta marca distingue visibilidad pública de disponibilidad comercial y permanece al editar o duplicar los bloques.

Solo los productos con una Landing publicada y esta marca se consideran Próximamente. El presenter y el checkout rechazan la compra de ese producto, incluso sin PRELAUNCH; el resto conserva sus reglas. El administrador puede guardar sus revisiones editoriales sin precio, PDF o portada mientras esté en este modo. PRELAUNCH sigue activo como bloqueo general independiente.

En esta presentación se conservan las once secciones. Indicadores, testimonios y garantía no verificados muestran el aviso de revisión y las notas de alcance. El hero muestra Disponible próximamente y los CTA solo navegan por la página. La oferta no presenta un precio inventado ni entrega inmediata.

`GluteoPerfectoVisibility` es una operación de release explícita, transaccional e idempotente. No crea productos, usuarios, órdenes, pagos, accesos ni configuración bancaria. Solo publica los dos registros existentes y ajusta la marca editorial, sus alcances y tres etiquetas CTA, preservando el resto del contenido.

Controlador canónico: `/opt/sites/jakawi.com/release-control.py`, fuente versionada `docker/new-server/gluteo-perfecto-coming-soon-release-control.py`. Verifica que el único cambio de checkout sea el rechazo de Próximamente, que no haya migraciones, que las demás filas del dominio sigan intactas y que los dos registros solo cambien en los campos autorizados. Exige pruebas/build/browser PASS, backup y LIVE PASS antes del fast-forward remoto.

El rollback restaura las imágenes COM anteriores y únicamente los campos editoriales de los dos registros desde una instantánea protegida. Rechaza sobrescribir cambios editoriales posteriores. Nunca restaura la base completa, modifica membresías ni recrea CLUB u otros servicios protegidos. Los fixtures y capturas previas al deploy se realizan en PostgreSQL aislado; las capturas LIVE son públicas y no ejecutan operaciones comerciales.
