# Manual de Inventario JAKAWI

**CURRENT / AUTHORITATIVE — 2026-10-09.** Referencia para comercial, operaciones, producto y Admin al cargar inventario REAL. Inventario es el conjunto de objetos de producto/valor publicables y disponibles para usuarios; no es un catálogo de demos ni historial de transacciones.

Arquitectura y límites: [ARCHITECTURE](../ARCHITECTURE.md), [DOMAIN](../DOMAIN.md), [CURRENT](../CURRENT.md). Uso: [Manual de Funciones](../product/MANUAL_DE_FUNCIONES.md). Esta guía distingue requisitos comerciales de campos/estados reales: un checklist operativo no crea enums ni permisos.

## 1. Benefits

Un Benefit representa valor acordado con un Partner, utilizable en Locations autorizadas y sujeto a condiciones. Antes de cargar, comercial debe confirmar negocio real, oferta, responsables, fechas, condiciones, elegibilidad y mecanismo de validación. Registrar el acuerdo por el proceso comercial; no tratar texto de demo como un compromiso del Partner.

Contenido recomendado: título, short_description, description, terms, category, benefit_type, estimated_savings, imagen válida y slug estable. Configurar Partner, starts_at/ends_at, redemption_limit_per_member, Locations o applies_to_all_locations. La ciudad se relaciona con Locations; no inventar un campo de ciudad del Benefit.

Acceso normal `public` exige Membership activa para canje; `social_challenge_grant` requiere el grant del reto correspondiente y no se ofrece como beneficio público normal. Un ahorro estimado no es ahorro confirmado. Disponibilidad requiere Benefit y Partner publicados, rango de vigencia y Location publicada del mismo Partner.

Antes de publicar: aprobar condiciones, verificar Locations/PIN operativamente sin documentar el PIN, límite y vigencia; probar contexto Guest/User/Member o grant específico, código/QR y validación Partner. Redemption pending/confirmed/expired/cancelled es historia separada del estado del Benefit. Sólo confirmed confirma ahorro; respetar TTL y límites.

## 2. Experiences

Una Experience representa actividad local con uno o varios Partners y sesiones. `experience_type` incluye `event`, `workshop`, `class`, `tour`, `tasting`, `wellness`, `outdoor`, `cultural`, `social`, `other`; elegir el tipo real, sin llamar evento a toda actividad.

Confirmar comercialmente anfitrión, organizador, lugar/ciudad, fechas, duración, precios regular/member, moneda, condiciones y contacto/destino de reserva. Contenido: título, descripción breve y completa, términos, categoría, imagen/cover y slug. Configurar sesiones con Location, starts_at/ends_at, capacity donde aplica, venue_label y Partner receptor de reserva cuando corresponda.

La política operativa del piloto usa WhatsApp, URL, teléfono o externo. El código sí admite publicar reservation_method=jakawi; no existe bloqueo técnico de ese modo en este SHA. No prometer cupos en tiempo real del proveedor ni pago interno. Las reservas internas implementadas tienen pending/confirmed/rejected/cancelled, party_size y check-in; exigen Member activo, Experience publicada, sesión upcoming y Partner receptor publicado/asociado. El servicio no hace cumplir capacity como límite de reserva: registrar capacidad no garantiza cupo. No equivalen a un click externo ni prueban adopción comercial del booking interno.

Sesión upcoming requiere scheduled y starts_at futuro/presente; al pasar la fecha ya no es upcoming aunque siga scheduled. Sesión cancelled no es disponible. Discovery exige sesiones elegibles y contexto Location/ciudad; publicar el objeto sin sesión apropiada no garantiza aparecer. Verificar disponibilidad real con Partner, y pausar/archivar oferta obsoleta conservando reservas y asistencia.

## 3. Unlocks

Un Unlock representa demanda colectiva que se habilita mediante compromisos. No sustituye un Benefit, Experience ni Campaign. Ruta nativa: **`/d/{slug}`**. Referencia del mecanismo: [UNLOCKS](../UNLOCKS.md).

Confirmar Partner, valor ofrecido, lugar/ciudad, minimum_commitments, maximum_capacity, elegibilidad `free_user_eligible`/`member_eligible`, garantía JP, condiciones de cancelación, deadlines de compromiso/confirmación y período de fulfillment. Imagen, título, descripción y slug deben expresar lo que se promete; revisar información oculta por secret_mode sin revelarla anticipadamente.

INTERESTED no progresa la meta; COMMITTED sí y puede reservar JP ajustados por reputación. Meta/confirmación no equivalen a fulfillment. JP no es dinero ni un depósito monetario. Cancelación válida libera garantía; expiración de confirmación libera y es neutral; no-show forfeits. Administrar expiración/invalidation mediante operaciones reales (cancelación, cierre, deadlines o retiro de participación), no mediante un enum INVALID inventado.

## 4. Challenges

Un Challenge representa acción verificable con reglas de participación y recompensa. Acordar propósito, instrucciones, fechas, ciudad/Partner cuando aplica, evidencia, selección, premio, responsables de revisión/entrega y elegibilidad antes de publicar.

Evidencia: `SOCIAL_POST` o `MANUAL`. Calificación: `VALID_EVIDENCE`, `METRIC_THRESHOLD`, `MANUAL`. Selección: `ALL_QUALIFIED`, `FIRST_N`, `TOP_N`, `MANUAL`. Calificar significa cumplir reglas; ganar depende de selección y límites. TOP_N requiere social, métrica, cantidad y evaluación al cierre. MANUAL exige revisión; no declarar ganador por aparecer en ranking provisional.

Premios: `BENEFIT`, `MANUAL_PRIZE`, `JP`. Benefit de premio debe pertenecer al mismo Partner y usar `social_challenge_grant`; MANUAL_PRIZE requiere descripción concreta; JP entero positivo. `participation_eligibility` y `reward_eligibility` son independientes: `ALL_USERS` o `ACTIVE_MEMBERS`. Revisar max_entries_per_user, plataformas permitidas, hashtags/menciones y ventana de publicaciones cuando se configuran.

La verificación social depende de jobs `social-interactive,social`, métricas externas y snapshots finales. Una métrica ausente puede requerir revisión; no fingir verificación ni garantía de alcance. Cierre deja de aceptar participación; no reabrir un reto cerrado/cancelado. Tras participaciones existen restricciones a cambios de reglas: crear nueva oferta cuando se necesite un contrato diferente, sin borrar la historia anterior.

## 5. Marketing Landings

`LandingPresentation` es la presentación de adquisición de un **Benefit, Experience, Unlock o Challenge**. Ruta directa **`/l/{slug}`**. `/go/{slug}` sólo sirve para redirect externo configurado; no crear allí una ruta nativa de Unlock.

Configurar contenido, imagen/CTA, slug y subject correcto. `default_scope`: `NONE` no sustituye navegación por defecto; `GUESTS` usa landing para visitantes; `ALL` la usa para todos. La ruta directa sigue su publicación y disponibilidad del subject. Archivar retira default (NONE).

La landing no cambia verdad, economía, precio, premios, elegibilidad, capacidad ni plazos del producto. Product Detail nativo debe seguir siendo válido. Publicar requiere subject publicable/publicado y Partner donde aplica; comprobar también disponibilidad real, no sólo status.

`campaign_key` es propiedad configurada de LandingPresentation cuando se usa, **no input arbitrario de URL**, y no activa Campaign económica. UTMs se preparan en enlaces externos de ads/QR/creator/email; no mezclarlas con cambios a reglas comerciales.

SEO: ALL usa canonical de landing; NONE/GUESTS usan canonical del producto y noindex. Preview es noindex y no genera adquisición. Verificar slug, canonical y contenido sin promesas contradictorias antes de publicar. Arquitectura: [ARCHITECTURE](../ARCHITECTURE.md); atribución: [Growth](../growth-measurement-v1.md).

## 6. Lifecycle operativo y estados reales

Conceptualmente **DRAFT → REVIEW → PUBLISHED → EXPIRED / UNPUBLISHED / INVALID** es una secuencia de trabajo, **no un enum común de base de datos**. Expired/unpublished/invalid pueden ser resultados de fecha, disponibilidad o decisión operativa.

| Dominio | Estados reales / disponibilidad |
| --- | --- |
| Benefit / Experience | status: draft, published, paused, archived. Revisión de propuestas: draft, submitted, approved, changes_requested, rejected. Vigencia de Benefit y sesiones de Experience determinan disponibilidad aparte |
| ExperienceSession | scheduled, cancelled; upcoming se calcula por fecha. No inventar status expired |
| Unlock | DRAFT, PENDING_REVIEW, APPROVED, SCHEDULED, ACTIVE, GOAL_REACHED, UNLOCKED, FULFILLMENT_ACTIVE, COMPLETED, GOAL_NOT_REACHED, CANCELLED, REJECTED |
| UnlockParticipation | INTERESTED, COMMITTED, UNLOCKED_PENDING_CONFIRMATION, CONFIRMED, FULFILLED, CANCELLED_ON_TIME, NO_SHOW, EXPIRED, REMOVED, WAITLISTED; no confundir con estado del objeto |
| Challenge | status draft/open/closed/cancelled; revisión DRAFT/SUBMITTED/APPROVED/CHANGES_REQUESTED/REJECTED. Público requiere APPROVED y open/closed; participar exige open y fechas válidas |
| LandingPresentation | DRAFT/PUBLISHED/ARCHIVED; default_scope NONE/GUESTS/ALL, independiente del status |

Para Challenge el cierre por fecha lo atiende `challenges:close-expired`; Unlock deadlines `unlocks:expire`. No asumir que toda expiración cambia automáticamente un status. Los estados de calificación/selección/grant son independientes de publicación.

## 7. Checklist único de calidad antes de publicar

### Comercial

- [ ] Partner/negocio real, responsable y operación verificados.
- [ ] Valor/oferta acordados y condiciones claras.
- [ ] Fechas, capacidad cuando aplica y elegibilidad de Member confirmadas.
- [ ] Mecánica de canje, reserva, acceso, cancelación y entrega explicada al Partner.

### Contenido

- [ ] Título y descripción claros; imagen válida y derechos de uso verificados por comercial.
- [ ] Ciudad y Location cuando aplica correctas.
- [ ] Fechas/horarios y CTA precisos; sin datos de prueba ni afirmaciones demo como reales.

### Producto

- [ ] Dominio correcto, slug estable, publicación/revisión válidas.
- [ ] Reglas de miembro, expiración, disponibilidad/cupo y límites coherentes.
- [ ] Términos, precio/valor y recompensa sin contradicciones entre tarjetas, detalle y acción.

### Marketing

- [ ] Landing opcional con subject y scope adecuados; Product Detail nativo sigue válido.
- [ ] campaign_key sólo si es intencional, configurado en la presentación.
- [ ] UTMs preparados externamente; nunca cambian la oferta ni activan economía.

### QA operativo

- [ ] Comportamiento Guest y User autenticado verificado.
- [ ] Active Member o grant donde corresponde verificado.
- [ ] Expirado/no disponible/sin cupo muestra resultado correcto.
- [ ] Revisión móvil a 390px: texto, imagen y CTA utilizables.
- [ ] Sin promesas demo, checkout/booking interno no activo ni premio garantizado sin selección.

Esta checklist describe verificaciones para cargar inventario; no acredita que se hayan ejecutado en esta consolidación documental.

## 8. Procedimientos de operación

**CREATE:** recopilar acuerdo real, seleccionar dominio, crear borrador y asociar Partner/Location/ciudad donde exista; cargar contenido/fechas/reglas y guardar. No publicar para completar campos después.

**REVIEW:** operaciones y comercial contrastan oferta con acuerdo, producto verifica reglas y Admin revisa propuestas por flujo real. Resolver changes_requested/rejected; registrar responsables y decisiones por las herramientas disponibles.

**PUBLISH:** completar checklist; aprobar revisión cuando corresponde y usar publicación del dominio. Revisar vista pública, acción y Discovery; después publicar landing opcional y elegir scope intencionalmente.

**UPDATE:** corregir contenido y disponibilidad con Admin/contexto Partner autorizado. Revalidar checklist; no cambiar retroactivamente reglas de participaciones existentes. Si el dominio bloquea modificación, gestionar nueva oferta o una operación auditada permitida.

**PAUSE / UNPUBLISH:** Benefit/Experience pueden pasar a paused/archived; landing a ARCHIVED. Challenge y Unlock deben usar su cierre/cancelación/operación disponible, no un estado paused ficticio. Revisar consecuencias para compromisos, holds, reservas y premios antes de actuar.

**EXPIRE:** retirar oferta comercial vencida, actualizar disponibilidad/sesiones y revisar scheduler correspondiente. Expiración de fechas no borra el objeto ni historial; comprobar también landing y enlaces compartidos.

**REMOVE FROM DISCOVERY:** modificar publicación/disponibilidad del objeto mediante operación real; revisar fechas, ciudad/Location y filtros del dominio. Quitar featured o archivar una landing no garantiza retirar un producto aún elegible. Discovery es lectura de producto, no una tabla de inventario que se limpia borrando transacciones.

**Nunca eliminar redemptions, reservations, challenge participation, unlock commitments ni reward ledger history sólo porque la oferta deja de estar disponible.** Despublicar inventario es diferente de borrar datos transaccionales. Conservar canjes, reservas, participación, compromisos, holds, rewards y auditoría; resolver obligaciones pendientes con operaciones autorizadas.

## 9. Inventario mínimo para piloto

El piloto comercial aún no comenzó. Preparación requiere Benefits reales suficientes para que Discovery sea útil; Experiences reales con disponibilidad válida; valor de membresía representativo; imágenes/contenido correctos; operación Partner verificada; flujos de canje y reserva probados.

**La cantidad comercial exacta debe decidirse por planificación de producto/comercial, no inferirse del código.** Límites de consulta de Discovery no son metas de inventario. Evitar llenar la oferta con demos para alcanzar una cifra ficticia.

Runbook técnico: [OPERATIONS](../OPERATIONS.md). Estado y automations que acompañan el piloto: [CURRENT](../CURRENT.md), [CRM](../CRM_FOUNDATION_V1.md).

## 10. Registros de trabajo y gate comercial

Este manual conserva las **reglas de operación**. Las [plantillas](templates/README.md) son registros/checklists **individuales** para [Benefit](templates/BENEFIT.md), [Experience](templates/EXPERIENCE.md), [Unlock](templates/UNLOCK.md), [Challenge](templates/CHALLENGE.md) y [Partner](templates/PARTNER.md); no schemas de código. [PILOT_READINESS](PILOT_READINESS.md) es el **gate GO / NO-GO** del lanzamiento, sin cantidades inventadas ni readiness acreditada por llenar una plantilla.

Términos: [GLOSSARY](../GLOSSARY.md). Límites de producto/presentación: [ARCHITECTURE](../ARCHITECTURE.md) y [ADRs](../decisions/README.md). Adquisición: [Growth](../growth-measurement-v1.md). Recuperación por síntoma: [RUNBOOKS](RUNBOOKS.md). No copiar registros comerciales reales con datos personales ni PIN al repositorio.
