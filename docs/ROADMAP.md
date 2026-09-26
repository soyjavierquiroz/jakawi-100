# Roadmap

## Ahora

- Affiliate Program V1: **LIVE**. Enrolamiento, enlace, atribución,

- Creator Program V1: **LIVE**. Enrolamiento CREATOR, enlaces con campaña/contenido, resultados agregados y payout reutilizado. Campaign como dominio queda para Fase 2.
  resultados y comisiones CASH de adquisición sin QR.

- Admin Promoter Management V1: **LIVE**. Admin enrola y opera promotores,
  vigencia, referral y override individual de comisión sin alterar Membership.

- Attribution Foundation V1: **LIVE**. Conserva origen anónimo, UTM,
  referente válido y conversiones; no incluye recompensas ni comisiones.

- System States MVP V1: **LIVE**. Cierra estados intencionales de vacío,
  indisponibilidad, expiración, error contextual y carga en el Core y ventas
  manuales, sin sumar flujos de pago.

- Pulido visual de la experiencia consumidora.
- Cierre del Core según el [Mapa UX MVP](UX-MVP.md).
- Contenido real y calidad fotográfica.
- Onboarding y calidad de supply.
- QA para preparación de lanzamiento.

## Siguiente

- Affiliate Payout V1: registrar pago externo con referencia, fecha y auditoría
  antes de habilitar estado PAID.
- Creator Program, Partner acquisition, Member referrals + JP y Campaigns.

- Manual Membership Sales V1: **LIVE**. Promotor crea cliente, pago efectivo,
  activación server-side, conversión y reglas/transacciones CASH genéricas.

- QR Payment Adapter Foundation: **READY / LIVE FOUNDATION**. El contrato y
  el gateway deshabilitado están desplegados; QR productivo continúa apagado.

- QR Payment: **BLOCKED BY EXTERNAL PROVIDER CONTRACT**. Implementar el
  módulo de pagos propio de JAKAWI para Bolivia, con QR como método principal
  previsto, requiere contrato de proveedor/banco. Esta fase incluye la
  verificación server-side e idempotente antes de activar una membresía; no
  hay aún proveedor ni integración. Véase [PAYMENTS.md](PAYMENTS.md).
- Retomar y desplegar el Paywall contextual preparado sólo cuando exista ese
  flujo de pago QR real.
- Refinamiento a partir del uso real.

## Diferido

- Puntos y rewards.
- Rewards, comisiones, payouts y dashboards de referidos/afiliados/creadores
  (la fundación de atribución ya existe; no incluye recompensas).
- JP, payouts de afiliados, dashboard de referidos de miembros, analítica de
  creadores y campañas avanzadas.
- IA.
- App nativa.
- Mapa complejo.
- CDN.
- AVIF.
- Editor de crop más allá del avatar.
- Marketplace abierto.
# Affiliate payout V1

Implementado: solicitudes de pago manuales, revisión admin, referencia externa, auditoría y reserva del ledger. Futuro: datos de pago bajo demanda, Creator/Promoter UI, payout provider y ajustes financieros para devoluciones después de pagos.
