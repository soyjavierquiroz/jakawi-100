# Fase 06D — una sola autoridad de afiliación

Decisión del propietario: CLUB es la única autoridad de comisiones, saldos y liquidaciones de afiliados desde el despliegue de esta fase. La fecha efectiva se registra en `artifacts/com-affiliation-retirement-v1/LIVE.json` del servidor COM, junto con el SHA desplegado. No hay retroactividad ni cambios en CLUB.

## COM

Se retiran paneles afiliado/creador, altas y solicitudes de programas comerciales, solicitudes/liquidaciones de afiliados, overrides y devengo CASH para usuarios de los programas PROMOTER/AFFILIATE/CREATOR. Las rutas GET antiguas envían a `https://jakawi.club/afiliados`; las mutaciones se eliminan. El acceso en CLUB sigue sujeto a su propia autenticación y permisos. COM no transfiere sesiones ni concede roles de CLUB.

El acceso operativo existente PROMOTER se limita a registrar y consultar ventas manuales de membresía. El administrador lo presenta como **Operadores de ventas**. No proporciona una cuenta económica de afiliado ni configura comisiones. Se mantienen cobros, recibos, reembolsos y controles de acceso.

Los servicios compartidos conservan únicamente JP de miembros y pagos comerciales a Partners, con reglas propias previamente existentes. La fase no activa ninguna regla, comisión ni pago. Los roles antiguos no se convierten en miembros premiados por fallback. Las reglas CASH de afiliados, incluso si persistieran, no pueden producir nuevos devengos.

Atribución comercial, campañas de miembros/Partners, códigos COM, puntos JP, retos sociales, canjes con PIN, origen `club_ref`, emisor COM→CLUB, identidad/OIDC y FluentCRM/SES se mantienen. Solo se retira del observador de CRM la fuente de solicitudes de programas que ya no existe; el transporte, configuración, outbox y entregas restantes se preservan.

## Esquema e historia

- `program_applications`: exclusiva de solicitudes al antiguo programa, vacía en LIVE antes de intervenir. La nueva migración `2026_10_30_120000_retire_com_affiliate_applications` elimina solo esa tabla si está vacía; si encuentra registros, conserva la tabla sin UI ni escritores. No edita migraciones aplicadas ni utiliza rollback general.
- `program_enrollments`: compartida con la autorización de operadores de venta; se conserva.
- `reward_rules`, `reward_transactions`: compartidas con JP y Partners; se conservan.
- `reward_payouts`, `reward_payout_reward_transaction`: solicitudes, pagos y trazabilidad de Partners; se conservan.
- `conversions`, `membership_purchases`, `operational_adjustments`, `campaigns`, `campaign_participants`, atribución y auditoría: operaciones comerciales compartidas; se conservan. La UI y los escritores activos excluyen CASH de afiliados.

En el estado real consultado no hay reglas, recompensas, solicitudes/pagos, ajustes de saldo, ventas ni conversiones antiguas. No existe una obligación monetaria pendiente que migrar o conciliar. Las pruebas crean historial financiero únicamente en PostgreSQL aislado y verifican que la limpieza no lo elimina ni permite pagarlo mediante paneles retirados.

## Contrato con CLUB

Por instrucción expresa del propietario **no se crea un endpoint de conciliación histórica**, ni tablas de corte, ni mecanismos de compatibilidad. No hay URL, método, JSON o credenciales nuevas que configurar en CLUB para esta fase. No interpretar ausencia histórica como autorización para aprobar o pagar una comisión.

El contrato comercial existente continúa en `COM_CLUB_COMMERCIAL_V1.md`: COM emite conversiones auténticas a `POST https://jakawi.club/internal/commercial/events`, conservando referencia original, HMAC y `club_referral_code` capturado. CLUB decide elegibilidad, regla y obligación bajo su propio motor. Esta fase no modifica ese emisor ni el receptor CLUB, y no autoriza devengos retroactivos.

## Recuperación

Backup y controlador privado vigentes: revisar `artifacts/com-affiliation-retirement-v1/REPORT.md` del servidor. El rollback de código conserva los libros, outboxes y datos comerciales. Como la única tabla eliminada estaba vacía, para servir código previo se debe recrear únicamente su esquema original vacío antes de abrir tráfico; no restaurar dumps ni revertir otras migraciones. La autorización económica definitiva sigue vigente: no volver a habilitar devengos antiguos para tráfico futuro.
