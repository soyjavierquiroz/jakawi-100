# JAKAWI Acquisition Funnel V1

**READY FOR DEPLOY — 2026-10-12.** Implementación sobre `9c49421d0ba8792144bff852a72f8daa82064f48`. Producción de referencia: `8d913038984ae5eb6d40f3ba7477beae5d928ece`. Esta entrega no despliega ni publica registros de producción.

Guest nativo → default `PUBLISHED` para `GUESTS|ALL` → `/l/{slug}`; sin default usa Product Detail. Authenticated nativo usa Product Detail. `/l` directo conserva visibilidad. El CTA abre registro/login contextual, vuelve a la misma Landing y habilita la acción del mismo dominio. Benefit reutiliza ubicación/membresía/confirmación existentes; no hay mutaciones automáticas.

Dos campos nuevos: `acquisition_headline`, `acquisition_supporting_copy`, con fallback por dominio y una migración aditiva. Registro contextual: nombre, correo, teléfono/WhatsApp y contraseña elegida por el usuario según las reglas actuales; sin ubicación ni passwordless. V3, reservas, verificación de Challenge y disclosure de Unlock conservan sus contratos.

## Validación

- [Resumen](validation.json): 29 tests focalizados / 1380 assertions, TypeScript, docs, git diff y un único build final PASS. PHP sólo Docker 8.4; no suite completa.
- [Backend](backend-validation.txt) y [regresión de routing](routing-regression.txt): auth/subject/Landing, fallback, cuatro dominios, selector de Benefit, no mutación, Challenge sin bypass de verificación y Unlock sin source/VSL restringido.
- [Browser](browser-validation.json): 390 px, registro de Café con alternativa login, login de Sunset y Reto Cena, y una Landing/modal a 1440 px. Frontend actual con Vite y backend/auth/sesión reales de Docker; producción sólo sirve assets de imagen por GET.
- Sólo tres copias aisladas de drafts existentes: `qa-calibration-benefit-value` (8), `qa-calibration-experience-desire` (10), `qa-calibration-challenge-action` (12). Sólo esas copias en `jakawi_test` se publicaron para QA; no se creó inventario productivo.
- [Contadores de dominio después de QA](browser-domain-counts.json): cero canjes, reservas, participaciones, compromisos, membresías o JP holds.
- [Retorno de Benefit](benefit-return-validation.json): modal auth cerrado, un único selector existente sobre la Landing. [Acento amarillo y CTA blanco](visual-validation.json); verde sólo en Cuenta lista dentro del nuevo recorrido.

## Capturas mínimas

[Modal 390](benefit-modal-390.png) · [Auth → ubicación 390](benefit-location-390.png) · [Un modal desktop 1440](benefit-modal-1440.png).

[Contrato](../../docs/ARCHITECTURE.md#acquisition-funnel-v1) · [Estado pendiente de deploy](../../docs/CURRENT.md#acquisition-funnel-v1--implementación-pendiente-de-deploy-2026-10-12) · [Manual](../../docs/product/MANUAL_DE_FUNCIONES.md#captación-desde-marketing-landing--acquisition-funnel-v1-pendiente-de-deploy).
