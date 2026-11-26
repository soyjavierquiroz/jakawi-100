# Administración del catálogo digital

La pantalla existente `/admin/productos-digitales` usa paginación de servidor (25/50/100), búsqueda por nombre, slug, categoría e identificador APP, filtros combinables y orden en URL. La selección explícita abarca únicamente la página visible; cambiar criterios o página la vacía.

La tabla tiene tarjetas equivalentes en móvil y requisitos desplegables. La edición rápida abre el mismo panel de lote con un único ID. El panel modifica exclusivamente campos incluidos; la categoría reemplaza explícitamente la anterior y los textos globales solo completan campos vacíos de APP. El preset Bs 49/Bs 29 se ofrece para productos BOB. Los importes manuales conservan la moneda actual.

`POST /admin/productos-digitales/lote` conserva los middleware auth/admin. La fase preview no modifica productos ni QR. Retorna diferencias y bloqueos por producto y una confirmación cifrada con usuario, IDs, cambios y vencimiento de 15 minutos. Apply exige esa confirmación, vuelve a validar bajo bloqueo de fila y de cuenta y registra auditoría. Los productos bloqueados se omiten íntegramente; las transacciones por producto conservan sus campos. Publicación llama `DigitalSaleAvailability::publishAndSell`, la misma autorización individual; no modifica regalos ni derechos. No hay selección implícita de todos los resultados.

La clasificación para contadores y filtros se hace en servidor por bloques de 200 registros sin galerías; solo se serializa la página solicitada. La instantánea de lectura dura una petición: precarga configuraciones, comparte comprobaciones de integridad QR y consulta health de APP por lotes con el mismo contrato y TTL existentes. Ninguna mutación usa esa instantánea como autorización. Las publicaciones vuelven a comprobar la protección de la APP.

Pruebas: `DigitalAdminCatalogTest`, `PlrSimplePublishTest`, `DigitalAdminProductSaveTest`, `DigitalSaleActivationTest`; TypeScript, build y smoke visual desktop/móvil. Pruebas mutables exclusivamente en PostgreSQL aislado. El smoke LIVE solo lee y revisa vistas previas.

Release mediante `/opt/sites/jakawi.com/release-control.py`, fuente versionada en `docker/new-server/digital-admin-premium-release-control.py`. Backup PostgreSQL validado y Compose anterior antes de deploy. Verify compara íntegramente productos, settings, compras, derechos, regalos y archivos STORE; comprueba servicios, manifest y smoke LIVE. Promote solo después de LIVE PASS. Rollback usa las imágenes y Compose anteriores sin restaurar la base de datos.
