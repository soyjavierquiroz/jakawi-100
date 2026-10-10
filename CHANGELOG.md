# Changelog

## [Unreleased]

### Documentation

- Consolidación V1 (2026-10-09): índice, estado productivo, arquitectura, CRM/schema/automations/cron, Growth/Meta y operaciones.
- Manual de Funciones y Manual de Inventario en español; referencias históricas conservadas y autoridad por tema delimitada.
- Sólo documentación, sin versión de producto nueva ni cambios de código/schema/deploy.

## [2026-09-28]

### Partner operations

- Administración de Partner users `owner`, `manager` y `staff`, con auditoría.
- Regla piloto: un usuario autorizado del Partner puede operar Locations del mismo Partner usando el PIN correspondiente; no existen permisos por Location.

### Membership

- `Membership.source`: `purchase` y `admin_grant`.
- Admin Grants con `amount_paid=0`, motivo obligatorio y `AuditLog`; no son ventas, revenue ni conversiones pagadas.

### Redemptions y Experiences

- TTL configurable de Redemptions pendientes. Corrección documental: el SHA actual aplica expiración en controller/service, sin comando de expiración programada registrado.
- Registro histórico de regla de piloto: reserva externa. La afirmación anterior de bloqueo server-side no corresponde al SHA actual; véase CURRENT para capacidad interna y límites.

### Attribution, rewards y payouts

- Promoter, Affiliate y Creator usan atribución y ledger con una sola recompensa de adquisición.
- Circuito Promoter `pending → available → payout externo → paid`.
- Corrección: payout/listado/disponibilidad Promoter se filtran por `participant_type`, evitando incluir rewards CASH de otro programa del mismo usuario.
- Campaigns, atribución por contenido y Partner attribution no crean doble comisión.

### Unlocks, Reputation y piloto

- Desbloqueos V1: compromiso, hold/release/forfeit JP, confirmación, fulfillment, bonus, expiración/no-show y operaciones Admin/Partner.
- `reputation_events`, estados, recuperación, factores de depósito JP y correcciones auditadas.
- Dashboard: cohortes de compras confirmadas y definiciones de conversión, activación, recurrencia, recovery y primer uso.

### Documentation

- Documentación consolidada contra el código actual para el piloto de 2026-09-28.

## [0.0.1] - 2026-09-21

### Added

- Laravel application bootstrap
- React/Inertia/TypeScript frontend
- Authentication
- PostgreSQL
- Docker production environment
- OpenLiteSpeed reverse proxy
- HTTPS production deployment
