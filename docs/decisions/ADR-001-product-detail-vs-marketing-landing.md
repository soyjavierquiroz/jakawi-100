# ADR-001 — Product Detail vs Marketing Landing

## Context

La adquisición puede presentar un producto de varias maneras sin cambiar su contrato.

## Decision

Product Detail es verdad operativa. Marketing Landing es presentación de adquisición; LandingPresentation nunca cambia economía, reglas, premio, elegibilidad, cupo ni plazos del subject.

## Consequences

Una Landing PUBLISHED con default_scope aplicable es presentación primaria: guest GUESTS/ALL, autenticados ALL. Sin Landing aplicable se conserva Product Detail como fallback funcional. El acceso directo a /l no es guest-only. Conservar detalle nativo y revalidación del dominio. Publicar/archivar una landing no sustituye las operaciones de producto.

## Status

ACCEPTED — documenta el contrato existente, cotejado con el código/config del precheck V1.1. No implica ejecución ni cambio de producto.

## Related docs

- [ARCHITECTURE.md](../ARCHITECTURE.md)
- [operations/MANUAL_DE_INVENTARIO.md](../operations/MANUAL_DE_INVENTARIO.md)

Contrato actual y excepciones: [Marketing System Audit V1](../architecture/MARKETING_SYSTEM_AUDIT.md).
