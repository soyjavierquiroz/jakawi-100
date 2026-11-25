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
