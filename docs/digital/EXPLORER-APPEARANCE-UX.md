# Explorador digital y apariencia

Baseline COM: `1de6182b9d288ba735122d63f5775c4778d7c1f7`.

`/explorar?type=digital` y `/productos-digitales` renderizan la misma página
Inertia `explore`: contenedor, título, búsqueda, filtros, ordenamiento, recuento,
cuadrícula y carga incremental. La entrada directa conserva su URL al buscar u
ordenar. Los demás filtros llevan a `/explorar`. El catálogo digital no consulta
la ciudad ni muestra categorías presenciales; el backend publica únicamente
productos de Partners publicados. Paginación de 18, orden por nombre o recientes,
búsqueda por nombre y contenido. Los resultados digitales usan `ExplorerCard`,
compartido con las oportunidades, y `JakawiImage`. No hay cambios comerciales.

`AppearanceMenu` usa los primitives Radix existentes y el store `use-appearance`.
`ThemeToggle` aporta la autenticación en Inertia; el entry de Blade monta el mismo
componente, sin otra preferencia ni navegación. Botón de 44 px con sol/luna/monitor,
menú de opciones radio, foco y Escape gestionados por Radix. Se conservan PATCH,
preferencia de cuenta, cookie, storage y detección del sistema existentes.

## Validación

- `bin/jakawi-test php artisan test tests/Feature/DigitalCatalogTest.php tests/Feature/ExploreDiscoveryTest.php tests/Feature/ThemePreferenceTest.php tests/Feature/DigitalCheckoutTest.php`
- `npm run types:check` y `npm run build`.
- `tests/marketing/explore-digital-ux-qa.php`: exige testing/db-test/jakawi_test;
  prepara 20 publicaciones ficticias, un borrador, perfiles y ofertas físicos de QA.
  PRELAUNCH activo, sin cuenta bancaria ni QR.
- `tests/marketing/explore-digital-ux-browser.cjs`: Playwright en la red interna de QA,
  390/1440, búsqueda, ordenamiento, paginación, filtros físicos, estados de tema,
  persistencia, teclado, contraste, React/Blade/Admin/Partner y formularios/video.
  Variables QA_ORIGIN, QA_EVIDENCE, QA_PLAYWRIGHT_MODULE, QA_CHROMIUM_PATH y
  QA_VIDEO_FIXTURE. Nunca ejecutar fixtures contra LIVE.

La evidencia operativa y capturas están fuera de Git en
`/srv/workspaces/jakawi/com/explore-digital-appearance-ux-evidence/`.

## Release sin migraciones

Controlador revisado: `docker/new-server/ux-release-control.py`, instalado y ejecutado
exclusivamente como `/opt/sites/jakawi.com/release-control.py`. Requiere PASS de QA,
fuente limpia, rama candidata publicada y main todavía en el baseline. Prepara
backup de imágenes/configuración/DB y rollback a las imágenes anteriores; no restaura
la DB. Compara configuración, esquema completo y datos del dominio, incluidos los
productos, cobros, pedidos y accesos digitales. No ejecuta migraciones. Conserva
PRELAUNCH=true, DEMO OFF y cero configuración de cobro/ventas. Solo recrea los cuatro
servicios COM; verifica que los contenedores protegidos no cambien. La promoción de
main es fast-forward posterior a la verificación LIVE y al navegador LIVE PASS.
