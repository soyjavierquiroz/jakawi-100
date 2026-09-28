# Changelog

## [Unreleased]

## [2026-09-28]

### Partner operations

- Administración de Partner users `owner`, `manager` y `staff`, con auditoría.
- Regla piloto: un usuario autorizado del Partner puede operar Locations del mismo Partner usando el PIN correspondiente; no existen permisos por Location.

### Membership

- `Membership.source`: `purchase` y `admin_grant`.
- Admin Grants con `amount_paid=0`, motivo obligatorio y `AuditLog`; no son ventas, revenue ni conversiones pagadas.

### Redemptions y Experiences

- TTL configurable y expiración programada de Redemptions pendientes.
- Publicar Experiences con `reservation_method=jakawi` queda bloqueado server-side durante piloto; la reserva pública es externa.

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
