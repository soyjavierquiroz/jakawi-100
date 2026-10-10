# ADR-005 — Clasificación acquisition_provider

## Context

UTMs, proveedor de adquisición y consentimiento expresan conceptos distintos.

## Decision

Clasificar explícitamente NONE/META/TIKTOK/GOOGLE; el resolver de nuevos touches usa acq reconocido. UTM sola no implica proveedor pagado.

## Consequences

Email puede conservar UTMs con NONE. Clasificación no habilita envío, rewards ni consentimiento; estado de Meta pertenece a CURRENT.

## Status

ACCEPTED — documenta el contrato existente, cotejado con el código/config del precheck V1.1. No implica ejecución ni cambio de producto.

## Related docs

- [growth-measurement-v1.md](../growth-measurement-v1.md)
- [CURRENT.md](../CURRENT.md)
