# JAKAWI — Global Pagination & Sorting V1

LIVE **a9803e8c7036b28b532ed49d80596d4ac6f321d5**, 2026-10-15 (America/La_Paz). Deploy PASS, una salida productiva. Cobertura amplia, **no global completa**.

A/B. Inventario y superficies cubiertas: [contrato canónico](../../docs/UX-MVP.md#paginación-y-ordenamiento-v1--2026-10-15). Explorer, catálogos públicos, perfiles Partner; Admin catálogo, Partners/Locations, usuarios/membresías, solicitudes, ventas/pagos/canjes, campañas/reglas, ciudades/expansión, revisión editorial y presentaciones; portal Partner, reservas e historiales indicados.

C. Pendientes crecientes: colecciones de ficha Location; participaciones/ranking del detalle Challenge; contactos/conversiones anidados de atribución; auditoría de fichas de venta/pago. Se conservan sus contratos especializados para no recortar elegibilidad, selección ni evidencia financiera. Métricas legadas por fila siguen pendientes de batching. Formularios/selectores y resúmenes deliberados de 3/8/10 no se paginan.

D/E. 18 consumer, 25 Admin/portal, 50 historiales. Tamaños fijos; parámetros validados, máximo general 100 y Explorer 18. Destacados editorial, publicación reciente y nombre estable; vencimiento en Benefits y orden de Retos. Admin conserva filtros y añade orden donde aplica. Unlock no inventa fecha de publicación; fechas ausentes al final.

F/G. Cargar más PASS: 18 → 36 → 54, doble clic bloqueado, sin duplicados, fin oculto y vacío claro. Búsqueda/filtros/orden reinician resultados y metadatos juntos. Paginación Admin, atrás/adelante y scroll PASS. Publisher confirma guardado/duplicado más allá de 25; Membresías alinea búsqueda, datos activos y la cancelación existente.

H. UNION ALL PostgreSQL filtra/ordena identidades antes de la página y sólo hidrata los 18 modelos seleccionados. Listas Admin cubiertas acotadas; destinos Landing eager loaded; total reutilizado fuera de rango; actividad reciente limitada desde SQL, manteniendo contadores completos. Sin porcentajes de rendimiento ni índices especulativos.

I. Integrada inicial: 720 pruebas, 714 PASS y 6 fallos corregidos. Revalidación de superficies afectadas: 145/145 (3206 aserciones); cierre Membresías 12/12 (857), Publisher 17/17 (519), Explorer/búsqueda 7/7 (987). Son ejecuciones focalizadas con solapamiento, no un total de pruebas distintas. TypeScript, manifest de 109 páginas y diff check PASS. PostgreSQL aislado con 100/500 registros. Navegador: 375/390/430/1024/1440; 14 vistas LIVE a 390/1440, cero errores/desbordes. Artefactos JSON adyacentes.

J. `/up` local/público 200. Doce rutas LIVE renderizadas bajo transacción READ ONLY, incluidas dos landings realmente publicadas y tres previews Admin. No hay Challenge abierto aprobado en producción; no se creó uno para QA. HTML público real y app/web coinciden con manifest compilado. Frontend validado en `194b2e70d458`, idéntico en las imágenes finales; los cierres posteriores sólo modifican PHP/tests. PRELAUNCH y DEMO preservados. Sin migraciones, registros QA, cambios comerciales ni v2proof. Estado comercial previo/posterior idéntico.

Rollback exacto app/web/worker: `backups/global-pagination-v1/rollback.sh`, imágenes protegidas `jakawi-pagination-rollback:app/web` (baseline runtime `277e14631e56`). Backup custom validado: `backups/global-pagination-v1/pre-cutover.dump`; SHA256 `f938cf855f154935c82843059b37707d799d1d48a3ff4b2b712e0bfc1c43f4d9`. No requiere rollback de datos.
