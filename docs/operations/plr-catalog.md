# Catálogo PLR español COM → STORE

Base: COM verificado `69f2c11cf2189f3f31c7075a926070c5ee852f89` y STORE protegido de esa integración. Glúteo Perfecto #1 se conserva: el importador rechaza su slug e identificador EvaFunnels. No se precargan productos sin paquetes.

## Subir por lote

SFTP: `store_upload@167.71.107.80`, puerto 22, misma clave autorizada. Entrar a **`/incoming/`**. Ruta real: **`/srv/sites/jakawi.store/incoming/`**. El directorio inicial histórico sigue siendo Glúteos: cambiar a incoming antes de subir.

```text
sftp -i /ruta/a/clave store_upload@167.71.107.80
cd /incoming
lcd /ruta/local/paquetes-espanoles
put *.zip
# O carpeta descomprimida:
put -r nombre-paquete-es
```

Una app/variante española por ZIP o carpeta. Completar la transferencia antes de importar; usar `.part` y renombrar al finalizar si el cliente lo permite. Los originales se conservan. Las instalaciones nuevas pertenecen a root y SFTP no puede reemplazarlas.

## Importar

El operador con shell/root ejecuta; SFTP continúa sin shell:

```bash
# Valida sin instalar ni conectar a COM:
jakawi-plr-import --check /srv/sites/jakawi.store/incoming/*.zip
# Lote:
jakawi-plr-import /srv/sites/jakawi.store/incoming/*.zip
# Carpeta:
jakawi-plr-import /srv/sites/jakawi.store/incoming/nombre-paquete-es
```

Solo requiere Python 3 estándar y Docker existentes. La instalación de infraestructura usa el lector YAML ya presente en el host. No hay panel ni dependencias nuevas.

Familia soportada: un index.php con AFP_APP_SLUG, AFP_LICENSE_KEY y AFP_API_URL original, app.js, sw.js, manifest.json, config-override.json e iconos locales. Rechaza archivos ausentes, varios index, PHP adicional, ZIP inseguro, enlaces, más de 512 MiB/10000 archivos, idioma no español e identificadores duplicados. Paquetes de otra estructura requieren revisión.

Extrae nombre, descripción e idioma del paquete. Deriva el slug técnico del identificador EvaFunnels convirtiendo guiones bajos a guiones. `--check` muestra el resultado. Para aportar datos reales o escoger slug, usar JSON externo y una sola app:

```bash
jakawi-plr-import --metadata /ruta/propietario/app.json /srv/sites/jakawi.store/incoming/app.zip
```

Campos opcionales: slug, name, description, category, cover (ruta local relativa). No incluir licencias/autorizaciones. Los valores iniciales editables son BOB 49 público / 29 miembro; no autorizan una venta. Categoría debe existir en COM; sin categoría/descripción se guarda null. Sin nombre se detiene. Usa la portada local o la URL original indicada por EvaFunnels (HTTPS, dominios de assets conocidos y máximo 10 MiB) y la almacena en MinIO. Si no está disponible, conserva el icono original provisional con aviso. No genera imágenes comerciales. Deriva la descripción corta del tagline o descripción real y asigna fitness/wellness solo ante indicios claros; las categorías ambiguas quedan vacías. No reutiliza soporte de otro producto: solo podría usarse una configuración global confirmada; no existe actualmente.

## Resultado y reintento

- Instalación: `/srv/sites/jakawi.store/apps/{slug}/public/`.
- Registro sin licencia: `/srv/sites/jakawi.store/catalog/records/{slug}.json`.
- Gateway/guard/worker: `/srv/sites/jakawi.store/catalog/apps/{slug}/access/`.
- Rutas explícitas: `/srv/sites/jakawi.store/catalog/routes/{slug}.conf`.
- URL: `https://jakawi.store/{slug}/`.

Mantiene licencia y Host real jakawi.store; no simula la validación de EvaFunnels. Activa verificación TLS cuando el paquete la desactiva. Manifest tiene id/start_url/scope propios. Worker obligatorio solo red, sin contenido offline ni cachés compartidas. Reutiliza pase COM, revalidación por request, cookie por slug y supervisión de pestaña. Assets adicionales exigen autorización; manifest/worker/supervisor no contienen contenido privado.

Mismo paquete y metadatos: devuelve el producto existente sin modificar precios/publicación/permisos. Paquete distinto, identificador duplicado o archivos instalados modificados: bloqueo. No hay force indiscriminado; un reemplazo requiere revisión y respaldo, conservando producto/derechos. Fallo COM: queda installed_pending_com; repetir retoma el borrador. Fallo Nginx: retira ese include. Un error de lote no impide procesar los siguientes; salida 1 si alguno falló.

## COM y publicación

Aparecen en **Administración → Productos digitales**, `https://jakawi.com/admin/productos-digitales`: Borrador, hosted_app, app_key vinculado al slug, partner JAKAWI Digital, moneda BOB, precios público/miembro 49/29 editables, venta y regalos desactivados. La moneda inicial es el valor por defecto existente; revisarla junto con precios. No crea pedidos, grants ni usuarios. El registro AppSetting hosted_app_catalog deriva URLs exclusivamente de STORE.

Para publicar: registrar legítimamente jakawi.store en EvaFunnels; comprobar salud y acceso protegido real desde COM; editar borrador; revisar portada/contenido/categoría/soporte/términos; configurar moneda y precios público/miembro; guardar QR y validación bancaria; confirmar entrega definitiva; habilitar venta mediante el flujo existente y publicar solo al completar requisitos. El único Explorador conserva sus reglas actuales. No se concede autorización comercial al importar. Guardar producto omite la autorización de venta y envía booleanos multipart como 1/0. Habilitar venta es una operación separada y exige los requisitos comerciales. Cada APP nueva tiene configuración comercial individual; Glúteos conserva la configuración anterior.

Pagos intactos: QR estáticos y validación bancaria actuales. Coincidir en importe no demuestra reutilización: solo usar un QR ya verificado si el banco permite reutilizarlo y corresponden moneda, importe y cuenta; conservar confirmación por producto. No se copian ni confirman QR automáticamente. El modo existente es de importe fijo: una nueva confirmación no admite el mismo archivo para 49 y 29, sin evidencia bancaria de un modo compatible; no existe autorización automática para ello.

## Glúteos adoptado como instalación existente

La operación puntual reutiliza COM #1, app_key/URL `gluteos`, identificador EvaFunnels original y archivos existentes. No ejecuta el escritor COM ni reinstala o completa datos comerciales:

```bash
jakawi-plr-import --adopt-existing gluteos
```

Repetirla comprueba el registro y huellas y devuelve unchanged. Una importación ordinaria de ese identificador reconoce que está adoptado y bloquea duplicación/reemplazo; no modifica COM ni usa los valores iniciales de productos nuevos.

Glúteos y Pilates comparten el mismo patrón:

```text
apps/{slug}/public/
catalog/apps/{slug}/access/{gateway.php,access-guard.js,sw.js}
catalog/apps/{slug}/route.conf
catalog/records/{slug}.json
catalog/routes/{slug}.conf
```

Slugs: `gluteos` y `pilates-app-es`. El registro de Glúteos indica origin=adopted_existing, COM #1 y huella instalada; package_sha256 es null porque no se inventa un ZIP original. Los archivos de protección se copian byte por byte y Nginx usa un solo include canónico. Las ubicaciones antiguas en `/opt/sites/jakawi.store/access/` quedan como enlaces de compatibilidad, sin copias ni rutas activas contradictorias; `/opt/sites/jakawi.store/catalog` apunta al catálogo persistente. Los archivos públicos, manifest, licencia y cachés del worker existente no se reescriben. Solo se recarga Nginx; PHP y sus sesiones permanecen.

Antes de cambiar rutas se guardan nginx.conf y access en `/var/backups/jakawi.store/adopt-gluteos-FECHA/`. El registro gluteos.json contiene adoption_backup con la ruta exacta. Rollback de esta adopción:

```bash
python3 /var/backups/jakawi.store/adopt-gluteos-FECHA/rollback.py
```

Restaura las rutas y protección anteriores, retira el registro/include adoptados y conserva COM, archivos públicos y sesiones. Los protectores canónicos quedan inactivos temporalmente para solicitudes en curso; no se crea otra instalación. Evidencia de comparación comercial, Javier/Janny, sesión anterior y Pilates: `/srv/workspaces/jakawi/com/adopt-gluteos-catalog-evidence/`.

## Completar importados y Pilates #2

Pilates en casa ya está instalado como producto #2 y slug pilates-app-es. Para completar únicamente campos vacíos usando el mismo importador:

```bash
jakawi-plr-import --fill-missing /srv/sites/jakawi.store/incoming/Pilates.zip
```

No sobrescribe nombre, slug, descripciones, precios, moneda, portada, categoría ni permisos ya guardados. Si un precio editado es incompatible con el otro valor predeterminado, no lo reemplaza ni instala un par inválido. La portada ya existente también se conserva. No modifica estado editorial ni permisos comerciales/regalos. Una reimportación normal sigue siendo idempotente y no vuelve a aplicar valores iniciales.

La verificación focalizada de esta corrección incluye guardado multipart de Pilates en Admin, borrador con 49/29 sin QR, venta incompleta bloqueada, importación/reimportación, conservación de ediciones y Glúteos intacto. No se conceden derechos de prueba ni se repite la implementación STORE/puente.

## Releases y rollback

COM se publica exclusivamente mediante `/opt/sites/jakawi.com/release-control.py`, con las acciones build, prepare, deploy, enrich, verify y promote. Sin migración nueva ni cambios CLUB/CRM. El procedimiento STORE actualiza solo catalog-tools/import-plr.py, con copia anterior en el backup; no recrea Nginx/PHP ni modifica archivos instalados o el puente.

Evidencia: `/srv/workspaces/jakawi/com/plr-admin-defaults-evidence/`. LIVE.json contiene SHA y backup exactos. Rollback:

```bash
JAKAWI_REPOSITORY=/srv/workspaces/jakawi/com/plr-admin-defaults \
JAKAWI_RELEASE_EVIDENCE=/srv/workspaces/jakawi/com/plr-admin-defaults-evidence \
python3 /opt/sites/jakawi.com/release-control.py rollback
```

Restaura imágenes y configuración COM anteriores y el importador anterior. Conserva datos editoriales, precios, derechos y compras actuales; no restaura DB. El dump validado se conserva para recuperación manual. Para la infraestructura inicial STORE permanece disponible el rollback protegido documentado en el backup de la release anterior; no se necesita ejecutarlo en esta corrección.

## Predeterminados editoriales de JAKAWI Digital

`app/config/jakawi-digital.php` contiene una única definición en texto plano de
soporte y condiciones PLR. `HostedAppCatalogImport` la consulta al crear y con
`--fill-missing`; este último solo completa campos vacíos. La reimportación
normal conserva todos los valores del propietario. Admin permite editar ambos
campos por producto. No cambia permisos, QR, publicación ni valores comerciales
existentes y excluye Glúteos. El CLI STORE permanece sin cambios.

Explorar consulta la autorización comercial individual `canSell()` para el
aviso editorial de cada app; no usa la antigua excepción exclusiva de Glúteos.

## Categorías PLR

El catálogo existente de `config/jakawi.php` define nombres y slugs. La lista
digital amplía las opciones existentes sin alterar categorías ni intereses de
negocios físicos. Admin y Explorar reciben las etiquetas del mismo catálogo.
Las categorías editoriales nuevas filtran únicamente productos digitales.

El importador contiene el mapa editorial de 36 títulos. Prioriza identificadores
comprobados contra paquetes originales y exige un título real compatible; los
identificadores aún no comprobados solo se asocian por un nombre español exacto
del paquete. No infiere categorías por palabras de la descripción. Los títulos
dudosos o en conflicto quedan sin categoría. El registro existente vincula
identificador y huella del paquete; los metadatos manuales tienen prioridad.

La release completa únicamente categorías vacías de importados registrados y
con huella instalada válida. Conserva categorías seleccionadas y todos los demás
campos, incluyendo precios, permisos, publicación y QR. No crea productos para
paquetes ausentes. Glúteos adoptado se excluye del completado.

## Publicación simplificada y QR compartidos por importe

El baseline de esta ampliación es COM LIVE/main `64d21884e1078dcfc5fcae15f739a3ac46489b8d`.
Hay 13 apps instaladas en este servidor; disponer de 112 licencias no equivale a disponer de los 112 paquetes aquí. El importador existente aplica un diccionario de títulos editoriales exactos (58), con IDs reales fijados cuando el paquete está disponible. Un ID fijado con título contradictorio queda pendiente. No clasifica por fragmentos ni descripciones; los títulos desconocidos quedan sin categoría para revisión. `Té Quemagrasas` sigue pendiente. Las ocho categorías adicionales tienen slugs estables en `config/jakawi.php`. El completado de categorías modifica solo celdas vacías de importaciones cuyo ID y huella coinciden; no modifica campos configurados de Glúteos #1 o Pilates #2.

El prellenado existente conserva nombre/slug reales, descripciones originales disponibles, categoría fiable, portada MinIO, partner JAKAWI Digital, hosted_app/STORE, BOB 49/29, soporte y condiciones globales. Cuando el paquete no aporta descripción no se inventa. Importar sigue creando únicamente borradores con regalos y venta desactivados; `--fill-missing` conserva ediciones y precios personalizados.

En **Administración → Cobro digital → QR compartidos por importe** (`/admin/digital/cobro`):

1. Autorizar la cuenta bancaria real.
2. Subir QR BOB 49 y constancia PDF del banco, registrar referencia/condiciones, vigencia y confirmar permiso real para reutilización entre clientes/productos.
3. Repetir para BOB 29. No usar la misma imagen para distintos importes.

La aplicación no establece si Banco Ganadero admite un QR concreto: el propietario debe obtener y verificar esa constancia. Sin ella no se habilita el recurso compartido. El archivo se guarda una vez en MinIO privado, con importe, moneda, cuenta/versionado, hash, verificador y vigencia. Puede desactivarse desde la misma sección. Cambiar cuenta, moneda, importe, vigencia o reemplazar/desactivar el recurso invalida su disponibilidad; cada nueva orden conserva su instantánea y referencia propia. No cambia la validación de ingresos, comprobantes ni protección cruzada de referencias de pago.

En el producto seleccionar **QR compartidos (predeterminado)** o **QR específicos del producto**. Las configuraciones específicas existentes se conservan hasta que el propietario elija cambiar. Al guardar modo compartido se retiran las asociaciones específicas; no se copian imágenes por producto. Un precio distinto exige su propio recurso verificado o QR específico. No se confirman QR compartidos nuevamente por producto.

**PUBLICAR Y ACTIVAR VENTA** guarda los archivos y campos editados, comprueba protección/funcionamiento STORE, contenido obligatorio, partner publicado, precios/cuenta y ambos QR. El clic es la autorización comercial expresa, registrada con actor y fecha. Para APP no requiere confirmar manualmente entrega técnica. Si falta un requisito comercial, guarda los datos válidos y conserva el estado de publicación/autorización previo; no concede una autorización nueva. Muestra requisitos concretos. **Guardar borrador** sigue siendo independiente. El listado muestra entrega, categoría, ambos precios, estado de venta y requisitos.

Los reemplazos de QR, portada y galería registran primero el objeto nuevo y confirman BD. Después eliminan el anterior solo si no aparece en ninguna fila persistida, incluyendo órdenes de cualquier estado, regalos, configuraciones, landings y auditoría. Los PDFs, comprobantes y constancias bancarias nunca se eliminan mediante esta limpieza. Solo admite rutas UUID gestionadas en `digital-private/qr/` e imágenes `digital-products/{id}/{cover|image}/`; ante fallo o ruta dudosa conserva y registra el resultado.

Revisión de cargas antiguas fallidas, idempotente y limitada a esos prefijos:

```bash
php artisan digital:cleanup-assets          # solo informe
php artisan digital:cleanup-assets --apply  # elimina únicamente sin referencias y con más de 24h
```

El scheduler existente ejecuta esa limpieza diaria a las 03:20 de Bolivia. No añade servicios ni almacenes. Las rutas recientes o desconocidas se conservan y cuentan en el informe.

Release: `/opt/sites/jakawi.com/release-control.py` parametrizado para esta fase. COM: build/prepare/deploy/enrich/verify/promote; STORE, independiente: store-prepare/store-deploy/store-verify. Evidencias en `/srv/workspaces/jakawi/com/plr-simple-publish-evidence/`, con `LIVE.json`, `STORE-LIVE.json` y los informes de limpieza. `rollback` restaura COM; `store-rollback` restaura solo importador/documentación STORE. Ambos conservan compras, permisos y ediciones actuales; no restauran automáticamente DB. No se activan productos reales durante la release.
