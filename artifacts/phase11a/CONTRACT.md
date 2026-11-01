# Fase 11A — avisos de pago e historial de extractos COM

COM conserva checkout, compras, atribución y motor de conciliación existentes.
CLUB/CRM, reglas económicas, ledger y formato `membership.activated` sin cambios.

## Contrato adicional para el consumidor CLUB

Se conserva `jakawi-club-purchases-v1`. La creación de intención, consulta, QR,
comprobante e identidad siguen en sus rutas existentes. CLUB no está desplegado
ni modificado por esta fase; para mostrar “Ya pagué” deberá consumir:

`POST https://jakawi.com/internal/club/purchases/{club_reference}/report`

Body JSON `{}`. Misma autenticación HMAC privada y protección antirreplay del
[contrato de compras existente](../../docs/integrations/club-direct-purchases-v1.md),
key ID `club-purchases-v1`. Firmar método POST, path exacto con UUID CLUB y digest
de los bytes exactos de `{}` con el esquema `jakawi-club-purchases-v1` existente.
Nonce nuevo por reintento; no incluir query strings. No exponer HMAC al navegador.

Respuesta 200: mismo objeto de compra existente, con `status: "PAYMENT_REPORTED"`
y campo aditivo `reported_at` RFC3339 UTC. Conserva `payment_reference`, comprador,
`guest_purchase_id`, atribución e identidad. `receipt_received:false`,
`membership_reference:null`, `activated_at:null` mientras no haya conciliación.
Los reintentos no cambian la fecha original ni crean otra compra. Si ya está
`under_review` o `approved`, conserva y devuelve su estado actual. Se requiere QR
bancario configurado para un aviso nuevo; 422 `PAYMENT_UNAVAILABLE` si falta.

`POST .../{club_reference}/receipt` sigue aceptando el contrato base64/SHA-256
existente. Ahora admite `PAYMENT_REPORTED` además de los estados previos. Una
subida directa de clientes anteriores registra también el instante del aviso;
no exige que CLUB haya integrado `/report`. Repetir el mismo SHA no duplica
archivos ni extracción. Los originales reemplazados no se borran.

Consulta GET existente añade `reported_at` (null para registros históricos sin
aviso observado; no se inventa retroactivamente). Un aviso sin comprobante no
indica confirmación bancaria. UI requerida para CLUB al integrar:

- “¡Tu aviso de pago quedó registrado!”
- “Adjunta tu comprobante de pago” y botón “Subir comprobante”.
- Permitir volver a la misma compra y adjuntar después.

No se emite `membership.activated` ni se conceden comisiones al avisar, subir,
extraer o importar. Solo el motor COM existente, con movimiento de crédito real
no utilizado y aprobación administrativa, activa y emite el evento previo.
CLUB conserva el cálculo y deduplicación de sus comisiones sobre ese evento.

## Checkout COM

`POST /membresia/checkout/aviso`, sesión autenticada + CSRF, conserva referencia y
`reported_at` bajo bloqueo transaccional. El índice único de compra abierta
incluye `PAYMENT_REPORTED`. El comprobante se adjunta por el endpoint existente,
con JPG/PNG/PDF hasta 10 MB, privado en MinIO y lectura mediante servicio IA
existente. IA no configurada/fallida conserva archivo, compra y aviso.

## Historial e importación

`/admin/extractos-bancarios`, auth/admin + límites existentes. Separado de
`/admin/conciliacion-membresias`, al que enlaza. Originales privados en el disco
persistente `membership-private`. SHA-256 global único por archivo; un mismo
archivo asignado a otra cuenta/banco genera conflicto. Cuenta, banco, período,
actor, fecha, estado, incidencias y filas preservados. Históricos legacy visibles
sin inventar banco, período ni filas que el importador anterior no conservó.

HTTP exige previsualizar y confirmar explícitamente la cuenta, período y datos.
Previsualizaciones fallidas también conservan el original y el error, sin crear
movimientos. Confirmación atómica e idempotente con bloqueo por cuenta. Los
contadores conciliados/pendientes se consultan por vínculo real, incluyendo filas
duplicadas de extractos superpuestos. Original y entradas permanecen tras activar.
No hay endpoint de borrado; DB impide borrar/truncar imports y cambiar/borrar
entradas. Rollback de imágenes, nunca eliminación de historia.

CSV UTF-8 normalizado previo y tablas con columnas Fecha, Hora, Referencia o
Documento, Descripción o Glosa, Débito, Crédito, Saldo. Se conservan débitos y
créditos con signo, saldo, cuenta y fila original. XLSX de una hoja, valores sin
fórmulas, strings compartidas/inline, fechas y horas con formatos Excel explícitos.
No admite calendario 1904 ni adivina fechas numéricas sin formato. Separadores de
miles/decimales no ambiguos. Hasta 10.000 movimientos, 10 MB y 30 MB XLSX expandido.

PDF digital mediante Poppler `pdftotext -layout`, sin llamada IA. Se validan
cabeceras y filas tabulares; layouts desconocidos, filas partidas o fechas
ilegibles bloquean incorporación completa con incidencia. PDFs escaneados
indican OCR local no disponible (no se instala ni simula OCR). No promete
convertibilidad universal. No hay envío de extractos a servicios externos.

**Compatibilidad Ganadero verificada:** perfil de PDF digital GanaMóvil en
bolivianos contrastado con una muestra privada real de seis páginas. Extrae la
columna TRANSACCIÓN como referencia bancaria; el número QR ACH y los bancos de
contraparte permanecen en la descripción multilineal. Reconoce signos -/+ y miles
con coma. Verifica cada saldo en centavos enteros, saldo inicial/final, cantidades
de débitos/créditos, período y secuencia completa de páginas. Conserva metadatos
leídos del documento en previsualización e historial privados. Una discrepancia
bloquea todos los movimientos. La cuenta numérica declarada distinta del PDF se
rechaza; los identificadores alias requieren contraste administrativo con la
cuenta extraída y deben mantenerse estables. Hora del movimiento en formato 24 h;
emisión AM/PM normalizada. Se interpreta hora boliviana por contexto bancario:
el PDF no declara zona horaria.

El alcance es ese diseño GanaMóvil y esa muestra. No certifica otros diseños de
GanaNet, PDFs escaneados/protegidos, moneda distinta ni exportaciones nativas
XLSX/CSV de Ganadero sin muestra. Bloques partidos entre páginas o extracción
con separación ambigua se rechazan. Pruebas versionables usan datos inventados;
original, texto, imágenes y resultados bancarios reales quedan fuera de Git y web.

Deduplicación por banco/cuenta/fecha/hora/referencia/importe/saldo y ocurrencia;
no por importe solo. Se preserva cada ocurrencia interna. Sin identidad suficiente
o ante precisión incompatible de un extracto superpuesto, se conserva movimiento
separado y retenido: no se fusiona ni concilia. Un movimiento sin comprador y un
aviso sin banco pueden permanecer pendientes. Retenidos no son aprobables.
El índice único original de `bank_movement_id` y el bloqueo del motor impiden
usar un movimiento para dos membresías. Compatibilidad con huellas legacy al
comparar sus datos conservados, sin reescribir historia anterior.

## Validación y despliegue

Fixtures de pagos, PDF, CSV, XLSX solo en `db-test/jakawi_test`. Ningún pago LIVE
ficticio. Pruebas de aviso/reintento/tardío/IA, ACL, validación/confirmación,
solapamientos, débitos, mismo importe genuino, retenciones, activación y outbox.
Evidencia de pruebas en VALIDATION.json; SHA y verificación COM en LIVE.json.
