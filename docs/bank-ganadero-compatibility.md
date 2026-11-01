# Banco Ganadero: compatibilidad comprobada en Fase 11A

Se contrastó una muestra privada real de **PDF digital GanaMóvil, en BOB y de seis
páginas**. El formato queda soportado por `BankStatementParser` mediante Poppler
`pdftotext -layout`, sin OCR, IA ni transmisión del extracto a servicios externos.
La muestra real se procesa únicamente en aislado. El release de Fase 11A
integra el parser con el historial y la conciliación existentes de COM; no
importa la muestra privada ni crea pagos LIVE.

## Campos y controles

| Campo | Lectura y verificación |
| --- | --- |
| Fecha y hora del movimiento | FECHA DD/MM/YYYY y HORA HH:MM:SS; se normalizan sin inventar segundos. |
| Emisión | Fecha y Hora del encabezado, con AM/PM convertido a 24 h; separada de la fecha del movimiento. |
| Número de operación | Columna TRANSACCIÓN. El número QR ACH se conserva en la descripción y no reemplaza la transacción. |
| Descripción | Bloque completo, incluyendo líneas anteriores y posteriores a la fila fechada, cuentas de contraparte, nombres y concepto; se preserva también el bloque original. |
| Débito y crédito | Signos negativos/positivos y separador de miles con coma; débito y crédito normalizados positivos en columnas separadas. |
| Importe | Crédito positivo o débito negativo; una sola dirección positiva por movimiento. |
| Saldo | Saldo anterior, todos los saldos consecutivos y saldo actual; comprobación con centavos enteros. |
| Cuenta y banco | Cuenta del encabezado y Banco Ganadero; separados de las cuentas/bancos de contraparte que figuran en las descripciones. Metadatos conservados en previsualización e historial privados. |
| Integridad | Secuencia de páginas, cantidades de débitos/créditos, período del PDF y fechas válidas. Una discrepancia bloquea el archivo completo. |
| Duplicados | Identidad y ocurrencia bancarias, banco/cuenta, hash de archivo y comparación de extractos solapados. Un importe repetido por sí solo no implica duplicación. |

## Resultado con la muestra real

- **44 movimientos:** 28 débitos y 16 créditos; coinciden con el resumen del PDF.
- **44/44** fechas, horas, números de transacción, débitos, créditos y saldos
  contrastados con una extracción independiente. Las 44 descripciones también
  coinciden completas, línea por línea y en orden. Todos los saldos cuadran.
- **0 duplicados internos** y ningún número de transacción repetido.
- Primera importación aislada: 44 movimientos; repetición del mismo archivo:
  detectada como duplicada; segundo archivo de otro formato con los mismos
  movimientos: 0 nuevos y 44 duplicados.
- Dos créditos genuinos de igual importe permanecen separados.
- Previsualizar no incorpora movimientos. Importar no crea pagos, membresías,
  eventos de activación ni recompensas.

La muestra se montó solo para lectura en un contenedor de validación conectado
exclusivamente a una red Docker interna y una base PostgreSQL temporal en RAM.
Los registros de la prueba se revirtieron; contenedor de base y red se eliminaron.
Los originales temporales del importador estuvieron en almacenamiento efímero.
El extracto, texto extraído, imagen de contraste y evidencia detallada están
exclusivamente bajo `/root/jakawi-commercial-v1/private/`, con archivos 0600 y
directorios de validación 0700; no se copiaron al repositorio ni a la web.
El informe privado es `bank-validation/validation.private.json` dentro de esa ruta.

## Límites

- Compatibilidad probada para este diseño GanaMóvil y esta muestra en BOB. No
  certifica todos los formatos de Banco Ganadero, GanaNet o versiones futuras.
- PDF escaneado, protegido, dañado, con bloques partidos entre páginas, moneda
  diferente o columnas ambiguas requiere otra exportación y se rechaza sin IA.
- Los CSV/XLSX nativos de Banco Ganadero aún requieren muestras propias; la prueba
  de solapamiento usó un CSV normalizado derivado localmente de la muestra.
- El PDF no declara zona horaria; America/La_Paz se interpreta por contexto
  bancario. No se deduce una hora para formatos que no la incluyan.
- Un identificador numérico de cuenta diferente al extraído bloquea la
  incorporación. Un alias de cuenta exige comprobación administrativa con la
  cuenta del documento y debe permanecer estable entre extractos; no se resuelve
  automáticamente el vínculo alias/cuenta.
- La identidad insuficiente o la repetición ambigua se conserva con retención
  administrativa, conforme al importador Fase 11A existente.

Las pruebas versionables contienen exclusivamente datos inventados; el PDF real
no es un fixture del repositorio. El despliegue de COM se documenta en la evidencia de release.

## Verificación técnica

La regresión se ejecuta con `bin/jakawi-test`, sus comprobaciones de aislamiento
y PostgreSQL `db-test/jakawi_test`. Se usa una configuración Compose temporal
con el runtime PHP 8.4 de pruebas existente más Poppler y el código actualizado;
no se incorporan archivos bancarios reales a esa imagen. Las suites cubren la
importación, el checkout y las protecciones financieras. El log y el resultado
resumido están en `artifacts/phase11a/ganadero-regression.log` y
`artifacts/phase11a/ganadero-validation.json`.

La prueba también detectó y corrigió la creación repetida de la función de
protección del historial durante la reconstrucción de la base de pruebas:
`CREATE OR REPLACE FUNCTION` conserva el mismo comportamiento protector.
