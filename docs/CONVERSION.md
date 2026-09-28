# Conversión de Membership

**STATUS: IMPLEMENTED FOR MANUAL SALES; QR PAYMENT NOT IMPLEMENTED.** La activación de Membership ocurre server-side. Venta manual confirmada usa `MembershipPurchase` y el pipeline idempotente para crear Membership, Conversion y reward.

Un Admin Grant es una activación distinta: `source=admin_grant`, Bs0, motivo y audit; no es conversión ni venta. Un futuro QR necesitará verificación confiable de servidor y no se activa por navegador, redirect o parámetro cliente. El gateway QR de producción continúa deshabilitado.
