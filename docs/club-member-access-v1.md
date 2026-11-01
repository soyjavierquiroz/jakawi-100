# Fase 11G — Acceso CLUB → COM

COM implementa `jakawi-club-member-access-v1`. Solo se despliega COM. CLUB no se modifica: su backend debe integrar este contrato y ofrecer el botón de continuación. No se ha agregado ese botón en CLUB en esta entrega.

## A. Contrato exacto para CLUB

Desde backend, `POST https://jakawi.com/internal/club/purchases/{club_reference}/access-ticket`. La referencia es la original de CLUB, no la referencia COM ni el número de membresía.

```json
{"club_user_uuid":"UUID original o autorizado del usuario CLUB","claim_secret":"SECRETO ORIGINAL conservado en intent cifrado de CLUB"}
```

No enviar email, nombre, teléfono ni identificadores de usuario COM. `global_subject_id`, `user_id` y `redirect` están prohibidos. El navegador no elige identidad ni recibe el claim.

Usar el transporte HMAC de compras existente (`CLUB_PURCHASE_HMAC_SECRET`, key ID `club-purchases-v1`). Headers: `Content-Type: application/json`, `Accept: application/json`, `X-Jakawi-Key-Id`, `X-Jakawi-Timestamp` (Unix segundos), `X-Jakawi-Nonce` (UUID nuevo por petición), `X-Jakawi-Signature` (HMAC-SHA256 hexadecimal). Firmar exactamente los bytes JSON enviados, sin query string:

```text
jakawi-club-purchases-v1\n{key_id}\n{timestamp}\n{nonce}\nPOST\n/internal/club/purchases/{club_reference}/access-ticket\n{sha256_hex_del_body}
```

Los `\n` representan saltos de línea LF reales; sin LF final. Ventana de transporte ±300 segundos y nonce de un solo uso. No reutilizar firmas ni registrar cuerpos de esta llamada ni su respuesta.

Respuesta 200:

```json
{"contract":"jakawi-club-member-access-v1","ticket":"64 caracteres hex aleatorios","expires_at":"fecha RFC3339","entry_url":"https://jakawi.com/club/access","destination":"/beneficios"}
```

`Cache-Control: private, no-store`. El ticket tiene 90 segundos desde emisión. Cada solicitud genera uno nuevo; un timeout puede recuperarse solicitando otro con nonce nuevo, sin compra, pago ni registro adicionales.

CLUB debe autorizar primero su acción POST con su propia sesión y CSRF. Resolver la compra desde su backend: dueño autenticado original/autorizado o sesión legítima que posee la compra invitada. Nunca aceptar del navegador un UUID o referencia ajenos sin esa comprobación. Cargar claim del intent cifrado original. El HMAC solo autentica transporte; COM exige además ese claim y el vínculo autorizado.

Tras recibir el ticket, responder no-store y navegar con `window.location.replace(entry_url + '#' + ticket)`. No ponerlo en query, pathname, enlaces persistentes, HTML cacheado, analytics, almacenamiento local, logs ni herramientas de seguimiento. El fragmento no se transmite a servidores ni como Referer; COM lo elimina inmediatamente con `history.replaceState`. La pantalla COM no carga scripts, imágenes o analytics externos y envía `Referrer-Policy: no-referrer`, CSP sin conexiones externas, y noindex. El comprador pulsa Continuar; el ticket viaja solo en body POST con CSRF. Se guarda su hash en sesión y se redirige a `/club/access/continue`. Un segundo POST de confirmación consume el ticket. GET/HEAD/prefetch nunca lo consumen. Si JavaScript está desactivado, volver a CLUB y solicitar otro.

Errores backend: 401 HMAC inválido; 403 `INVALID_PURCHASE_CLAIM`; 409 `PURCHASE_OWNER_CONFLICT`, `MEMBERSHIP_NOT_ACTIVE`, `IDENTITY_LINK_REQUIRED`, `IDENTITY_CONFLICT`; 422 validación; 429 límite de solicitudes; 503 transporte no disponible. El código de error actual está en `message` para rechazos del controlador, y `error` para rechazos del middleware existente. No mostrar mensajes técnicos al comprador: explicar que debe completar su identidad, resolver un conflicto o volver a intentar, según corresponda.

## B. Autorización del titular

COM verifica hash SHA-256 del claim original; compra CLUB aprobada, activada y con membresía real; UUID CLUB original inmutable si ya asignado; vínculo `identity_links` vigente, autorizado, hacia el usuario COM y su `global_subject_id` emitido por COM. Ninguna coincidencia de email autoriza acceso.

Si falta vínculo, devuelve `IDENTITY_LINK_REQUIRED`, sin mover custodia ni crear usuarios. CLUB debe completar primero su flujo existente de identidad: prueba criptográfica de activación y consentimiento → `/internal/identity/handoffs`. Una identidad nueva se aprovisiona mediante la autoridad existente. Si ya existe cuenta COM, requiere autorización autenticada existente; una coincidencia de email no la vincula. Revocaciones y conflictos bloquean acceso. No se inventa otro proveedor SSO ni se altera OIDC.

Una vez autorizado el vínculo, esta llamada puede completar el attach existente de `/identity` usando el claim original. No es necesario llamar `/identity` nuevamente. Conserva compra, membresía e identidad. No reactiva membresía ni crea comisiones. Solo emite los eventos de vinculación existentes e idempotentes.

## C. Apertura de sesión COM

32 bytes de `random_bytes`, 90 segundos, solo SHA-256 persistido en tabla COM. Atado a compra, usuario COM, UUID CLUB, global subject y finalidad fija `club_member_benefits`.

El consumo vuelve a comprobar vínculo vigente, sujeto, titular y membresía activa bajo el mismo bloqueo de compra usado por `/identity`. Un UPDATE condicional sobre `consumed_at IS NULL`, finalidad y vencimiento elige un único ganador atómicamente incluso entre sesiones concurrentes. Fallos de autorización revierten la transacción.

Laravel autentica el usuario autorizado mediante guard `web`; invalida la sesión anterior, rota CSRF y regenera ID de sesión. No solicita ni expone contraseña. Otra cuenta COM abierta exige checkbox explícito para cambiar de cuenta; sin ello responde conflicto y conserva sesión y ticket. Cancelar conserva la cuenta actual y vuelve al origen fijo `https://jakawi.club`.

## D. Destino real

`https://jakawi.com/beneficios`, ruta `benefits.index`, controlador `PublicController::benefits`, página existente `benefits/index`. Destino fijo local, sin parámetro de retorno. La membresía activa pertenece al mismo usuario de la sesión y habilita el flujo existente de canje.

## E. Límites legítimos y Janny

Sin claim original válido no se emite ticket, incluso si existe vínculo. No hay recuperación por email, nombre, teléfono, referencia pública ni HMAC. Se requiere recuperar prueba legítima mediante los canales existentes; esta entrega no agrega un bypass ni una recuperación manual.

También bloquean: compra no aprobada, membresía vencida/cancelada, vínculo ausente/revocado, UUID ajeno, titular inconsistente o colisión de membresía activa. Un ticket vencido/usado se solicita nuevamente desde CLUB, sin pagar ni registrar otra cuenta.

Janny: CLUB `97cb9a04-9f79-448d-be20-7a0b2bbb795d`, COM `1ef96c20-5ab7-4322-b969-9a902660930a`. Verificación de solo lectura: membresía 2 activa, identidad pendiente, custodia conservada, sin identity_link. CLUB conserva el claim original cifrado y su hash coincide con COM; el dueño CLUB verificado coincide con el UUID original de compra. Puede continuar por identidad autorizada existente y luego solicitar ticket. No se ejecutó vínculo, emisión de ticket ni login sobre Janny. Las pruebas usan únicamente fixtures de la base aislada `jakawi_test`.

## F. Publicación

Consultar `artifacts/phase11g/LIVE.json` para SHA e inspección del despliegue COM. Pruebas y evidencias no contienen tickets, claims ni credenciales reales. La integración del botón en CLUB queda para su backend, fuera del despliegue solicitado exclusivamente COM.
