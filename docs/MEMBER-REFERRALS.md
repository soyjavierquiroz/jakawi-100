# Member referrals + JP V1

Un Member con Membership activa puede compartir su código global `/r/{CODE}`. El primer referente válido se conserva y el programa es de un solo nivel: no hay MLM ni recompensa por referidos de referidos.

Al confirmarse una compra `membership_purchased`, la precedencia del único beneficiario de adquisición es Promoter vendedor activo, Creator activo, Affiliate activo, y finalmente Member con Membership activa. Partner conserva únicamente atribución de marketing. Un Member que además es Creator o Affiliate recibe la recompensa comercial, nunca CASH y JP para la misma conversión.

La fuente de verdad de JP es `RewardTransaction`: `reward_type=JP`, `currency=JP`, monto entero, y estado. Una regla activa `RewardRule(MEMBER, membership_purchased, JP, FIXED)` configura el monto; no existe valor hardcodeado ni conversión JP↔BOB. El JP de referidos confirmados queda `available` inmediatamente. El saldo es la suma de JP `available` (menos futuros burns `redeemed` cuando existan); JP ganados excluye `cancelled`. Un refund cancela el ledger relacionado y conserva historia. La unicidad conversión/regla impide duplicados.

Mi JAKAWI muestra el módulo sólo a Members activos, con código/enlace, compartir, y agregados sin PII: “amigos que se unieron” son referidos con `membership_purchased` confirmado; JP ganados y saldo se calculan en servidor. Una Membership vencida conserva el historial y los JP, pero no genera nuevos JP hasta reactivarse. No hay catálogo, gasto, drops, quests ni redenciones JP en V1.
