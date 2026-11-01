# Fuente canónica de JAKAWI.COM

Repositorio: `/srv/repos/jakawi.com`.
Origin: `git@github-jakawi:soyjavierquiroz/jakawi-100.git`.
La autenticación SSH existente permanece fuera de Git; no se copiaron claves.

Al consolidar el 1 de noviembre de 2026, `main`, `origin/main` y las etiquetas
de revisión de app/web/worker/scheduler coincidían en
`03f7b0bb44f426285ad77344fbc095c979b98773`.
La rama local `chore/canonical-source` añade exclusivamente herramientas y
documentación operativa; no está publicada ni desplegada. No confundir su HEAD
con el SHA LIVE. Las próximas funcionalidades parten de `origin/main`.

La fuente histórica `/root/jakawi-commercial-v1/repository` era un worktree de
`/root/jakawi-desktop-center-v1/repository/.git`, con HEAD `fac7527`, no el último
LIVE. Se transfirieron sus ramas, tags y referencias remotas a una base Git
independiente: sin alternates, enlaces a objetos ni gitdir dentro de `/root`.
El main local antiguo se preserva en `refs/archive/historical-main`.
Las referencias de los demás repositorios históricos se conservan bajo
`refs/archive/jakawi-<directorio>/...`. No se fusionaron commits antiguos.
Estas referencias son locales: un push normal de ramas no las publica.

## Desarrollo

```sh
cd /srv/repos/jakawi.com
git fetch origin
bin/com-worktree feat/mi-cambio
# Trabajar en /srv/workspaces/jakawi/com/feat--mi-cambio
```

El helper acepta `JAKAWI_REPOSITORY` y `JAKAWI_WORKSPACE_ROOT`. También se puede
trabajar en una rama directamente en el repositorio canónico. Al terminar un
worktree temporal, revisar cambios y evidencias antes de retirarlo mediante
`git worktree remove`; nunca borrar directorios históricos incidentalmente.

## Build y despliegue

Los lockfiles, Dockerfiles y scripts completos están en Git. El build existente
`docker/new-server/build.sh` resuelve el checkout desde su propia ubicación y
no depende de una ruta `/root`. Ejecutarlo desde un worktree limpio, con Node
compatible y RAM disponible; véase `docker/new-server/README.md`.

```sh
cd /srv/workspaces/jakawi/com/feat--mi-cambio
NODE_BIN=/opt/node-v24.22.1-linux-x64/bin bash docker/new-server/build.sh
```

Ese comando construye imágenes; no ejecutarlo como parte de una simple
consolidación de fuente. Para comprobar esta consolidación se instalaron las
dependencias del lock y se verificaron TypeScript y assets en un worktree aislado,
usando la imagen PHP de herramientas existente, sin construir imágenes de servicio.

El controlador operativo permanece en `/opt/sites/jakawi.com/release-control.py`.
Su fuente Git predeterminada es ahora `/srv/repos/jakawi.com`, configurable con
`JAKAWI_REPOSITORY`. El directorio operativo y la evidencia son configurables con
`JAKAWI_LIVE_ROOT` y `JAKAWI_RELEASE_EVIDENCE`. El controlador sigue correspondiendo
a la release histórica payments-admin: sus guardas, imágenes, validaciones y
evidencias no constituyen un deploy genérico para una nueva funcionalidad.
No se ejecutó prepare/deploy/verify durante la consolidación.
Los valores del host se guardan en `/opt/sites/jakawi.com/source-paths.json`,
fuera de Git. Se retiró la autocopia al finalizar `verify`, que podía restaurar
el controlador archivado con las rutas antiguas. Las demás funciones de release
se conservaron. La autenticación SSH existente todavía utiliza la configuración
privada del usuario del servidor; no se trasladó a Git.

Se conserva una copia parametrizada del controlador en
`docker/new-server/payments-admin-release-control.py`. Esta copia exige
`JAKAWI_RELEASE_EVIDENCE` explícito y no incorpora secretos. Antes de cualquier
release futura se deben preparar y revisar sus propias evidencias y procedimiento.

## Rutas que permanecen

- Compose y manifiesto: `/opt/sites/jakawi.com/compose.yaml` y `release.env`.
- Secretos y claves: archivos privados existentes dentro de `/opt/sites/jakawi.com`.
- PostgreSQL: `/srv/sites/jakawi.com/postgres`.
- Storage: `/srv/sites/jakawi.com/storage`.
- Claves OIDC montadas: `/opt/sites/jakawi.com/oidc-keys`.
- Evidencia histórica del controlador: `/root/jakawi-payments-admin-release/evidence`.

Ningún servicio COM LIVE monta código desde `/root` ni desde el repositorio nuevo.
Los directorios antiguos no se eliminaron. La antigua base Git sigue siendo
necesaria para sus worktrees heredados; los mounts de ensayos detenidos y los
respaldos privados también se conservan. La clasificación completa y la evidencia
de la migración están en `/opt/sites/jakawi.com/artifacts/source-consolidation/`.
