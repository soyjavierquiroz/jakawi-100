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

Campos opcionales: slug, name, description, category, cover (ruta local relativa). No incluir precios/licencias/autorizaciones. Categoría debe existir en COM; sin categoría/descripción se guarda null. Sin nombre se detiene. No descarga imágenes remotas: usa portada local o icono original provisional en MinIO, reemplazable antes de publicar.

## Resultado y reintento

- Instalación: `/srv/sites/jakawi.store/apps/{slug}/public/`.
- Registro sin licencia: `/srv/sites/jakawi.store/catalog/records/{slug}.json`.
- Gateway/guard/worker: `/srv/sites/jakawi.store/catalog/apps/{slug}/access/`.
- Rutas explícitas: `/srv/sites/jakawi.store/catalog/routes/{slug}.conf`.
- URL: `https://jakawi.store/{slug}/`.

Mantiene licencia y Host real jakawi.store; no simula la validación de EvaFunnels. Activa verificación TLS cuando el paquete la desactiva. Manifest tiene id/start_url/scope propios. Worker obligatorio solo red, sin contenido offline ni cachés compartidas. Reutiliza pase COM, revalidación por request, cookie por slug y supervisión de pestaña. Assets adicionales exigen autorización; manifest/worker/supervisor no contienen contenido privado.

Mismo paquete y metadatos: devuelve el producto existente sin modificar precios/publicación/permisos. Paquete distinto, identificador duplicado o archivos instalados modificados: bloqueo. No hay force indiscriminado; un reemplazo requiere revisión y respaldo, conservando producto/derechos. Fallo COM: queda installed_pending_com; repetir retoma el borrador. Fallo Nginx: retira ese include. Un error de lote no impide procesar los siguientes; salida 1 si alguno falló.

## COM y publicación

Aparecen en **Administración → Productos digitales**, `https://jakawi.com/admin/productos-digitales`: Borrador, hosted_app, app_key vinculado al slug, partner JAKAWI Digital, precios público/miembro vacíos y regalos desactivados. La moneda inicial es el valor por defecto existente; revisarla junto con precios. No crea pedidos, grants ni usuarios. El registro AppSetting hosted_app_catalog deriva URLs exclusivamente de STORE.

Para publicar: registrar legítimamente jakawi.store en EvaFunnels; comprobar salud y acceso protegido real desde COM; editar borrador; revisar portada/contenido/categoría/soporte/términos; configurar moneda y precios público/miembro; guardar QR y validación bancaria; confirmar entrega definitiva; habilitar venta mediante el flujo existente y publicar solo al completar requisitos. El único Explorador conserva sus reglas actuales. No se concede autorización comercial al importar.

Pagos intactos: QR estáticos y validación bancaria actuales. Coincidir en importe no demuestra reutilización: solo usar un QR ya verificado si el banco permite reutilizarlo y corresponden moneda, importe y cuenta; conservar confirmación por producto. Esta etapa no aplica reutilización ni cambia cobros.

## Segunda app

No se recibió/encontró otro paquete español real en los directorios revisados. **Validación con segunda app real pendiente de entrega**. No se duplicó Glúteos ni se crearon productos ficticios LIVE. Fixtures temporales aisladas comprueban idioma, archivos, ZIP seguro, manifest, rutas, instalación, reintento, bloqueo de reemplazo y borrador sin precios, además de las pruebas existentes del puente.

Al recibirlo: validar/importar una app; comprobar 403 directo, manifest/rutas, salud autenticada y biblioteca con derecho explícitamente autorizado, borrador COM, reimportación sin duplicados y hashes de Glúteos intactos. No conceder regalos/accesos automáticos para probar. Sin 36 pruebas de navegador.

## Releases y rollback

COM/STORE independientes; sin migración nueva ni cambios CLUB/CRM/pagos. Evidencia e imágenes previas: `/srv/workspaces/jakawi/com/plr-catalog-evidence/`; backup exacto indicado en LIVE.json.

STORE: `bash /var/backups/jakawi.store/plr-catalog-FECHA/rollback.sh`. Restaura compose/nginx/php.ini protegidos; conserva cargas/catálogo/apps y deshabilita rutas nuevas. Glúteos permanece protegido.

COM: ejecutar rollback-com.py desde evidencia. Restaura imágenes previas app/web/worker/scheduler sin restaurar DB ni borrar derechos/borradores posteriores. Si ya hay apps nuevas, revertir primero STORE para deshabilitar sus rutas: COM anterior solo conoce Glúteos. Nunca restaurar la configuración pública anterior a la protección.
