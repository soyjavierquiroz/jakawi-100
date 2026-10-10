# Recuperación operativa — índice de incidentes

**CURRENT / AUTHORITATIVE para triage y recuperación por síntoma.** Extiende [OPERATIONS](../OPERATIONS.md), que conserva los procedimientos técnicos de backup/deploy/rollback/disco, y [CRM](../CRM_FOUNDATION_V1.md), que conserva contrato y operación CRM. No duplica esa autoridad ni autoriza mutaciones por sí solo. Estado de producción: [CURRENT](../CURRENT.md).

Registrar hora, síntoma, alcance, responsable y evidencia sanitizada. Lecturas primero; cambios/requeue/envíos sólo bajo autorización operativa aplicable. Nunca usar ni inspeccionar PHP del host: Laravel usa Docker PHP 8.4.

## A. CRM delivery DEAD

**SYMPTOM:** Una fila crm_deliveries alcanza DEAD.

**CHECK:** Revisar ID, estado, attempts y error sanitizado; diagnostics del bridge y configuración efectiva sin payload/secret. Distinguir error permanente de presupuesto agotado.

**ACTION:** Corregir causa raíz. Con autorización operativa, usar una sola fila elegible: `docker compose exec app php artisan crm:requeue <deliveryId>` (contrato `crm:requeue {deliveryId}`). Sólo DEAD/RETRY pasan a RETRY con next_attempt_at=ahora; conserva event_id, payload cifrado, source, attempts e historia. No reinicia presupuesto ni despacha ni habilita sync.

**VALIDATE:** Comprobar RETRY y siguiente intento del scheduler, respuesta sanitizada y SENT/vínculo si tuvo éxito. Si presupuesto sigue agotado, escalar; no resetear attempts.

**DO NOT:** No repetir compra/canje para reparar CRM; no editar UUID/payload ni borrar intentos. No requeue masivo.

## B. CRM bridge 401

**SYMPTOM:** Entrega rechazada por autenticación.

**CHECK:** Confirmar presencia y configuración del shared secret en ambos extremos, reloj/timestamp y configuración efectiva; ventana HMAC ±300s. Nunca mostrar valor, firma ni fingerprint.

**ACTION:** Corregir configuración/desfase por procedimiento autorizado, preservando identidad de la entrega. Tras resolver, recuperar la fila según A.

**VALIDATE:** Entrega firmada obtiene respuesta válida; observar estado y diagnostics sanitizados.

**DO NOT:** Nunca imprimir secret, copiar .env ni pegar headers de firma. No deshabilitar HMAC ni ampliar ventana improvisadamente.

## C. CRM bridge 404

**SYMPTOM:** No se encuentra el endpoint del bridge.

**CHECK:** Contrastar plugin habilitado/ruta registrada con URL exacta aceptada. Canónico: https://crm.jakawi.com/wp-json/jakawi-fluentcrm/v1/events. Fallback de producción documentado: https://crm.jakawi.com/?rest_route=/jakawi-fluentcrm/v1/events. Un GET no demuestra el contrato POST.

**ACTION:** Si el problema es el routing pretty, usar el fallback exacto por configuración autorizada siguiendo OPERATIONS; después recuperar entrega elegible. Separar routing del servidor y ruta del plugin.

**VALIDATE:** Comprobar endpoint efectivo y POST firmado mediante entrega autorizada, sin crear contactos de prueba ni seguir redirects.

**DO NOT:** No modificar inmediatamente rewrites CyberPanel; no aceptar hosts, queries extra o variantes arbitrarias.

## D. FluentCRM cron detenido

**SYMPTOM:** Timestamps del scheduler no avanzan o backlog no progresa.

**CHECK:** Revisar entrada de cron, log wordpress-cron, resultados HTTP, timestamps FluentCRM entre observaciones, locks/procesos y backlog. Consultar contrato operativo en CRM; HTTP exitoso solo no prueba email procesado.

**ACTION:** Corregir causa de cron/red/config por operación autorizada. Investigar lock/proceso antes de intervenir. Mantener cron WordPress y scheduler Laravel separados.

**VALIDATE:** Confirmar ejecución periódica, avance de timestamps y tendencia del backlog sin envíos de prueba.

**DO NOT:** No borrar locks sin diagnóstico, habilitar page-load/alternate cron ni publicar/enrolar automations para probar cron.

## E. Backlog CRM email inesperado

**SYMPTOM:** Email pendiente o envíos crecen fuera de lo esperado.

**CHECK:** Revisar tendencia, scheduler, errores, automation/campaña de origen y alcance con responsables CRM, usando datos agregados.

**ACTION:** Pausar el despacho afectado por controles soportados y bajo autorización; investigar antes de mutar, registrar alcance y plan de recuperación. Esta guía no prescribe un comando de pausa inexistente.

**VALIDATE:** Origen explicado y plan aprobado; reanudación controlada con backlog y resultados observables.

**DO NOT:** No mass-dispatch, reenvío, enrollment o publicación a ciegas. No extraer contactos/cuerpos a documentación.

## F. Presión de disco Docker

**SYMPTOM:** Falta de espacio o fallos de escritura/build.

**CHECK:** Leer `df -h /` y uso de artefactos Docker; identificar imágenes y contenedores que las usan, incluidos detenidos.

**ACTION:** Detener nuevas operaciones consumidoras de espacio. Aplicar limpieza autorizada de artefacto exacto sólo si cero contenedores lo usan, según OPERATIONS; escalar si no hay candidato seguro.

**VALIDATE:** Revisar espacio y salud de servicios sin eliminar evidencia ni datos. No fijar cifras volátiles en docs.

**DO NOT:** No borrar manualmente containerd, blind docker prune, imágenes activas ni volúmenes productivos; no down -v.

## G. failed_jobs aumenta

**SYMPTOM:** Nuevos fallos de cola o crecimiento del total.

**CHECK:** Comparar IDs/fechas/colas con baseline conocido de 3 fallos históricos suministrado para V1.1; ese total no prueba estado actual. Distinguir nuevos fallos de los históricos y del baseline CampaignTest de tests. Revisar errores sanitizados y social-worker.

**ACTION:** Diagnosticar causa, preservar evidencia y escalar/reintentar sólo el trabajo identificado con autorización y procedimiento soportado. No añadir comandos de recuperación no establecidos.

**VALIDATE:** No aparecen fallos nuevos y el trabajo afectado progresa; registrar IDs/fechas y resultado sin payload/PII.

**DO NOT:** No borrar failed_jobs ni resetear colas para ocultar fallos antes de diagnóstico. No interpretar tres históricos como tolerancia a tres nuevos.

## H. Fallo de backup productivo

**SYMPTOM:** Dump vacío, formato inválido o backup requerido ausente.

**CHECK:** Revisar servicio DB real, permisos, espacio, resultado pg_dump y `pg_restore --list` según OPERATIONS; no restaurar para probar sobre producción.

**ACTION:** Resolver causa y repetir el backup aprobado; conservar el fallo como evidencia y obtener archivo válido antes del release/migración cuando backup sea requerido.

**VALIDATE:** Archivo no vacío, formato/listado correcto, ruta y tamaño registrados; checksum recomendado y restauración conocida conforme al procedimiento.

**DO NOT:** No desplegar/migrar sin backup válido cuando es requerido; no dump del host ni restauración destructiva de prueba.

## I. Fallo de salud tras despliegue

**SYMPTOM:** /up falla o servicios/rutas no responden después del release.

**CHECK:** Consultar `docker compose ps`, health local/público /up, logs sanitizados de app/web/db/social-worker y scheduler (cron/wrapper, no servicio Compose separado).

**ACTION:** Contener el release y evaluar retorno al último commit bueno según OPERATIONS. Reconstruir/recrear coherentemente servicios afectados, app/web juntos; evaluar compatibilidad de migraciones y backup antes de revertir. No se ejecuta deploy en V1.1.

**VALIDATE:** Health local/público, rutas críticas, DB, worker y scheduler recuperados; mismo release compatible en app/web.

**DO NOT:** No inventar rollback automatizado; no borrar volúmenes/datos ni revertir migraciones ciegamente.

## J. Problema de proveedor Meta

**SYMPTOM:** No llegan eventos externos Meta.

**CHECK:** Consultar CURRENT y flags efectivos: Meta actualmente OFF intencionalmente. Diferenciar AnalyticsEvent canónico de growth_provider_deliveries/browser/CAPI.

**ACTION:** Mientras OFF, verificar sólo hechos canónicos si existe incidente de producto. Si se habilita en un trabajo futuro autorizado, seguir Meta Provider V1 para configuración/diagnóstico.

**VALIDATE:** Con OFF no se exige entrega externa; eventos canónicos conservan resultados de producto.

**DO NOT:** No tratar ausencia de eventos Meta como fallo de runtime mientras está deshabilitado; no activar flags, enviar ni backfill para esta revisión.

Referencias: [gate piloto](PILOT_READINESS.md), [inventario](MANUAL_DE_INVENTARIO.md), [Meta Provider V1](../../app/docs/meta-provider-v1.md).
