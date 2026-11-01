# JAKAWI.COM — fuente canónica

El repositorio oficial de COM en este servidor es `/srv/repos/jakawi.com`.
Desarrollar mediante ramas Git. Para aislamiento, crear worktrees temporales
en `/srv/workspaces/jakawi/com`; no crear nuevos `/root/jakawi-*` por fase.

Producción se administra en `/opt/sites/jakawi.com`; su estado persistente está
en `/srv/sites/jakawi.com`. No mover esos directorios, secretos, claves, mounts,
Compose ni volúmenes como consecuencia de cambios de código. Una consolidación
de fuente no requiere reconstruir imágenes ni desplegar servicios.

Conservar los directorios históricos hasta una limpieza explícitamente autorizada.
No modificar CLUB, CRM, Pame ni otros proyectos dentro de una tarea de COM.
Consultar `docs/operations/canonical-source.md` para builds y dependencias históricas.
