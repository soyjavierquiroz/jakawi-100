# CLUB → COM: acceso económico Partner v1

Endpoint exclusivo de backend: `POST https://jakawi.com/internal/club/partners/access`.
CLUB obtiene el UUID del usuario de su sesión SSO verificada (claim `sub` OIDC),
nunca de un UUID o email arbitrario presentado por el navegador. El navegador no
llama este endpoint ni recibe la credencial HMAC. No requiere cookies ni sesión
COM. No crea cuentas, contraseñas, membresías, atribuciones ni comisiones.

## Solicitud y autenticación

Body JSON:

```json
{"global_subject_id":"7f359e97-3fd3-4e7a-8ea4-516d242d4144"}
```

Reutiliza el middleware `AuthenticateClubPurchase`, la credencial privada existente
`CLUB_PURCHASE_HMAC_SECRET` y el almacén antirreplay `club_purchase_nonces`.
COM ya carga el secreto desde `/opt/sites/jakawi.com/club-purchases.env` (0600).
No se crea ni rota ninguna credencial. Ver [contrato HMAC existente](club-direct-purchases-v1.md).

Headers:

- `X-Jakawi-Key-Id: club-purchases-v1`
- `X-Jakawi-Timestamp`: Unix seconds, tolerancia ±300 segundos.
- `X-Jakawi-Nonce`: UUID nuevo por intento, también en reintentos.
- `X-Jakawi-Signature`: HMAC-SHA256 hexadecimal minúsculo, sin prefijo.
- `Content-Type: application/json`
- `Accept: application/json`

Cadena canónica exacta, separada por LF sin LF final:

```text
jakawi-club-purchases-v1
club-purchases-v1
<Timestamp>
<Nonce>
POST
/internal/club/partners/access
<sha256 hexadecimal de los bytes exactos del body>
```

Firma: `hash_hmac('sha256', $canonical, $secret)`. Sin query string ni redirecciones.
La firma vincula método, path y body. Nonces compartidos con compras/orígenes:
no reutilizarlos entre endpoints. Límite existente de 60 solicitudes/minuto por IP.

## Respuesta exacta

200; `Cache-Control: private, no-store`; sin cookies:

```json
{
  "global_subject_id": "7f359e97-3fd3-4e7a-8ea4-516d242d4144",
  "partners": [
    {
      "id": 123,
      "name": "Nombre comercial",
      "status": "published",
      "role": "owner",
      "economic_access": true
    }
  ]
}
```

`id` es `partners.id` de COM, no un usuario/beneficiario individual. `name` es
`partners.name`. `role` proviene de `partner_user.role`. `economic_access` es el
resultado de autorización vigente, no el valor crudo del permiso de manager.
Se devuelve solo esta lista de campos, ordenada por `partners.id`, sin PII de
usuarios ni información financiera. UUID consultado normalizado a minúsculas.
Usuario sin Partners o UUID desconocido: 200 con el UUID consultado y `partners: []`.

| Condición actual en COM | economic_access |
| --- | --- |
| `published` + `owner` | true |
| `published` + `manager` + permiso explícito true | true |
| `manager` sin permiso | false |
| `staff`, incluso con permiso true | false |
| Partner `draft`, `paused`, `archived` o cualquier estado distinto de `published` | false |
| Vínculo eliminado/revocado | Partner ausente |

Migración aditiva: `partner_user.economic_access BOOLEAN NOT NULL DEFAULT false`.
No concede permiso a ningún manager existente. El permiso se concede/revoca solo
mediante una operación administrativa confiable en COM sobre la relación exacta
(`user_id`, `partner_id`); no se añade escritura desde el portal ni desde CLUB.
Ejemplo en consola administrativa COM autorizada:

```php
$user->partners()->updateExistingPivot($partnerId, ['economic_access' => true]);
// Revocar permiso de manager:
$user->partners()->updateExistingPivot($partnerId, ['economic_access' => false]);
// Revocar representación completa mediante la relación existente:
$user->partners()->detach($partnerId);
```

Cada consulta usa una lectura nueva de COM. CLUB debe consultar para cada acceso
a resultados económicos y negar acceso cuando `economic_access` no sea `true`,
el Partner esté ausente o COM no esté disponible. No almacenar autorización
permanente en claims OIDC, sesión o caché. Un enlace visible no autoriza resultados.
La revocación tiene efecto en la siguiente consulta. El login/OIDC no cambia.

Errores: 401 HMAC/key/timestamp/nonce/query inválido; 409 `REQUEST_REPLAY`;
422 UUID ausente/inválido o body no JSON; 429 rate limit; 503 credencial existente
no disponible. Reintentar transporte con un nonce nuevo. No permitir acceso ante error.

## Portal COM y alcance

La navegación Partner incluye únicamente **Mis referidos y comisiones** hacia
`https://jakawi.club/afiliados`, visible para la relación actual con acceso
económico usando la misma regla que el endpoint. No añade un dashboard económico
COM. CLUB conserva la responsabilidad de mostrar resultados y aplicar esta consulta.
Solo se despliega COM; la integración consumidora CLUB queda fuera de este cambio.
PARTNER/CASH continúa deshabilitado; login, SSO/OIDC, beneficios, experiencias,
canjes, reservas, pagos y outbox conservan su comportamiento.
