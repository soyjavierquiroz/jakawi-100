# Atribución, referidos y conversiones V1

## Principios

La atribución de marketing explica cómo llegó alguien (UTM y landing); el referente identifica quién lo trajo; una recompensa es un dominio posterior y no forma parte de esta versión. La evidencia se conserva antes del registro y el origen de una venta se congela al crear su conversión.

## Identidad y contactos

Se reutiliza el UUID first-party `jakawi_visitor_id` como `anonymous_id`: es aleatorio, opaco, no contiene PII y no usa IP, fingerprint ni user-agent. Cada `/r/{CODE}` registra un `attribution_touch`, incluyendo UTMs permitidas y la landing. Al registrarse, los touches anónimos se asocian al usuario sin destruir su `anonymous_id` histórico.

## Códigos y ruta

Los códigos son únicos, normalizados a mayúsculas ASCII y se generan para usuarios registrados y para un promotor activo que aún no tiene uno. Admin puede regenerarlo sólo mediante una acción explícita auditada; un código existente nunca se sobrescribe silenciosamente. Las rutas `/r/{CODE}` resuelven el código sin exponer datos del referente y sólo redirigen a Inicio, preservando únicamente UTMs controladas: no existe open redirect.

## First Valid Referrer

La política es primer referente válido durante la ventana global configurable (30 días por defecto). Un segundo código siempre queda como touch, pero no reemplaza una relación activa. Tras expirar, un nuevo touch válido puede crear la siguiente relación activa. Auto-referidos se bloquean y no se borran contactos históricos.

## Códigos manuales y conversiones

Registro ofrece un código opcional y aplica exactamente la misma política. `ConversionRecorder` es el único punto para registrar conversiones y exige `idempotency_key`; conserva referencias a la relación, el touch y un snapshot mínimo de referente/UTMs. Sirve para futuras ventas de membresía, QR, experiencias o productos, sin implementar ninguno de esos flujos aún.

## Ventas manuales

Una venta manual de promotor conserva su atribución de marketing en el snapshot de `Conversion` y agrega el cobrador como `credited_seller_user_id`. No se modifica `ReferralRelationship` para pagar una comisión. Esto diferencia el origen de marketing del crédito comercial de una venta específica.

## Administración, privacidad y alcance

Admin permite ajustar la ventana (con audit log) y buscar usuarios para ver referente, historial y conversiones. Manual Membership Sales V1 agrega reglas y transacciones CASH mínimas; no añade payouts, árboles, dashboards completos, campañas ni QR de pago.
