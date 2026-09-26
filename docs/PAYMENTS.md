# Pagos QR: límite de integración V1

## Scope

Esta fundación prepara una futura compra de Membership por QR sin integrar un
proveedor. No hay endpoint, autenticación, secreto, formato QR, checkout,
webhook, callback ni ruta productiva de confirmación. Producción conserva QR
deshabilitado.

## Canales actuales y futuros

`manual_cash` está operativo: `MembershipPurchaseService` crea la compra
confirmada, activa Membership, registra Conversion, evalúa Reward y deja audit
logs dentro de su transacción. QR será un canal de pago futuro para esa misma
compra; no sustituye ese pipeline comercial.

Payment QR es dinero para comprar Membership. Redemption QR es la validación
de un Benefit en Partner. Son dominios distintos: no comparten modelos,
estados, servicios ni reglas de seguridad sólo porque ambos puedan mostrarse
como QR.

## Gateway contract

El código de aplicación dependerá de `QrPaymentGateway`, con dos operaciones:

```php
createPayment(QrPaymentRequest $request): QrPaymentCreationResult
checkStatus(string $providerReference): QrPaymentStatusResult
```

`QrPaymentRequest` contiene sólo referencia interna de compra, monto, moneda y
descripción opcional. No contiene email, teléfono u otra PII. El resultado de
creación expone referencia del proveedor, `paymentData` opaco opcional,
expiración opcional y estado. No interpreta ni almacena una representación
visual: el futuro proveedor puede entregar URL, EMV, base64, PNG u otro dato.

Los únicos estados internos canónicos son `pending`, `confirmed`, `failed` y
`expired`. Cada adaptador futuro traduce sus estados externos a éstos.

## Providers y seguridad de producción

`DisabledQrPaymentGateway` es el binding actual de producción y lanza el error
de dominio controlado `QR payments are not available` para crear o consultar.
`FakeQrPaymentGateway` vive sólo para test/desarrollo: crea pendiente y puede
simular confirmación, fallo o expiración en memoria. Tiene guard propio y el
contenedor rechaza `QR_PAYMENT_DRIVER=fake` cuando el entorno es producción.

La configuración central está en `config/payments.php`:

```text
payments.qr.enabled=false
payments.qr.driver=disabled
```

Los valores de entorno correspondientes son `QR_PAYMENT_ENABLED` y
`QR_PAYMENT_DRIVER`. No hay secretos ficticios.

## MembershipPurchase, confianza e idempotencia

`MembershipPurchase` ya posee `payment_channel` y `status` como campos
neutrales; puede representar conceptualmente `qr` y `pending` sin migración.
No se persiste todavía una referencia del proveedor: su ubicación, unicidad y
ciclo de vida se decidirán con el contrato real. No se creó una tabla de
intentos de pago por adivinanza.

El flujo futuro exacto será:

```text
Benefit → Paywall → MembershipPurchase pending → QrPaymentGateway.createPayment()
→ QR presentado → pago externo → validación confiable del servidor
→ purchase confirmed → pipeline existente de activación → Membership ACTIVE
→ Conversion confirmed → reward evaluation → retorno al Benefit originador
```

El gateway nunca activa Membership. El servicio de aplicación futuro debe
aceptar sólo la confirmación que resulte de una consulta/verificación confiable
del proveedor. `paid=true`, `success=1`, un redirect de navegador o estado
enviado por cliente no son evidencia de pago. Reintentos de una misma
confirmación deben seguir usando las llaves únicas de compra, conversión y
reward existentes para producir una sola activación, conversión y recompensa.

No se elige aún polling ni webhook: ambos son transporte de integración. Un
adaptador podrá informar estado al mismo límite de aplicación cuando el
proveedor confirme cuál mecanismo soporta.

## Información pendiente del proveedor QR

No completar ni diseñar estos datos hasta recibir su documentación:

- Crear QR: URL, método, auth, campos, representación de monto, moneda,
  referencia merchant/interna, respuesta, ID de transacción, representación QR
  y expiración.
- Estado: endpoint o mecanismo, estados posibles, referencia de consulta y
  estados finales.
- Confirmación de servidor: polling, webhook o ambos.
- Seguridad: firma de requests, validación de webhook, replay protection e IP
  requirements si aplica.
- Timeout/expiración y entorno sandbox/test.

## Checklist de integración

1. Obtener y revisar toda la información pendiente anterior.
2. Definir persistencia mínima y migración sólo si el contrato la hace necesaria.
3. Implementar un adaptador que traduzca el proveedor al contrato canónico.
4. Crear el servicio de aplicación de compra pendiente y confirmación confiable.
5. Reutilizar el pipeline comercial idempotente, sin lógica de activación en el adapter.
6. Añadir autenticación, validación de firma/replay y pruebas sandbox del proveedor.
7. Decidir y probar transporte polling/webhook según contrato.
8. Sólo entonces habilitar configuración y diseñar UX/paywall/retorno al Benefit.
