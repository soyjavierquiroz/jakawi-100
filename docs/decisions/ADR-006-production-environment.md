# ADR-006 — Entorno de producción

## Context

El workspace comparte host productivo y no dispone de staging/preview operativo aprobado.

## Decision

Runtime de JAKAWI: Docker PHP 8.4; nunca usar ni inspeccionar PHP del host. Workflow de producción directo y manual según OPERATIONS. WordPress mantiene su runtime independiente.

## Consequences

Laravel/Composer se ejecutan en Docker. Release y rollback conservan coherencia app/web, backup y health checks; un cambio documental no requiere build/deploy.

## Status

ACCEPTED — documenta el contrato existente, cotejado con el código/config del precheck V1.1. No implica ejecución ni cambio de producto.

## Related docs

- [OPERATIONS.md](../OPERATIONS.md)
- [DEPLOYMENT.md](../DEPLOYMENT.md)
