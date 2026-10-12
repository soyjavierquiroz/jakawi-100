# JAKAWI Landing-first Product Journey V1

**READY FOR DEPLOY — 2026-10-12.** Refinamiento sobre `63a1751a4efaebecca681343de1d0dae33b5eedb`. Producción permanece en `8d913038984ae5eb6d40f3ba7477beae5d928ece`; sin migración, cutover ni publicación de registros.

LandingPresentation publicada aplicable es la presentación principal: guest `GUESTS > ALL`; cuenta gratuita y miembro activo `ALL`. `NONE` nunca se resuelve automáticamente. Sin presentación aplicable, Product Detail sigue como fallback funcional mínimo. Se conserva el único default por subject y la visibilidad de `/l` directo. Marketing normal recomienda `ALL`, sin cambios automáticos de inventario.

Benefit reutiliza ubicación/confirmación sobre la misma Landing. Experience reutiliza selección y reserva del dominio; Challenge mantiene participación/verificación. Unlock conserva su renderer y autorización de disclosure, con los controles existentes en contexto. Membresía conserva Landing, sucursal/fecha en `journey_context` existente y vuelve mediante su retorno explícito; sin nueva persistencia. Auth no ejecuta acciones económicas ni de dominio. V3 permanece congelado.

## Validación

- [Backend](backend.txt): 13 tests / 416 assertions PASS, incluyendo auth, cuentas gratuitas y miembros, fallback, membresía, fecha/sucursal, no mutación y restricted source de Unlock.
- [Routing](routing.txt) y [Experience/Unlock](experience-unlock-routing.txt): 6 regresiones / 609 assertions PASS. Total: **19 tests / 1025 assertions**; PHP sólo Docker 8.4, sin suite amplia.
- [TypeScript](types.txt), docs y git diff PASS. [Un único build frontend final](frontend-build.txt) con guard de manifest PASS (101 páginas); [resumen](validation.json).
- [Browser](browser-validation.json): tres representantes aislados, 390 px; Café guest/free/member, Sunset y Reto Cena autenticados, una regresión 1440. Backend/auth reales del Docker de tests; producción sólo sirve imágenes por GET. Ninguna publicación productiva.
- [Contadores](domain-counts.json): cero canjes, reservas, participaciones, compromisos y JP holds. Una membresía activa creada exclusivamente como fixture antes del smoke; cero activaciones por navegación.

[Benefit gratuito 390](benefit-free-390.png) · [Experience autenticado 390](experience-auth-390.png) · [Único desktop 1440](benefit-1440.png).

[Arquitectura](../../docs/ARCHITECTURE.md#acquisition-funnel-v1) · [Estado pendiente](../../docs/CURRENT.md#acquisition-funnel-v1--implementación-pendiente-de-deploy-2026-10-12) · [Manual](../../docs/product/MANUAL_DE_FUNCIONES.md#captación-desde-marketing-landing--acquisition-funnel-v1-pendiente-de-deploy).
