# Auditoría de cambios y galería responsive — 14 de octubre de 2026

## Alcance y revisión

Se revisaron `git status`, `git diff --stat`, `git diff --check` y el contenido de los quince archivos previamente modificados, además de los tres archivos nuevos del carrusel. No todos los cambios pertenecían a la galería. Se identificaron los siguientes desarrollos terminados:

- **Importación JSON de landings:** seleccionar producto ya no abre implícitamente un formulario de creación. Importar requiere una landing existente; el parser no aplica el slug importado. Se conservan identidad, asociación, scopes y estado de publicación. Incluye pruebas del parser y de persistencia.
- **Hero de Offers:** fuente desktop 4:5 desde el master mediante el preset existente y `picture/source`, conectada a Benefits, Experiences y Challenges. Conserva la fuente móvil y no cambia VSL. Incluye prueba PHP de las fuentes.
- **Portada Partner:** fuente vertical desktop desde el master, marco 4:5 y `object-contain`; móvil conserva su tratamiento existente.
- **Avatar Partner:** máscara circular propia sin padding interior, conservando el master y `object-contain`.
- **Subidas Nginx:** `client_max_body_size 16m`, consistente con `docker/php/media.ini` (`post_max_size=16M`, `upload_max_filesize=12M`). No cambia rutas, upstreams ni acceso a archivos.
- **Galería Partner:** componente compartido `PartnerGallery`, activo como carrusel por debajo de 768 px. Imágenes al 84%, separación de 12 px, peek, swipe y flechas de teclado. Copias equivalentes permiten normalizar los extremos sin cambiar la composición visible; con una fotografía no hay copias ni autoplay. La transición dura 400 ms y el autoplay avanza una fotografía cada 2000 ms. Interacción/foco pausa por 4000 ms; una pulsación mantenida permanece pausada. También pausa fuera del viewport, con pestaña oculta y con reduced motion. No añade dependencias. El grid desktop conserva la composición anterior.

No se encontraron cambios de propósito desconocido ni código pendiente de integración. Se preservaron los cambios anteriores en `partners/show.tsx`, `app.css` y los controladores. No se modificaron PRELAUNCH_MODE, PrelaunchNotice, migraciones ni datos de aplicación como parte de la sincronización.

## Validaciones ejecutadas

- `git diff --check`: sin errores.
- `bash app/tests/marketing/run-landing-json-tests.sh`: 14 pruebas aprobadas.
- `node --test tests/marketing/prelaunch-components.test.mjs` desde `app`: 2 pruebas aprobadas.
- `npm run types:check` con PHP 8.4 Docker y `tsc --noEmit`: aprobado. También se repitió `tsc --noEmit` después del ajuste final del carrusel.
- Lint focalizado del componente del carrusel: aprobado.
- Build Vite completo hacia carpeta temporal, con PHP Docker compatible; comprobador de manifest: 104 entradas Inertia válidas. No se reemplazaron los assets servidos por producción. Advertencias de tamaño de chunks y fontaine opcional; build exitoso.
- PHP 8.4 lint: ambos controladores modificados y `MarketingLandingAdminV1Test.php`, sin errores.
- Nginx: `nginx -t` en contenedor efímero, configuración fuente montada en modo lectura y sin red; aprobado.
- Navegador JSON: importar sobre una landing seleccionada solo modifica el formulario, conserva slug/audiencia y el guardado explícito apunta por PUT a la ruta existente. Formulario nuevo bloquea la importación. Mutaciones capturadas localmente, sin enviarlas.
- Navegador Offers: 390/1024/1440 px, fuentes existentes cargadas, imagen vertical desktop, dos columnas desktop, sin overflow ni errores JS y aviso prelaunch conservado.
- Navegador Partners: 320/375/390/430 px con main al 84% y peek superior a 30 px; 1440 px con captura completa idéntica píxel a píxel a la página anterior al carrusel (incluyendo las correcciones previas de portada/avatar).
- Gestos táctiles reales mediante Chromium CDP: ambas direcciones, extremos del loop, desplazamiento vertical normal, pausa mientras se mantiene el toque y reanudación tras soltar y esperar 4 s. Teclado, reduced motion, galerías de 1/2/4 fotos y segundo Partner aprobados.
- Autoplay medido: una fotografía cada aproximadamente 2000 ms, transición CSS de 0.4 s; pausas/reanudación por foco, interacción, viewport y visibilidad de pestaña aprobadas. Sin errores JS.
- Smoke test del build de producción en servidor local: 390 y 1440 px, CSS del carrusel/grid cargado, imágenes reales, sin overflow ni errores JS.

Antes de recibirse la instrucción adicional de auditoría se ejecutó `bin/jakawi-test php artisan test --filter='PartnerEditorialMediaTest|PartnerPublicProfileTest|PartnerIdentityRefinementTest'`: 22 pruebas, 542 aserciones aprobadas en la base aislada de pruebas. Después de esa instrucción no se ejecutaron pruebas que escriban en bases de datos. La suite PHP MarketingLandingAdminV1Test no se reejecutó en esta auditoría; sus pruebas añadidas tenían evidencia local previa y se revisaron y validaron sintácticamente. No se afirma ejecución nueva de esa suite ni de toda la suite del proyecto.

## Evidencia y exclusiones

Capturas y reportes locales del carrusel: `artifacts/partner-gallery-carousel/` (`mobile-{320,375,390,430}.png`, `gallery-{320,375,390,430}.png`, `desktop-before-1440.png`, `desktop-1440.png`, `production-{390,1440}.png`, `validation.json`, `production-smoke.json`). Son evidencia de QA local, excluida de los commits.

Los siguientes directorios se conservaron completos y sin versionar; no se movieron ni eliminaron:

- `artifacts/landing-import-hero-responsive/`
- `artifacts/partner-avatar-circular-v1/`
- `artifacts/partner-hero-responsive-fix/`

Se tomó un inventario SHA-256 de sus 93 archivos para comprobar preservación. Se excluyeron capturas, copias de masters, respuestas de datos, metadatos/respaldos de releases y logs. No se incluyeron `.env`, credenciales, dumps, builds temporales ni fixtures de datos privados. Revisión manual y búsqueda de patrones de secretos sobre los 18 archivos fuente: sin hallazgos.

El navegador utilizó fuentes locales y snapshots de respuestas públicas existentes, con imágenes reales y sin escrituras de aplicación. El build y sus servidores de QA son locales y temporales. La sincronización de GitHub no constituye un despliegue; no se desplegaron cambios ni se reiniciaron servicios de aplicación.

## Archivos incorporados al historial

- `app/app/Http/Controllers/LandingPresentationController.php`
- `app/app/Http/Controllers/PublicController.php`
- `app/resources/css/app.css`
- `app/resources/js/components/marketing/LandingJsonControls.tsx`
- `app/resources/js/components/marketing/offer/blocks.tsx`
- `app/resources/js/components/marketing/offer/presentation.ts`
- `app/resources/js/components/partner-gallery.css`
- `app/resources/js/components/partner-gallery.tsx`
- `app/resources/js/lib/landing-json.ts`
- `app/resources/js/pages/admin/landing-presentations/index.tsx`
- `app/resources/js/pages/landing-presentations/adapters/benefit-offer-adapter.tsx`
- `app/resources/js/pages/landing-presentations/adapters/challenge-offer-adapter.tsx`
- `app/resources/js/pages/landing-presentations/adapters/experience-offer-adapter.tsx`
- `app/resources/js/pages/partners/show.tsx`
- `app/tests/Feature/MarketingLandingAdminV1Test.php`
- `app/tests/marketing/landing-json.test.mjs`
- `app/tests/marketing/partner-gallery-browser.mjs`
- `docker/nginx/default.conf`
- `docs/qa/2026-10-14-responsive-partners-and-repository-audit.md`
