# COM — Fase 10A: Partners y separación de afiliación

COM conserva establecimientos, sucursales, permisos, catálogo, operación,
pagos y activación de membresías. CLUB conserva captación y economía de afiliación.
No cambia ningún contrato CLUB, feed, identidad, checkout o outbox.

## Solicitud aprobada → establecimiento → owner

1. El público usa `/partners/aplicar` y el formulario de ciudad existente.
2. Admin revisa `/admin/solicitudes-partner/{id}` y aprueba la solicitud.
3. La acción **Crear Partner en borrador** copia negocio/categoría y contacto.
   El nombre y slug vienen prellenados y se pueden corregir. La solicitud original,
   su ciudad, mensaje, consentimiento y atribución se conservan. No se inventa una
   sucursal a partir de la ciudad de interés. La administración existente completa
   el establecimiento/sucursales y publica cuando esté preparado.
4. `partner_applications.partner_id` (nullable, unique, FK restrict) registra una
   sola conversión. La fila se bloquea dentro de una transacción: repetir la acción
   devuelve el mismo Partner. No se crean accesos por email, por `user_id` de la
   solicitud ni por el responsable comercial de `ownership_assignments`.
5. Admin busca una cuenta COM existente, selecciona su ID y confirma que comprobó
   autorización para representar al establecimiento, con motivo obligatorio.
   Se añade `owner` sin retirar otros accesos. La auditoría registra administrador,
   solicitud, Partner, usuario, rol anterior y motivo; repetir el mismo vínculo
   no genera otra concesión. La solicitud pública y cuentas no administradoras
   no pueden ejecutar estas acciones.
6. El titular usa `/partner/login`, Fortify/SSO existentes y sus credenciales propias.
   Si no tiene cuenta, usa el registro COM existente y luego Admin la vincula.
   Este flujo no crea contraseñas, usuarios de custodia, enlaces mágicos ni otro
   registro público. No cambia `email_verified_at`, credenciales ni identidad global.

**Correo saliente:** LIVE usa `mail.default=log`: no entrega correo a destinatarios.
No se implementa envío de invitaciones; el vínculo administrativo explícito a una
cuenta existente queda disponible. No publicar enlaces de verificación extraídos
de logs ni marcar manualmente cuentas como verificadas.

**Corrección de la auditoría previa:** aunque las rutas conservan middleware
`verified`, el modelo `User` actual no implementa `MustVerifyEmail`; ese middleware
no impone hoy verificación. Esta fase conserva el comportamiento de autenticación
existente para no bloquear accesos legítimos. Una futura obligatoriedad de
verificación debe tratarse junto con correo saliente; no es un requisito nuevo
ni se simula en este onboarding.

## Roles y estados

- `published`: owner/manager editan contenido y usan las operaciones existentes;
  staff conserva canjes PIN/QR, reservas y asistencia según permisos existentes.
- `draft`: owner/manager preparan y envían contenido por el flujo editorial;
  no valida canjes ni confirma reservas/asistencias como establecimiento activo.
- `paused`/`archived`: consulta de información/histórico; no mutaciones del portal
  ni operaciones comerciales. Mantener lectura no republica la ficha.
- Staff no crea, edita ni envía contenido. Una ruta futura de personal que use
  el middleware Partner tampoco admite escrituras staff por defecto.
- No se añade una interfaz de personal ni se modifican los tres vínculos LIVE.

## Economía y preservación

La venta manual mantiene compra, conversión, membresía, atribución y auditoría;
solo admite el candidato operativo MEMBER/JP existente. Un origen Partner no
se degrada a empleado/miembro. `RewardBeneficiaryResolver` no resuelve Partners
por membresía y `RewardResolver` rechaza directamente ese devengo y sus reglas,
aunque haya reglas antiguas. Admin no puede crear reglas Partner de ese evento.

El portal no expone métricas de afiliación, link de captación, saldo ni solicitudes
de payout. Se retira su POST. El servicio compartido de payout no reserva nuevas
recompensas de captación de membresía, pero mantiene los métodos para tratar
obligaciones históricas y consumidores operativos. No se borran tablas, reglas,
conversiones, ledger JP, recompensas, pagos, atribución ni referencias.

Las redirecciones legacy hacia CLUB se conservan. No se implementa afiliación
Partner ni se modifica el ledger de CLUB en esta fase. El checkout bancario,
compras CLUB, eventos `membership.activated`/`membership.identity_linked`, HMAC,
reintentos y feed `/api/catalog/v1/offers` conservan sus implementaciones.
