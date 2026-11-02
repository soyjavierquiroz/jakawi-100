# Estado efectivo COM para CLUB

`POST https://jakawi.com/internal/club/memberships/status`

Consulta servidor a servidor bajo el transporte autorizado existente de compras,
orígenes y acceso Partner. Reutiliza `AuthenticateClubPurchase`, su secreto
exclusivo, key ID, ventana ±300 segundos y tabla de nonces; no hay credenciales
nuevas, cookies, sesiones entre dominios, réplica ni sincronización paralela.

Body JSON: `{"global_subject_id":"UUID global COM"}`. CLUB debe obtener ese UUID
únicamente de su vínculo autorizado o del `sub` de un ID token COM cuya firma,
issuer, audience y nonce haya validado. Nunca resolver por correo ni aceptar
un sujeto enviado por el navegador como prueba de identidad.

Firma existente, HMAC-SHA256 hexadecimal sobre estas líneas separadas por LF:

```text
jakawi-club-purchases-v1
<key ID existente>
<timestamp Unix>
<nonce UUID nuevo>
POST
/internal/club/memberships/status
<SHA256 hexadecimal de los bytes exactos del body>
```

Headers: `X-Jakawi-Key-Id`, `X-Jakawi-Timestamp`, `X-Jakawi-Nonce`,
`X-Jakawi-Signature`, `Content-Type: application/json`. No query string.

Respuesta 200 sin `Set-Cookie`, `Cache-Control: private, no-store`:

```json
{
  "contract": "jakawi-com-membership-status-v1",
  "global_subject_id": "UUID global COM",
  "membership": {
    "type": "pioneer",
    "status": "active",
    "is_active": true,
    "starts_at": "2026-11-01T00:00:00.000000Z",
    "effective_ends_at": null,
    "expiry_policy": "official_launch_plus_15_calendar_months",
    "official_launch_date": null,
    "benefits": {
      "public_catalog": true,
      "club_access": true,
      "exclusive_member_benefits": true
    }
  }
}
```

Fechas del ejemplo ilustrativas. `type`: `free` / `standard` / `pioneer`;
`status`: `free` / `active` / `scheduled` / `expired` / `cancelled`.
El tipo conserva el último plan aunque haya vencido o se haya cancelado;
solo `is_active` habilita beneficios exclusivos de membresía. Condiciones y
existencias de cada beneficio siguen aplicando. Si hay membresía activa se
prioriza; si no, se informa la última por inicio e ID. Sin compras se devuelve
`free`, sin fechas ni beneficios exclusivos, con acceso a CLUB y catálogo público.

Estándar usa su vencimiento fijo. Pionero usa la política de `Membership`
existente: lanzamiento oficial +15 meses calendario sin desbordamiento, inclusive
hasta fin del día de America/La_Paz, expresado en UTC. Mientras el lanzamiento
no esté configurado, ambas fechas oficiales son null; Pionero ya iniciado sigue
activo salvo cancelación. No usar el `ends_at` histórico ni calcular un lanzamiento
por la fecha de registro. Inicio futuro sigue siendo programado.

La lectura de dominio usa una transacción PostgreSQL READ ONLY y un snapshot
REPEATABLE READ; solo la protección de nonce del middleware escribe previamente.
401 firma inválida, 409 nonce repetido, 422 entrada inválida, 404 sujeto inexistente.
Ante error, CLUB no debe conceder beneficios exclusivos por defecto. El acceso
a autenticación OIDC no depende de esta respuesta ni de pagos.

Antes de esta ampliación, las consultas de compras informaban el pago y la
referencia de membresía, las de identidad el vínculo y las de Partner el permiso
económico. Ninguna exponía el tipo Pionero ni la vigencia efectiva por identidad.
Los contratos anteriores y sus payloads se conservan. Esta entrega implementa
solo COM; la adopción de la consulta en CLUB queda fuera de su alcance.
