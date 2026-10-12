# Admin UX y calibración V2

12 de octubre de 2026. Implementación local; sin despliegue ni escritura de drafts en producción. Migración aditiva pendiente: `2026_10_12_000002_add_offer_layout_controls`.

Tres drafts QA seleccionados; los otros tres se excluyen de esta recalibración:

| Draft | Hero | Densidad |
| --- | --- | --- |
| qa-calibration-benefit-value | OFFER_FIRST | STANDARD |
| qa-calibration-experience-desire | MEDIA_LED | STANDARD |
| qa-calibration-challenge-minimal | OFFER_FIRST | COMPACT |

Comparación local usando copy/facts capturados en V1 y el bundle nuevo. Experience recupera `VIVE EL ATARDECER EN COCHABAMBA.` y `Música suave y vista a la ciudad en Mirador Cobalto.`. Challenge Minimal conserva `Premio de prueba sin valor comercial.` sin agregar mecánica/selección al subheadline, y omite la descripción de cierre vacía. Sus reglas siguen visibles en los bloques de dominio.

El fixture visual de Challenge representa participación abierta y usuario invitado para demostrar que preview no fuerza un estado cerrado. No acredita el estado actual de producción. La prueba backend adicional verifica estado abierto/cerrado real, mientras el renderer desactiva interacciones y polling en preview.

| Draft | 390 | 430 | 1440 |
| --- | --- | --- | --- |
| Benefit Value | [Screenshot](benefit-value-390.png) | [Screenshot](benefit-value-430.png) | [Screenshot](benefit-value-1440.png) |
| Experience Desire | [Screenshot](experience-desire-390.png) | [Screenshot](experience-desire-430.png) | [Screenshot](experience-desire-1440.png) |
| Challenge Minimal | [Screenshot](challenge-minimal-390.png) | [Screenshot](challenge-minimal-430.png) | [Screenshot](challenge-minimal-1440.png) |

[Resultados DOM](results.json). Sin errores JS, overflow ni prompt PWA; CTA desactivados. Assets demo son evidencia QA, no inventario comercial.

Validación: 41 pruebas backend (1612 assertions), TypeScript y compilación con manifest completo de 101 páginas. PHP para compilación/pruebas ejecutado en contenedor de test.

Para aplicar en producción: desplegar bundle/backend/migración juntos y guardar exclusivamente las tres configuraciones indicadas mediante Admin; repetir preview protegido con props actuales antes de dar por terminada la calibración live. Esta evidencia no declara esos pasos ejecutados.

Inventario comercial: carga pendiente, sin registros confirmados; [fuente de onboarding](../../docs/operations/onboarding/README.md).
