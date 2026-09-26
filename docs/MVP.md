# Histórico — JAKAWI MVP

Este snapshot se conserva como contexto de la etapa MVP. La definición actual de producto y arquitectura está en [PRODUCT.md](PRODUCT.md), [DOMAIN.md](DOMAIN.md) y [ARCHITECTURE.md](ARCHITECTURE.md); no usar este documento como runbook ni como arquitectura vigente.

JAKAWI es la app para vivir más tu ciudad.

```text
DESCUBRIR -> DESEAR -> USAR / RESERVAR -> RECIBIR VALOR -> REGISTRAR -> VOLVER
```

## Construido ahora

- Partners y Locations, incluyendo Locations independientes.
- Benefits con alcance a todas o a Locations seleccionadas del Partner.
- Experiences multi-Partner, Sessions, solicitud de reserva, confirmación de
  Partner y check-in con código/QR.
- Membership, Redemption con PIN por Location y ROI de ahorros confirmados.
- Member: Membership, Benefits, Experiences, Perfil y personalización limitada.
- Partner: Content Studio, reservas, validación/check-in y KPI con alcance
  explícito al Partner asignado.
- Admin: Partners, Locations, Benefits, Experiences, Memberships, Redemptions
  y revisión de contenido.
- Analytics first-party limitado y sin endpoint genérico de ingestión.

## No construido

- Booking, pagos, puntos, rewards, referrals y capacidad decrementable.
- Near-me avanzado, notificaciones, self-service de Partners y recomendaciones
  de IA.
- Near-me, geofencing, push, IA y app nativa.

Las reservas son solicitudes internas simples para la operación Partner; no
son Booking ni procesan pago.
