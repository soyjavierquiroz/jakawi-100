# JAKAWI

> JAKAWI es la app para vivir más tu ciudad.

JAKAWI descubre Partners, Locations, Benefits y Experiences en Cochabamba. La Membership habilita Benefits; el valor se confirma cuando un Partner valida el canje.

## Current Status

**Implementation ready / functional environment verification pending.** El código contiene el MVP de piloto y no hay P0 conocidos en el último fix pack. La verificación funcional completa está pendiente en un entorno con PHP >= 8.4.1; no es ejecutable en este entorno por incompatibilidad PHP ya conocida.

## Core Product Loop

`DISCOVER → JOIN → USE → VALIDATE → RECEIVE VALUE → RETURN`

Visitantes y usuarios gratuitos descubren la oferta. Un Member con Membership activa inicia un Redemption de un Benefit elegible, recibe código/QR temporal y recibe ahorro sólo tras la validación del Partner.

## Main Domains

- **Membership:** compra confirmada o Admin Grant; un grant no es venta ni revenue.
- **Benefits y Redemptions:** código temporal, PIN de Location, confirmación idempotente y ahorro confirmado.
- **Experiences:** durante piloto, reserva pública externa (WhatsApp, URL, teléfono o destino externo).
- **Partners:** Partner → Locations, Benefits y Experiences; equipo `owner`, `manager` o `staff`.
- **Attribution y rewards:** un único beneficiario por adquisición.
- **JP, Unlocks y Reputation:** JP no es dinero; Desbloqueos V1 usa compromisos, holds, fulfillment y reputación.

## Architecture / Stack

Laravel 13, React 19, TypeScript, Inertia 3, Tailwind 4, PostgreSQL 16 y Docker Compose. El runtime requiere PHP >= 8.4.1.

## Local Development / Testing

Los comandos Laravel/Composer deben usar el contenedor PHP compatible:

```bash
./bin/jakawi-test
./bin/jakawi-test php artisan test
./bin/jakawi-test npm run types:check
```

No ejecutar Artisan, Composer ni tests con el PHP 8.1.2 del host.

## Documentation

El mapa está en [docs/README.md](docs/README.md). Para producto operativo, [docs/CURRENT.md](docs/CURRENT.md); para límites de piloto y futuro, [docs/ROADMAP.md](docs/ROADMAP.md).

## Scope

No hay pagos internos de Experiences, booking público interno durante piloto, marketplace abierto, permisos por Location, app nativa ni conversión JP↔dinero.
