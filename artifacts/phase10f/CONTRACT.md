# Fase 10F — evidencia de adquisición CLUB → COM

Solo COM. Contrato `jakawi-acquisition-evidence-v1`. No calcula importes, no
modifica tarifas PRELAUNCH-10E-v1, no crea comisiones ni duplica el ledger CLUB.

## Endpoint exacto

`POST https://jakawi.com/internal/club/acquisitions/evidence`

```json
{"purchase_reference":"REFERENCIA_COM","affiliate_global_subject_id":null}
```

`purchase_reference` obligatorio, string de hasta 100 caracteres: el
`payment_reference` UUID de los eventos/checkout bancario COM o CLUB, o el
`membership_purchases.reference` durable de una venta manual. No es
`club_reference`, email ni un identificador aportado como prueba por un navegador.
CLUB obtiene la referencia del evento COM confirmado. Para INDIVIDUAL, enviar el
UUID global COM del afiliado obtenido de SSO/vínculo autorizado. Para Partner,
omitir el afiliado o enviar null. COM no resuelve beneficiarios ni montos.

Autenticación privada existente: `CLUB_PURCHASE_HMAC_SECRET`, key ID
`club-purchases-v1`. Sin credenciales nuevas. Solo backend CLUB, sin sesión,
cookies ni acceso firmado desde navegador. Mismos headers y antirreplay de
[compras directas](club-direct-purchases-v1.md):

- `Content-Type: application/json`, `Accept: application/json`.
- `X-Jakawi-Key-Id: club-purchases-v1`.
- `X-Jakawi-Timestamp`: Unix seconds, tolerancia ±300 segundos.
- `X-Jakawi-Nonce`: UUID nuevo por intento, compartido entre endpoints existentes.
- `X-Jakawi-Signature`: HMAC-SHA256 hexadecimal minúscula sin prefijo.

Cadena canónica exacta (LF, sin LF final):

```text
jakawi-club-purchases-v1
club-purchases-v1
<Timestamp>
<Nonce>
POST
/internal/club/acquisitions/evidence
<sha256 hexadecimal de los bytes exactos del body>
```

Firma `HMAC-SHA256(cadena, secreto existente)`. Sin query strings ni redirecciones.
Límite existente 60/min/IP. Firma cubre path, método y body. Sin CORS nuevo.

## Respuesta exacta

200, `Cache-Control: private, no-store`, sin `Set-Cookie`. Ejemplo ilustrativo
(no representa una compra LIVE):

```json
{
  "contract": "jakawi-acquisition-evidence-v1",
  "purchase_reference": "REFERENCIA_COM",
  "purchase_source": "membership_bank_payments",
  "membership_reference": "123",
  "global_subject_id": "UUID_GLOBAL_COM_COMPROBADO",
  "identity_reference": "com:user:45",
  "first_activated_at": "2026-11-01T00:00:00+00:00",
  "first_paid_acquisition": true,
  "reason": "COMPLETE_COM_HISTORY_SINCE_IDENTITY_CREATION",
  "evaluated_at": "2026-11-01T00:00:00+00:00",
  "affiliate": {
    "global_subject_id": "UUID_GLOBAL_COM_AFILIADO",
    "state": "PAID",
    "reason": "EFFECTIVE_PAID_MEMBERSHIP",
    "evaluated_at": "2026-11-01T00:00:00+00:00",
    "membership_references": ["456"]
  },
  "history_observed_from": "2026-10-31T23:00:00+00:00",
  "evidence_references": ["com:membership-evidence:789"],
  "evidence_reference": "com:acquisition-evidence:sha256:HEX_SHA256"
}
```

`first_paid_acquisition` es **boolean true, boolean false o string `"unknown"`**.
`affiliate.state` es `FREE`, `PAID` o `UNKNOWN`. `affiliate=null` si no se solicitó.
Fechas RFC3339 UTC. La fecha evaluada la determina COM desde la activación original;
no se admite una fecha arbitraria ni se usa el estado de hoy. Campos de identidad,
membresía o fecha no certificables son null. La custodia nunca se expone como
UUID del comprador. `purchase_source` también puede ser `membership_purchases`.

Referencias `com:membership-evidence:<id>` identifican filas permanentes del
journal COM (operación, instante observado y hechos permitidos, sin PII ni secretos).
El digest es SHA256 del JSON de todos los campos anteriores a `evidence_reference`,
en el orden mostrado, codificado con PHP `JSON_UNESCAPED_SLASHES`, sin espacios.
CLUB debe conservar el body recibido y sus referencias. No es un token de cobro ni
un derecho económico. Una consulta posterior puede añadir evidencia o resolver
identidad pendiente, con otro digest; nunca reemplaza las filas fuente anteriores.
No se publica un endpoint de lectura general del journal.

## Cómo certifica primera adquisición

La compra debe estar `approved`/`confirmed`, con importe positivo, membresía real
con importe pagado positivo, referencia de pago coincidente e inicio original
consistente. Para banco usa `activated_at` contrastado con el primer inicio de la
membresía; para venta manual usa ese primer inicio persistido. Cambios de inicio,
referencias ambiguas, compras pendientes/reembolsadas o relaciones incoherentes
no certifican activación.

Se revisan versiones y registros actuales de membresías y compras bancarias/QR,
CLUB, ventas manuales y sus referencias, incluyendo canceladas, vencidas,
reembolsadas y eliminaciones observadas. Una membresía pagada anterior o una compra
pagada anterior demuestra `false`; renovación/reactivación no vuelve a ser primera.
Otra membresía de pago incierto, compra pagada sin fecha o adquisición concurrente
produce `unknown`. No basta con que CLUB desconozca la operación.

Un invitado sin vínculo completo devuelve `unknown`. El vínculo posterior requiere
`identity_status=linked`, relación CLUB autorizada y no revocada, coincidencia de
usuario propietario y conservación de la membresía de custodia. Se recorre la
misma historia con sus fechas originales y los antecedentes de la identidad real.
No se vincula por email. Transferencias de propietarios ajenas a esta custodia
autorizada son inciertas.

**Límite histórico explícito:** COM no tenía antes de 10F un historial completo
inmutable de bajas/borrados. La migración observa el estado existente en su instante
real (`BASELINE`) sin modificar ni reconstruir compras. Registra después los cambios
con triggers DB, incluso sin eventos Eloquent, de forma atómica con la operación.
`true` exige una identidad COM creada bajo esta cobertura desde su inserción,
activación dentro de cobertura y ausencia certificada de antecedentes de pago.
Para identidades anteriores sin prueba suficiente de completitud, devuelve
`unknown / HISTORY_BEFORE_OBSERVATION_BOUNDARY`; sus antecedentes reales sí pueden
demostrar `false`. No se inventa un certificado positivo para usuarios legacy.

## Cómo certifica FREE/PAID histórico

El UUID global debe resolver una identidad COM durable. Desde la frontera de
observación, el snapshot inicial y todas las versiones de membresía permiten
consultar el estado en la fecha de activación del comprador, aunque la consulta
llegue después de un cambio, vencimiento, baja, borrado o vínculo autorizado.

`PAID` exige membresía de pago positiva con estado `active`, inicio <= instante y
vencimiento >= instante (misma semántica de vigencia existente COM), y sin baja o
eliminación observada antes de ese instante. Una baja posterior conserva PAID en
la consulta histórica anterior. FREE→PAID posterior conserva FREE en el instante
anterior. La custodia reclamada aporta la membresía y fecha originales.

`FREE` exige identidad existente en ese instante, cobertura histórica COM y ninguna
membresía de pago vigente; es una clasificación de COM, no ausencia de eventos
CLUB. Membresías de importe/estado incierto, incorporación tardía con fecha
retroactiva incierta o fechas anteriores a la cobertura producen `UNKNOWN`.
Una tolerancia inferior a un segundo cubre la precisión de segundos del flujo
existente de activación frente al registro DB con microsegundos. Fechas de
revocación/baja se conservan por el instante observado en el journal. No se
reconstruyen supuestas fechas de baja anteriores a 10F.

## UNKNOWN y errores

Motivos de compra/identidad: `PURCHASE_NOT_FOUND`, `PURCHASE_REFERENCE_CONFLICT`,
`PURCHASE_OR_ACTIVATION_UNCERTIFIED`, `BUYER_IDENTITY_UNRESOLVED`,
`BUYER_LINK_UNAUTHORIZED`, `MEMBERSHIP_OWNER_CONFLICT`,
`MEMBERSHIP_OWNER_HISTORY_UNCERTAIN`, `HISTORY_BEFORE_OBSERVATION_BOUNDARY`,
`OTHER_PAID_PURCHASE_UNDATED_OR_CONCURRENT`, `OTHER_MEMBERSHIP_PAYMENT_UNCERTAIN`,
`RELATED_PURCHASE_IDENTITY_UNRESOLVED`.
Motivos del afiliado: `ACTIVATION_UNCERTIFIED`, `IDENTITY_UNKNOWN`,
`HISTORICAL_STATE_NOT_OBSERVED`, `MEMBERSHIP_STATE_UNCERTAIN`.

CLUB no debe conceder comisión especulativa ante `unknown`/`UNKNOWN`, conflicto,
error, timeout o respuesta no verificable. Puede reconsultar la misma referencia
con nonce nuevo tras un vínculo autorizado; nunca crear otra compra o activación.
El endpoint no expresa elegibilidad económica ni sustituye deduplicación CLUB.

401 HMAC/key/timestamp/query inválidos; 409 `REQUEST_REPLAY`; 422 body/validación;
429 límite; 503 secreto no disponible. Una referencia inexistente devuelve 200 con
unknown, no una compra ficticia. Errores de infraestructura fallan sin certificar.

## Compatibilidad y validación

Consulta de dominio de solo lectura con snapshot transaccional. Única escritura
por solicitud: nonce antirreplay existente. No activa, reactiva, cancela, enlaza ni
reescribe compras. Journal y frontera son permanentes; rollback solo de imágenes.
No modifica checkout COM/CLUB, conciliación, SSO, Partners, atribución inmutable,
`membership.activated`, `membership.identity_linked`, transporte outbox ni la
deshabilitación PARTNER/CASH. No se despliega ni modifica CLUB.

Pruebas aisladas: `bin/jakawi-test php artisan test --compact
--filter='ClubAcquisitionEvidenceTest|ClubDirectPurchaseTest|ClubPartnerAccessTest|PartnerMembershipAttributionTest|PartnerMembershipOutboxTest|MembershipBankCheckoutTest|MembershipBankQrTest|AffiliationRetirementTest|OidcFlowTest|ManualMembershipSalesTest'`.
Se cubren primera compra, renovación, reactivación, historial/manual, invitado,
vínculo posterior y revocación, FREE/PAID, upgrade posterior, vencimiento,
baja/borrado posterior, desconocido, cobertura insuficiente y reconsulta histórica.
Ver resultado y SHA LIVE en `artifacts/phase10f/LIVE.json` después del despliegue.

La limpieza de suites con transacciones confirmadas usa `EvidenceDatabaseTruncation`,
que permite reiniciar fixtures y frontera exclusivamente con APP_ENV=testing,
host db-test y base jakawi_test; el bloqueo permanente LIVE no tiene excepciones.
