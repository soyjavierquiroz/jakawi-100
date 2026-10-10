# Manual de Funciones JAKAWI

**CURRENT / AUTHORITATIVE — 2026-10-09.** Para producto, operaciones, soporte, equipo comercial y desarrolladores. Referencia funcional, no contrato API. Estado productivo y límites técnicos: [CURRENT](../CURRENT.md); arquitectura: [ARCHITECTURE](../ARCHITECTURE.md); carga de oferta: [Manual de Inventario](../operations/MANUAL_DE_INVENTARIO.md).

## A. Qué es JAKAWI

**La app para vivir más tu ciudad.** JAKAWI presenta oferta local curada y permite convertir descubrimiento en valor mediante canjes, experiencias, participación y membresía.

**DESCUBRIR → DESEAR → ACCEDER / ACTUAR → RECIBIR VALOR → VOLVER.** Ver una oferta no equivale a recibirla; el canje confirmado, la acción de dominio o la entrega correspondiente son los hechos operativos.

## B. Actores y acceso real

| Actor | Acceso y límites |
| --- | --- |
| Guest / visitante | Descubre oferta, landings y ciudades; puede iniciar registro o solicitud de Partner donde se admite. No ejecuta acciones que requieren cuenta |
| User / usuario registrado | Perfil y Mi JAKAWI; solicita membresía; participa según elegibilidad específica del objeto |
| Active Member | Membership active dentro de su vigencia; accede a beneficios normales y acciones reservadas a miembros según reglas |
| Admin | users.is_admin; administra las funciones expuestas en el panel, con reglas/auditoría de cada acción |
| Partner applicant | Estado de solicitud comercial, no permiso Partner ni membresía |
| Partner | Entidad comercial, no rol de User; posee Locations y oferta |
| Partner User | Asignación partner_user owner/manager/staff; sólo opera el Partner asignado. Ser Admin no asigna automáticamente ese acceso |
| Promoter / Affiliate / Creator | ProgramEnrollment del User, pueden coexistir; permisos específicos de programa, no nuevos roles globales |

Durante piloto no hay autorización por Location: un usuario autorizado del Partner puede validar una Location de ese Partner si conoce su PIN. No ofrecer un portal comercial abierto ni permisos no implementados.

## C. Módulos funcionales

Cada fila indica propósito, usuario, acciones, estados y reglas principales. Los estados detallados de oferta están en el Manual de Inventario.

| Módulo | Propósito / quién | Acciones principales | Estados y reglas |
| --- | --- | --- | --- |
| Inicio | Descubrimiento inicial; Guest/User/Member | Ver oportunidades de la ciudad y abrir su destino | Oferta disponible por dominio; no garantiza cupo ni derecho adquirido |
| Explorar | Encontrar oferta; todos | Buscar y filtrar oportunidades por tipo/categoría cuando corresponde | Cerca es una faceta; Unlock/Challenge no reciben una categoría inventada |
| Mi JAKAWI | Volver al valor propio; User/Member | Consultar membresía, ahorro confirmado, JP, actividad y participaciones | Separa cuenta gratuita y miembro; ahorro sólo confirmado, JP separado de BOB |
| Perfil | Mantener cuenta; User | Editar identidad, ciudad, preferencias y opción de email | Consentimiento explícito; un NULL previo no implica aceptación |
| Membership | Acceder a valor de miembro; User/Admin/Promoter autorizado | Ver oferta, solicitar, registrar compra manual o grant, activar/cancelar | Solicitud REQUESTED/COMPLETED/CANCELLED; Membership active/cancelled; active depende de fechas; solicitar no activa |
| Benefit | Oferta del Partner; Member o usuario con grant de reto válido | Ver condiciones, elegir Location, generar código/QR y validar | Publicación/vigencia/Location/límites; acceso normal exige Membership; social_challenge_grant exige grant específico |
| Experience | Actividad local; todos y equipo Partner | Ver sesiones y abrir reserva externa; operaciones internas donde el backend las admite | Política de piloto: reserva externa; código también soporta jakawi con Member/sesión/Partner válidos, sin límite capacity aplicado; sin pago interno ni disponibilidad externa en tiempo real |
| Unlock | Demanda colectiva; User/Member elegible y Partner/Admin | Interés, compromiso, confirmación, cancelación y fulfillment | INTERESTED no suma; COMMITTED suma y puede reservar JP; fechas/cupo/eligibilidad mandan |
| Challenge | Acción con evidencia y premio; User/Member elegible y Partner/Admin | Participar, enviar evidencia, consultar resultado; revisar/seleccionar/entregar | Participación, calificación, selección y recompensa son hechos distintos; participar o calificarse no asegura premio |
| Cities | Descubrimiento local y expansión; todos/Admin | Seleccionar ciudad activa; interés y aplicación comercial donde corresponde | ACTIVE; UNLOCKING/COMING_SOON/PREPARING admiten interés/aplicaciones; PAUSED no |
| Discovery | Agregar oferta útil; todos | Abrir Benefits, Experiences, Unlocks y Challenges por ciudad | Lectura, ranking, diversidad y afinidad limitados ya existen; no altera reglas de producto |
| Marketing Landings | Adquisición; Guest/User/Member y Admin | Abrir /l/{slug}, leer presentación y actuar sobre subject | DRAFT/PUBLISHED/ARCHIVED; scope NONE/GUESTS/ALL; nunca modifica economía ni elegibilidad |
| Partner Applications | Recibir interés comercial; solicitante/operaciones | Enviar datos y propuesta; Admin revisar solicitud | SUBMITTED/CONTACTED/QUALIFIED/APPROVED/REJECTED; no crea permisos ni confirma Partner; sólo teléfono no proyecta CRM |
| Program Applications | Solicitar participación comercial; User/Admin | Solicitar programa y revisar candidatura | SUBMITTED/CONTACTED/QUALIFIED/APPROVED/REJECTED; no equivale a ProgramEnrollment activo ni derecho a comisión |
| Referrals / acquisition | Conservar origen; visitantes, User, programas y Partner | Abrir /r/{CODE}, registrar y usar enlaces de adquisición | Primer referente válido, ventana configurada y snapshots; UTM es evidencia, no recompensa automática |
| Campaigns | Reglas económicas; Admin | Crear/editar configuración y vigencia | Campaign económica requiere reglas elegibles; campaign_key de landing no la activa |
| Rewards / JP | Registrar recompensas; beneficiario/Admin | Consultar ledger, holds y payouts CASH; Admin liberar/ajustar según reglas | RewardTransaction pending/available/paid/cancelled; JP no es dinero; JpHold HELD/RELEASED/FORFEITED |
| CRM projection | Contactos y email; operaciones CRM | Proyectar identidad, lifecycle, FIRST/LATEST y segmentos | crm_deliveries PENDING/PROCESSING/SENT/RETRY/DEAD; FluentCRM no almacena el historial analítico completo |

Partner/program application son procesos de solicitud; soporte debe consultar el registro y decisión reales, sin inferir aprobación por tags de CRM. Detalles económicos: [ATTRIBUTION](../ATTRIBUTION.md), [CONVERSION](../CONVERSION.md), [CAMPAIGNS](../CAMPAIGNS.md).

## D. Recorridos principales

1. **Guest → Register:** descubre, abre registro, envía cuenta/consentimiento; el backend asocia touches previos y registra signup_completed. Registrarse no activa Membership.
2. **User → Membership request:** abre `/membresia`, conserva intención válida de producto si existe y solicita. REQUESTED es intención, no pago. UTMs opcionales no cambian esa intención.
3. **Membership activation:** operador autorizado registra compra confirmada/manual; Admin puede conceder grant con motivo y monto cero. Membership activa por estado y vigencia; grant no cuenta como venta ni revenue. La solicitud completada permite volver a la intención original, revalidando disponibilidad.
4. **Benefit redemption:** usuario elegible abre Benefit, selecciona Location válida, recibe código/QR temporal; Partner valida con PIN. Sólo confirmed confirma ahorro. Pending puede expired/cancelled; no prometer éxito antes de confirmación.
5. **Experience reservation:** durante piloto abre WhatsApp/URL/teléfono/destino externo y coordina con Partner. Ese click no produce experience_reserved interno. El código soporta reserva interna jakawi (pending/confirmed/rejected/cancelled y check-in) con Member activo, sesión upcoming y Partner receptor publicado/asociado. No aplica capacity como límite; no inferir operación comercial de ese modo por su existencia.
6. **Challenge participation:** usuario con elegibilidad participa, aporta evidencia SOCIAL_POST o MANUAL; calificación verifica reglas, selección aplica modalidad, premio se concede sólo si corresponde. Elegibilidad de premio puede exigir Membership aunque participación no la exija.
7. **Unlock commit:** descubre `/d/{slug}`, consulta condiciones, expresa interés o se compromete. Compromiso valida cupo, elegibilidad y JP; al alcanzar meta solicita confirmación. Fulfillment libera garantía y puede generar bonus; cancelación válida libera; no-show pierde hold; expiración de confirmación es neutral para reputación.
8. **Partner application:** desde superficie de ciudad habilitada envía propuesta y consentimiento; operaciones revisa. Tag partner-applicant no convierte en jakawi-partner.
9. **Marketing landing → Product:** abre `/l/{slug}`, CTA lleva al producto o acción válida; puede requerir registro/Membership. La landing aporta adquisición sin cambiar precio, premio, cupo ni condiciones.

## E. Estados y fallos

CRM failure **no revierte la acción de dominio**. El outbox se atiende por scheduler; soporte debe distinguir acción exitosa y proyección pendiente. Nunca repetir una compra/canje para reparar un contacto.

Fallo de proveedor Meta o verificación social no puede cambiar la verdad del producto por su cuenta. Un estado pendiente o dato no verificado no debe presentarse como éxito. La verificación social usa colas y snapshots; métricas externas pueden faltar y requerir revisión. No confirmar ganadores basándose sólo en ranking provisional.

Una oferta expirada/pausada deja de ser accionable según dominio; su historial permanece. Una membresía solicitada no otorga los privilegios de una activa. JP, efectivo y ahorro confirmado son magnitudes distintas.

## F. Funciones administrativas existentes

El panel administra Partners y equipos asignados, Locations/PIN, Benefits, Experiences/sesiones y revisión de propuestas; ciudades e intereses, solicitudes Partner/programa, Memberships/solicitudes, ventas manuales/refunds, programas Promoter/Affiliate/Creator, Campaigns y reglas de recompensa, payouts manuales y ajustes/export auditados.

Para Unlocks permite revisión y operaciones de plazos/cierre/cancelación/fulfillment y reputación con límites/motivos. Para Challenges permite revisión editorial, actualización social, cierre, revisión de participación, selección y concesión/entrega/cancelación de premio según contrato. LandingPresentation permite editar, preview, publicar, archivar y elegir default. Métricas administrativas de piloto/adquisición son de lectura; Growth Analytics conserva eventos y contexto consultable, sin dashboard dedicado identificado. Ninguno activa rewards.

No existe transferencia automática de payout ni permiso implícito para que Admin opere un Partner sin asignación. Operación CRM y schema se administran en WordPress, no sustituyen el panel de producto.

## G. Limitaciones actuales

- Piloto comercial todavía no iniciado; inventario comercial real y operación Partner siguen requeridos.
- Meta Pixel+CAPI implementado pero deshabilitado; no hay evento Purchase.
- Automations consumidor 1–4 DRAFT/INACTIVE; sólo Partner Applicant V1 PUBLISHED/ACTIVE. Véase [CRM](../CRM_FOUNDATION_V1.md).
- Experiences: reserva pública externa, sin pago interno ni disponibilidad externa en tiempo real; booking interno existe en código pero no acredita operación comercial del piloto.
- Pagos QR no operativos; payouts externos manuales; JP sin conversión a dinero.
- Sin marketplace abierto, Partner self-service completo, permisos por Location, app nativa ni Desbloqueos Comerciales monetarios.

No vender funciones de [ROADMAP](../ROADMAP.md) como activas. Para preparar oferta real usar el [Manual de Inventario](../operations/MANUAL_DE_INVENTARIO.md).

## H. Matriz de actores y capacidades

YES = capacidad expuesta para ese contexto; NO = ese contexto no concede acceso; CONDITIONAL = depende de verificación, asignación, vigencia o reglas del producto. Las acciones autenticadas indicadas requieren auth + verified. Guest y Authenticated User son contextos; Active Member es estado de Membership, Admin usa users.is_admin y Partner-related actor usa partner_user (owner/manager/staff). No son roles mutuamente excluyentes. Partner Applicant es una solicitud, sin privilegio adicional: hereda Guest/User según sesión.

| Capacidad | Guest | Authenticated User | Active Member | Admin | Partner-related actor |
| --- | --- | --- | --- | --- | --- |
| Discover | YES | YES | YES | YES | YES |
| Ver Product Detail | CONDITIONAL | CONDITIONAL | CONDITIONAL | CONDITIONAL | CONDITIONAL |
| Ver Marketing Landing pública | CONDITIONAL | CONDITIONAL | CONDITIONAL | CONDITIONAL | CONDITIONAL |
| Registrar nueva cuenta | YES | NO | NO | NO | NO |
| Solicitar membresía | NO | CONDITIONAL | CONDITIONAL | CONDITIONAL | CONDITIONAL |
| Usar acceso Member | NO | CONDITIONAL | CONDITIONAL | CONDITIONAL | CONDITIONAL |
| Canjear Benefit como consumidor | NO | CONDITIONAL | CONDITIONAL | CONDITIONAL | CONDITIONAL |
| Reservar Experience internamente | NO | CONDITIONAL | CONDITIONAL | CONDITIONAL | CONDITIONAL |
| Participar Challenge | NO | CONDITIONAL | CONDITIONAL | CONDITIONAL | CONDITIONAL |
| Commit Unlock | NO | CONDITIONAL | CONDITIONAL | CONDITIONAL | CONDITIONAL |
| Ver Mi JAKAWI | NO | CONDITIONAL | CONDITIONAL | CONDITIONAL | CONDITIONAL |
| Enviar Partner Application | CONDITIONAL | CONDITIONAL | CONDITIONAL | CONDITIONAL | CONDITIONAL |
| Enviar Program Application | NO | CONDITIONAL | CONDITIONAL | CONDITIONAL | CONDITIONAL |
| Administrar inventario | NO | NO | NO | CONDITIONAL | CONDITIONAL |
| Administrar Campaigns económicas | NO | NO | NO | YES | NO |
| Administrar Challenges | NO | NO | NO | CONDITIONAL | CONDITIONAL |
| Administrar LandingPresentations | NO | NO | NO | CONDITIONAL | NO |

Partner Applicant no tiene capacidades adicionales: todas son las de Guest o Authenticated User según sesión y, sólo si existen por separado, las de Member/Admin/equipo asignado. Una aplicación no concede ninguno de esos accesos.

Notas de condiciones:

- Detalle/landing públicos requieren publicación y reglas de disponibilidad; admin preview es una superficie protegida aparte. Discovery consulta oferta elegible por ciudad, sin garantizar inventario real.
- Mi JAKAWI y solicitudes autenticadas requieren cuenta verificada; solicitar no implica compra/activación. Member requiere status active y starts_at ≤ ahora ≤ ends_at. Admin/Partner no reciben acceso Member automáticamente.
- Benefit normal requiere Member activo; un grant válido social_challenge_grant habilita su Benefit exclusivo sin Membership. Partner valida canjes de su contexto, no elude requisitos de consumidor.
- Reserva interna exige Member activo, método jakawi, Experience publicada, sesión upcoming y Partner receptor publicado/asociado. Piloto usa reserva externa; abrir su destino público no es reserva interna ni evento experience_reserved. capacity no se aplica como límite en el servicio.
- Challenge depende de elegibilidad de participación y estado/fechas; premio tiene elegibilidad independiente. Unlock depende de free_user_eligible/member_eligible, estado, cupo, condiciones y JP.
- Partner Application depende de ciudad/superficie habilitada; no concede acceso Partner. Program Application valida programa y solicitud, sin conceder enrollment automáticamente.
- Admin opera inventario/revisión/publicación bajo reglas de dominio. Partner asignado prepara/edita/envía oferta propia y Challenges con permisos/estados específicos; no tiene administración global ni publicación Admin implícita. Ser Admin no concede asignación Partner. Roles owner/manager/staff no implican permisos por Location.

Cotejo: [rutas](../../app/routes/web.php), [admin middleware](../../app/app/Http/Middleware/EnsureAdmin.php), [partner middleware](../../app/app/Http/Middleware/EnsurePartner.php), [Membership](../../app/app/Models/Membership.php), [RedemptionService](../../app/app/Services/RedemptionService.php), [ExperienceReservationService](../../app/app/Services/ExperienceReservationService.php), [UnlockParticipationService](../../app/app/Services/UnlockParticipationService.php) y controllers de solicitudes/Challenge. La matriz resume acceso existente, no crea RBAC.

Referencias de autoridad: [GLOSSARY](../GLOSSARY.md), [ARCHITECTURE](../ARCHITECTURE.md), [CURRENT](../CURRENT.md), [CRM](../CRM_FOUNDATION_V1.md), [Manual de Inventario](../operations/MANUAL_DE_INVENTARIO.md). La arquitectura detallada permanece en su fuente.
