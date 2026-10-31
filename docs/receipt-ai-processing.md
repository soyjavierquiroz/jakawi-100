# Comprobantes de membresía: lectura privada

El comprador autenticado solo envía un JPG/PNG/PDF (máximo 10 MB). No se aceptan datos transaccionales del navegador. COM recupera su intención abierta bajo bloqueo del usuario y guarda el archivo original en `minio-media` con visibilidad **private**, prefijo `memberships/receipts`, nombre aleatorio y SHA-256. La intención y su InitiateCheckout se conservan. No se crea otro pago. Los comprobantes anteriores en el disco privado local siguen disponibles para compatibilidad; no había comprobantes almacenados en LIVE al preparar esta implementación.

La misma transacción cambia a `under_review`, reinicia la extracción y encola un job en la conexión database existente, cola `membership-receipts`. El payload del job contiene únicamente ID y versión de extracción. El worker COM consume esta cola junto a las existentes. El cliente recibe confirmación inmediata, sin esperar a IA. Recargar la página no encola ni consulta al proveedor. Una nueva presentación invalida los resultados de versiones anteriores. La corrección manual nunca es sobrescrita por un resultado en vuelo.

## Configuración privada pendiente

`RECEIPT_AI_API_KEY` (o `OPENAI_API_KEY` existente) configura OpenAI. `RECEIPT_AI_MODEL` es opcional, por defecto `gpt-4.1-mini`. Configurar la clave únicamente en el entorno privado COM, recrear los cuatro servicios con ese entorno y refrescar config cache. Nunca poner claves en variables VITE ni frontend. Una vez configurada, probar con un comprobante ficticio aislado y sin activar membresías LIVE. La integración y sus contratos están probados con respuestas simuladas; **sin API key no se declara lectura real operativa**.

Si falta clave, el job termina como `unconfigured`, conserva el archivo y mantiene `under_review`. El administrador puede corregir/transcribir los datos y conciliar manualmente o reintentar explícitamente tras configurar IA. Los errores transitorios tienen hasta tres intentos con backoff; timeout HTTP 45 s, job 65 s, lease 90 s y retry_after de la cola database 90 s. Solo se registran códigos seguros; nunca documentos, base64, respuestas del proveedor ni excepciones HTTP completas.

## Tratamiento externo

POST fijo a `https://api.openai.com/v1/responses`, sin redirecciones, con `store=false`, sin conversaciones, herramientas ni Files API. Se transmite solo el comprobante como data URI (imagen o PDF), instrucciones de transcripción y un esquema JSON estricto. No se adjuntan cuenta COM, correo, global_subject_id, referencia comercial, extractos bancarios ni información de otros usuarios. El documento es entrada no confiable: sus instrucciones deben ignorarse y los campos ilegibles/ambiguos deben quedar NULL. No se proporciona al modelo el importe/destinatario esperado para evitar rellenar hechos por expectativa.

El resultado contiene referencia, importe, moneda, fecha/hora, banco, destinatario, pagador, confianza y evidencias breves por campo, normalizados y limitados en longitud. El timestamp, estado, intentos, versión y códigos de error se guardan en la tabla de pagos existente. La IA no aprueba pagos. `store=false` no garantiza retención cero: pueden existir registros de seguridad del proveedor, normalmente hasta 30 días salvo controles específicos de la cuenta. Las entradas de API no se usan para entrenamiento por defecto. El comprador puede leer el tratamiento antes de enviar en `/membresia/checkout/privacidad-comprobante`.

Fuentes: [entradas de archivos/PDF](https://developers.openai.com/api/docs/guides/file-inputs), [Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs), [controles de datos](https://developers.openai.com/api/docs/guides/your-data).

## Conciliación y acceso

Se reutilizan `declared_reference`, `paid_date`, `paid_time`, `origin_bank` y `payer_name` para el motor CSV existente; importe/moneda/destinatario/evidencias se conservan en JSON de extracción. La UI administrativa permite consultar el original, ver JSON/evidencias, editar los datos y reintentar fallos. La corrección queda auditada sin copiar valores personales al log de auditoría. No hay otra tabla de pagos ni aprobación automática.

Para nuevas lecturas una coincidencia fuerte exige la referencia bancaria exacta y única, importe leído 100.00 BOB, datos legibles/confianza alta (o corregidos por administrador), ausencia de conflicto de destinatario y un solo reclamante. Un candidato consistente sin referencia solo es probable y exige revisión individual; datos incompatibles, baja confianza o múltiples candidatos son ambiguos. Fecha/hora nunca aprueban por sí solas. El lote solo admite fuertes y únicas y vuelve a validar. La aprobación conserva el bloqueo, unicidad del movimiento bancario, activación operativa idempotente y outbox HMAC anteriores.

El original se descarga únicamente por su titular autenticado o un administrador. No hay enlaces públicos, presignados ni imgproxy. CLUB, CRM y publicidad no reciben el original ni datos de extracción. Se conserva el QR bancario existente sin cambios.
