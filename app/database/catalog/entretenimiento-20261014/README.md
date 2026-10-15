# Lote 02 · Experiencias y entretenimiento · Cochabamba

Fuente reproducible de dos propuestas privadas: un Benefit y una Experience.
Decisión comercial: CONFIRMED BY JAKAWI; operación y aceptación de los negocios pendientes.

`manifest.json` conserva fichas, sedes y condiciones. Cada directorio incluye producto,
brief generado por LandingAiBriefBuilder, landing canónica, patch del parser y CSV Catalog V2.
El mínimo de dos personas de Duelo gamer es una condición comercial propuesta en los campos
existentes; no se instrumenta creando sesiones o capacidad ficticia. Benefits no tiene precio
estructurado: Bs 55 queda en descripción, condiciones y presentación, sin ahorro estimado.

Procedimiento y evidencia: `artifacts/catalog-batch-entretenimiento-20261014/`.
Reutiliza el importador y Publisher del Lote 01, con transacción por Partner y checkpoints.
`execute.php` valida sin escribir; `--apply` persiste únicamente identidades nuevas o verifica
las ya completas sin sobrescribirlas. Si hay una identidad ambigua o un alta parcial, se detiene
antes de sobrescribir datos y requiere revisión del checkpoint.

No ejecutar los CSV solos como recreación completa: no representan city_id, access_mode,
review_status ni controles de Experiences V2. Copiar fuente y script al contenedor LIVE,
ajustar propietario del directorio temporal al usuario PHP y ejecutar desde Laravel:

```bash
php /tmp/ent-execute.php /tmp/ent-batch
php /tmp/ent-execute.php /tmp/ent-batch --apply
```

Los IDs se resuelven por identidad y no se fijan en la fuente. Las landings quedan DRAFT,
NONE/NONE; Partners, Locations y productos draft; Experience hidden, operations_confirmed=false,
reservation_method=none, member_price y duración nulos. Sin imágenes inventadas: media.mode=NONE.
No requiere migraciones, build ni deploy.
