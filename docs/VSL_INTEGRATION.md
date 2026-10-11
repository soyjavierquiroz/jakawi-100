# Integración reutilizable de Jakawi Video Player

```tsx
import { JakawiVslPlayer, CourseVideo } from '@/components/media/jakawi-vsl-player';

<JakawiVslPlayer
    source={{ type: 'youtube', url: 'https://www.youtube.com/watch?v=hI-Ecy2D8pg' }}
    framing="embedded-vertical"
/>

<CourseVideo source={{ type: 'mp4', url: 'https://cdn.example/lesson.mp4' }} />
```

API: `source` ({type: youtube | mp4 | bunny, url}), `mode` (vsl | course),
`framing` (embedded-vertical | native-vertical | horizontal), `autoplay`
(muted | off), `poster`, `theme` (inherit | custom), `colors`
({accent?, text?, overlay?}). Los eventos y las opciones avanzadas existentes
siguen disponibles. También se conservan autoplay booleano y framing objeto.
Sin parámetros adicionales conserva VSL, autoplay silenciado sujeto a preferencias
del navegador y encuadre automático previo. Curso comienza manualmente.
Bunny usa una URL directa MP4/HLS, no una URL de iframe de Stream.

Hereda `--brand`, `--cream`, `--scrim`, `--overlay`, `--ink` y `--shadow-featured`.
`theme="custom"` identifica una instancia personalizada; los valores omitidos
siguen heredando tokens. `colors` permite overrides incluso con `theme="inherit"`.
Las variables por instancia son `--jakawi-video-accent`, `--jakawi-video-text`
y `--jakawi-video-overlay`, configurables desde una clase del contenedor.
Los overrides vía props admiten hexadecimal de seis dígitos; el par texto/fondo
se corrige a contraste mínimo 4.5:1. El override de texto fija un fondo opaco;
sin overrides se mantienen los tokens. Las variables CSS definidas externamente
deben respetar el mismo contraste. La franja del curso mantiene un scrim detrás
de los controles, incluso sobre video claro.

## Marketing Landings

Migración incremental: columna JSON nullable `landing_presentations.vsl`.
No se crea VideoAssets. Los endpoints admin existentes aceptan, por ejemplo:

```json
{
  "vsl": {
    "source": { "type": "youtube", "url": "https://www.youtube.com/watch?v=hI-Ecy2D8pg" },
    "framing": "embedded-vertical",
    "autoplay": "muted",
    "theme": "inherit"
  }
}
```

El formulario existente ofrece fuente, URL, encuadre y autoplay. Colores y póster
se configuran mediante el mismo payload validado de Laravel. Omitir `vsl` conserva
la configuración almacenada; enviar `null` la elimina y restaura la imagen.
Las cuatro variantes (Challenge, Benefit, Experience y Unlock) usan el mismo player
en su visual existente. Las CTA y Growth mantienen sus handlers.

Unlock utiliza la frontera existente `Unlock::revealable()`: toda configuración
VSL se considera contenido restringido hasta revelar, incluso en preview.
No se serializan fuente, URL ni póster durante ese bloqueo. No se inventa un permiso
por video: este modelo no contiene tal sistema. Las URLs que se entregan después
de revelar deben ser apropiadas para esa audiencia; el player no convierte una
URL pública en un recurso protegido.

## Cursos y shortcode

No hay una página real de lecciones ni una superficie compatible con shortcodes
en este workspace. `CourseVideo` es el wrapper preparado, sin nuevas rutas.
El futuro servidor de lecciones debe autorizar el acceso antes de serializar la
fuente: el wrapper no concede permisos.

`parseVideoShortcode` transforma un único shortcode completo en props o devuelve
`null`. Usa allowlist, rechaza duplicados y atributos desconocidos, admite HTTPS
sin credenciales y no interpreta HTML, JSX ni código. Ejemplo:

```text
[jakawi_video source="youtube" id="hI-Ecy2D8pg" mode="vsl" framing="embedded-vertical"]
```

Para MP4/Bunny usar `url`, sin `id`; para colores usar `accent`, `text`, `overlay`.
El parser está exportado y probado, pero no conectado a campos de texto plano.

## Validación aislada

- 61 tests del player, HLS, YouTube, encuadre, curso, parser y colores: `npm run test:vsl` (todos pasan).
- 17 feature tests de landings/configuración/permisos (540 assertions, todos pasan) en `jakawi-vsl-test`, PostgreSQL
  dedicado, mediante guard de `bin/jakawi-vsl-test --check` y PHPUnit directo
  (`php vendor/bin/phpunit -c phpunit.vsl.xml --filter LandingPresentationTest`).
  El wrapper Artisan añade una advertencia de opción `--configuration` duplicada.
- `npm run types:check` y `npm run build` en herramientas VSL de desarrollo aisladas.

No se aplicó la migración a preview ni producción. No hubo deploy ni push.
