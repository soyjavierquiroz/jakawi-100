# COM vende, STORE entrega: Glúteo Perfecto

Registro explícito `config/hosted_apps.php`: `gluteos` -> `https://jakawi.store/gluteos/`.
Solo se añade `digital_products.app_key`; se amplía delivery_type y se permiten
snapshots PDF nulos en pedidos/regalos APP. Los productos, pedidos, regalos y
grant existentes se conservan. El PDF y sus hashes permanecen privados.

Desde la biblioteca, COM autentica y autoriza el DigitalAccessGrant. Genera un
pase aleatorio de 256 bits, guarda solo su SHA256 en la caché de base de datos
existente, TTL 60 segundos; consumo con lock atómico. La página de transferencia
no-store envía el pase por POST a STORE con Referrer-Policy strict-origin,
sin ruta ni pase en el referer. Esto conserva Origin COM para validar el POST
entre dominios (no-referrer lo convertiría en null en navegadores). STORE
solo acepta Origin COM y consume por HTTPS autenticado con secreto compartido.
La credencial cifrada de sesión nunca se envía al navegador: vive en la sesión
PHP de STORE, cookie HttpOnly Secure SameSite=Lax, solo /gluteos/, máximo 8 h.
No hay cuentas, contraseñas ni cookies compartidas. COM verifica estado activo,
revoked_at, entrega ready y pedido confirmado (o regalo) en cada consulta.

STORE revalida cada petición de contenido. Una pestaña abierta consulta cada
30 segundos y al volver a primer plano; si COM/red falla, oculta el contenido.
La revocación/reembolso deniega el siguiente request y la siguiente comprobación
del navegador (30 s con pestaña activa; al volver de suspensión en móviles).
Los navegadores suspendidos no ejecutan temporizadores. El service worker usa
solo red, sin fallback offline, y limpia únicamente cachés conocidas de Glúteos
o entradas /gluteos/ en cachés mixtas. No borra cachés de otras apps.
Una copia antigua ya instalada y totalmente desconectada no puede actualizarse
remotamente: debe reconectarse una vez para recibir la limpieza. No prometer
revocación retroactiva de bytes ya entregados antes de esta protección.

El gateway PHP, script de supervisión y service worker obligatorio viven fuera
del área SFTP, en /opt/sites/jakawi.store/access. Nginx solo ejecuta el gateway,
que requiere el index EvaFunnels después de autorizar. La API, licencia, Host
real, manifest, navegación y renderizado se conservan. El único cambio al index
generado es CURLOPT_SSL_VERIFYPEER=true. Originales privados en
/var/backups/jakawi.store/hosted-app-20261125/original. Después de actualizar el
paquete EvaFunnels, conservar el gateway y alias sw.js, volver a aplicar TLS y
comprobar _health autenticado, acceso directo 403 y acceso autorizado 200.
Los logs de acceso STORE registran $uri, sin query ni cuerpos. No hay tokens en
URLs. PHP, config, licencias, cachés, backups y rutas no autorizadas no se sirven.

## Release y rollback

COM: exclusivamente /opt/sites/jakawi.com/release-control.py, con
JAKAWI_REPOSITORY=/srv/workspaces/jakawi/com/hosted-app y
JAKAWI_RELEASE_EVIDENCE=/srv/workspaces/jakawi/com/hosted-app-evidence.
Acciones build, prepare, deploy (migración compatible antes de recrear), activate,
verify, promote. Backup previo completo de DB y configuración; rollback restaura
el método PDF y autorización previos y las imágenes retenidas, sin restaurar DB
ni borrar derechos posteriores. Mantener la ampliación compatible del esquema.

STORE: Compose existente, archivos cargados persistentes, solo web/php STORE.
Release protegida y hashes bajo /opt/sites/jakawi.store/releases/hosted-app-v1.
Rollback seguro: restaurar esa versión protegida con rollback-protected.sh. La
configuración previa sin protección se conserva para historia, nunca se publica
mientras COM entregue APP. Ante fallo ir a denegación, o volver COM a PDF y dejar
STORE bloqueado. No se modifica Traefik, CLUB ni CRM.
