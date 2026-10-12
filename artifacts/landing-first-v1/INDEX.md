# JAKAWI Landing-first Product Journey V1

**LIVE — 2026-10-12.** Refinamiento sobre `63a1751a4efaebecca681343de1d0dae33b5eedb`. Desplegado en `e848b9f4833b514ea64b1b337bdd426235273100`, con la única migración de captación y tres representantes DEMO / ALL. [Release productivo](production-release.json) y [smoke productivo](production-smoke.json).

LandingPresentation publicada aplicable es la presentación principal: guest `GUESTS > ALL`; cuenta gratuita y miembro activo `ALL`. `NONE` nunca se resuelve automáticamente. Sin presentación aplicable, Product Detail sigue como fallback funcional mínimo. Se conserva el único default por subject y la visibilidad de `/l` directo. Marketing normal recomienda `ALL`, sin cambios automáticos de inventario.

Benefit reutiliza ubicación/confirmación sobre la misma Landing. Experience reutiliza selección y reserva del dominio; Challenge mantiene participación/verificación. Unlock conserva su renderer y autorización de disclosure, con los controles existentes en contexto. Membresía conserva Landing, sucursal/fecha en `journey_context` existente y vuelve mediante su retorno explícito; sin nueva persistencia. Auth no ejecuta acciones económicas ni de dominio. V3 permanece congelado.

## Validación

- [Backend](backend.txt): 13 tests / 416 assertions PASS, incluyendo auth, cuentas gratuitas y miembros, fallback, membresía, fecha/sucursal, no mutación y restricted source de Unlock.
- [Routing](routing.txt) y [Experience/Unlock](experience-unlock-routing.txt): 6 regresiones / 609 assertions PASS. Total: **19 tests / 1025 assertions**; PHP sólo Docker 8.4, sin suite amplia.
- [TypeScript](types.txt), docs y git diff PASS. [Un único build frontend final](frontend-build.txt) con guard de manifest PASS (101 páginas); [resumen](validation.json).
- [Browser](browser-validation.json): tres representantes aislados, 390 px; Café guest/free/member, Sunset y Reto Cena autenticados, una regresión 1440. Backend/auth reales del Docker de tests; producción sólo sirve imágenes por GET. Esta validación aislada precedió al deploy; el release posterior publicó sólo las tres representantes DEMO / ALL.
- [Contadores](domain-counts.json): cero canjes, reservas, participaciones, compromisos y JP holds. Una membresía activa creada exclusivamente como fixture antes del smoke; cero activaciones por navegación.

[Benefit gratuito 390](benefit-free-390.png) · [Experience autenticado 390](experience-auth-390.png) · [Único desktop 1440](benefit-1440.png).

[Arquitectura](../../docs/ARCHITECTURE.md#acquisition-funnel-v1) · [Estado LIVE](../../docs/CURRENT.md#landing-first-product-journey-v1--live-2026-10-12) · [Manual](../../docs/product/MANUAL_DE_FUNCIONES.md#captación-desde-marketing-landing--landing-first-journey-v1-live).

## Producción — revisión humana

[Café](https://jakawi.com/l/qa-calibration-benefit-value) · [Sunset](https://jakawi.com/l/qa-calibration-experience-desire) · [Reto Cena](https://jakawi.com/l/qa-calibration-challenge-action) · [Fallback Product Detail](https://jakawi.com/beneficios/demo-beneficio-04). Tres DEMO / ALL sintéticas, no comerciales, pre-launch; no inventario de lanzamiento.

[Modal Café 390](production-benefit-modal-390.png) · [Café auth → ubicación 390](production-benefit-location-390.png) · [Modal Sunset 390](production-experience-modal-390.png) · [Modal Reto Cena 390](production-challenge-modal-390.png).

Revisión visual 390: sin overflow ni shell duplicado; login visible y CTA del modal coral con texto blanco. El CTA principal de las Landings conserva texto oscuro sobre coral en V3: pendiente de revisión humana, sin modificación de código durante este release.
