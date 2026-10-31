# CLUB → COM: compras directas de membresía, v1

COM recibe compras desde el checkout nativo CLUB por HTTPS. No se necesita cookie,
sesión COM, SSO, email verificado ni prueba de activación de email para crear,
enviar comprobante, conciliar o activar una compra. La conciliación y la autoridad
de activación son exclusivamente COM. El checkout COM conserva sus rutas y flujo.

## Credencial y firma de entrada

Credencial nueva, exclusiva: `CLUB_PURCHASE_HMAC_SECRET`. Key ID:
`club-purchases-v1`. En COM se instala mediante el archivo privado
`/opt/sites/jakawi.com/club-purchases.env` (0600). Codex CLUB debe tomar esta
credencial por un canal privado e instalarla **solo en el backend CLUB** cuando
implemente su parte; este despliegue no cambia CLUB ni entrega secretos al navegador.
No reutilizar secretos de identidad, prueba de email, CRM u outbox.

Todas las rutas siguientes exigen estos encabezados:

- `X-Jakawi-Key-Id: club-purchases-v1`
- `X-Jakawi-Timestamp`: Unix seconds UTC, tolerancia ±300 s.
- `X-Jakawi-Nonce`: UUID nuevo por intento HTTP, incluso en reintentos.
- `X-Jakawi-Signature`: HMAC-SHA256 hexadecimal minúsculo, sin prefijo.
- `Accept: application/json`; POST también `Content-Type: application/json`.

Cadena canónica, separador LF, **sin LF final**:

```text
jakawi-club-purchases-v1
club-purchases-v1
<Timestamp>
<Nonce>
<METHOD en mayúsculas>
<path exacto, sin host ni query>
<sha256 hexadecimal del body exacto>
```

GET lleva body vacío. No se aceptan query strings. Firmar los bytes JSON que se
transmiten, no una reserialización posterior. La firma cubre también el comprobante.
No seguir redirecciones. Usar exclusivamente `https://jakawi.com`.

Ejemplo de firma para el backend CLUB (PHP):

```php
$path = '/internal/club/purchases';
$body = json_encode($intent, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$timestamp = (string) time();
$nonce = (string) Str::uuid();
$canonical = implode("\n", ['jakawi-club-purchases-v1', 'club-purchases-v1',
    $timestamp, $nonce, 'POST', $path, hash('sha256', $body)]);
$signature = hash_hmac('sha256', $canonical, getenv('CLUB_PURCHASE_HMAC_SECRET'));
// Enviar $body literalmente, junto con los cuatro encabezados de arriba.
```

## Rutas

| Método | Ruta | Función |
| --- | --- | --- |
| POST | `/internal/club/purchases` | Crear intención, o recuperar duplicado idéntico |
| GET | `/internal/club/purchases/{club_reference}` | Recuperar estado actual |
| GET | `/internal/club/purchases/qr` | Obtener imagen QR original existente de COM |
| POST | `/internal/club/purchases/{club_reference}/receipt` | Transferir comprobante privado entre backends |
| POST | `/internal/club/purchases/{club_reference}/identity` | Reclamar/vincular compra tras autorización de identidad |

### Crear intención

```json
{
  "club_reference": "346c81c5-672d-4146-93c9-e82d9c6a2c72",
  "club_user_uuid": null,
  "guest_purchase_id": "ca1ea3fb-17b2-4b97-973a-21e5b0e7e669",
  "name": "Nombre declarado",
  "email": "comprador@example.com",
  "phone": "+59170000000",
  "amount": "100.00",
  "currency": "BOB",
  "origin": "CLUB",
  "claim_secret": "GENERAR_32_BYTES_ALEATORIOS_EN_HEX_64_CARACTERES"
}
```

UUIDs en minúsculas. `club_reference` es la referencia UUID estable de esta compra
CLUB, diferente del UUID de usuario. `club_user_uuid` es opcional. Si no existe,
`guest_purchase_id` es obligatorio. Nombre, correo y teléfono son declarados, no
credenciales. Precio fijo Bs 100. CLUB genera y conserva `claim_secret` (32–128
caracteres, recomendado 32 bytes aleatorios codificados en hex); no enviarlo en
URLs, logs, eventos o analytics. COM guarda solamente su SHA256.

201 crea; 200 devuelve el duplicado. Reintentar con los mismos campos/valores;
una referencia repetida con datos distintos devuelve 409 `INTENT_CONFLICT`.
Persistir el payload original CLUB. Las llamadas concurrentes se serializan por
referencia. `club_reference` nunca se reasigna. Campos de verificación de email
no son necesarios y no alteran la autorización de cobro.

Respuesta de creación/consulta/comprobante/vínculo:

```json
{
  "contract": "jakawi-club-purchases-v1",
  "club_reference": "346c81c5-672d-4146-93c9-e82d9c6a2c72",
  "payment_reference": "UUID-EMITIDO-POR-COM",
  "status": "awaiting_payment",
  "amount": "100.00",
  "currency": "BOB",
  "origin": "CLUB",
  "identity_status": "pending",
  "club_user_uuid": null,
  "guest_purchase_id": "ca1ea3fb-17b2-4b97-973a-21e5b0e7e669",
  "global_subject_id": null,
  "membership_reference": null,
  "activated_at": null,
  "receipt_received": false,
  "receipt_sha256": null,
  "review_note": null,
  "qr_ready": true,
  "qr_path": "/internal/club/purchases/qr"
}
```

CLUB debe deshabilitar la presentación de pago si `qr_ready=false`; puede recuperar
el QR vía su backend. QR no configurado devuelve 404. El endpoint QR usa el mismo
QR bancario original y destinatario administrado en COM, sin generar otro cobro.

### Transferir comprobante

```json
{
  "content_base64": "BASE64_ESTANDAR_DEL_ARCHIVO_COMPLETO",
  "sha256": "SHA256_HEXADECIMAL_MINUSCULO_DEL_ARCHIVO_BINARIO"
}
```

Transferencia HTTPS autenticada backend a backend; máximo binario 10 MiB;
JPG/PNG/PDF detectado por contenido. No se aceptan URLs ni se descarga contenido
de hosts externos. COM almacena su propia copia MinIO con visibilidad privada,
SHA256 y MIME. Nunca devuelve rutas MinIO, URL pública o URL del comprobante.
Solo administradores COM pueden consultarlo para una compra sin cuenta vinculada.

201 acepta y pone `under_review`; 200 para el mismo SHA en cualquier estado, sin
segunda tarea IA. Un archivo diferente solo se acepta en `awaiting_payment` o
`rejected`; en revisión/aprobado devuelve 409 `RECEIPT_LOCKED`. Corrección rechazada
conserva archivos anteriores privados y reinicia la extracción con nueva versión.
SHA/MIME inválidos devuelven 422. Una falla de almacenamiento/DB no crea activación.

La extracción usa la cola IA existente cuando esté configurada. Si falta la
configuración, revisión manual sigue disponible. Solo la conciliación explícita
individual o batch con el CSV común puede aprobar. Subir comprobante no activa.

## Identidad después del pago

1. La compra no crea ni vincula una cuenta COM por su email declarado.
2. En la aprobación COM busca un vínculo `identity_links` CLUB autorizado y vigente.
   Si existe y la cuenta no tiene membresía activa,
   activa con el servicio existente para esa cuenta. No exige email verificado.
3. En otro caso activa realmente en COM sobre una identidad de custodia por compra,
   con email reservado `club-purchase-<payment_reference>@purchases.invalid`,
   contraseña aleatoria no entregada, sin login ni email de bienvenida. Se crea
   únicamente dentro de la transacción de aprobación. No se proyecta esa identidad
   de custodia hacia CRM/marketing y no se emite su UUID como identidad del comprador.
4. Para crear/vincular la cuenta real después de comprar, CLUB usa el contrato
   existente de identidad (`/internal/identity/handoffs`, consulta de vínculo y
   autorización autenticada COM cuando corresponde). Ese proceso puede exigir
   prueba de email/consentimiento para identidad; **nunca condiciona el pago**.
   Una coincidencia de email existente requiere autorización COM del titular;
   no se enlaza por coincidencia de correo.
5. Una vez obtenido el vínculo autorizado (o mientras se espera), CLUB envía:

```json
{
  "club_user_uuid": "UUID-DEL-USUARIO-CLUB",
  "claim_secret": "MISMO_SECRETO_PRIVADO_DE_LA_INTENCION"
}
```

El endpoint `/identity` requiere firma HMAC y secreto de compra. Admite invitadas,
pero no cambiar el UUID CLUB ya asociado. Sin vínculo autorizado: 200 `pending`.
Con vínculo válido: 200 `linked`; se transfiere la misma membresía de custodia a
la cuenta, conservando referencia, fechas, comprobante y movimiento. No se genera
otra activación ni extensión ni comisión. Los reintentos son idempotentes.

Si la cuenta destino ya tiene membresía activa:
409 `identity_status=conflict`. La compra sigue aprobada/activa en custodia y el
comprobante permanece intacto. El administrador resuelve la membresía incompatible mediante las operaciones
existentes y CLUB reintenta `/identity`. Un checkout COM pendiente se conserva:
no impide vincular una compra CLUB ya aprobada.
No hay fusión automática, sobrescritura de cuenta, devolución automática ni
reasignación manual por email. `global_subject_id` solo identifica al comprador
cuando el vínculo está completado. La membresía activa de invitado se reconoce en
CLUB por la referencia segura de compra, no por un email ni UUID global de custodia.

## Estados, reintentos y eventos

Pago: `awaiting_payment` → `under_review` → `approved`, o `rejected` con corrección.
Identidad: `pending`, `linked`, `conflict`. Son dimensiones independientes.
Una aprobación repetida con el mismo movimiento no crea otra membresía/evento;
otro movimiento en una compra aprobada es rechazado. Un ingreso bancario no puede
usarse en dos compras, sean CLUB o COM. CSV, batch y panel administrativo son únicos.

Los rechazos HMAC usan `error`; los rechazos de dominio usan el `message` JSON
de Laravel (por ejemplo `INTENT_CONFLICT`), y la validación usa `errors` por campo.
Errores: 401 firma inválida; 403 claim inválido; 404 referencia/QR inexistente;
409 replay de nonce/conflicto; 422 validación; 429 límite de tráfico; 503 credencial
o almacenamiento no disponible. Usar nonce nuevo en cada reintento, backoff con
jitter para transporte/429/5xx. No reinterpretar 409 como compra nueva. Un timeout
se resuelve con GET o reintento de la misma intención/comprobante.

Tras aprobación **y activación real**, COM escribe atómicamente en el outbox HMAC
existente un `membership.activated`, event_id estable
`com:membership:<payment_reference>:activated`. Conserva los campos COM existentes
(`schema_version=1`, `membership_reference`, `com_reference`, `payment_reference`,
`occurred_at`) y para compras CLUB añade `origin=CLUB`, `club_reference`,
`club_user_uuid`, `guest_purchase_id`, `identity_status`. `global_subject_id` es
null para custodia y UUID global real para comprador vinculado. No contiene PII,
claim, comprobante ni datos bancarios.

Si el vínculo ocurre después, COM emite una vez `membership.identity_linked` con
ID `com:membership:<payment_reference>:identity-linked`, `schema_version=1`,
`payment_reference`, `club_reference`, `club_user_uuid`, `global_subject_id`,
`membership_reference`, `occurred_at`. CLUB debe aceptar este evento sin repetir
la activación. Los eventos pueden llegar fuera de orden: reconciliar por compra,
conservar el vínculo más específico y no degradarlo a null con un evento atrasado.

Entrega a `https://jakawi.club/internal/commercial/events`, firma existente:
`X-Jakawi-Com-Key-Id: com-commercial-v1`, `X-Jakawi-Com-Timestamp`,
`X-Jakawi-Com-Signature: sha256=<HMAC>` de
`<timestamp>\nPOST\n/internal/commercial/events\n<sha256-body>`, con el secreto
comercial existente (diferente del de entrada). Acuse CLUB requerido:
201 `{"event_id":"...","status":"accepted"}` o
200 `{"event_id":"...","status":"duplicate"}`. Ambos eventos de membresía se
conservan en RETRY si CLUB aún no los entiende. La entrega puede repetirse: deduplicar
por event_id. No depender de la entrega HTTP para considerar comprometida la compra COM.

## Alcance del despliegue

Solo COM (app, web, worker, scheduler), migración aditiva y credencial nueva COM.
No se despliegan cambios CLUB. CLUB debe implementar este contrato para presentar
su checkout nativo y recibir/reconciliar los eventos. No hay nuevo motor bancario,
conciliación, integración CRM, canjes ni comisiones.
