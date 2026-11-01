# Fase 11C — confirmación consciente de pago

Se conserva el checkout, la compra y los estados de Fase 11A. La casilla se inicia
desmarcada y se reinicia en refresco/historial; el botón se habilita al aceptar y
se bloquea durante el envío. COM valida la aceptación y la referencia existente
del comprador en backend. Un POST atrasado no inicia otro checkout.

La primera confirmación explícita se conserva en `payment_confirmed_at`, mediante
una migración aditiva nullable sin inventar aceptación histórica. El servicio
existente serializa el aviso sobre la compra, conserva su primera fecha y evita
otro registro de aviso. No degrada revisión/aprobación y nunca activa por declaración.
Las subidas directas anteriores siguen funcionando sin simular la casilla.

Después del aviso: «¡Recibimos tu aviso de pago!» y «Adjunta tu comprobante».
Navegación disponible sin archivo; pendiente en Mi JAKAWI con enlace a subir y en
conciliación COM. Desaparece con archivo o banco confirmado. CLUB/invitadas mantienen
HMAC, referencias, claim, identidad y upload existentes; no se despliega CLUB.

El [contrato exacto para /activar](../../docs/integrations/club-payment-confirmation-11c.md)
detalla el cuerpo requerido del aviso y los campos aditivos de respuesta/consulta.
La aprobación individual existente admite PAYMENT_REPORTED sin archivo con un
movimiento bancario inequívoco; el pendiente no condiciona la activación por banco.

Validación: **71 pruebas, 729 assertions** en PostgreSQL aislado; tipos y build
frontend correctos. Chromium a 390/1440 px sin desbordamiento ni errores JS:
casilla/botón, doble envío, refresh, historial, abandono, logout/login, acceso desde
cuenta y comprobante tardío. Las pruebas de backend cubren además compras invitadas,
conciliación sin archivo, fechas estables, rechazo de avisos sin confirmación,
acceso ajeno y conservación de activación/outbox/idempotencia existentes.

Se ajustaron dos expectativas previas de validación HTML para comprobar errores
de sesión; el runtime aislado recibió poppler-utils, ya requerido por el Dockerfile
del repositorio y presente en COM. No se alteró el parser bancario.

La publicación COM usa imágenes app/web de la misma revisión y la migración
`2026_11_03_000000_add_explicit_payment_confirmation.php`. El resultado y las
comprobaciones posteriores se guardan en `/opt/sites/jakawi.com/artifacts/phase11c/`.
No se crean usuarios, avisos, comprobantes ni pagos ficticios en producción.
