# Experiences V2 — contrato y diagnóstico (2026-10-14)

## Auditoría previa a modificaciones

Experience 1: published, published_at 2026-10-14, Partner 3 published, Landing 5 DRAFT/default_scope NONE, cero sesiones; WhatsApp seleccionado sin número. Precios 75/60 BOB; duración 90. Ningún dato se corrige automáticamente.

Explorar usa ExperienceDiscoveryQuery: published + sesión scheduled futura + location en ciudad seleccionada. Partner usa upcoming: published + sesión scheduled futura. Landing requiere PUBLISHED; middleware deriva a landing según default_scope GUESTS/ALL y si no existe usa ficha nativa. DRAFT de landing no es causa del filtrado del catálogo. published_at, review_status y PRELAUNCH_MODE no filtran esas consultas; prelaunch cambia Home, aviso y SEO. La ficha nativa permitía cualquier Experience no draft, incluso sin operación.

regular_price y member_price son columnas decimales opcionales de presentación. No representan un precio externo acreditado; no hay checkout Experience ni importe/precio congelado en reserva. El precio nulo no genera descuento, cálculo ni fallback. hasActiveMembership comprueba status active y starts_at <= now <= ends_at. Reserva jakawi exige membresía activa y cuenta verificada contextual, Experience published, sesión futura scheduled, Partner publicado y asociado. Externo no exige membresía ni cuenta y no verifica descuento, asistencia, vigencia del copy o límite por persona. Duración sólo presentación; no genera intervalos ni capacidad. Capacidad de sesión se presenta, pero reserva no limita cupos automáticamente: debe bloquear nuevas activaciones internas hasta resolver capacidad. Check-in interno controla reservas confirmadas, no cobros.

Copy Toc Toc: terms dice Bs 60 general/sin membresía; structured dice 75/60. Landing final_cta_description dice Bs 75 general. Descripción propone explicación 15–20 minutos y capacidad 8, sin confirmar; no contradice necesariamente duración total 90. Preservar copy; señalar discrepancias. Las restricciones escritas no se transforman en automatismos.

## Diseño mínimo antes de cambiar schema

Se mantienen status (draft/published/paused/archived), sesiones, métodos, membresías, landings y dos precios. No se añade precio habitual externo ni sistema de pagos.

Extensión aditiva: catalog_visibility (legacy/hidden/upcoming/active) y operations_confirmed boolean. legacy conserva descubrimiento previo con sesiones; no autoriza propuestas sin sesiones. La base y los nuevos formularios empiezan hidden. La migración inicializa únicamente el control nuevo de registros existentes no draft como legacy; los drafts quedan hidden, sin alterar ningún campo comercial anterior. upcoming permite ficha informativa sin sesiones, requiere published y Partner público; ciudad se deriva de sedes publicadas del Partner, no de una sesión ficticia. hidden siempre privado, incluso landing publicada. active exige confirmación explícita y requisitos reales; un cambio editorial nunca confirma operación. paused/archived suspenden acciones. Legacy conserva comportamiento operativo existente con canal válido y sesión, pero no habilita propuestas nuevas.

La disponibilidad se resuelve centralmente y se aplica a ficha, landing y endpoints de reservas. La ficha nativa es un destino informativo real; DRAFT de landing nunca se enlaza públicamente. Preview Admin independiente con autorización del grupo Admin. Catálogo próximo informa En preparación y no entrega sesiones/canales operativos.

Precios UI: sin membresía (sólo aplicable al flujo externo); con membresía opcional. Sin precio de miembros = sin trato preferencial. No se promete precio de miembros externo como beneficio verificado: elegibilidad no implementada. No se modifica el requisito de membresía interno sin decisión comercial. Nuevas activaciones con descuento externo se bloquean por falta de verificación. Nuevas activaciones internas con capacidad definida se bloquean por falta de control automático de cupos. Preparar/guardar continúa posible. Activación requiere copy coherente; detección monetaria por contexto Bs/BOB, no todos los números (duración/fechas no son precios).

Rollback: volver a imágenes previas conservando columnas aditivas; down sólo bajo recuperación explícita tras exportar configuración nueva. No cambia valores/relaciones/landings/sesiones de producción. La decisión de mostrar Toc Toc queda en Admin.

## Decisión confirmada por el usuario

Conservar reservas internas y bloquear activación de descuento externo hasta verificar elegibilidad. El precio 60 es preferencial previsto, condicionado a futura activación y verificación; no descuento vigente. Landing pública informativa permitida sin activar operación. Visibilidad de Toc Toc no se autoriza automáticamente: se elige en Admin. Migración no cambia sus valores ni su landing.

## Desarrollo posterior

Verificación de membresías para Benefits y Experiences con pago directo al Partner: definir prueba de elegibilidad, momento de verificación, trazabilidad de tarifa aceptada, revocación/expiración y controles de entrega/límites. No ampliar en esta entrega pagos, reservas ni canjes. La reserva interna actual y su requisito de membresía se mantienen.


## Entrega verificada

Código LIVE effd76e. [Informe, capturas y pruebas](../../artifacts/experiences-v2/REPORT.md). 87 pruebas críticas finales pasan; comparación global conserva 10 fallos anteriores. PRELAUNCH_MODE=true y datos comerciales de Toc Toc preservados.
