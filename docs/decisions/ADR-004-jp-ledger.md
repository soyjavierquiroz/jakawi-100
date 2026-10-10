# ADR-004 — Ledger JP

## Context

Los compromisos reservan JP y necesitan distinguir reserva de contabilización.

## Decision

RewardTransaction con reward_type=JP es el único ledger contable JP. JpHold sólo representa reservas y su lifecycle; disponible descuenta HELD del ledger.

## Consequences

No escribir saldos alternativos ni convertir JP a BOB. Pérdidas/bonus/correcciones usan los servicios y ledger existentes conservando audit.

## Status

ACCEPTED — documenta el contrato existente, cotejado con el código/config del precheck V1.1. No implica ejecución ni cambio de producto.

## Related docs

- [CURRENT.md](../CURRENT.md)
- [OPERATIONS.md](../OPERATIONS.md)
