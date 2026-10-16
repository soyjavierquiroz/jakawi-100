# UX MVP

**STATUS: IMPLEMENTED / PILOT RULES.** Navegación consumidor: Inicio, Explorar, Mi JAKAWI y Perfil. Visitantes descubren; Members canjean Benefits. El flujo de canje conserva contexto, muestra confirmación, código/QR temporal y éxito sólo después de validación Partner.

Experience muestra destino de reserva externo durante piloto. Mi JAKAWI separa cuenta gratuita, Membership activa, valor/ahorro confirmado, JP y reputación cuando aplique. Unlocks tienen superficie pública, interés/compromiso/confirmación, sin exponer supply secreta antes de meta.

No prometer checkout QR, booking público interno, puntos convertibles, gamificación avanzada, mapa avanzado ni recomendaciones avanzadas.

## Paginación y ordenamiento V1 — 2026-10-15

Contrato de listados: consumer **18**, Admin/portal operativo **25**, historiales
extensos **50**. Tamaños fijos en backend; `per_page` no aumenta el payload y se
valida entre 1 y 100 (Explorer conserva máximo 18). `page` positivo; páginas fuera
de rango muestran la última página válida sin repetir el COUNT. Filtros se aplican
antes de paginar; los controles GET omiten la página al cambiar filtros. Los links
conservan los parámetros. `ListingPage` sólo comparte límites y recuperación de
página, no reglas de negocio ni un framework de tablas.

Inventario focalizado y cobertura:

| Superficie | Antes por petición | V1 |
| --- | --- | --- |
| Explorer/búsqueda, cuatro dominios | hasta 24 por dominio, ranking/recorte en PHP a 24; sin continuación | UNION ALL PostgreSQL de identidades elegibles; filtros y orden global antes de 18; hidrata sólo la página |
| Lugares en Explorer | límite 24 sin continuación | 18, filtros ciudad/categoría/búsqueda y continuación |
| Benefits / Experiences públicos | `get()` sin límite; categoría | 18; búsqueda literal por título, categoría, orden y paginador |
| Retos públicos | `get()` sin límite; ciudad/estado/fechas | 18; búsqueda y vencimiento estable; publicación y destinos eager loaded |
| Perfil público Partner | todas las sedes, beneficios, experiencias y retos | 18 por colección con parámetros independientes; scopes públicos conservados |
| Admin Partners / Locations | todos; búsqueda/estado/ciudad/Partner en React | 25; búsqueda, estado y asociaciones en SQL; nombre/recientes; filtros en URL y contexto de edición |
| Admin Benefits / Experiences / Challenges | 20; filtros backend, anterior/siguiente | 25; búsqueda/Partner/estado/revisión; actualización, creación o nombre; paginador compacto |
| Admin afiliados / promotores / creadores | 20; búsqueda/estado backend, navegación ausente | 25; navegación y query string conservados |
| Admin solicitudes Partner / programas / membresía | 20/20/30; filtros parciales, navegación desigual | 25; filtros existentes, búsqueda en solicitudes de membresía y navegación |
| Admin usuarios/membresías, atribución | 25, sin navegación | 25; búsqueda y navegación; acciones conservadas |
| Admin ventas / pagos | 30; filtros backend | 25; navegación; filtros de venta expuestos |
| Canjes / ajustes inmutables | 50/30, navegación ausente | 50; navegación; búsqueda en canjes; filtros operativos conservados |
| Campañas / reglas / ciudades / expansión | `get()` sin límite | 25; búsqueda en primeras tres; estado existente en expansión; métricas limitadas a la página |
| Marketing Landings de un producto | todas sus presentaciones | 25; default publicado resuelto independientemente de la página; editor conservado |
| Revisión editorial Benefit/Experience | todos los pendientes | 25 independientes por dominio; acciones preservadas |
| Auditoría de afiliado/promotor/creador | todo el historial | 50, `audit_page` |
| Portal Partner contenido/retos/desbloqueos | `get()` sin límite | 25; scope de propiedad conservado |
| Reservas Partner | pendientes/próximas completos; 30 históricos | 25 pendientes y próximas; 50 históricos, parámetros independientes |
| Ventas de promotor | 20 sin navegación | 25 con paginador |
| Mi JAKAWI | todas las actividades para mostrar 8; todos los retos personales | actividad limitada a 8 en cada fuente antes del merge, conteos SQL completos; retos personales 50 |
| Intereses por ciudad | 20 | 50 con navegación |

Explorer: `sort=featured` usa `featured` y `sort_order` reales; Challenges no
posee esos campos y aporta valores neutrales. `newest` usa `published_at` en
Benefit/Experience/Partner y `published_from` en Challenge. Unlock no tiene
publicación comparable: NULL al final, sin inventar fecha. `name` usa `lower(title)`
o `lower(name)`; desempate por tipo/ID. Vencimiento no se ofrece en Explorer porque
Experience no tiene una única fecha final comparable; Benefit ofrece `ending` (`ends_at ASC NULLS LAST`), y Retos ordena por `ends_at`. No se utiliza afinidad,
popularidad ni puntuación para el orden editorial de Explorer. Home conserva su
contrato de secciones pequeñas.

«Cargar más» solicita únicamente resultados y metadatos mediante una visita
parcial Inertia; `Inertia::merge` añade los resultados, filtro nuevo reinicia arrays,
se bloquean dobles clics y se conserva scroll. El historial de Inertia conserva el
conjunto cargado y el scroll al volver de una ficha; no hay store global ni
localStorage. Recargar una URL `page=N` muestra esa página coherente y permite
continuar. Admin muestra hasta cinco números cercanos más extremos en desktop;
en móvil, anterior/actual/siguiente. No se rediseñan tarjetas ni navegación.

Excepciones y pendientes explícitos (la cobertura **no es global completa**):

- Ficha Location: beneficios con comprobación `isAvailableAt` y sesiones próximas
  siguen sin paginación. Requiere trasladar toda esa elegibilidad a SQL antes de
  paginar; no se trunca después de filtrar una página.
- Detalle Admin de Challenge: participaciones y ranking final se cargan completos;
  ranking usa desempates y revisión conjunta. Se conserva para evitar alterar
  selección/entrega. Ranking público conserva su contrato especializado.
- Atribución: contactos/conversiones anidados de cada usuario no están paginados.
  Auditoría dentro de ficha de venta/pago continúa completa. Necesitan navegación
  propia de detalle, no recortar silenciosamente la evidencia financiera.
- Métricas de Unlock y algunas métricas comerciales existentes siguen realizando
  agregados por fila, ahora sobre una página acotada. No se declara eliminación
  global de N+1 en esas superficies especializadas.
- Dashboards con últimos 3/8/10 resultados son resúmenes deliberados, no historiales
  completos. Agregaciones de performance/CRM y exportaciones conservan contratos;
  no existe listado web independiente de contactos/CRM deliveries/transacciones.
- Formularios, selectores de asociaciones, menús, configuración y catálogos
  estáticos no se paginan. Algunos selectores dinámicos cargan todas sus opciones;
  sustituirlos por autocomplete queda fuera de esta pasada de listados.

No hay migraciones ni índices nuevos. PostgreSQL aislado verifica 100 y 500
registros mixtos, estabilidad, no duplicación, extremos, filtros y privacidad.
Los datos QA nunca se insertan en producción. Evidencia de release y rollback en
`artifacts/global-pagination-v1` y `backups/global-pagination-v1`.
