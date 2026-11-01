# Fase 11C — contrato COM para /activar de CLUB

Extiende `jakawi-club-purchases-v1` y el aviso de Fase 11A; no crea otro checkout,
compra, mecanismo de identidad ni conciliación. Despliegue exclusivo COM.

## Confirmación consciente

CLUB obtiene la compra existente mediante
`GET https://jakawi.com/internal/club/purchases/{club_reference}`. Conserva la
referencia original y el acceso seguro de cuenta/invitada ya existente; no busca
compras por email ni permite consultar una referencia ajena desde su navegador.
`beneficiary_name` es el nombre de la persona cuya membresía se activa, guardado
como `buyer_name` en la intención original; no es el titular bancario del QR.
Mostrar este nombre y **Bs 100**, además del destinatario bancario existente.

Casilla inicialmente desmarcada, también al volver con historial/bfcache:

> Confirmo que ya realicé la transferencia de Bs 100 para activar la membresía JAKAWI de {beneficiary_name}.

Botón: **Ya pagué · Registrar mi aviso**, deshabilitado hasta marcar. CLUB valida
la casilla en su backend y transmite solo cuando el usuario la haya marcado:

```http
POST https://jakawi.com/internal/club/purchases/{club_reference}/report
Content-Type: application/json
Accept: application/json
```

```json
{"payment_confirmed":true}
```

Misma firma HMAC privada del [contrato base](club-direct-purchases-v1.md):
key ID `club-purchases-v1`, timestamp, nonce nuevo por intento y firma de los
bytes JSON exactos. El HMAC y el claim permanecen en backend. Sin query strings.
El cuerpo vacío de Fase 11A ya no es válido: COM devuelve 422 con
`errors.payment_confirmed` si falta o es falso. Tampoco acepta `"yes"`/`"true"`.
No convertir ese error en una nueva intención.

HTTP 200, mismo objeto de compra con estos campos aditivos:

```json
{
  "contract": "jakawi-club-purchases-v1",
  "club_reference": "UUID-ORIGINAL-CLUB",
  "payment_reference": "UUID-ORIGINAL-COM",
  "beneficiary_name": "Nombre del beneficiario",
  "status": "PAYMENT_REPORTED",
  "reported_at": "2026-11-01T12:00:00+00:00",
  "payment_confirmed": true,
  "payment_confirmed_at": "2026-11-01T12:00:00+00:00",
  "receipt_pending": true,
  "receipt_received": false,
  "amount": "100.00",
  "currency": "BOB",
  "membership_reference": null,
  "activated_at": null
}
```

Es un extracto del objeto: conserva todos los demás campos del contrato base.
Las fechas son RFC3339, generadas por COM. `reported_at` y
`payment_confirmed_at` conservan su primer valor. `payment_confirmed` significa
aceptación explícita de la declaración, **no confirmación bancaria**.
Avisos históricos y subidas directas previas conservan confirmación false/null;
no se inventa aceptación retroactiva. En un aviso explícito nuevo COM persiste la
confirmación, el aviso y PAYMENT_REPORTED sobre la misma fila y transacción.

Reintentos/doble clic: 200 con la misma compra y fechas; sin otro aviso, activación,
compra ni evento. Si ya está `under_review` o `approved`, conserva el estado y
no lo degrada. Un aviso de una compra rechazada reutiliza su fecha original.
Se mantiene 422 PAYMENT_UNAVAILABLE si el QR no está disponible, además de los
errores de autenticación/rate limit/conflicto existentes. Tras timeout, consultar
GET o reenviar el mismo aviso con nuevo nonce. Nunca crear otra compra como retry.

## Comprobante y retorno

Después del aviso mostrar **¡Recibimos tu aviso de pago!** y destacar
**Adjunta tu comprobante**. No mostrar «Opcional» ni un botón destacado de omisión.
La navegación sigue disponible aunque no haya archivo.

GET/POST actuales devuelven `receipt_pending`: hay fecha de aviso, falta archivo,
falta movimiento bancario conciliado y el pago no está approved. Para true,
mostrar **Falta adjuntar comprobante** en cuenta/compra con acceso directo al
formulario de la misma compra. Restaurar esta información desde GET tras refresh,
abandono y nueva sesión. Para invitadas conservar el acceso seguro existente;
no incluir claim ni secretos en URL, almacenamiento del navegador o analytics.
COM enlaza desde Mi JAKAWI a `https://jakawi.club/activar` para compras CLUB
vinculadas: /activar debe recuperar la compra por su mecanismo autorizado actual.

Subir mediante `POST .../{club_reference}/receipt`, con el mismo base64/SHA256,
MIME JPG/PNG/PDF y máximo 10 MiB. Sigue admitiendo PAYMENT_REPORTED y la subida
directa de clientes anteriores; esta última no inventa confirmación de casilla.
Una subida tardía pasa a under_review, receipt_pending=false; duplicado del mismo
SHA no duplica archivo ni extracción. Confirmación explícita y reported_at se
conservan. En approved se mantienen las reglas RECEIPT_LOCKED existentes: no
pedir un archivo después de que COM haya confirmado el banco.

## Conciliación y activación

La casilla, el aviso y la subida **nunca activan** una membresía. COM puede aprobar
individualmente PAYMENT_REPORTED sin archivo cuando tenga evidencia bancaria
inequívoca, mediante el motor y movimiento único existentes. Al aprobar,
receipt_pending=false aunque receipt_received=false. CLUB actualiza el pendiente
con GET/eventos existentes; los eventos de activación y de identidad no cambian.
No crear un segundo flujo para pagos CLUB o invitadas. No desplegar CLUB junto a COM.
