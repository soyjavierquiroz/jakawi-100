# ADR-003 — FluentCRM como CRM externo

## Context

Contactos y automations requieren una proyección externa independiente de la transacción de producto.

## Decision

JAKAWI conserva la verdad de dominio. FluentCRM sirve contactos/segmentación/marketing automation mediante bridge HTTPS firmado; no hay integración directa entre bases JAKAWI y WordPress.

## Consequences

Outbox CRM independiente; fallo externo no revierte producto. No enviar el historial analítico completo ni tratar tags como aprobación comercial.

## Status

ACCEPTED — documenta el contrato existente, cotejado con el código/config del precheck V1.1. No implica ejecución ni cambio de producto.

## Related docs

- [CRM_FOUNDATION_V1.md](../CRM_FOUNDATION_V1.md)
- [ARCHITECTURE.md](../ARCHITECTURE.md)
