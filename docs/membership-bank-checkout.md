# Membresía bancaria COM

Entrada autenticada existente: `/membresia/checkout`. CLUB puede enlazarla después de su SSO existente; no requiere otra cuenta ni modifica atribuciones de CLUB. GET inicia o recupera una sola intención abierta y conserva un InitiateCheckout canónico de Bs 100. Pixel/CAPI reutilizan los adaptadores existentes con el mismo event_id. Sin proveedor publicitario el registro interno permanece.

Administración: `/admin/conciliacion-membresias`, exclusivamente `is_admin`. CSV UTF-8 normalizado: `date,time,reference,description,amount,direction`. Fecha YYYY-MM-DD, hora opcional HH:MM[:SS] de Bolivia, importe con punto decimal y direction credit/debit. Los XLSX del banco deben exportarse a este CSV; no hay OCR/PDF ni conexión bancaria. Mantener un identificador estable de cuenta sin datos bancarios sensibles. No cambiarlo entre exportaciones. Máximo 10 MB y 10.000 filas. Solo créditos positivos. Extractos idénticos y movimientos solapados se deduplican; filas indistinguibles sin referencia se consolidan conservadoramente.

Los candidatos exactos por referencia e importe se consideran fuertes solo con un movimiento disponible y una sola declaración en revisión. Fecha/hora se presentan como auxiliares; no aprueban por sí solas. Toda aprobación exige acción del administrador. El lote vuelve a validar las coincidencias y es transaccional: un conflicto revierte todo el lote. Los ambiguos pueden asignarse individualmente tras revisar comprobante e ingreso. El estado sin coincidencia conserva under_review y registra motivo; el rechazo permite corregir la misma solicitud.

La activación también encola un aviso de correo una sola vez en la cola `membership-notifications` del servicio database existente. El worker COM consume esa cola además de sus colas operativas previas.

Aprobación, asociación única al ingreso, activación real por MembershipService, auditoría y outbox se confirman juntos. No se llama al registro de conversiones/comisiones de COM. Repetir una aprobación del mismo pago/movimiento devuelve la misma operación. Una membresía ya activa impide consumir otro ingreso. `approved` describe pago conciliado; el comprador muestra el estado real de su membresía.

`membership.activated` viaja por commercial_outbox y el cliente HMAC existentes, con event_id estable, global_subject_id, referencia de membresía, fecha real de activación y referencia comercial. No contiene recibos ni referencias bancarias. Si CLUB no admite el evento, conserva cuerpo original y reintenta con backoff. No se modifica CLUB.

## Habilitar el QR real

Desplegado inicialmente sin QR y sin habilitar cobros. El operador debe guardar el PNG/JPG bancario real en `storage/app/membership-private/qr/<nombre>.png` (directorio persistente privado, accesible al usuario PHP), configurar `MEMBERSHIP_BANK_QR_PATH=qr/<nombre>.png` en el entorno COM y refrescar config cache/reiniciar los cuatro servicios COM. No introducir un QR generado o de prueba. El QR se sirve únicamente a compradores autenticados. Las declaraciones permanecen bloqueadas si falta el archivo o la configuración.

Recibos y extractos se guardan en el disco membership-private, fuera de public/storage, sin URLs firmadas públicas, imgproxy ni rutas de descarga pública. Solo el administrador descarga recibos y cada consulta queda auditada. Comprobantes JPG/PNG/PDF hasta 10 MB; nombre aleatorio y descarga forzada.

Pruebas aisladas: `bin/jakawi-test php artisan test --filter=MembershipBankCheckoutTest`. No crear fixtures LIVE.
