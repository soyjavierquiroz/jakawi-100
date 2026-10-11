# Jakawi Video Player — manual canónico

Cierre documental: 11 de octubre de 2026. Autoridad para reutilizar la implementación actual; el estado observado se detalla al final. Historial: [integración](VSL_INTEGRATION.md), [autonomía](VSL_AUTONOMY.md) y [desarrollo](VSL_DEVELOPMENT.md).

## Introducción y arquitectura

`JakawiVslPlayer` es el reproductor React/TypeScript nativo de JAKAWI para VSL comerciales y videos de curso. Ofrece YouTube, reproducción HTML5 MP4, transporte HLS, encuadres, temas, manejo de bloqueo/error y controles accesibles. No requiere un directorio de Experience Platform: las adaptaciones del Smart Player original están incorporadas en el repositorio.

Código: [módulo](../app/resources/js/components/media/jakawi-vsl-player/), [exports](../app/resources/js/components/media/jakawi-vsl-player/index.ts) y [tipos](../app/resources/js/components/media/jakawi-vsl-player/types.ts). El hook de playback crea y destruye un motor por sesión de fuente; los motores HTML5/YouTube controlan el medio y HLS sólo su transporte. Encuadre, progreso decorativo y tema son módulos locales. `@/*` apunta a `app/resources/js/*`.

Dependencias declaradas en [package.json](../app/package.json) y lockfile: React/React DOM, `lucide-react`, `hls.js` (rango `^1.7.3`, lock `1.7.3`), `clsx` y `tailwind-merge` para `cn`; Inertia para la aplicación, administración y landings. El entorno también requiere herramientas frontend y Laravel/Composer para Wayfinder. Los tokens están en [theme.css](../app/resources/css/theme.css).

## Fuentes y restricciones

El objeto `source` siempre lleva `type` y `url`:

| Fuente | Forma admitida por el motor actual |
| --- | --- |
| YouTube | `{ type: 'youtube', url: 'https://www.youtube.com/watch?v=hI-Ecy2D8pg' }`; React también acepta el ID de 11 caracteres solo |
| MP4, incluido MP4 alojado en Bunny | `{ type: 'mp4', url: 'https://…/video.mp4' }` |
| Bunny HLS | `{ type: 'bunny', url: 'https://…/playlist.m3u8' }` |
| Fixture aislado | `{ type: 'development-fixture', url: '/__vsl-fixtures__/alpha.mp4' }`, únicamente con `import.meta.env.DEV` y mismo origen; no utilizar en producción |

**Bunny MP4 se configura como `type: 'mp4'`.** El tipo `bunny` exige HTTPS y pathname terminado en `.m3u8` (puede tener query). El motor MP4 exige HTTPS, pero no valida la extensión ni convierte una página/iframe en video. Ninguna fuente admite credenciales embebidas en la URL.

YouTube acepta HTTP/HTTPS en el motor, con hosts `youtu.be`, `youtube.com`, `www.youtube.com`, `m.youtube.com`, `music.youtube.com`, `youtube-nocookie.com` o `www.youtube-nocookie.com`: `/watch?v=…` o `/embed/…`, `/shorts/…`, `/live/…`, `/v/…`. Preferir HTTPS. Laravel exige URL HTTPS completa, no ID solo. El shortcode usa `id` para YouTube. URLs de iframe Bunny Stream no son fuentes directas válidas.

La reproducción depende de permisos de embed, disponibilidad y restricciones del proveedor, red, codecs y políticas del navegador. HLS usa soporte nativo cuando existe; en caso contrario carga `hls.js` y requiere MediaSource compatible. El CDN debe permitir CORS en manifiestos, segmentos y claves necesarias. No hay gestión de DRM ni generación de URLs firmadas en el player. Los fixtures públicos del propietario ilustran formatos; esta revisión no certifica su disponibilidad ni su orientación visual mediante reproducción remota.

## Modos

- `vsl` (predeterminado): solicita autoplay muted, sujeto a reduced motion, ahorro de datos y política del navegador. La activación del audio reinicia desde 00:00. Controles mínimos de inicio, pausa, reintento y replay. La barra es decorativa y no lineal: reloj independiente que sólo avanza mientras reproduce, horizonte de 90 s, curva `1 - (1 - ratio)^acceleration`, exponente 3 limitado a 1–6, máximo visual 97% hasta terminar. No representa retención ni tiempo real del medio.
- `course`: inicio manual por defecto, progreso de `currentTime/duration`, seek, volumen, mute y controles discretos según capacidades del motor. Un `autoplay` explícito puede alterar el inicio manual; omitirlo o usar `off` para cursos.

## Encuadres existentes

| Encuadre | Uso y algoritmo |
| --- | --- |
| `embedded-vertical` | Contenido 9:16 dentro de un canvas YouTube 16:9 con bandas. Ventana 3:4; recorte original de `youtube-crop.ts`: iframe de ancho `316.049383%`, altura automática, aspecto 16:9, left/top `100 - focalPoint%`, traslación `(-50%, -50%)`. Punto focal predeterminado 50%/50%. |
| `native-vertical` | Archivo vertical real 9:16 dentro de ventana 3:4. HTML5 cover amplía la altura a 133.333…% y centra el recorte; YouTube usa iframe 9:16 centrado de ancho 100%. |
| `horizontal` | Ventana 16:9 con imagen completa; HTML5 utiliza `contain`. |

Sin `framing`, el layout se fija antes de cargar: 16:9 por defecto, sin inferir orientación de metadata; el fixture de desarrollo usa 9:16. La forma anterior `{ kind: 'auto', sourceAspectRatio: … }` permite elegir vertical si el ratio configurado es menor que 1.

Para HTML5 vertical, `verticalFraming` calcula `height = max(1, viewportAspectRatio/contentAspectRatio)` y `width = sourceAspectRatio * height`; limita los offsets para cubrir la ventana y los puntos focales al rango 0–1. `embedded-vertical` presupone contenido 9:16; el objeto avanzado puede configurar ratios y `focalX/focalY`. `fit="contain"` evita esa geometría cover. No cambiar este algoritmo para integrar el componente.

## API pública

Todos los campos salvo `source` son opcionales. Referencia exacta: [types.ts](../app/resources/js/components/media/jakawi-vsl-player/types.ts).

| Prop | Tipo / predeterminado |
| --- | --- |
| `source` | `VslSource`, obligatorio; formatos anteriores |
| `mode` | `'vsl' \| 'course'`; `vsl` |
| `autoplay` | `'muted' \| 'off' \| boolean`; omitido: muted en VSL, manual en curso |
| `poster` | `string`; sin póster explícito; curso YouTube usa miniatura del proveedor |
| `title` | `string`; `'Video'`, etiqueta accesible de región/medio |
| `theme` | `'inherit' \| 'custom'`; `inherit` |
| `colors` | `{ accent?: string; text?: string; overlay?: string }`; herencia |
| `framing` | Tres strings anteriores o `{ kind: 'auto' \| 'native-vertical' \| 'horizontal' \| 'embedded-vertical'; sourceAspectRatio?: number; contentAspectRatio?: number; focalX?: number; focalY?: number }`; auto estable |
| `fit` | `'cover' \| 'contain'`; `cover`, horizontal impone contain |
| `focalPosition` | `string` de object-position; `'center'` |
| `progressAcceleration` | `number`; 3, limitado a 1–6; sólo barra VSL |
| `progressSeconds` | `number`; 90, valores no positivos/no finitos vuelven a 90; sólo barra VSL |
| `className` | `string`; sin clase adicional |
| `onEvent` | `(event: VslEvent) => void`; sin callback |

Se conservan autoplay booleano y objeto de framing de la API previa. No existe prop `onProgress`: usar `onEvent`. Eventos: `ready`, `playing`, `paused`, `progress`, `ended`, `audio-activated` y `error`; todos llevan `state`, error además lleva `error`. El estado incluye `status`, `ready`, `hasPlayed`, `currentTime`, `duration`, `aspectRatio?`, `muted`, `volume` y `error`. Errores: `autoplay-blocked`, `playback-blocked`, `media`, `source`, con mensaje y código opcional. El progreso del callback corresponde al medio, nunca a la barra decorativa.

## Temas, colores y accesibilidad

Hereda `--brand`, `--cream`, `--scrim`, `--overlay`, `--ink` y `--shadow-featured`. Variables públicas por instancia: `--jakawi-video-accent`, `--jakawi-video-text`, `--jakawi-video-overlay`. `theme="custom"` marca la instancia; no introduce una paleta predeterminada distinta. `colors` funciona también con inherit; los campos omitidos heredan.

Props de colores válidas: hexadecimal de seis dígitos. Valores inválidos se ignoran en React y se rechazan en Laravel/parser. Si se solicita texto o fondo, el helper fija fondo opaco (`#111111` si se omite overlay), comprueba contraste texto/fondo 4.5:1 y corrige texto a negro/blanco si hace falta. No garantiza automáticamente contraste del acento ni de CSS externo. Revisar esos valores y el foco. Los controles tienen etiquetas, foco visible y estados anunciados; curso mantiene scrim detrás de controles. Autoplay respeta reduced motion y ahorro de datos.

Ejemplo CSS externo aplicado al contenedor (sin overrides de esas props):

```css
.video-campana {
    --jakawi-video-accent: #ff6600;
    --jakawi-video-text: #ffffff;
    --jakawi-video-overlay: #111111;
}
```

## Ejemplos React/TypeScript

Este bloque es una unidad TSX: incluye los siete ejemplos de reproducción/tema/curso. Las URLs MP4 de `example.com` son marcadores públicos de formato y deben reemplazarse por medios autorizados; no se presentan como videos disponibles. La elección de framing depende del archivo real, no de su URL.

```tsx
import { CourseVideo, JakawiVslPlayer } from '@/components/media/jakawi-vsl-player';

const youtube = { type: 'youtube', url: 'https://www.youtube.com/watch?v=hI-Ecy2D8pg' } as const;
const mp4 = { type: 'mp4', url: 'https://example.com/video-vertical.mp4' } as const;
const bunny = {
    type: 'bunny',
    url: 'https://vz-febf8c0d-fb8.b-cdn.net/31eb7a5d-2d72-4159-9735-4aa89bb599d3/playlist.m3u8',
} as const;

export function EjemplosPlayer() {
    return <>
        {/* 1. YouTube con vertical incrustado */}
        <JakawiVslPlayer source={youtube} framing="embedded-vertical" />
        {/* 2. YouTube horizontal: usar con un video de composición horizontal */}
        <JakawiVslPlayer source={youtube} framing="horizontal" />
        {/* 3. MP4 vertical; también para un MP4 directo de Bunny */}
        <JakawiVslPlayer source={mp4} framing="native-vertical" />
        {/* 4. Bunny HLS vertical */}
        <JakawiVslPlayer source={bunny} framing="native-vertical" />
        {/* 5. VSL con tema heredado */}
        <JakawiVslPlayer source={youtube} mode="vsl" framing="embedded-vertical"
            autoplay="muted" theme="inherit" title="Presentación JAKAWI" />
        {/* 6. VSL con colores personalizados */}
        <JakawiVslPlayer source={youtube} mode="vsl" framing="embedded-vertical"
            theme="custom" colors={{ accent: '#ff6600', text: '#ffffff', overlay: '#111111' }} />
        {/* 7. Curso: el servidor ya debe haber autorizado esta fuente */}
        <CourseVideo source={mp4} framing="native-vertical" autoplay="off"
            title="Lección autorizada" onEvent={(event) => {
                if (event.type === 'progress' && event.state.duration > 0) {
                    const ratio = event.state.currentTime / event.state.duration;
                    console.log('Progreso real', ratio);
                }
            }} />
    </>;
}
```

El callback ilustrativo no persiste avance ni acredita finalización. Para una página Inertia importar el componente y pasar una fuente autorizada desde las props de página; no se necesita un provider adicional.

## Marketing Landings

Configuración opcional JSON nullable `landing_presentations.vsl`, [migración](../app/database/migrations/2026_10_11_000001_add_vsl_to_landing_presentations.php). [LandingVideoConfig](../app/resources/js/components/media/jakawi-vsl-player/LandingVideo.tsx) admite source productiva, mode sólo VSL, framing string, autoplay string, poster, theme y colors; no toda la API avanzada React. [Validación Laravel](../app/app/Services/LandingVideoConfiguration.php) limita claves y colores.

Ejemplo 8: payload parcial para los endpoints admin existentes (en creación añadir los campos obligatorios de la presentación):

```json
{
  "vsl": {
    "source": { "type": "youtube", "url": "https://www.youtube.com/watch?v=hI-Ecy2D8pg" },
    "mode": "vsl",
    "framing": "embedded-vertical",
    "autoplay": "muted",
    "theme": "inherit"
  }
}
```

Ejemplo 9: restaurar el visual de imagen almacenado:

```json
{ "vsl": null }
```

Omitir `vsl` en una actualización conserva la configuración. Enviar null la elimina; no borra la imagen. El [administrador](../app/resources/js/pages/admin/landing-presentations/index.tsx) permite fuente, URL, framing y autoplay mediante [LandingVideoFields](../app/resources/js/components/media/jakawi-vsl-player/LandingVideoFields.tsx). Poster y colores están soportados por payload, sin campos dedicados en ese formulario.

| Variante | Ubicación |
| --- | --- |
| [Challenge](../app/resources/js/pages/landing-presentations/challenge.tsx) | Columna media del hero de conversión, sobre CTA y antes del premio |
| [Benefit](../app/resources/js/pages/landing-presentations/benefit.tsx) | Visual del aside de MarketingHero, antes del valor/partner |
| [Experience](../app/resources/js/pages/landing-presentations/experience.tsx) | Visual bajo el encabezado |
| [Unlock](../app/resources/js/pages/landing-presentations/unlock.tsx) | Visual bajo el encabezado, sujeto a revelación |

Sin configuración entregada se usa la imagen existente donde haya una; Challenge omite el wrapper media si no tiene video ni imagen. Un error de reproducción no cambia automáticamente a imagen. Unlock usa `Unlock::revealable()`: antes de revelar se devuelve `vsl: null`, sin serializar fuente, URL ni póster, también en preview. El player no convierte una URL pública en privada. CTA y handlers Growth de las páginas siguen siendo los existentes; no hay wiring automático de `onEvent` a Growth ni desbloqueo por ver el video.

## Cursos

[CourseVideo](../app/resources/js/components/media/jakawi-vsl-player/CourseVideo.tsx) acepta `Omit<JakawiVslPlayerProps, 'mode'>` e impone `mode="course"`; ejemplo 7 arriba. No hay todavía página de lecciones integrada ni rutas nuevas de curso que utilicen el wrapper. **El servidor debe verificar autorización del alumno antes de enviar la URL al cliente**, incluidos payloads Inertia y SSR. El wrapper no verifica matrícula ni permisos. Para contenido protegido entregar URLs temporales mediante una capa autorizada externa al player.

## Shortcodes

[parseVideoShortcode](../app/resources/js/components/media/jakawi-vsl-player/video-shortcode.ts) está exportado y probado, pero no conectado a campos de texto: no funcionan automáticamente en cualquier descripción.

```text
[jakawi_video source="youtube" id="hI-Ecy2D8pg" mode="vsl" framing="embedded-vertical"]
[jakawi_video source="bunny" url="https://vz-febf8c0d-fb8.b-cdn.net/31eb7a5d-2d72-4159-9735-4aa89bb599d3/playlist.m3u8" mode="course" framing="native-vertical" autoplay="off"]
```

Sólo un shortcode completo, máximo 4096 caracteres, atributos minúsculos con comillas dobles. Allowlist: `source`, `id`, `url`, `mode`, `framing`, `autoplay`, `poster`, `theme`, `accent`, `text`, `overlay`. Rechaza duplicados, desconocidos, HTML/JSX y sintaxis adicional; devuelve props o null, no evalúa ni renderiza. YouTube exige ID de 11 caracteres y prohíbe url; MP4/Bunny exigen url HTTPS sin credenciales y prohíben id. Poster también exige HTTPS; colores `#RRGGBB`; enums como API string. La validación del parser no reemplaza las restricciones del motor: Bunny aún requiere `.m3u8`.

## Operación y validación

Desde `app`, en entorno de desarrollo aislado con dependencias npm/Composer instaladas:

```bash
npm ci
npm run test:vsl
npm run types:check
npm run build
```

`types:check` ejecuta Wayfinder y `tsc --noEmit`; build usa Vite Plus. No ejecutar el build sobre artefactos servidos de producción para una revisión documental. [Dockerfile.vsl](../docker/Dockerfile.vsl) target assets valida tipos y build con contexto aislado. [run-vsl-tests.sh](../app/tests/media/run-vsl-tests.sh) cubre motores HTML5/YouTube, HLS, encuadres, curso, parser y colores; no invoca el test opcional histórico de navegador de demo.

Feature tests: `LandingPresentationTest`, usando sólo entorno VSL aislado y su guard, no la DB productiva:

```bash
VSL_ENV_CONFIRM=jakawi-vsl-test bin/jakawi-vsl-test --check
# Con contenedor de test ya preparado y configuración aislada comprobada:
docker compose --project-name jakawi-vsl-test --env-file .env.vsl-test -f compose.vsl-test.yaml exec -T app-test php vendor/bin/phpunit -c phpunit.vsl.xml --filter LandingPresentationTest
```

Consultar [OPERATIONS](OPERATIONS.md) para aislamiento; `.env.vsl-test` es configuración local no incluida en el commit. El wrapper Artisan histórico puede advertir configuración duplicada; PHPUnit directo evita esa duplicación.

Errores comunes: `source` inválida (HTTP MP4, Bunny MP4 mal tipado, iframe en lugar de medio); autoplay bloqueado (usar inicio manual); HLS HTTP/CORS o decodificación (revisar CDN/codecs, usar reintento); vertical mostrado horizontal (declarar framing); URL expirada (renovar mediante servidor autorizado). Seek/volumen dependen de las capacidades reportadas por el proveedor/navegador.

Dependencias de red: API YouTube, iframe nocookie, miniaturas `i.ytimg.com`, CDN del medio/póster; el build general además obtiene fuentes Bunny de Vite. Recomendar MP4 con codecs compatibles y fast-start/Range, y HLS con variantes adaptativas, MIME y CORS correctos. Evitar URLs privadas permanentes en documentación; no exponer secretos de firma al cliente.

## Activación de sonido en VSL

Mientras el VSL reproduce silenciado o espera confirmación de audio, toda la superficie libre del video es un botón semántico: tocar el centro o una esquina solicita volver a 00:00, activar sonido y reproducir mediante la acción existente, directamente desde el gesto. La cápsula «Enciende tus parlantes / Toca para escuchar» es sólo la indicación visual; admite personalización con los tokens de texto, fondo y acento existentes. Enter y Space también activan el botón, con foco visible.

La capa se retira al confirmar reproducción audible y deja disponibles las interacciones del proveedor. Pausa permanece por encima de la capa; replay y el inicio manual conservan sus estados. Un bloqueo mantiene inicio/reintento manual, sin simular éxito. El cambio de fuente limpia la solicitud. Curso no utiliza esta capa ni reinicia la posición al activar sonido.

Sobre el iframe YouTube el botón pertenece al contenedor visual, sin remontar el iframe ni modificar el crop vertical. En móviles se usa el clic del toque, sin handlers de touch/pointer que dupliquen acciones y permitiendo scroll vertical y zoom. Las políticas del navegador/proveedor pueden bloquear audio o autoplay: la confirmación procede del motor y se conserva recuperación manual.

## Estado de entrega y evidencia

Mejora tap-to-sound del 11 de octubre de 2026: implementada en el worktree limpio `/home/jakawi-video-touch-dev`, rama `feat/video-tap-to-sound`, desde `origin/main` (`af80147`). La demo pendiente de `/home/jakawi-vsl-dev` se preserva. Validación aislada: 61 tests del motor/parser, TypeScript y build aprobados; Chromium comprueba centro/esquina, Enter/Space, foco, toque y scroll emulados, una solicitud por gesto, espera de confirmación, bloqueo de autoplay/audio, pausa/replay, Curso y cambio de fuente. MP4/HLS utilizan medios reales de prueba; YouTube utiliza su contrato de API y un iframe de prueba. La comprobación adicional con YouTube remoto no llegó a reproducir en este entorno y no se certifica; no hubo dispositivo móvil físico. Advertencias del build: optimización opcional de fuentes y chunk HLS mayor de 500 kB. El [harness de pruebas](../app/tests/media/tap-harness/README.md) describe ejecución y requisitos.

Evidencia histórica previa, conservada como referencia (no describe el release tap-to-sound):

- Reproductor e integraciones consolidados en `cdfed97d7d7988a50501904102f464bad37f6a8a`.
- Principal `/home/jakawi.com`: rama `main`, HEAD `d440c02628df5785b7d51a971daf326db1253bd3` (merge sin demo local), árbol de trabajo inicialmente limpio. El módulo es idéntico al commit consolidado.
- Desarrollo `/home/jakawi-vsl-dev`: rama `feat/jakawi-vsl-v1`, HEAD `cdfed97`; cambios previos sin commit en demo y archivos opcionales `course-browser.test.mjs`, `VSL_COURSE.md` y `VSL_MARKETING.md`. No se incorporaron ni modificaron en este cierre.
- Runtime principal observado, sin desplegar: contenedores `jakawicom-app-1` y `jakawicom-web-1` iniciados el 11 de octubre a las 09:53 (America/La_Paz). El archivo de tipos dentro de app coincide por SHA-256 con el principal; web contiene el chunk HLS. `php artisan migrate:status` en app registra `2026_10_11_000001_add_vsl_to_landing_presentations` como `Ran`, batch 35. Esto confirma presencia del código y migración; no certifica reproducción remota, una landing configurada ni identidad Git completa de la imagen.
- Este cierre sólo modifica documentación en el principal; no hace push, commit, deploy ni migraciones. El HEAD anterior se conserva hasta que se registre el cambio documental.

La auditoría aislada previa reportó 61 tests y TypeScript/build aprobados; los 17 feature tests/540 assertions corresponden al informe previo de integración. No se presentan como ejecuciones nuevas. La validación de este cierre se registra abajo. Advertencias históricas: chunk HLS mayor de 500 kB y optimización opcional de fuentes.

Limitaciones de entrega: falta una página de lecciones y wiring de shortcodes; protección de medios depende del servidor/CDN; fixtures y proveedores requieren conectividad. Son límites documentados, no funcionalidades añadidas ni tareas necesarias para reconstruir el player.

### Validación ejecutada durante este cierre

- Se extrajo el bloque TSX anterior a un archivo temporal y se compiló con `tsc --noEmit` junto con la aplicación en `jakawi-vsl-native-audit:local`, contenedor efímero sin red. Los 18 archivos del módulo de esa imagen coinciden por SHA-256 con el principal. TypeScript aprobado; archivo temporal eliminado.
- `npm run test:vsl` en la misma imagen: 61 pruebas aprobadas, sin fallos. No se ejecutó el test histórico de navegador de demo.
- Ambos payloads JSON del manual pasan `LandingVideoConfiguration::rules()` mediante Validator en el contenedor de test, tras comprobar entorno `testing` y base configurada `jakawi_vsl_test`; sin consultas ni cambios a DB.
- `python3 bin/check-docs.py`: sin enlaces relativos rotos; `git diff --check`: aprobado. Rutas revisadas contra archivos locales. El checker no verifica anchors ni enlaces externos.
- El intento local de TypeScript en el principal falló porque su instalación npm existente carece de `hls.js`; no se alteraron esas dependencias. La validación aislada anterior resuelve la comprobación de ejemplos. No se ejecutaron `npm run types:check` (generación Wayfinder), build ni feature tests nuevos; sus resultados previos siguen identificados como históricos.

### Contexto de color de marketing

Las cuatro Marketing Landings pasan `theme="inherit"`: MarketingLayout establece `dark-conversion` (Challenge) o `light-conversion` (Benefit/Experience/Unlock). El player hereda tokens locales deterministas independientemente de `.dark` del usuario. Sus internals, colores por instancia y proveedores permanecen intactos. El [contrato marketing](DESIGN-SYSTEM.md#marketing-theme-foundation-v1) no depende del video: admite imagen o ausencia de media donde el renderer lo soporta. No existe selección persistida de tema en LandingPresentation todavía.
