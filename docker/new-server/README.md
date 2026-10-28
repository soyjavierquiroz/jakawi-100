# Build y candidato para el servidor nuevo

Estos archivos no despliegan ni reemplazan `/opt/sites/jakawi.com/compose.yaml`.
El Dockerfile histórico sigue disponible; este target usa PHP 8.4 Alpine,
assets generados antes de construir las imágenes, Nginx separado y el entrypoint
oficial `docker-php-entrypoint`. No incluye PostgreSQL, imgproxy, CRM ni Traefik.

## Build desde Git

Usar un checkout privado limpio, sin `.env`, `vendor`, `node_modules`, caches PHP
ni assets heredados. Se necesitan Docker con constructor clásico, systemd y Node
24.11 o posterior. En el ensayo se usó Node 24.22.1. Los scripts PHP se ejecutan
exclusivamente en Docker. Revisar RAM disponible antes de cada fase; no iniciar
otro build al mismo tiempo. El script no decide por sí solo si el host tiene
memoria suficiente. No ejecutarlo desde LIVE.

```sh
NODE_BIN=/opt/node-v24.22.1-linux-x64/bin bash docker/new-server/build.sh
```

Composer instala el lock sin plugins/scripts y sin paquetes de desarrollo;
`package:discover` se ejecuta explícitamente en red `none`. Npm usa `npm ci`.
`types:check` genera Wayfinder con form variants y ejecuta `tsc --noEmit`;
`build` vuelve a generar rutas, compila React/Tailwind y valida todas las páginas
Inertia y referencias del manifest. No se toma `public/build` de producción.
La imagen final comparte exactamente el public recién compilado con web.
El script copia vendor a un checkout limpio; no sirve para actualizar un vendor
preexistente. Repetir desde otro checkout limpio para otra release.

Las bases PHP/Composer/Nginx están fijadas por digest. Los paquetes APK y las
fuentes descargadas por el plugin de fonts siguen dependiendo de repositorios
externos; esta prueba demuestra reproducción funcional, no un build hermético
ni igualdad binaria. Conservar lockfiles, logs, manifest y los IDs/exportaciones
de las imágenes por release. El builder clásico se eligió por sus límites
explícitos de RAM/CPU; migrar a BuildKit requiere configurar límites equivalentes.

## Compose candidato

`compose.candidate.yaml` es una definición completa para revisión. No es un
override para combinar a ciegas con el Compose histórico. Requiere APP_IMAGE y
WEB_IMAGE de la misma release, DB_IMAGE por digest, rutas absolutas privadas
APP_ENV_FILE/DB_ENV_FILE y STATE_ROOT. Ningún secreto va en Git. No contiene
build; `pull_policy: never` exige tener las imágenes verificadas localmente.

Mantiene redes existentes como externas, storage/PostgreSQL en `/srv/sites`,
alias web, límites, colas y perfil `production-jobs`. App/worker/scheduler esperan
DB healthy. El healthcheck usa POSTGRES_USER y DB_HEALTH_DATABASE del manifiesto
de release; no confundir POSTGRES_DB de inicialización con la base efectiva.
Verificar además una consulta SELECT en
esa base. Conserva logging 5m x 3. Retira wrapper y su bind de inicio.

Las caches son propias de cada contenedor: no vienen de LIVE ni sobreviven a
una recreación. Antes de servir tráfico, ejecutar explícitamente
`php artisan package:discover`, `config:cache`, `route:cache` y `view:cache` en
app con su entorno; validar /up, Explorer y assets. Worker/scheduler arrancan
con el entorno inyectado y sin cache compilada; no requieren cache para operar.
No copiar caches productivas a un ensayo. Nunca usar `optimize:clear` compartido
como paso de despliegue: podría borrar cache de datos/locks.

Una futura aplicación requiere su propia autorización. Al cambiar app/web/jobs,
seleccionar sólo esos servicios, con `--no-deps --no-build --pull never`. No
recrear DB para aplicar incidentalmente un healthcheck; esa reconciliación se
programa aparte. No aplicar el candidato a imgproxy, CRM ni Traefik.

## Ensayo aislado

PostgreSQL en `--network none`, socket Unix privado compartido con app. No montar
un tmpfs adicional en `/var/run/postgresql`: es alias de `/run/postgresql` y
ocultaría el bind del socket. Esperar al arranque definitivo de PostgreSQL y
comprobar el socket desde app antes de pedir Explorer. App usa
`--network none`; web comparte sólo ese namespace (`--network container:<app>`)
y FastCGI `127.0.0.1:9000`. Ningún puerto se publica. Antes de ejecutar Laravel,
verificar NetworkMode, PortBindings, mounts, interfaces (sólo lo), rutas (sin
default) e imposibilidad de conexión a direcciones externas y al gateway del
host. DB_DATABASE de prueba, nunca `jakawi_final`. No montar `/srv/sites` ni
envs/caches de producción. Usar APP_KEY desechable, mail log, CRM/Meta/QR apagados,
S3 sin credenciales reales y endpoint loopback cerrado. No iniciar workers ni
scheduler; los jobs en la DB restaurada quedan sin consumidores. No probar
operaciones comerciales. Detener todos los contenedores de ensayo al terminar.

## Rollback de una futura release

1. Antes de cambiar nada, registrar IDs completos e inventario de app, web,
   worker y scheduler, env privado/caches, Compose y hashes de persistencia.
   Conservar imágenes anteriores: `docker image save` de los IDs exactos,
   SHA256 del tar, y etiquetas únicas de rollback. Nunca depender de `:current`.
   El backup existente conserva app `a797d0b65c4b…` y web `72e2a22664e7…`.
2. Guardar código/configuración en `/opt/sites/jakawi.com` y estado en
   `/srv/sites/jakawi.com`. Mantener PostgreSQL/storage en sus binds actuales.
   Cada pareja app/web debe incluir sus assets compatibles. Conservar también
   los assets de la release anterior para clientes con pestañas abiertas; un
   rollback de imágenes por sí solo no garantiza esos clientes concurrentes.
3. Si faltan las imágenes locales, verificar el SHA256 del tar privado y usar
   `docker image load`; cotejar IDs completos. En la ventana autorizada,
   seleccionar APP_IMAGE/WEB_IMAGE anteriores en un manifiesto privado y
   recrear sólo app/web/worker/scheduler con `--no-deps --no-build --pull never`.
   Usar el mismo APP_KEY y entorno productivo. No reutilizar config cache de la
   release fallida. Generar caches propias de los contenedores restituidos.
4. Validar /up, DB efectiva mediante SELECT, PRELAUNCH, DEMO, Explorer, assets y
   los servicios externos intactos antes de reabrir tráfico/colas. El ensayo
   aislado no ejecuta trabajos de negocio ni acredita entregas externas.
5. No usar `compose down`, no eliminar volúmenes, no restaurar la DB productiva.
   Sólo puede revertirse la imagen cuando el esquema y los datos nuevos sigan
   siendo compatibles con la versión anterior. Una migración incompatible
   requiere otro plan; restaurar el dump descartaría escrituras posteriores.

El ensayo de reversión reutiliza una DB/storage privados y cambia app/web entre
imágenes nuevas y anteriores. No es un rollback de producción ni prueba de
roles/ACL productivos, tráfico concurrente o migraciones futuras.

## Evidencia V1

El ensayo del 27/oct/2026 pasó npm ci, TypeScript, generación Wayfinder, Vite
(109 páginas Inertia), imagen PHP 8.4.27 y arranque con 57 tablas/65 migraciones
del respaldo reciente. Explorer devolvió 10 oportunidades y 20 lugares totales.
Chromium montó React/DEMO sin excepciones ni fallos de bundles, tanto con las
imágenes nuevas como con la pareja anterior. No se ejecutaron migraciones,
workers, scheduler, operaciones comerciales ni suite GOLD. El reporte del
servidor está en `/opt/sites/jakawi.com/artifacts/new-server-build-v1/REPORT.md`.
