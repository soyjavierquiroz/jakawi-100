# Lote 01 · Cafeterías y brunch · Cochabamba

Fuente reproducible de seis propuestas privadas: 4 Benefits y 2 Experiences.
Decisión de diseño: CONFIRMED BY JAKAWI. No equivale a aceptación del negocio.

`manifest.json` contiene fichas, sedes y condiciones. Cada directorio contiene
producto, brief canónico, landing editorial, patch del parser real y CSV Catalog V2.
El importador CSV no representa access_mode, city_id ni controles Experiences V2;
no ejecutar esos CSV solos como recreación completa.

Operación completa y evidencia: `artifacts/catalog-batch-cafeterias-20261014/`.
`execute.php` usa el importador existente y el guardado oficial del Publisher,
con transacción por propuesta y control de identidad. Sin --apply sólo valida.
La repetición verifica los registros existentes y no escribe. Si un registro fue
editado después, se detiene para evitar sobrescribirlo. No hay despliegue runtime.
