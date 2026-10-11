# Fase 3B — revisión HTML5 Alpha y aceptación de navegador

Fecha: 11 de octubre de 2026 (America/La_Paz). Checkout: `/home/jakawi-vsl-dev`; rama `feat/jakawi-vsl-v1`. Infraestructura previa: `57aec795cae667fbd487359aeef4ef0bdea3b541`.

## A. Revisión de las fuentes reales

Se leyeron los siete módulos del player, el poster inline y SVG sintético, los estilos CSS Modules, las 17 pruebas originales, el runner, la demo, su configuración Vite, el launcher y el informe de Fase 3A. No se usó únicamente el resumen de entrega.

No hay store global de reproducción, frameworks adicionales ni servicios de producción usados por el player. El objeto de estado inicial es una constante; cada sesión mantiene estado local nuevo. El motor nativo, el hook y los controles separan responsabilidades sin un sistema de adaptadores prematuro. `Button` y `cn` reutilizan componentes/utilidades locales. El cambio de `package.json` agrega únicamente el comando de prueba; el lockfile y las dependencias no cambian.

Archivos incluidos en la revisión/commit Alpha:

- `app/resources/js/components/media/jakawi-vsl-player/`: `types.ts`, `html5-engine.ts`, `use-vsl-playback.ts`, `JakawiVslPlayer.tsx`, `PlaybackControls.tsx`, `JakawiVslPlayer.module.css`, `index.ts`.
- `app/dev/vsl-demo/`: `main.tsx`, `index.html`, `vite.config.ts`, `tsconfig.json`, `public/poster.svg`.
- `app/tests/media/`: `jakawi-vsl-player.test.mjs`, `run-vsl-tests.sh`.
- `bin/jakawi-vsl-demo`, `app/package.json`, `docs/VSL_HTML5_ALPHA.md`, este informe.

No se encontraron secretos ni credenciales de medios de producción. `user:secret` en una prueba es un literal artificial usado para verificar rechazo de URLs con credenciales. `.invalid` se usa exclusivamente para probar un fallo de red en el navegador. No se incluyen MP4 privados ni sintéticos en Git: los fixtures se generan en `.vsl-state`, ignorado por Git.

## B. Comportamiento crítico

| Área | Resultado de revisión |
| --- | --- |
| Autoplay | JSX establece `playsInline` y `muted`; el motor establece `muted`, `defaultMuted` y `loop=false` antes de asignar/cargar la fuente. Solicita reproducción una vez por sesión al recibir metadata. La promesa resuelta no confirma reproducción: solo `playing`. Rechazo deja poster/acción manual. El contador de solicitudes evita que un rechazo anterior sobrescriba una acción posterior. |
| Sonido | El handler llama directamente al motor. Pausa, solicita seek a cero, desmutea y llama a `play()` sin `await` ni efecto React intermedio. El estado pasa a `requesting`; `audio-activated` exige `playing`, `muted=false` y volumen mayor que cero. Un fallo deja retry y vuelve a silenciar. Esto confirma estado del elemento, no sonido físico del dispositivo. |
| Pausa/replay | No hay `key` ni remount del video en pausa/resume/replay. Pausa conserva posición/mute/volumen. End detiene, prepara cero, marca `ended`; no reinicia por un `canplay` posterior. Replay es una acción explícita. |
| Ciclo de vida | Listeners de video y preferencias se eliminan; promesas se invalidan al pausar, cambiar sesión y disponer. Guards de sesión y fuente seleccionada rechazan callbacks viejos. StrictMode vuelve a crear una sesión después del cleanup; metadata nativa es asíncrona y los listeners anteriores quedan inertes. No hay timers del motor/hook. La revisión no sustituye una ejecución real de React StrictMode en navegador. |
| Buffering | `waiting` cambia el estado a `buffering`; muestra “Cargando video…” y conserva el botón de pausa. Solo un nuevo `playing` confirma recuperación. `stalled` no se confunde con pausa: puede quedar contenido descargado en reproducción. |
| Geometría | Frame normal 3:4; `object-fit` cover/contain y `object-position` sin modificar el archivo. Para una fuente nativa 9:16, cover recorta verticalmente y contain muestra el frame completo con espacio lateral. No se infiere que todo MP4 sea 9:16 ni se eliminan bandas codificadas en una fuente 16:9. Fullscreen usa contain/centro para video y poster. WebKit usa fullscreen nativo donde existe. |

## C. Defectos encontrados y correcciones

1. **Repetir play sobre reproducción confirmada:** cambiaba a `requesting` esperando un `playing` que el navegador no está obligado a emitir de nuevo. Se hizo idempotente esa llamada cuando el elemento sigue reproduciendo. La activación de audio sigue forzando una transición nueva.
2. **Buffering no reflejado:** el player mantenía “Reproduciendo” durante `waiting`. Se agregó estado `buffering`, lectura de progreso, mensaje accesible y controles de pausa durante espera.
3. **Pausa por preferencias después de error:** convertía el estado `error` a `paused`, haciendo que el retry omitiera `load()`. Se preservan los estados de error/bloqueo al pausar.
4. **Fullscreen después del desmontaje:** los `.catch()` podían llamar a `setState` después del cleanup. Un contador local invalida respuestas anteriores y pendientes al desmontar, también durante el ciclo de StrictMode.
5. **Volumen cero:** la UI decía “con sonido” con `muted=false` y volumen cero. El mensaje ahora considera ambos valores.
6. **Demo fuera del TypeScript principal:** `app/tsconfig.json` solo incluye `resources/js`; un check exitoso no validaba la demo. Se agregó un tsconfig local, sin ampliar el build de producción. El check inicial detectó además un warning de resolución de tipos en la demo; quedó resuelto con ese proyecto explícito.

Las tres regresiones del motor se reprodujeron antes del fix: **17 pasadas y 3 fallidas**. Después del fix: **20 pasadas, cero fallidas**. No se agregaron pruebas de funcionalidades nuevas ni se cambiaron pruebas Laravel.

La demo existente recibió selectores de fuente sintética/fallida y posición focal, además del identificador del nodo y último snapshot técnico. Permite verificar cambio de fuente y remount de forma reproducible. El snapshot refleja el último callback emitido; el mensaje del player es la referencia para `requesting`/`buffering` entre callbacks.

## D. Verificación automática

Ejecutada en un contenedor temporal de herramientas del proyecto aislado, con copias del código actual, sin bind mounts ni red de salida:

| Check | Resultado |
| --- | --- |
| 17 pruebas originales, antes de cambios | 17/17 pasadas |
| Reproducción de defectos | 17/20 pasadas; fallaron las 3 regresiones nuevas |
| Suite corregida | 20/20 pasadas |
| `npm run types:check` | Correcto: Wayfinder y TypeScript de aplicación |
| `tsc --noEmit -p dev/vsl-demo/tsconfig.json` | Correcto: demo, configuración e imports del player |
| `vp check` limitado a player/tests/demo/package.json | Formato correcto; cero warnings/errores de lint |
| `npm run build` | Vite offline correcto, usando fonts cacheadas |
| Guards dev/test | Ambos pasan |
| HTTP del demo, módulos y CSS | Correcto |
| Range MP4 | HTTP 206, `video/mp4`, 32 bytes para `bytes=0-31` |
| Exclusión de demo de producción | Build propio de demo rechazado; no figura en manifest de aplicación; ninguna ruta Laravel agregada |
| Límite de archivos Vite | Solicitud fuera del allowlist frontend: HTTP 403 |

Las pruebas del motor usan un HTMLVideoElement **simulado** en Node. No prueban decodificación, audio físico, foco, geometría ni políticas reales del navegador. Logs locales ignorados: `.vsl-state/phase3b-red-tests.log`, `phase3b-tests.log`, `phase3b-build.log`. Los avisos opcionales existentes de fontaine y tiempos de plugins no son errores del player. No se formatearon archivos ajenos.

## E. Aceptación manual reproducible

**Ningún escenario fue ejecutado en un navegador.** No se encontró Chrome/Chromium/Firefox, caché de Playwright ni conector de navegador. No se instaló ninguna dependencia. Tampoco se dispone de una máquina remota de QA conectada/autorizada. Todos los resultados reales de navegador quedan pendientes, sin dispositivo ni versión atribuida.

Preferir una máquina de desarrollo/QA con navegador ya instalado. Mantener PameServer en loopback:

```bash
# Ejecutar en la máquina de QA; sustituir usuario y host.
ssh -N -o ExitOnForwardFailure=yes \
  -L 127.0.0.1:8094:127.0.0.1:8094 usuario@HOST_PAMESERVER
# Abrir http://127.0.0.1:8094 en ese navegador.
```

Registrar para cada fila: fecha/zona, dispositivo, sistema y versión, navegador y versión completa, perfil/política de autoplay, reduced-motion, Save-Data, condiciones de red, resultado y evidencia. Desactivar cache de DevTools para fallos/throttling. El tono solo debe oírse tras acción explícita. El fixture dura seis segundos.

| # | Navegador/dispositivo a registrar | Procedimiento | Criterio | Estado real |
| --- | --- | --- | --- | --- |
| 1 | Chrome desktop / SO / versión | Perfil normal, reduced-motion y Save-Data desactivados; cargar demo con autoplay | Video muted/inline, `playing`, tiempo avanza, ningún audio automático | Pendiente |
| 2 | Chrome desktop / política / versión | Perfil con bloqueo real de autoplay muted; registrar `NotAllowedError`. Usar también el harness controlado inferior para comprobar fallback | Poster y acción manual; sin falso `playing`; acción manual recupera | Pendiente; harness tampoco ejecutado |
| 3 | Chrome desktop / versión | Tras 2–4 segundos, pulsar ACTIVAR SONIDO | Regresa al inicio, muted=false; audio empieza desde cero y `audio-activated` llega después de `playing` | Pendiente |
| 4 | Chrome desktop / versión | Pausar sobre segundo 2; esperar; reproducir | Tiempo conservado; mismo nodo #; mantiene preferencia de audio | Pendiente |
| 5 | Chrome desktop / versión | Dejar terminar; esperar al menos dos segundos; Repetir | `ended`, posición preparada en cero, permanece parado; replay explícito, mismo nodo | Pendiente |
| 6 | Chrome desktop / versión | Network → solicitud del MP4; revisar Range/respuesta | `video/mp4`, soporte de rangos, HTTP 206 | Navegador pendiente; HTTP comprobado con curl 7.81.0, x86_64 Linux |
| 7 | Chrome desktop / versión | Selector Fuente → URL HTTPS fallida; volver al fixture | Error accesible y manual retry; volver a fuente válida recupera | Pendiente |
| 8 | Chrome desktop / versión | Tras cargar la UI, throttling personalizado lento (por ejemplo 10 KB/s), disable cache; cambiar fallida → fixture; después quitar throttle | Espera/buffering visibles, pausa disponible, progreso no inventado, recuperación por `playing` | Pendiente |
| 9 | Chrome desktop / versión | Mientras reproduce, cambiar fixture → fallida → fixture | Mismo nodo #; sesión previa invalidada; nuevo estado muted; errores/promesas viejos no sobrescriben nueva sesión | Pendiente |
| 10 | Chrome desktop / versión | Con reproducción/carga pendiente, desmarcar Montar; montar otra vez | Nuevo nodo # tras mount; no callbacks posteriores del nodo viejo ni autoplay duplicado; StrictMode activo | Pendiente |
| 11 | Chrome desktop / versión / viewport | Anchos 320/375/768; cover/contain; focal arriba/abajo. Repetir con el fixture 16:9 embebido indicado abajo | Frame visible 3:4; proporción original intacta; recorte CSS esperado; distingue dimensiones codificadas | Pendiente |
| 12 | Safari iOS / dispositivo / SO / versión | Abrir mediante un túnel local en el propio dispositivo, o simulador iOS en Mac con túnel; carga, sonido, pausa/replay | Inline inicial, gesto de audio válido; sin fullscreen automático ni audio al cargar | Pendiente |
| 13 | Chrome Android / dispositivo / SO / versión | Si QA tiene adb y USB: `adb reverse tcp:8094 tcp:8094` en la máquina que tiene el túnel; abrir localhost en el dispositivo | Sonido por gesto, reinicio cero, pausa y replay correctos | Pendiente |
| 14 | Chrome desktop / versión | Solo Tab/Shift+Tab, Enter/Espacio y flechas sobre sliders | Foco visible, etiquetas comprensibles, acciones equivalentes al ratón; sin controles click-only | Pendiente |
| 15 | Chrome desktop y Safari iOS / versiones | Entrar/salir de fullscreen durante reproducción; rechazar permiso donde sea posible; desmontar con una solicitud pendiente | Frame fuente completo con contain; fallback/errores recuperables; sin respuestas tardías después del unmount | Pendiente |

No abrir puertos LAN/públicos para pruebas móviles. Si no existe un túnel local en el dispositivo/simulador o adb autorizado en la máquina de QA, dejar esas filas pendientes. Un simulador debe registrarse como simulador, no como dispositivo físico.

### Harness de bloqueo controlado (no equivale a una política real de Chrome)

Desmarcar Montar reproductor. En DevTools del navegador de QA, ejecutar:

```js
{
    const original = HTMLMediaElement.prototype.play;
    let rejectNext = true;
    window.vslRestorePlay = () => {
        HTMLMediaElement.prototype.play = original;
        delete window.vslRestorePlay;
    };
    HTMLMediaElement.prototype.play = function () {
        if (rejectNext) {
            rejectNext = false;
            return Promise.reject(new DOMException('QA autoplay block', 'NotAllowedError'));
        }
        return original.call(this);
    };
}
```

Volver a montar con autoplay activo; comprobar fallback y pulsar Reproducir. Restaurar con `window.vslRestorePlay()` o recargar la página. Registrar el resultado como **rechazo inyectado**, nunca como bloqueo nativo del navegador. Si Chrome permite muted autoplay bajo la política disponible, el caso de bloqueo real sigue pendiente.

### Fuente nativa versus vertical embebida en 16:9

Se preparó solo un fixture sintético adicional ignorado por Git: `.vsl-state/phase3b-embedded-16x9.mp4`. ffprobe debe mostrar **576×324** para este archivo; el fixture normal es **180×320**. El segundo contiene el patrón vertical centrado con padding codificado; no es un archivo nativo 9:16.

Para probarlo durante una sesión de QA, intercambiar únicamente el fixture del contenedor del demo y recargar el navegador:

```bash
cd /home/jakawi-vsl-dev
# Asegurar que el contenedor indicado pertenece al demo aislado antes de copiar.
docker cp .vsl-state/phase3b-embedded-16x9.mp4 \
  jakawi-vsl-dev-demo:/var/www/html/dev/vsl-demo/public/__vsl-fixtures__/alpha.mp4
# Probar cover, contain y fullscreen; comprobar video.videoWidth/video.videoHeight.
# Restaurar al finalizar:
docker cp .vsl-state/demo-fixture/alpha.mp4 \
  jakawi-vsl-dev-demo:/var/www/html/dev/vsl-demo/public/__vsl-fixtures__/alpha.mp4
```

No se intercambió el fixture activo durante esta revisión: la demo conserva el original 9:16. Estas dimensiones verificadas con ffprobe no sustituyen la prueba visual.

## F. Exposición y producción

Pre-flight: 4,5 GiB RAM disponibles y 17 GiB de disco libre. Rama e infraestructura previa correctas; ambos guards pasan. Producción permanece limpia en `16909608893ee1e955c03ed52516198a1b44ba5b`; app/DB reportan healthy, restantes contenedores de producción running, `/up` devuelve HTTP 200.

Inspección de socket y Docker: único puerto de demo `127.0.0.1:8094:80`. Vite no publica puertos y permanece en `jakawi-vsl-dev-network` interno. Solo su nginx de ingreso une `jakawi-vsl-dev-network` y `jakawi-vsl-dev-entry`; ambos son dedicados. Sin mounts, volúmenes compartidos ni etiquetas de producción. El launcher y las rutas públicas no fueron cambiados en Fase 3B.

## G. Git

Se crea un commit local de implementación/revisión Alpha después de pasar los checks y revisar el staged diff. Incluye únicamente el player, demo, pruebas, launcher existente, comando de pruebas y documentación identificados arriba. La infraestructura previa no cambia. El SHA se entrega en el cierre y se puede localizar con `git log -1`. Sin push ni deploy. El contenedor temporal de revisión se elimina; la demo queda disponible en loopback.

## H. Bloqueos restantes

Aceptación real de Chrome desktop, Safari iOS y Chrome Android, audio físico, accesibilidad visual/teclado y fullscreen. No hay browser/device/version que pueda registrarse honestamente como ejecutado en esta sesión. El harness simulado no prueba una política nativa; los unit tests no prueban React DOM ni decodificación.

## I. Preparación para YouTube

La base de código y el contrato técnico están preparados para un futuro adaptador. Las validaciones automáticas pasan y los defectos identificados están corregidos. **La Alpha no tiene todavía aceptación completa en navegador**; conviene cerrar la matriz antes de usarla como base aprobada de integración. No se implementó YouTube, Bunny ni Marketing Landings.
