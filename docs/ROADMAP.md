# Roadmap

## Partner payout V1

- **LIVE**: payout genérico USER/PARTNER, actor owner/manager separado, mínimo Partner independiente y pago externo manual.

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
# Roadmap

## Member referral + JP V1

Delivered: one-level Member referrals, configured JP ledger rewards, and aggregate Mi JAKAWI invite metrics. Deferred: JP catalog, burns, redemptions, drops, quests, levels, and any JP-to-BOB conversion.

## Partner acquisition V1

Delivered: Partner referral attribution and aggregate operational metrics. Deferred: Partner RewardRule incentives, payouts, CREDIT, JP, payment QR, campaign engine, loyalty, and geofencing.
# Deferred: Partner CREDIT and payouts

Partner CREDIT needs its own ledger and consumption semantics. Partner payout requests are deferred until the existing user-only payout workflow can be safely generalized with an independent configured partner minimum.
# Campaigns V1

Campaigns V1 entrega campañas administrables para membership purchase, reglas especiales y métricas por contenido. Automatización de marketing, plataformas sociales y A/B testing quedan fuera de alcance.

# Admin adjustments + CSV export V1

Delivered: immutable, reason-required Admin adjustments for attribution,
CASH/JP ledger movements and permitted reward-status transitions, plus filtered
CSV downloads for affiliates, conversions, rewards, payouts and attribution.
Excluded by design: BI, XLSX, imports, scheduled exports and accounting.
# Unlocks

Slice 1: core collective commitments. Slice 2: JP commitment delivered: earned JP is reserved through an auditable hold ledger and released on valid cancellation or failed/cancelled Unlock. Completion bonus and forfeiture remain deferred to Slice 3 confirmation and fulfillment. Positive-deposit production activation stays guarded until then. Slice 4: growth and operations.
# Unlock Slice 3

Confirmation, trusted fulfillment, JP release/bonus/forfeiture, and zero-deposit acquisition are delivered as one idempotent lifecycle. Sponsor attribution and reputation scoring remain deferred.
