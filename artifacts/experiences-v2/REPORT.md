# JAKAWI — Experiences V2

Fecha: 14 de octubre de 2026, hora de Bolivia. Repositorio `/home/jakawi.com`.

## Diagnóstico

Toc Toc (Experience 1, Partner 3, Landing 5) tenía `published`, dos precios 75/60 BOB y duración 90; no tenía sesiones ni número de WhatsApp. Explorar exigía una sesión futura `scheduled` con ubicación en la ciudad seleccionada. El perfil del Partner exigía una sesión futura. **Esa ausencia de sesiones era la causa exacta del filtrado del catálogo.** La landing DRAFT era una publicación independiente, no la causa del filtrado: sin landing pública, el resolver podía usar la ficha nativa.

| Pregunta auditada | Comportamiento original |
|---|---|
| `regular_price` | Precio informativo base. No acreditaba un precio habitual externo ni ejecutaba un cobro. |
| `member_price` | Segundo precio informativo opcional; no congelaba importe en reserva ni ejecutaba descuentos externos. |
| Elegibilidad | `User::hasActiveMembership`: membresía `active`, inicio <= ahora y fin >= ahora. Autenticarse no equivale a ser miembro. |
| Precio sin membresía | Compatible con coordinación externa. Reserva interna JAKAWI exige membresía, independientemente de los dos precios. |
| Membresía para reservar | Obligatoria en el servicio interno; no comprobada en redirecciones externas. Verificación contextual de cuenta preservada. |
| Precio de miembros nulo | No se mostraba una segunda tarifa. No había cálculo ni precio inventado. |
| Duración | Presentación y brief comercial. No genera horarios, fin de sesión, cupos ni disponibilidad. |
| Explorar | Published + sesión futura programada + ubicación en ciudad seleccionada. Búsqueda por título/Partner y filtros de categoría. |
| Perfil del Partner | Published + sesión futura programada, con asociación por pivot y funciones. |
| Landing | LandingPresentation PUBLISHED; destino automático según default_scope GUESTS/ALL. DRAFT privado; fallback a ficha nativa. |
| Participación | Interna: cuenta/membresía, published, sesión futura y Partner de reservas publicado y asociado. Externa: sesión futura y canal real. No checkout Experience. |

`published_at` y `review_status` no eran filtros de esas consultas. PRELAUNCH_MODE cambia Home, aviso y SEO; no explica la ausencia de Toc Toc. Las relaciones pivot permiten varias funciones del mismo Partner y se conservan.

Problemas adicionales: campos administrativos ambiguos, validaciones distintas entre formularios, falta de resumen de publicación, precios nativos con moneda fija, canal WhatsApp vacío, preview condicionado a tener landing, tarjetas del Partner con precios y estados propios, capacidad informativa sin control automático de cupos y condiciones externas sin aplicación automática de vigencia/límites.

## Arquitectura resultante

Decisión previa a cambios estructurales: [contrato y auditoría](../../docs/decisions/EXPERIENCES_V2.md).

Se conservan estados editoriales draft/published/paused/archived, modelos, dos precios, sesiones, landings, reservas y reglas de membresía. Se añaden únicamente `catalog_visibility` y `operations_confirmed` mediante migración aditiva.

| Control de catálogo | Descubrimiento y operación |
|---|---|
| Oculta | Propuesta privada, incluso si su landing quedó publicada. |
| Próximamente | Producto editorialmente published y Partner público. Sin necesidad de sesiones; ficha y landing informativas, sin reservas/pagos/canjes. |
| Oferta disponible | Exige confirmación operativa explícita, sesión real futura, lugar real y canal válido; cumple los bloqueos comerciales. |
| Regla anterior | Compatibilidad de registros anteriores publicados: descubrimiento mediante sesiones. No autoriza nuevas propuestas sin sesiones. |

La base y el formulario crean nuevas Experiences ocultas. La migración inicializa el control nuevo de los registros existentes no draft como regla anterior; los drafts quedan ocultos. No cambia precios, duración, copy, imágenes, sesiones, relaciones ni timestamps comerciales.

`ExperiencePublication` centraliza publicidad y bloqueos operativos. Se aplica a la ficha, landing y endpoints. Próximamente se descubre por la presencia del Partner en sedes publicadas de la ciudad, sin atribuir una sede de ejecución ni fecha ficticias. Sin sede del Partner en una ciudad, la propuesta puede aparecer en su perfil pero no en Explorar de esa ciudad.

`ExperiencePricing` presenta los mismos importes y etiquetas en ficha, landing y tarjetas del Partner. El precio preferencial externo se identifica como **previsto, con activación y verificación pendientes**, también para usuarios con membresía. Cualquier reserva externa con ese precio configurado queda bloqueada. No hay un precio habitual del Partner inventado ni nuevos pagos/verificadores.

Una nueva activación interna con capacidad definida se bloquea mientras no exista control automático de cupos; las reservas internas anteriores mantienen sus reglas. Pausar/archivar impide nuevas operaciones. Las condiciones externas de vigencia y límite por cuenta siguen siendo responsabilidad operativa del Partner; escribirlas no las ejecuta.

Los importes opcionales no se calculan ni rellenan automáticamente. Se validan decimales, no negatividad, necesidad de precio base cuando hay preferencial y preferencial <= base. El detector conservador señala cifras Bs/BOB incoherentes y distingue texto explícito de miembros; no sustituye una revisión editorial completa ni interpreta todas las frases posibles.

## UX y evidencia visual

Editor por identidad, oferta/precios, operación y publicación; navegación por secciones, guardado accesible al inicio y en publicación, ayudas españolas, confirmación de guardado, campos con errores, persistencia tras errores y advertencia al salir con cambios. El enlace se genera desde el título al crear si se deja vacío; los existentes se conservan. El Partner y sus funciones son editables explícitamente, sin reasignaciones implícitas.

La duración se explica como duración total, diferente de una explicación guiada. Coordinación, teléfono/WhatsApp y sesiones pueden quedar pendientes. Sesiones con fecha/hora de Bolivia, sede, responsable, capacidad y cancelación usan los endpoints existentes. No se crean sesiones ficticias. Pago y condiciones usan el campo existente; se explica qué controles no automatiza JAKAWI.

El panel muestra la situación guardada de Experience, presentación, catálogo, Explorar, Partner y operación, con motivos derivados del código. Guardar o publicar editorialmente no confirma operación ni publica la landing. Preview de ficha disponible sin landing, sólo Admin y con acciones deshabilitadas. Preview de landing existente sigue privado. La vista pública sólo enlaza destinos reales.

| Evidencia | Desktop | Mobile |
|---|---|---|
| Antes, producción | [1440](before-1440.png) | [375](before-375.png), [390](before-390.png) |
| Después, réplica aislada | [1440](after-1440.png) | [375](after-375.png), [390](after-390.png) |
| Precios | [1440](after-oferta-1440.png) | [390](after-oferta-390.png) |
| Operación | [1440](after-operacion-1440.png) | [390](after-operacion-390.png) |
| Publicación | [1440](after-publicacion-1440.png) | [390](after-publicacion-390.png) |
| Explorar Próximamente | [1440](public-explore-1440.png) | [375](public-explore-375.png), [390](public-explore-390.png) |
| Partner Próximamente | [1440](public-partner-1440.png) | [375](public-partner-375.png), [390](public-partner-390.png) |
| Landing informativa | [1440](public-landing-1440.png) | [375](public-landing-375.png), [390](public-landing-390.png) |

Las capturas públicas de Próximamente corresponden a una réplica de Toc Toc en `jakawi_test`, con autorización de visibilidad y corrección de copy realizadas exclusivamente allí. No demuestran una publicación de Toc Toc en producción. LIVE: [editor desktop](live-1440.png), [375](live-375.png), [390](live-390.png). [Navegador aislado](candidate-browser.json) y [navegador LIVE](live-browser.json): PASS, sin errores de JavaScript.

## Toc Toc y decisiones pendientes

Preservados ID 1, Partner 3, Landing 5, Bs 75/Bs 60, moneda BOB, duración 90, portada y textos. No se ejecutó el script histórico de configuración. No se restauraron valores anteriores ni se inventaron números/sesiones.

Las condiciones originales dicen «Bs 60 por persona» y «Sin membresía», mientras los campos estructurados contienen 75/60 y la presentación dice Bs 75. El editor advierte esta discrepancia; los textos de producción se conservan. Los 15–20 minutos de explicación no contradicen necesariamente los 90 minutos totales. Las condiciones declaran capacidad propuesta 8 y vigencia hasta 30/11/2026, sin mecanismos externos automáticos que apliquen estos límites.

Estado preservado: publicada editorialmente; catálogo con regla anterior y sin sesiones, por lo tanto no visible en Explorar/perfil; operación pendiente y reservas deshabilitadas; landing 5 borrador privado. La ficha nativa informativa por enlace sigue siendo un destino real. No hay CTA operativo válido. Toc Toc sí tiene una sede publicada en Cochabamba (Location 3, City 1 ACTIVE): una vez autorizado Próximamente, cumple el criterio geográfico de Explorar sin inventar una sesión.

Para presentarla: revisar el copy de precios, seleccionar Próximamente y guardar; publicar por separado la presentación comercial si se desea usar esa landing. Para mantener privacidad completa: seleccionar Oculta. Para activarla posteriormente: confirmar operación y resolver los bloqueos reales; el descuento externo seguirá bloqueado hasta verificar elegibilidad. Ninguna de estas decisiones se ejecuta automáticamente.

## Calidad

Suite completa anterior (`4bc202a`): 662 pasan y 10 fallan. Suite tras cambios de arquitectura (`2cef128`): 675 pasan y los mismos 10 fallan, sin regresiones adicionales. [Comparación](tests-comparison.json). El código final effd76e pasa 87 pruebas críticas (2356 aserciones), incluidas las 14 pruebas nuevas de negocio y el perfil público del Partner. [Calidad final](quality.json).

Los fallos previos pertenecen a AdminV2 (2), ApplicationV2Http, Campaign, DemoCatalogV2, LandingPresentation (video), PublicV2Http, UnlockCore y UnlockMarketingLanding (2). Se mantienen fuera del alcance; la suite global no se declara verde.

Build Docker desde `git archive` confirmado; TypeScript con generación Wayfinder; guard de manifest (106 páginas) y comprobación de assets, ausencia de `public/hot` e igualdad entre app/web. Lint frontend sin errores; la página del Partner conserva cuatro advertencias anteriores por defaults de props requeridas. Pint de archivos cambiados: el formato previo de routes/web.php sigue señalado, reproducido también en baseline; el resto se verifica separadamente. No se cambian imports de todo el archivo de rutas para ocultar esa deuda.

Chromium: 375/390/1440, teclado, ausencia de overflow, campos, guardado, errores, conservación de importes, advertencia de cambios, creación sin slug, publicación informativa, filtros, privacidad de preview y acciones deshabilitadas. Guardado y cambios comerciales de QA sólo en base aislada; producción se inspecciona sin guardar productos. Se verifica además carrusel móvil existente tras deploy.

## Publicación y recuperación

**LIVE: effd76e84e6b8a369ba22e9a057ba69850873728**, sincronizado con GitHub antes del deploy. [Resultado de deploy](deploy.json): PASS. App y social-worker comparten la imagen de ese SHA; web contiene el mismo manifest y assets. Se aplicó únicamente la migración aditiva, sin reiniciar PostgreSQL ni imgproxy. PRELAUNCH_MODE sigue true. Snapshot comercial antes/después idéntico: [antes](toc-toc-before.json), [después](toc-toc-after.json). Los artefactos ajenos al alcance permanecen intactos.

Commits de implementación: 67db2bd (arquitectura/editor), 2cef128 (sesiones y lugar), 7cd6890 (creación sin conocer enlaces), 3673955 (formato de tests), effd76e (estados/precios en tarjetas del Partner). El commit documental posterior incorpora este informe y no cambia el código LIVE.

Gates HTTP/HTML compilado pasan en Home, Explorar, los tres Partners, Experience, Benefits, Challenges y las dos landings públicas de Benefits. Landing 5 DRAFT y reserva sin operación devuelven 404 deliberadamente y no se enlazan como acciones públicas. Tres previews existentes y el carrusel móvil pasan en navegador LIVE. [Artefacto candidato](candidate-artifact.json): siete recorridos sobre transacción READ ONLY, con analítica deshabilitada sólo en esas peticiones.

No se guardaron productos en producción durante QA. Se retiraron y verificaron nueve eventos de telemetría de las peticiones de prueba y tres sesiones privadas de QA, sin tocar atribución comercial ni datos de usuarios: [limpieza verificada](qa-cleanup.json). La réplica temporal HTTP se retiró.

Backup PostgreSQL custom validado con `pg_restore --list`: [metadatos](backup.json). La configuración de recuperación queda también conservada en [rollback.yaml](rollback.yaml), con IDs exactos de app/web/worker anteriores. El rollback conserva las columnas aditivas y vuelve coherentemente a esas imágenes; no restaura la base ni borra datos. El `down` de la migración sólo retira los controles nuevos y fue probado en aislamiento; requiere exportar esa configuración antes de usarlo en una recuperación real.

Rollback disponible para el snapshot actual, sin ejecutarlo:

```bash
docker compose --project-name jakawicom -f compose.yaml -f artifacts/experiences-v2/rollback.yaml up -d --no-deps --no-build --force-recreate app web social-worker
```

Después, comprobar salud local/pública, assets y previews como en OPERATIONS.md. Las imágenes anteriores siguen presentes. Si posteriormente se utilizan controles nuevos de visibilidad, revisar esa configuración antes de volver a código anterior: ese código no interpreta los controles V2.

Desarrollo posterior documentado: verificación real de membresía para Benefits y Experiences con pago directo al Partner, tarifa aceptada, expiración/revocación, trazabilidad y controles de entrega. Esta entrega no amplía pagos, reservas ni canjes.
