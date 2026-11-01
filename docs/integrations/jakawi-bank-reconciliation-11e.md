# Fase 11E — conciliación automática COM

URL: https://jakawi.com/admin/extractos-bancarios (administradores).
Excepciones: https://jakawi.com/admin/extractos-bancarios?estado=review_required.

COM conserva su importador, PDF privado, historial íntegro y deduplicación por
cuenta/identidad bancaria/ocurrencia. `jakawi_bank_reconciliations` es una
proyección acumulativa por movimiento, no una compra ni otro motor de pagos.
Solo contiene créditos positivos, sin débito, cuyo concepto contiene JAKAWI;
los conceptos se comparan normalizando mayúsculas, acentos y espacios.

## Procesamiento

Importar un extracto confirmado y completar la extracción de un comprobante
encolan `ReconcileJakawiBank` después del commit en la cola existente
`membership-receipts`. `bank:reconcile-jakawi`, programado cada minuto, recupera
trabajo pendiente, evidencia corregida y avisos sin recibo. Repetir el proceso
no repite activaciones, eventos ni auditorías idénticas.

El original de un PDF con errores queda privado y su previsualización con
incidencias permite revisión; no se incorporan movimientos ni se aprueba nada.
El historial bancario completo se consulta aparte, bajo el listado acumulativo.

## Criterios exactos de autoaprobación

Todos son obligatorios:

1. Crédito real no utilizado, sin retención de deduplicación, importe y crédito
   exactamente 100.00 BOB, débito cero. Extracto procesado con moneda BOB,
   validación íntegra de saldos, páginas y totales por el parser existente.
2. Una sola compra pendiente (incluye awaiting_payment, PAYMENT_REPORTED y
   under_review). Una compra rechazada, activa previamente o con identidad
   incompatible no se autoaprueba. La fecha bancaria no precede la creación
   local de la compra.
3. Identificación por una de estas evidencias:
   - UUID completo de compra COM o referencia CLUB, delimitado, en el concepto
     bancario. Esta vía permite identificar sin comprobante.
   - Comprobante privado extraído con confianza alta o corregido por un admin,
     importe 100.00, moneda BOB, destinatario explícito igual al QR configurado,
     misma fecha y referencia bancaria presente en la transacción o descripción
     (incluye ACH contenido en la descripción multilineal).
   - Con identificadores distintos: mismo comprobante consistente, misma fecha
     y hora exacta, y nombre completo del pagador (mínimo dos palabras y diez
     caracteres) igual al beneficiario, también presente en el concepto bancario.
4. Unicidad en ambos sentidos: ninguna otra compra identificable/reclamante
   contradictoria para ese crédito y ningún otro crédito JAKAWI compatible con
   esa compra, incluso movimientos previamente aplicados o retenidos.
5. Ninguna contradicción del comprobante en importe, moneda, destinatario,
   fecha, hora o banco remitente cuando está explícito en una línea bancaria.
   No se exige igualdad entre ACH y número de transacción bancaria.
6. Ningún reverso, anulación, extorno o devolución en el propio concepto o en
   la cuenta/fecha; un débito con la misma referencia también bloquea.
7. COM vuelve a verificar la decisión dentro de la transacción y ejecuta
   `MembershipBankCheckout::approve`, `MembershipService::activate` y el
   `membership.activated` idempotente ya existentes. El administrador importador
   se conserva como actor operativo; la auditoría indica `automatic=true`.

Importe o cercanía temporal por sí solos nunca identifican al comprador. Los
avisos "Ya pagué" sin archivo siguen participando en la búsqueda, pero solo la
referencia inequívoca del extracto permite aprobarlos sin recibo.

## Excepciones, auditoría y carreras

Sin identificar también forma parte de "Requiere revisión". La bandeja muestra
motivo, evidencia y compra/usuario cuando hay asociación sustentada. Incluye
ambigüedad, importe incorrecto, comprobante contradictorio, identidad retenida,
PDF no verificado y bloqueos de activación. Los PDFs inválidos figuran en las
previsualizaciones con la indicación "Requiere revisión · PDF con incidencias".

La revisión conserva las herramientas manuales existentes. No se deshacen
activaciones históricas si aparece evidencia posterior: no hay nueva lógica de
reembolsos o ledger.

La transacción bloquea escrituras concurrentes en compras y movimientos mientras
valida el conjunto de candidatos y activa. Mantiene los locks de usuario,
identidad CLUB y movimiento, así como las restricciones únicas existentes.
Reintenta deadlocks hasta cinco veces. Cada cambio de decisión guarda una
`jakawi_bank_automatic_decision` con versión, movimiento, extracto, cuenta,
SHA-256, candidatos y datos del comprobante. El mismo estado/evidencia no genera
auditoría repetida. La aprobación registra además `automatic=true`.

CLUB continúa recibiendo el evento existente y gestionando comisiones. No se
modifican CLUB, CRM, tarifas, ledger ni infraestructura.

## Validación aislada

`JakawiAutomaticReconciliationTest`, `PaymentReportsAndStatementsTest`,
`ReceiptExtractionTest`, `ClubDirectPurchaseTest` y las pruebas existentes de
MembershipBank cubren identificación inequívoca, falsos avisos, dos créditos de
Bs 100, ausencia de archivo, comprobante tardío y su job real, IDs distintos,
importación duplicada, solapamiento, importe incorrecto, movimiento utilizado,
evento/job repetido, banco contradictorio, PDF inválido, compra ya activa y
casos ambiguos. PDFs/personas son sintéticos; PostgreSQL usa exclusivamente
`db-test` / `jakawi_test`. No se importan fixtures en LIVE.

## Comprobantes BNB y desfase de segundos (decisión v2)

Se admite hasta 5 segundos de diferencia únicamente con comprobante válido,
importe/moneda/fecha/destinatario completos, banco remitente explícito equivalente,
nombre del pagador igual a una línea completa del extracto y cuenta de origen
con máscara que coincida con al menos tres dígitos visibles en cada extremo.
El pagador puede ser distinto del comprador. Se preservan todas las retenciones,
contradicciones, verificación del extracto y unicidad entre compras y créditos,
incluidos créditos usados o retenidos. BNB equivale a Banco Nacional de Bolivia.
El destinatario debe contener exactamente todos los mismos términos, admitiendo
que el banco imprima primero los apellidos.

La extracción conserva `origin_account` en el JSON, sin nueva columna ni módulo.
Prioriza Bancarización/identificador de transacción frente al concepto libre.
La administración permite buscar por usuario o referencias COM/CLUB y consultar
el original privado, estado/error IA, reintento, candidatos y razón del bloqueo.
