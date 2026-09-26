# Creator Program V1

Creator es una especialización comercial distinta de Affiliate, pero ambos usan el mismo motor de atribución, `RewardRule`, ledger y `RewardPayout`. Un Creator es un `User` con `ProgramEnrollment(CREATOR)` operativo; no tiene rol ni autenticación propios y puede pertenecer simultáneamente a otros programas.

Admin puede enrolar, activar/desactivar, limitar vigencia, generar código, configurar la regla CREATOR o un override individual y consultar resultados/auditoría. Las cuentas creadas reciben el flujo seguro existente de definición de contraseña.

El dashboard **Mis resultados** expone enlace `/r/{CODE}`, generador `utm_campaign`/`utm_content`, métricas agregadas, comisiones y pagos. No muestra datos personales de referidos. El contenido se agrupa desde `AttributionTouch.utm_content`; campaña es un filtro V1, no una entidad Campaign. Un dominio Campaign completo queda para Fase 2.

La prioridad de adquisición es vendedor Promoter acreditado en venta manual, luego Creator activo, luego Affiliate activo. Por ello un usuario Creator+Affiliate recibe un único reward: CREATOR. La atribución se conserva aunque gane el vendedor Promoter.
