# COM → CLUB: emisor comercial piloto

Piloto: `https://jakawi.com/l/dossis-43-cafe-y-antojo`, partner existente
`dossis43` (Dossis 43 | Coffee). El producto se resuelve desde la landing existente;
no se crea ni modifica partner, landing, campaña, membresía o beneficio.

El evento es **`redemption.confirmed`**, una transacción de canje confirmada por
el servicio existente y su PIN de local. Visitas, clics, registro de cuenta,
solicitudes pendientes, códigos vencidos y canjes cancelados no son conversiones.
No se altera el servicio de canje ni su autorización.

## Procedencia y entrega

Un observer independiente captura al crear el canje una fila en
`commercial_conversion_origins`, dentro de la misma transacción/savepoint. Contiene
solo el partner original, la identidad global ya existente y el origen comprobado.
Una falla de captura queda en el log sin impedir el canje; no se reconstruye luego
una atribución desde la sesión del operador. Esa ausencia debe investigarse, nunca
rellenarse con valores inferidos.

Landing: continuación explícita del cliente para ese beneficio o touch aplicable
de COM que coincida exactamente con landing y beneficio. Sin esa evidencia se
omite `origin`, incluso si existe una landing del mismo producto. Campaña: valor
capturado originalmente; si está ausente, se omite. Referido: solo el ID del registro
COM vinculado al touch exacto y al cliente. No se deduce de email, partner, identidad
global o sesión. COM no captura actualmente códigos CLUB en este flujo; sus códigos
locales no se envían como `club_referral_code`. No hay cambios de afiliación.

El scheduler existente ejecuta **`php artisan commercial:dispatch`** cada minuto.
Solo lee canjes confirmados y comprometidos, junto a la procedencia capturada.
Materializa el outbox durable con ID `com:redemption:<public_id original>:confirmed`,
referencia `{type: redemption, id: <public_id original>}`, fecha `confirmed_at`,
tipo/estado originales y los campos disponibles. No modifica tablas de dominio.
No incorpora conversiones históricas sin procedencia capturada al iniciar el canje.

Destino fijo: `POST https://jakawi.club/internal/commercial/events`.
Se implementa el contrato HMAC v1 entregado por CLUB: caracteres ASCII de la clave,
hash de los bytes UTF-8 exactos, timestamp nuevo por intento y firma sin LF final.
Sin cookies, Bearer ni redirecciones; conexión 5 s, total 15 s.

`commercial_outbox.raw_body` y `event_id` permanecen estables. PostgreSQL serializa
los consumidores con `FOR UPDATE SKIP LOCKED`. Un proceso muerto libera el lock y
revierte su estado local; el recibo remoto perdido se recupera como `duplicate`.
Backoff exponencial de 60 s a 6 h, más jitter de 0–30 s. 5xx, timeout, 429 o recibo
incoherente se reintentan sin límite de intentos. 400/401/409/413/415/422 y otros
errores definitivos quedan `DEAD`, sin cambiar ID. 200/201 solo completan entrega
si el recibo devuelve el ID y status esperados.

Activación: `CLUB_COMMERCIAL_ENABLED=true`,
`CLUB_COMMERCIAL_HMAC_SECRET` en archivo env privado de COM, root 0600, fuera de
Git/imágenes, instalado desde el archivo privado comercial de CLUB. Se añade a
app/worker/scheduler sin cambiar la conexión de cola existente ni FluentCRM.

## Validación y operación

`bin/jakawi-oidc-test php vendor/bin/phpunit tests/Feature/Commercial` valida
transacciones, atribución ausente/real, PIN, firma, backoff, duplicados y conflictos
en PostgreSQL desechable. Requiere el servicio interno `jakawi-oidc-db-test` sano.

`bin/jakawi-commercial-e2e` usa el emisor real, HTTPS/TLS, HMAC y código CLUB montado
solo lectura. Crea dos bases desechables en ese PostgreSQL aislado y las elimina al
terminar. No lee secretos LIVE ni tiene acceso de red a producción. Prueba canje
con PIN → 201 → duplicado 200 → recuperación concurrente → ruta administrativa
protegida y filtros de atribución de CLUB. Los fixtures nunca llegan a LIVE.

Panel real: `https://jakawi.club/admin/conversiones-com`. El resultado aislado
no acredita una conversión LIVE. Al iniciar esta fase no había un canje confirmado,
reserva, transacción o lead con atribución a esta landing que pudiera enviarse
honestamente. La primera entrega real requiere un canje auténtico posterior.

Operación: consultar `status`, `attempts`, `last_http_status`, `last_error_code`,
`next_attempt_at` y `sent_at` del outbox, sin publicar secretos ni cuerpos completos.
Ante `DEAD` corregir la causa y revisar el recibo CLUB antes de reencolar el mismo
evento; un conflicto no autoriza sustituir ID o contenido.

La publicación recrea exclusivamente app/web/worker/scheduler de COM y aplica
solo `2026_10_30_040000_create_commercial_outbox`. Backup privado y controlador
en `/root/jakawi-commercial-v1/evidence/`; controlador vigente copiado a
`/opt/sites/jakawi.com/release-control.py`. El rollback restaura imágenes/configuración
previas y conserva ambas tablas, procedencia, recibos y clave. No ejecutar rollback
de migraciones ni restaurar dumps sobre conversiones/identidades emitidas.

No se crean comisiones, cuentas CLUB, vínculos de identidad ni trabajos CRM.
