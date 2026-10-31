# Fase 10B — atribución de membresías originadas en Partners COM

COM emite procedencia y hechos. CLUB valida beneficiarios, conserva el referente
original y aplica su ledger. No se reactiva PARTNER/CASH ni se configura economía
nueva en COM. No se modifica ni despliega CLUB en esta fase.

## Regla y momento de fijación

1. Una atribución COM ya fijada o una relación de referido original persistida
   prevalece, incluso si venció la antigua ventana comercial de esa relación.
2. Antes de fijar: código CLUB explícito candidato, o código COM legacy válido,
   prevalece sobre el Partner de la página.
3. Sin referente ni código: el perfil Partner que origina el registro o checkout
   aporta su `partners.id`. Antes de fijar se puede cambiar el candidato al navegar;
   después, ningún otro perfil o código sustituye el original.
4. Ver un perfil, hacer login o cruzar a CLUB no crea atribución, lead ni comisión.
   Se fija al crear realmente una cuenta COM atribuida o la primera intención de
   compra válida. Prefetch y registro inválido no fijan nada.

Un `club_ref` público es candidato, no credencial ni prueba de que exista un
beneficiario. COM verifica exactamente 12 caracteres `[A-Z0-9]`, conserva el código
sin convertirlo en un usuario COM y envía `beneficiary_resolution=CLUB`. CLUB debe
validar su existencia/elegibilidad en su modelo y fijar su beneficiario original;
si el candidato no es válido, aplica `fallback_partner_id` cuando esté presente.
COM no consulta la DB CLUB ni atribuye al email de un empleado/owner.

Los candidatos viven en sesión servidor por la ventana existente (30 días por
defecto). Los snapshots fijados son durables y no expiran por esa ventana.
`membership_acquisition_origins` conserva UUID, usuario opcional, JSON de atribución
y `fixed_at`; hay un origen por usuario, bloqueo transaccional y protección DB
contra reemplazo/borrado. Las compras bancarias referencian un origen por FK
nullable e inmutable. No hay backfill ni alteración de compras históricas.

## Perfiles y navegación

Los perfiles existentes (`/dossis43`, etc.) conservan su contenido y registro.
También enlazan al checkout COM existente y a redirecciones, sin nuevas páginas:

- `/p/{partner:slug}/membership` → checkout COM existente; resuelve el Partner del CTA aun con otras pestañas abiertas.
- `/p/{partner:slug}/club/carta` → `https://jakawi.club/membresia` (carta pública vigente).
- `/p/{partner:slug}/club/checkout` → `https://jakawi.club/activar`.

COM resuelve el slug publicado a `partners.id`; no utiliza un `partner_id` aportado
por navegador. Añade `com_origin`, firmado por COM, y conserva `club_ref` cuando
corresponde. La respuesta lleva `Cache-Control: private, no-store` y
`Referrer-Policy: no-referrer`. No cambia enlaces cortos históricos ni el origen
separado de los canjes de beneficios.

## Prueba de origen COM → CLUB

Contrato: `jakawi-com-origin-v1`. Token:

```
base64url(JSON-sin-padding) + "." + HMAC-SHA256-hex
```

JSON firmado (ejemplo; los IDs los resuelve COM):

```json
{
  "contract": "jakawi-com-origin-v1",
  "source": "jakawi.com",
  "audience": "jakawi.club",
  "issued_at": 1793462400,
  "expires_at": 1796054400,
  "acquisition": {"source":"jakawi.com","kind":"partner","partner_id":1}
}
```

Firma: `hash_hmac('sha256', "jakawi-com-origin-v1\n" + segmento_base64url,
CLUB_COMMERCIAL_HMAC_SECRET)`, hexadecimal minúscula. Se usa el secreto existente
COM→CLUB como cadena, con prefijo de propósito distinto del de eventos. No es un
secreto nuevo, ni se envía al navegador. No decodificar IDs sin verificar firma,
source, audience, contrato, expiración e issued_at (máximo 300 s futuro).

El token prueba procedencia, no identidad del comprador, autorización SSO, cobro,
activación ni beneficiario económico ya validado. No fija una atribución al emitirse.
Un token compartido jamás identifica al titular de una cuenta.

CLUB puede verificarlo en backend o llamar:

`POST https://jakawi.com/internal/club/origins/resolve`

```json
{"com_origin":"TOKEN","club_user_uuid":null}
```

Autenticación: el HMAC de compras existente `jakawi-club-purchases-v1`, key ID
`club-purchases-v1`, nonce UUID nuevo y timestamp ±300 s; misma cadena canónica
documentada en `club-direct-purchases-v1.md`, usando este path exacto. Sin query.
`club_user_uuid` es opcional; solo un vínculo de identidad previamente autorizado
en COM permite priorizar su origen real, nunca una coincidencia de email.
Respuesta 200, sin cookies/sesión:

```json
{"contract":"jakawi-com-origin-v1","source":"jakawi.com","acquisition":{"source":"jakawi.com","kind":"partner","partner_id":1},"beneficiary_resolution":"CLUB"}
```

401: HMAC inválido; 409: nonce repetido; 422: token manipulado/expirado.
Consultar el token no crea lead, usuario, compra ni origen fijado.

## Compras CLUB → COM

`POST /internal/club/purchases` conserva todos sus campos y firma. Extensiones
opcionales, incluidas en firma e idempotencia de intención:

```json
{"com_origin":"TOKEN_COM_O_NULL","original_club_referral_code":null}
```

CLUB debe conservar el token desde carta hasta checkout, incluso invitado, y
enviarlo por su backend. Si CLUB ya fijó un referente original, puede enviar
`original_club_referral_code` desde su registro autorizado: nunca reenviar sin
validar una afirmación arbitraria del navegador. El origen COM previamente
establecido de una identidad autorizada gana; después el original CLUB declarado
por su backend; después el contexto firmado. El email declarado no vincula identidad.
Un `partner_id` suelto no tiene efecto.

La respuesta añade `acquisition` (snapshot o null en compras antiguas). Ejemplo:

```json
{"source":"jakawi.com","kind":"partner","partner_id":1,"attribution_id":"UUID","fixed_at":"2026-10-31T18:00:00+00:00","basis":"purchase"}
```

Otros `kind`: `club_referral` con `club_referral_code`, resolución CLUB y fallback
opcional; `com_referral` con `referrer_global_subject_id` y referencia COM si existe;
`none` para una primera compra sin procedencia. Un original de compra nunca se
reemplaza; repetir intención idéntica sigue funcionando tras expirar el token.
Una intención nueva con token inválido/expirado se rechaza sin crear compra/origen.

## Outbox y eventos nuevos

Misma tabla `commercial_outbox`, endpoint `https://jakawi.club/internal/commercial/events`,
key ID `com-commercial-v1`, firma y acuses existentes. Todos incluyen:
`schema_version=1`, `source=jakawi.com`, `event_id`, `event_type`, `occurred_at`.

| event_type | event_id | Hecho / campos adicionales |
|---|---|---|
| `member.registered` | `com:member:<global_subject_id>:registered` | Usuario COM realmente creado: global_subject_id real, com_reference={type:user,id:ID_COM}, acquisition |
| `membership.attribution_fixed` | `com:membership:<payment_reference>:attribution-fixed` | Intención válida: payment_reference, club_reference, guest_purchase_id, club_user_uuid, global_subject_id real si conocido/autorizado, acquisition |
| `membership.attribution_identity_linked` | `com:membership:<payment_reference>:attribution-identity-linked` | Vínculo autorizado: mismas referencias de compra/identidad, global_subject_id real, purchase_acquisition y acquisition efectiva del usuario |

Los nuevos eventos son atómicos con el alta/compra/vínculo correspondiente. Acuse
201 `{"event_id":"...","status":"accepted"}` o 200 con `status=duplicate`.
Deduplicar por event_id, conservar raw_body y no crear otra obligación al reintentar.
No se emite lead por visitas/login. No se reemiten registros históricos.

Los cuerpos existentes de `membership.activated` y `membership.identity_linked`
no cambian. CLUB conserva siempre cualquier referente original ya establecido en su propio modelo,
antes de resolver candidatos recibidos de COM. Une los nuevos hechos por
payment_reference/club_reference, soporta
entrega desordenada y **solo remunera tras membership.activated real**. No remunera
un lead, token, intención, comprobante ni evento de identidad.

En invitadas, la procedencia se conserva en la compra sin convertir la identidad
de custodia en el comprador. Al vincular después, no se sustituye el snapshot de
la compra ni el original de un usuario ya referido. El nuevo evento de vínculo
expone ambos: el original del usuario gana según A y la procedencia inicial de la
compra se conserva como trazabilidad. CLUB no genera una segunda activación/comisión.

## Estado del consumidor CLUB

**Pendiente de implementación en CLUB.** El código CLUB actual no consume
`com_origin` en su checkout y rechaza los nuevos tipos de evento. En esta fase no
se modifica. COM conserva esos nuevos eventos en RETRY incluso ante 4xx de esquema;
no los marca DEAD ni altera los eventos existentes. Por tanto, el contrato COM está
operativo, pero la atribución empresarial y la resolución de códigos válidos en
CLUB no están activas de extremo a extremo hasta actualizar su consumidor.

Trabajo mínimo CLUB: consumir/verificar y conservar com_origin en carta/checkout;
reenviarlo en intención; reconocer los tres eventos nuevos; resolver el código
explícito contra su beneficiario original o fallback Partner por source+partner_id;
respetar referente previo; unir activación/identidad por compra y reutilizar su ledger.
No inventar una cuenta individual con email del owner como beneficiario Partner.
