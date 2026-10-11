# Autonomía nativa del reproductor JAKAWI

Auditoría histórica local: 11 de octubre de 2026, previa al merge principal. No se implementaron funcionalidades nuevas.

Manual canónico: [JAKAWI_VIDEO_PLAYER.md](JAKAWI_VIDEO_PLAYER.md). Este documento conserva la auditoría de autonomía; sus resultados de build/tests son evidencia anterior, no una nueva ejecución durante el cierre documental. El estado Git/runtime actual está en el manual.

## Resultado

El reproductor es código nativo y autónomo de JAKAWI respecto al Smart Player
original. Su ubicación definitiva es
`app/resources/js/components/media/jakawi-vsl-player/`: componente, controles,
motores HTML5/YouTube, transporte HLS, hooks, encuadre, estilos, parser, tema y
wrappers de integración. `cn` reside en `app/resources/js/lib/utils.ts`; los
tokens globales están en `app/resources/css/theme.css`.

Todas las importaciones locales resuelven dentro del repositorio. El único alias
TypeScript de la aplicación, `@/*`, resuelve a `./resources/js/*`. No se encontraron
symlinks en el workspace ni dependencias npm `file:`, `link:` o paquetes enlazados
en el lockfile. Los Compose VSL usan contexto local y volúmenes nombrados
`pgdata`/`uploads`, sin montajes de código desde otra carpeta.

No hay imports, aliases, referencias ejecutables de build ni montajes hacia
`/home/jakawi-vsl-source-review`, `/opt/projects/experience-platform` u otra
carpeta del Smart Player original. Esa carpeta no se eliminó, movió ni utilizó
para compilar.

## Dependencias declaradas

- `react`: hooks/componentes; `react-dom`: aplicación anfitriona.
- `lucide-react`: controles e iconos.
- `hls.js`: transporte HLS mediante import dinámico, declarado como dependencia
  frontend `^1.7.3` y resuelto a `1.7.3` en `app/package-lock.json`.
- `clsx` y `tailwind-merge`: utilizados por la utilidad local `cn`.
- `@inertiajs/react`: integración de landings y formulario admin.

Todos están declarados en `app/package.json` y el lockfile. El build utiliza
además las herramientas frontend declaradas y las dependencias Composer de
Laravel para Wayfinder; genera los tipos de rutas desde código del proyecto.

Autonomía del código no significa funcionamiento sin internet: YouTube carga
su IFrame API, iframe nocookie y miniaturas; MP4/Bunny utilizan la URL configurada.
El build general obtiene fuentes Bunny declaradas en `app/vite.config.ts`.
Estos recursos remotos no son imports de la carpeta original ni código del
Smart Player. Por tanto, la afirmación estricta de que *todos* los insumos del
build son archivos Git o paquetes npm tiene esa salvedad: fuentes y tooling
Laravel/Composer existentes.

## Verificación con archivos del índice Git

Se exportó un árbol del índice mediante `git write-tree` y `git archive`,
excluyendo cambios de demos, snapshots, `.env` privados, caches y artefactos
ignorados. El árbol exacto de `app` verificado es
`435c6c802f4ef760679709994d26ba664c122b53`.

Se compiló esa exportación con `docker/Dockerfile.vsl`, target `assets`, que
instala dependencias mediante `npm ci` y ejecuta:

```text
npm run types:check
npm run build
```

Docker recibió únicamente el contexto exportado. No hubo montajes de la carpeta
original ni de `/home/jakawi.com`. La documentación añadida después de exportar
no cambia el árbol de código `app` verificado.

TypeScript y build: aprobados. Build emitió la advertencia de chunk HLS mayor
a 500 kB y de optimización opcional de fuentes. Las 61 pruebas dirigidas del
player/parser pasan dentro de la imagen exportada, con red deshabilitada.

## Referencias históricas y Git

`docs/VSL_DEVELOPMENT.md` conserva una referencia histórica a
`/opt/projects/experience-platform/src/components/media/smart-video-player`
y las instrucciones de entrega del snapshot. No es una dependencia ejecutable.
Los comentarios de procedencia de `youtube-crop.ts`/`youtube-engine.ts` describen
la adaptación; las implementaciones completas se encuentran en JAKAWI.
El script legado `bin/jakawi-schedule` menciona `/home/jakawi.com`; no participa
en el build/player y no se ejecutó ni modificó durante esta auditoría.

El commit local reúne código nativo, dependencia HLS, integraciones Laravel/React,
migración nullable, pruebas dirigidas y documentación de integración/autonomía.
La revisión previa de los archivos preparados no detectó claves privadas,
tokens de credenciales ni URLs firmadas privadas. No contiene snapshots,
medios de prueba, outputs de build, caches ni cambios de demos.

Se conservan fuera del commit los cambios previos de `app/dev/vsl-demo/`, el test
opcional ligado a esa demo `app/tests/media/course-browser.test.mjs` y los informes
de desarrollo `docs/VSL_COURSE.md`/`docs/VSL_MARKETING.md`.

Durante aquella auditoría no hubo push, deploy, migración de producción ni cambios en `/home/jakawi.com`. Esa frase es histórica: el cierre posterior observó el player en el principal y la migración aplicada. Consultar el [estado de entrega](JAKAWI_VIDEO_PLAYER.md#estado-de-entrega-y-evidencia) para evidencia y límites actuales.
