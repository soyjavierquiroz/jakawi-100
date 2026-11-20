# Apariencia global COM V1

Se recuperó el trabajo de `feat/global-appearance-v1` en
`/srv/workspaces/jakawi/com/global-appearance-v1`, iniciado desde el LIVE y main
`ebef236f68bfb0071f3c7d50c08805168dacfa22`. Se conservaron los cambios válidos,
la matriz preparada y el contrato; no se alteró el checkout canónico que tenía
modificaciones ajenas. El reinicio había interrumpido build y tests, sin deploy.

## Causa y cambios

Admin y Marketing fijaban paletas locales; Mi JAKAWI duplicaba tokens. El
selector rápido tenía sólo dos opciones y faltaba sincronización entre pestañas.
El arranque temprano no cubría las vistas Blade ni resolvía exactamente igual
que el cliente. Había además títulos negros, textos coral decorativos y pares
fondo/texto destructivos que no servían en ambos modos.

Se reutilizan `theme.css`, Tailwind 4, shadcn y el almacenamiento existente.
Los alias Admin y Marketing apuntan a los tokens globales, con negro, crema,
coral, verde y amarillo. No se agregaron colores hex en TSX ni temas por módulo.

| Área | Completado |
| --- | --- |
| Público y productos | Selector desktop/móvil, portada PRELAUNCH, navegación, fondos y textos |
| Marketing y Partners público | Layout, footer y CTA final semánticos; acción móvil usa el mismo primary |
| Autenticación | Claro/Oscuro/Sistema en el layout compartido de login, registro y recuperación |
| Mi JAKAWI y perfil | Paleta global; selección, avisos y botones destructivos con tokens legibles |
| Admin | Layout React, vistas Blade bancarias, títulos, acciones, tablas, filtros y formularios |
| Partner | Selector en el layout común a owner, manager y staff; formularios, tablas y KPIs |
| Componentes | Alertas, diálogos, sheets, menús, notificaciones, foco y controles nativos |

## Persistencia real

Cuenta: `users.theme_preference`, mediante el endpoint existente
`PATCH /settings/appearance`. Invitados: localStorage y cookie host-only de COM.
Valores canónicos: `light/dark/system`. El usuario autenticado recupera su
preferencia en otro navegador al iniciar sesión; su cuenta prevalece sobre la
preferencia previa del invitado. Los cambios de cuenta se serializan; un error
HTTP/red avisa que no se pudo guardar por cuenta.

`system` sigue y escucha `prefers-color-scheme`. Las pestañas se sincronizan con
el evento storage, usando una clave por cuenta para autenticados. El head
compartido aplica clase y color-scheme antes de pintar, también en Blade.
El selector actualiza el almacén sin visitas Inertia ni desmontar formularios.
No hay sincronización push entre dispositivos abiertos ni cookies compartidas
con CLUB. Sin localStorage, la cookie conserva la elección pero no se garantiza
sincronización inmediata entre pestañas.

## Validación

- 28 tests focalizados, 547 aserciones: ThemePreferenceTest,
  PrelaunchAccountMaintenanceTest, PrelaunchCommercialContinuityTest,
  PrelaunchModeTest, PrelaunchOperationsTest y MarketingVideoFirstTest.
- TypeScript `tsc --noEmit`: PASS. Wayfinder generado por el build.
- Build y manifest: PASS, 106 páginas Inertia y sus dependencias presentes.
- Navegador Chromium en la red interna de `jakawi_test`: 116 casos de tema,
  a 390 y 1440; público, autenticación, Admin React/Blade, miembro y los tres
  roles Partner. Sin desbordes ni errores JS. Contraste del texto visible,
  foco, bordes y superficies revisados, con capturas claras/oscuras.
- Sistema reacciona a cambios del SO; persistencia tras recarga y entre
  pestañas; preferencia de cuenta verificada desde otro contexto de navegador.
- Formularios administrativos y Partner conservan cambios locales al alternar
  apariencia. Diálogo permanece abierto al cambiar desde otra pestaña; menú y
  aviso de fallo de persistencia comprobados.
- VSL: marco 3:4 y dimensiones idénticas en ambos temas/ancho; tiempo y estado
  de reproducción conservados; pausa y reanudación funcionales. Fixture de video
  reproducible en QA. Ningún archivo interno del reproductor fue modificado.
- Se reutilizaron casos válidos y se retomó la matriz tras corregir su apertura
  del menú móvil. Las vistas afectadas por contraste se revalidaron. El último
  ajuste de color de la acción móvil tiene una revalidación VSL focalizada.
- El primer filtro exploratorio amplio incluyó un fixture ajeno que intenta
  crear una operación protegida por PRELAUNCH antes de renderizar. Se conserva
  ese log; los seis archivos focalizados indicados arriba pasan completos.
- No GOLD; no cambios de precios, contenido editorial, permisos ni operación
  comercial; sin migraciones ni endpoints nuevos.

## Evidencias y release

Directorio operativo: `/srv/workspaces/jakawi/com/global-appearance-v1-evidence`.
`CAPTURAS.md` enlaza las capturas; `validation.json`, `browser.json`,
`contrast-final.json`, `browser-video-final.json`, `types-result.json` y los logs
conservan la validación. `browser-matrix-full.json` conserva el recorrido amplio
antes de reconciliar la revalidación final de la acción VSL.

La release usa exclusivamente `/opt/sites/jakawi.com/release-control.py` para
prepare/deploy/verify/rollback-plan/promote. Construcción COM desde fuente Git
limpia, locks sin cambios y baseline verificado. Las imágenes previas y el dump
validado se conservan para rollback; `rollback-plan` sólo hace un dry-run.
El estado final se registra en `release-metadata.json`, `LIVE.json`,
`portal-live.json`, `live-browser.json` y `promotion.json`; main se promueve
únicamente después del LIVE PASS. PRELAUNCH ON y DEMO OFF deben conservarse.

Contrato compatible e independiente para CLUB:
`docs/design/appearance-contract-v1.md`. No se cambió CLUB ni se inició SSO.
