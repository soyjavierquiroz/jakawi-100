# Attribution, referidos y rewards

**STATUS: IMPLEMENTED.** `/r/{CODE}` registra `AttributionTouch`; el primer referente válido dentro de la ventana configurable crea `ReferralRelationship`. Touches posteriores permanecen como historia. UTM es origen/evidencia de marketing, no recompensa. Partner attribution puede vivir en la relación/snapshot.

Una `Conversion` idempotente congela attribution. Venta manual agrega `credited_seller_user_id` sin reemplazar el referido. Para `membership_purchased` hay sólo un beneficiario: Promoter vendedor activo, Creator activo, Affiliate activo, Member activo, Partner publicado de adquisición o ninguno. Campaigns operativas vinculan `utm_campaign` y reglas elegibles; no apilan rewards.

Véase [CURRENT.md](CURRENT.md) para estados económicos y payouts.
