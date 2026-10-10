# ADR-002 — Campaign vs campaign_key

## Context

El tráfico necesita contexto analítico; las comisiones necesitan reglas económicas explícitas.

## Decision

Separar Campaign económica de campaign_key de adquisición. utm_campaign es evidencia cruda que puede resolver Campaign por código activo y ventana; campaign_key no participa de esa resolución.

## Consequences

No crear comisiones desde Growth ni interpretar campaign_key como query libre. Conservar snapshots económicos sin reescribir historia.

## Status

ACCEPTED — documenta el contrato existente, cotejado con el código/config del precheck V1.1. No implica ejecución ni cambio de producto.

## Related docs

- [CAMPAIGNS.md](../CAMPAIGNS.md)
- [growth-measurement-v1.md](../growth-measurement-v1.md)
