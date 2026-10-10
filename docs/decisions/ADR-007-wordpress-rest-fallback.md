# ADR-007 — Fallback REST WordPress

## Context

El contrato de ruta del plugin y el routing HTTP del servidor son capas diferentes.

## Decision

Laravel acepta exclusivamente el endpoint canónico /wp-json y el fallback exacto ?rest_route= para jakawi-fluentcrm/v1/events en crm.jakawi.com. Ambos llegan a la misma ruta; no normalizar, seguir redirects ni aceptar queries extra.

## Consequences

Producción usa actualmente fallback por routing del servidor, según CRM. Ese estado puede cambiar sin alterar el contrato; no modificar rewrites como primera reparación.

## Status

ACCEPTED — documenta el contrato existente, cotejado con el código/config del precheck V1.1. No implica ejecución ni cambio de producto.

## Related docs

- [CRM_FOUNDATION_V1.md](../CRM_FOUNDATION_V1.md)
- [operations/RUNBOOKS.md](../operations/RUNBOOKS.md)
