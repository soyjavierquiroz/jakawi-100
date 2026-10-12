# JAKAWI Acquisition Funnel V1

**LIVE — 2026-10-12.** Implementación sobre `9c49421d0ba8792144bff852a72f8daa82064f48`. Desplegado junto con Landing-first Journey V1 en `e848b9f4833b514ea64b1b337bdd426235273100`; tres representantes sintéticas DEMO / ALL publicadas para evaluación pre-launch.

Guest nativo → default `PUBLISHED` para `GUESTS|ALL` → `/l/{slug}`; sin default usa Product Detail. Contrato refinado por Landing-first V1: authenticated nativo usa Landing `ALL` aplicable; sin ella usa Product Detail. Guest prioriza `GUESTS > ALL`. Acciones y retorno de membresía conservan la misma Landing. `/l` directo conserva visibilidad. El CTA abre registro/login contextual, vuelve a la misma Landing y habilita la acción del mismo dominio. Benefit reutiliza ubicación/membresía/confirmación existentes; no hay mutaciones automáticas.

Dos campos nuevos: `acquisition_headline`, `acquisition_supporting_copy`, con fallback por dominio y una migración aditiva. Registro contextual: nombre, correo, teléfono/WhatsApp y contraseña elegida por el usuario según las reglas actuales; sin ubicación ni passwordless. V3, reservas, verificación de Challenge y disclosure de Unlock conservan sus contratos.

Contrato y validación posteriores: [Landing-first Product Journey V1](../landing-first-v1/INDEX.md), LIVE en producción.

## Validación

- [Resumen](validation.json): 29 tests focalizados / 1380 assertions, TypeScript, docs, git diff y un único build final PASS. PHP sólo Docker 8.4; no suite completa.
- [Backend](backend-validation.txt) y [regresión de routing](routing-regression.txt): auth/subject/Landing, fallback, cuatro dominios, selector de Benefit, no mutación, Challenge sin bypass de verificación y Unlock sin source/VSL restringido.
- [Browser](browser-validation.json): 390 px, registro de Café con alternativa login, login de Sunset y Reto Cena, y una Landing/modal a 1440 px. Frontend actual con Vite y backend/auth/sesión reales de Docker; producción sólo sirve assets de imagen por GET en esa QA aislada previa al deploy.
- Sólo tres copias aisladas de drafts existentes: `qa-calibration-benefit-value` (8), `qa-calibration-experience-desire` (10), `qa-calibration-challenge-action` (12). Sólo esas copias en `jakawi_test` se publicaron para QA; no se creó inventario productivo.
- [Contadores de dominio después de QA](browser-domain-counts.json): cero canjes, reservas, participaciones, compromisos, membresías o JP holds.
- [Retorno de Benefit](benefit-return-validation.json): modal auth cerrado, un único selector existente sobre la Landing. [Acento amarillo y CTA blanco](visual-validation.json); verde sólo en Cuenta lista dentro del nuevo recorrido.

## Capturas mínimas

[Modal 390](benefit-modal-390.png) · [Auth → ubicación 390](benefit-location-390.png) · [Un modal desktop 1440](benefit-modal-1440.png).

[Contrato](../../docs/ARCHITECTURE.md#acquisition-funnel-v1) · [Estado LIVE](../../docs/CURRENT.md#landing-first-product-journey-v1--live-2026-10-12) · [Manual](../../docs/product/MANUAL_DE_FUNCIONES.md#captación-desde-marketing-landing--landing-first-journey-v1-live).
