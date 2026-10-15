# Reversión limitada

No restaurar globalmente la base ni resetear secuencias. Backup validado en backup.json,
fuera de Git; reservado para recuperación de desastre.

Para retirar el lote, revisar results.json y comparar cada fila actual con after.json.
rollback.php rechaza cambios posteriores, nuevas sedes o productos del Partner,
uso de la sede por otros Benefits, canjes y sesiones/reservas. Las claves foráneas
conservan los demás bloqueos de integridad. Nunca se elimina una identidad reutilizada:
en esta ejecución los ocho registros fueron creados por el lote.

Copiar el directorio de artefactos a `/tmp/ent-recovery` del contenedor LIVE y ejecutar:

```bash
php /tmp/ent-recovery/rollback.php /tmp/ent-recovery
```

El modo predeterminado revierte la transacción, sin guardar eliminaciones.
`--apply` persiste la eliminación acotada, en orden landing, pivots, producto, sede y Partner.
Requiere una decisión explícita de recuperación. No se ejecutó el rollback ni su ensayo
destructivo en producción. Este lote no cambia runtime: no corresponde rollback de imágenes.
