# Registro de trabajo — BENEFIT

**WORKING TEMPLATE — no schema de código.** Copiar fuera del repo a un registro operativo autorizado. Sin datos reales de ejemplo; sólo contacto comercial necesario, sin datos personales sensibles ni credenciales. [Reglas](../MANUAL_DE_INVENTARIO.md) · [Gate piloto](../PILOT_READINESS.md) · [Glosario](../../GLOSSARY.md).

Referencia interna: [completar]
Fecha de revisión: [completar]
Estado de trabajo: [completar; no implica estado del producto]

| Campo / control | Registro / evidencia |
| --- | --- |
| Negocio / Partner | [completar o no aplicable con motivo] |
| Título | [completar o no aplicable con motivo] |
| Ciudad (por Locations) | [completar o no aplicable con motivo] |
| Descripción | [completar o no aplicable con motivo] |
| Valor real para Member | [completar o no aplicable con motivo] |
| Precio normal, si relevante | [completar o no aplicable con motivo] |
| Condición Member / elegibilidad | [completar o no aplicable con motivo] |
| Fechas de vigencia | [completar o no aplicable con motivo] |
| Método de canje y validación | [completar o no aplicable con motivo] |
| Cantidad / capacidad, si aplica (no crea un límite técnico) | [completar o no aplicable con motivo] |
| Exclusiones | [completar o no aplicable con motivo] |
| Locations / ubicación | [completar o no aplicable con motivo] |
| Imágenes y permiso de uso | [completar o no aplicable con motivo] |
| Responsable operativo (rol o referencia interna) | [completar o no aplicable con motivo] |
| Aprobación comercial | [completar o no aplicable con motivo] |
| QA: Guest / User / Member o grant, expirado, canje, móvil 390px | [completar o no aplicable con motivo] |
| Aprobación de publicación | [completar o no aplicable con motivo] |

Revisión pendiente no equivale a aprobación. Conservar evidencia del acuerdo por canal autorizado; publicar sólo mediante el flujo real y la checklist del manual.


## Benefit access mode V2

`access_mode` is the single source of truth:

- `public`: active JAKAWI membership required (historical default, unchanged).
- `authenticated`: authenticated JAKAWI account required; no active membership or Challenge grant required. Guests first complete the existing acquisition/login flow.
- `social_challenge_grant`: valid owned Challenge reward required; existing verification and grant rules remain.

All modes preserve applicable locations, canonical owned Delivery, expiring code/QR,
Location PIN validation and successful per-user redemption limits. Ordinary account
access creates no membership, purchase or courtesy entitlement. Unknown Admin modes
are rejected. Existing records are not rewritten. The existing VARCHAR column is
reused; no migration or capacity field is added.

Admin → Beneficios → Editar → access mode exposes MEMBRESÍA JAKAWI, CUENTA JAKAWI
and PREMIO DE RETO. Admin → Locations → existing Location → PIN nuevo (6 dígitos)
sets/resets the PIN; Admin shows whether configured, never the existing plaintext.

Welcome Courtesy remains FUTURE / OFF.

Dossis 43 V1 uses `authenticated`, one successful redemption per user, and a manual
prelaunch operational target of 20 redemptions. This target is not domain-enforced:
archive/unpublish manually after target if desired. Bs 35 is paid directly to
Dossis 43, with no JAKAWI payment collection. Benefit has no offer-price or internal
name field: commercial truth belongs in its description/terms; internal naming
can be retained in Admin review notes. Never use estimated savings as the price.
