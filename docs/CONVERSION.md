# Conversión de membresía

## Estado de producto

El Paywall contextual (Slice 1) está preparado en la rama
`feature/conversion-paywall-v1`, pero no está desplegado ni integrado en
`main`. Se retoma únicamente cuando exista un flujo de pago real.

JAKAWI tendrá un módulo de pagos propio. El mercado inicial es Bolivia y el
método principal previsto es pago por **QR**. La implementación del módulo,
sus integraciones y cualquier proveedor/banco se deciden y construyen después;
no se integra Stripe, PayPal u otro proveedor ahora.

La activación de la membresía continúa siendo una operación exclusiva del
servidor. Ningún parámetro del navegador, redirección ni pantalla de éxito
puede activar una membresía.

## Límite futuro del módulo de pagos

Cuando el módulo exista, el flujo conceptual será:

```text
Paywall
  → crear membership checkout
  → generar o solicitar payment QR
  → usuario paga
  → servidor verifica el pago
  → activación idempotente de membresía
  → éxito
  → volver al Benefit que originó el flujo
```

La fundación actual define sólo el contrato intercambiable
`QrPaymentGateway`: crea una solicitud y traduce su estado a `pending`,
`confirmed`, `failed` o `expired`. No existe todavía `membership_checkouts`,
un proveedor, callback, endpoint productivo ni integración financiera. Véase
[PAYMENTS.md](PAYMENTS.md).

Manual Membership Sales V1 usa `MembershipPurchase` y una confirmación de efectivo para activar server-side, registrar la conversión y evaluar recompensa. El futuro QR reutilizará exactamente la misma compra, activación y conversión: sólo cambia cómo se confirma el pago. Véase [MANUAL-SALES.md](MANUAL-SALES.md).

## Dos dominios QR distintos

**Payment QR** corresponde a dinero y a la compra de una membresía. Su ciclo
de vida pertenece al futuro módulo de pagos y sólo el servidor podrá verificar
su resultado y activar acceso.

**Redemption QR** corresponde al uso de un Benefit y a su validación por un
Partner. No representa dinero ni compra una membresía.

Ambos dominios tienen estados, seguridad y ciclos de vida distintos; no deben
compartirlos de manera casual.

## Paywall diferido

El Paywall conserva el contexto del Benefit que motivó el interés y será el
origen del futuro flujo. Hasta que el pago QR esté disponible:

- no se despliega el Paywall;
- no se crean checkouts ni tablas de checkout;
- no se implementan pagos ni QR de pago;
- no cambia el flujo de activación de membresía vigente.
