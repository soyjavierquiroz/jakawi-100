# Glúteo Perfecto: primer borrador digital

El producto usa el Partner existente JAKAWI Digital #29, sin sede. La creación se ejecuta explícitamente mediante `GluteoPerfectoDraft`, dentro de una transacción y con bloqueo del Partner. Es idempotente y conserva las revisiones editoriales. No hay seeder en el arranque ni creación desde una petición pública.

`LandingPresentation.editorial_blocks` es una extensión JSON opcional. Las presentaciones anteriores conservan sus campos y contratos. Once tipos reutilizables contienen únicamente contenido editorial. El adapter digital utiliza `OfferConversionTemplate`, el diseño y la navegación actuales. Producto, precios, membresía, entrega y checkout proceden del modelo y servicios digitales existentes; los bloques no admiten precios ni endpoints comerciales. Los archivos privados no se serializan.

El Builder permite guardar, duplicar, previsualizar 390/1440 y publicar con un producto preparado. Los indicadores, testimonios y garantía requieren verificación y fuente para mostrarse públicamente. El material original íntegro está disponible dentro del editor como referencia; el borrador de conversión evita las cifras fisiológicas y los plazos universales. No hay contador, precios USD del original, Pix ni componentes de entrega sin configurar.

Pendientes para publicación: PDF definitivo; portada/galería autorizadas; precio público y, opcionalmente, de miembro; condiciones de acceso y una garantía única; soporte/contacto y enlaces legales reales; fuentes y permisos de testimonios/indicadores; configuración bancaria digital autorizada y aprobación editorial/comercial. El borrador se puede editar sin estos datos. La compra permanece bloqueada por PRELAUNCH.

La única migración añade tres columnas nullable: `landing_presentations.editorial_blocks`, `digital_products.category`, `digital_products.gallery_paths`. No altera órdenes, pagos, accesos, membresías ni configuración. Es compatible con las imágenes anteriores.

El controlador exclusivo es `/opt/sites/jakawi.com/release-control.py`, con fuente versionada en `docker/new-server/gluteo-perfecto-release-control.py`. Prepare exige candidato limpio, remoto verificado, pruebas y browser PASS; respalda imágenes, manifiesto y PostgreSQL y valida el dump. Deploy aplica solamente la migración revisada y crea producto/Landing DRAFT. Verify coteja fuentes, assets, esquema aditivo, huellas del dominio existente, Partner #29 único, borrador único y ausencia de ventas/cobros. Main avanza únicamente después de LIVE PASS.

Rollback: `release-control.py rollback-plan` valida los manifiestos anteriores y realiza un dry run. `release-control.py rollback` restaura solo imágenes y manifiestos COM; conserva el borrador real y las columnas nullable, sin restaurar la base ni borrar datos editoriales. No ejecutar `migrate:rollback` en LIVE. La evidencia privada incluye las variables exactas de cada release y el respaldo. CLUB y CRM son servicios protegidos; no se recrean.
