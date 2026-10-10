# Glosario canónico JAKAWI

**CURRENT / AUTHORITATIVE.** Para producto, comercial, operaciones, soporte y developers. Define términos; estado de producción en [CURRENT](CURRENT.md).

| Término | Definición y límites | Fuente |
| --- | --- | --- |
| Benefit | Oferta de valor de un Partner, canjeable en Locations autorizadas bajo condiciones. No es un canje ni ahorro confirmado. | [Referencia](operations/MANUAL_DE_INVENTARIO.md) |
| Experience | Actividad local con condiciones y sesiones. No garantiza disponibilidad externa en tiempo real. | [Referencia](operations/MANUAL_DE_INVENTARIO.md) |
| Experience Session | Fecha, horario y lugar de una Experience; upcoming se calcula con estado scheduled y fecha. No es una reserva. | [Referencia](operations/MANUAL_DE_INVENTARIO.md) |
| Unlock | Oferta de demanda colectiva que progresa por compromisos y condiciones de meta. No es Benefit, Experience ni Campaign. | [Referencia](UNLOCKS.md) |
| Challenge | Acción verificable con participación, calificación, selección y premio separados. Participar no implica ganar. | [Referencia](operations/MANUAL_DE_INVENTARIO.md) |
| Membership | Registro de acceso de membresía. Activa exige status active y starts_at ≤ ahora ≤ ends_at; una solicitud no activa acceso. | [Referencia](CURRENT.md) |
| Member | Usuario con Membership activa y vigente. No es un rol RBAC independiente. | [Referencia](product/MANUAL_DE_FUNCIONES.md) |
| Partner Applicant | Solicitante comercial pendiente del proceso de revisión. El tag partner-applicant no confirma un Partner. | [Referencia](CRM_FOUNDATION_V1.md) |
| Partner | Entidad de oferta local asociada a Locations y productos. No es un rol User; el acceso de su equipo usa partner_user. | [Referencia](ARCHITECTURE.md) |
| Program Application | Solicitud autenticada para afiliados, creadores o promotores. No equivale a inscripción/aprobación del programa. | [Referencia](product/MANUAL_DE_FUNCIONES.md) |
| Product Detail | Detalle nativo que expresa disponibilidad, reglas y acciones del producto: verdad operativa. No es Marketing Landing. | [Referencia](ARCHITECTURE.md) |
| Marketing Landing | Presentación de adquisición que dirige al producto o acción válida. No cambia economía, elegibilidad, cupo ni plazos. | [Referencia](ARCHITECTURE.md) |
| LandingPresentation | Objeto configurable de presentación para Benefit, Experience, Unlock o Challenge; publicación y default_scope son independientes. No reemplaza el dominio del subject. | [Referencia](operations/MANUAL_DE_INVENTARIO.md) |
| Campaign | Entidad económica administrada que puede aplicar reglas a membership_purchased cuando corresponde. Campaign ≠ utm_campaign y Campaign ≠ campaign_key. | [Referencia](CAMPAIGNS.md) |
| utm_campaign | Etiqueta UTM de tráfico, evidencia cruda. Sólo un código de Campaign activa aplicable puede resolver contexto económico; la etiqueta no es la entidad. | [Referencia](CAMPAIGNS.md) |
| campaign_key | Clave controlada de adquisición de LandingPresentation. No activa Campaign económica ni acepta un query arbitrario como override. | [Referencia](growth-measurement-v1.md) |
| AttributionTouch | Registro de señal de adquisición y contexto de atribución; fuente de snapshots. No es Conversion ni reward. | [Referencia](ATTRIBUTION.md) |
| acquisition_provider | Clasificación explícita NONE/META/TIKTOK/GOOGLE del contexto adquirido; el resolver de nuevos touches interpreta acq reconocido. UTM sola no implica proveedor pagado ni consentimiento. | [Referencia](growth-measurement-v1.md) |
| NONE | Valor de acquisition_provider sin proveedor reconocido. No significa ausencia de UTMs o de contexto de email. No confundir con default_scope NONE. | [Referencia](growth-measurement-v1.md) |
| META | Valor de acquisition_provider para Meta. No prueba que Pixel/CAPI estén habilitados. | [Referencia](growth-measurement-v1.md) |
| TIKTOK | Valor de acquisition_provider para TikTok. No acredita una integración de envío de eventos a TikTok. | [Referencia](growth-measurement-v1.md) |
| GOOGLE | Valor de acquisition_provider para Google. No acredita una integración de envío de eventos a Google. | [Referencia](growth-measurement-v1.md) |
| Conversion | Registro económico de conversión, incluido membership_purchased en el alcance actual. No equivale a cualquier evento Growth ni a una solicitud de compra. | [Referencia](CONVERSION.md) |
| AnalyticsEvent | Registro en analytics_events con evento/contexto; conserva historia analítica de JAKAWI. No es delivery CRM. | [Referencia](growth-measurement-v1.md) |
| Growth event | Uno de los nueve hitos canónicos de adquisición, intención y resultado, escrito en AnalyticsEvent. No crea por sí solo Conversion o reward. | [Referencia](growth-measurement-v1.md) |
| CRM projection | Proyección de identidad, lifecycle, atribución y segmentos a contactos externos. FluentCRM ≠ analytics warehouse; JAKAWI conserva la verdad del dominio. | [Referencia](CRM_FOUNDATION_V1.md) |
| crm_delivery | Entrega individual del outbox crm_deliveries, con event_id, payload cifrado, estado e intentos. No es la acción comercial ni un job social. | [Referencia](CRM_FOUNDATION_V1.md) |
| crm_contact_link | Vínculo durable en crm_contact_links entre proveedor, usuario y contacto externo. No convierte al CRM en identidad autoritativa del producto. | [Referencia](CRM_FOUNDATION_V1.md) |
| JP | Unidad entera de recompensa de JAKAWI. No es dinero, BOB ni saldo convertible a efectivo. | [Referencia](CURRENT.md) |
| RewardTransaction | Ledger canónico de recompensas; RewardTransaction con reward_type=JP es el único ledger contable JP. CASH y JP se mantienen separados. | [Referencia](CURRENT.md) |
| JpHold | Reserva JP de compromiso: HELD/RELEASED/FORFEITED. JpHold ≠ ledger contable; el disponible descuenta holds HELD del ledger. | [Referencia](CURRENT.md) |
| Redemption | Canje de Benefit con código/QR temporal y validación Partner. Sólo confirmed confirma ahorro; pending no es éxito. | [Referencia](product/MANUAL_DE_FUNCIONES.md) |
| Reservation | Reserva interna ExperienceReservation con estado propio. Un click a WhatsApp/URL externa es intención, no reserva interna confirmada. | [Referencia](product/MANUAL_DE_FUNCIONES.md) |
| Challenge Participation | Registro de participación en Challenge sujeto a elegibilidad/evidencia. No equivale a calificación, selección ni entrega de premio. | [Referencia](operations/MANUAL_DE_INVENTARIO.md) |
| Unlock Commit | Compromiso COMMITTED en UnlockParticipation que progresa la meta y puede reservar JP. INTERESTED no progresa; commit no es fulfillment. | [Referencia](UNLOCKS.md) |
| Mi JAKAWI | Superficie autenticada /mi-jakawi para estado y actividad del usuario. Entrar no exige ser Member ni activa membresía. | [Referencia](product/MANUAL_DE_FUNCIONES.md) |
| City | Contexto territorial y estado de ciudad para disponibilidad, selección e interés/aplicaciones. No equivale a una Location del Partner. | [Referencia](cities-v1.md) |
| Discovery | Exploración agregada de oferta disponible por ciudad y reglas del dominio. No es una tabla de inventario ni un permiso de publicación. | [Referencia](product/MANUAL_DE_FUNCIONES.md) |
