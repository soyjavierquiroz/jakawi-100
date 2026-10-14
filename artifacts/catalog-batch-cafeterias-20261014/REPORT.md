# JAKAWI · Lote 01 · Cafeterías y brunch · Cochabamba

14 de octubre de 2026, hora de Bolivia. Operación de datos compatible con LIVE `effd76e84e6b8a369ba22e9a057ba69850873728`. Rama `main`; commit inicial `4652895ecbc739449a7e2d51b661ee4154ed3758`.

Se crearon 6 Partners, 6 sedes principales, 4 Benefits, 2 Experiences y 6 landings. Los 24 registros están privados. Decisión comercial: **CONFIRMED BY JAKAWI**; no acredita aceptación del negocio ni operación confirmada.

| Partner | ID | Location ID | Producto | Tipo | ID producto | Precio BOB/persona | Landing ID | Estado | Preview Admin |
|---|---:|---:|---|---|---:|---:|---:|---|---|
| Coffee Shop Lattente | 6 | 5 | La pausa que mereces. | Benefit | 4 | 55 | 6 | draft / DRAFT | [Abrir](https://jakawi.com/admin/beneficios/lattente-la-pausa-que-mereces/landings/lattente-la-pausa-que-mereces/preview) |
| MISHIN.café | 7 | 6 | Michi & Café. | Experience | 2 | 65 | 7 | draft / DRAFT | [Abrir](https://jakawi.com/admin/experiencias/mishin-cafe-michi-y-cafe/landings/mishin-cafe-michi-y-cafe/preview) |
| KOA Café | 8 | 7 | Café de origen. | Experience | 3 | 85 | 8 | draft / DRAFT | [Abrir](https://jakawi.com/admin/experiencias/koa-cafe-cafe-de-origen/landings/koa-cafe-cafe-de-origen/preview) |
| Lemon Sabor Fusión | 9 | 8 | Viernes sin excusas. | Benefit | 5 | 35 | 9 | draft / DRAFT | [Abrir](https://jakawi.com/admin/beneficios/lemon-sabor-fusion-viernes-sin-excusas/landings/lemon-sabor-fusion-viernes-sin-excusas/preview) |
| SÜSS Café | 10 | 9 | Desayuno en El Pueblito. | Benefit | 6 | 65 | 10 | draft / DRAFT | [Abrir](https://jakawi.com/admin/beneficios/suss-cafe-desayuno-en-el-pueblito/landings/suss-cafe-desayuno-en-el-pueblito/preview) |
| Le Petit Brunch & Bakery | 11 | 10 | Brunch a tu ritmo. | Benefit | 7 | 85 | 11 | draft / DRAFT | [Abrir](https://jakawi.com/admin/beneficios/le-petit-brunch-bakery-brunch-a-tu-ritmo/landings/le-petit-brunch-bakery-brunch-a-tu-ritmo/preview) |

Lemon: mínimo dos personas, Bs 70 por la propuesta completa. Viernes es el día objetivo propuesto; horarios y elegibilidad no están confirmados.

## Datos y persistencia

Partners creados: 6. Reutilizados: 0. No había coincidencias por nombre, slug, teléfonos, redes ni dirección; [auditoría](duplicate-audit.json). Locations creadas: 6, `city_id=1`, Cochabamba, sede Principal. Se conservaron contactos, correos, redes, menú/link y Maps entregados. Sin coordenadas inventadas, sin WhatsApp inferido de un teléfono y sin PIN de canje.

Persistencia mediante `jakawi:import-catalog-v2`, modelos existentes y `AdminLandingPresentationController::save`, siguiendo el precedente de Toc Toc. Los campos no representados en CSV se guardaron en el mismo modelo: `city_id`, `access_mode`, revisión y controles Experiences V2. No hay nuevas tablas, rutas ni componentes. La auditoría por URL directa descubrió que Benefits draft exponía su ficha nativa: se corrigió con un guard mínimo de privacidad, sin cambiar datos ni operaciones.

Fuente reproducible: [catálogo](../../app/database/catalog/cafeterias-20261014/README.md). [Manifest con IDs](manifest.json), [resultado completo](results.json), [dry-run](dry-run.json), [segunda ejecución](idempotence.json). La repetición realizó seis verificaciones sin escrituras ni duplicados. Cada propuesta se guardó en una transacción independiente; el checkpoint incremental se escribe después de cada commit. La transacción impide un Partner parcialmente persistido si falla su producto o landing. Los registros ya completados no se recrean.

Durante la preparación se corrigieron valores del paquete: booleanos CSV como `false`, `sort_order=0` explícito y slug de landing proporcionado al guardado administrativo (el parser preserva la identidad de rutas). Los intentos fallidos revirtieron su transacción; quedaron huecos legítimos de secuencia, sin registros parciales ni modificación de productos anteriores. El defecto posterior de privacidad se resolvió en el código de acceso, sin alterar el importador ni los registros.

## Contenido y límites del dominio

Acceso comercial previsto con cuenta JAKAWI, sin membresía de pago. Benefits usa `authenticated`, un uso por cuenta y fin de vigencia en `2026-12-01T03:59:59Z`, equivalente al 30/11/2026 23:59:59 en Bolivia. El límite sólo será aplicable al activar y verificar el flujo de canje existente. No se creó ningún canje ni PIN.

Benefits no tiene columna contractual de precio ni moneda: los Bs 55/35/65/85 se conservan explícitamente como precio comercial JAKAWI en descripción, condiciones y anotación editorial, nunca como `estimated_savings` ni precio habitual externo. Todos los ahorros quedan nulos.

Experiences usa precio base BOB 65/85, `member_price=null`, `duration_minutes=null`, `reservation_method=none`, `catalog_visibility=hidden`, `operations_confirmed=false`, editorial `draft`. Sin sesiones, cupos, reservas ni canal activo. Vigencia comercial y límite por cuenta son condiciones pendientes de instrumentación externa, no controles automáticos. No se utiliza reserva interna incompatible con el acceso sin membresía.

Pago directo al Partner sólo tras activación operativa; JAKAWI no cobra. Las landings quedan DRAFT con `default_scope=NONE` y `publish_scope=NONE`. CTA editorial preparado en el cierre, con acción real deshabilitada. Los enlaces de ficha del template apuntan a `#condiciones`, sin rutas públicas rotas.

Cada oferta tiene concepto e incluidos propios, objetivos y pendientes específicos. MISHIN no garantiza contacto, presencia ni interacción animal; los Bs 65 no incluyen una donación automática y el aporte voluntario exige mecanismo acordado. KOA no tiene duración ni participantes inventados. SÜSS conserva las alternativas de desayuno como propuesta a verificar. Lattente no promete upgrade gratuito. Lemon no publica como vigente la promoción histórica de Bs 46 ni inventa ahorro.

Fotografías y logos autorizados pendientes en los seis casos. Se usa `media.mode=NONE`, tratamiento nativo sin imagen. No hay imágenes generadas, flyers ni interiores inventados. Sin imagen no procede certificar un recorte hero 4:5; el requerimiento queda para el futuro upload autorizado.

Los briefs se generan con `LandingAiBriefBuilder` mediante el contexto real del Publisher. Se añade Cochabamba desde la sede real porque el builder no infiere ciudad de sedes privadas ni de sesiones inexistentes. No se inventan sesiones/locations públicas para llenar el brief. El lugar de referencia figura en descripción/condiciones; el template señala que no hay sedes aplicables publicadas ni fechas confirmadas. El contrato no acepta bloques arbitrarios ni un botón CTA personalizado: el texto solicitado queda como titular editorial de cierre.

## Pendientes por Partner

- **Coffee Shop Lattente**: Bebidas elegibles, dulces disponibles, condiciones del upgrade y aporte comercial por unidad. Fotografía y permisos pendientes.
- **MISHIN.café**: Aforo, convivencia, bienestar animal, zonas habilitadas, disponibilidad, reservas y mecanismo de aporte voluntario. Fotografía y permisos pendientes.
- **KOA Café**: Disponibilidad de barista, método de preparación, café elegible, repostería, duración, capacidad y horarios. Fotografía y permisos pendientes.
- **Lemon Sabor Fusión**: Tipos de frappé elegibles, postre o acompañamiento, horarios, días aplicables y disponibilidad real. Fotografía y permisos pendientes.
- **SÜSS Café**: Confirmar si la carta realmente ofrece las opciones de desayuno propuestas, productos elegibles y horarios. Fotografía y permisos pendientes.
- **Le Petit Brunch & Bakery**: Platos elegibles, bebidas, mini dulces, horarios y días de utilización. Fotografía y permisos pendientes.

## QA

[Integridad relacional](integrity.json): PASS para identidad, contactos, ciudad, sede, relaciones, estados y ausencia de operación. [Contratos](contracts.json): seis briefs `jakawi-product-brief-v1` y seis landings `jakawi-landing-v1` válidos con el parser real. [Preservación](preservation.json): registros preexistentes y PRELAUNCH_MODE sin cambios inmediatamente después del guardado del lote, con snapshots [antes](before.json) y [después](after.json), sin hashes de PIN.

[Navegador](browser.json): resultados finales y comprobaciones detalladas. Capturas por Partner: `preview-390.png` y `preview-1440.png` en sus seis directorios. Vistas verificadas con assets LIVE; sin suite global. Debido al defecto real de privacidad se ejecutaron pruebas específicas, TypeScript y build de producción. Los scripts del lote son herramientas operativas y no se instalan ni se ejecutan como runtime.

## Publicación y recuperación

Deploy ejecutado: **YES**, una sola vez para la corrección necesaria de privacidad; sin migraciones. LIVE inicial `effd76e`; candidato `df8f543`. Datos fuente: commit `4aca9e4`. Informe y evidencia se incorporan en un commit documental posterior; ambos se sincronizan con GitHub. Los cuatro directorios de artefactos ajenos que ya estaban sin seguimiento permanecen intactos.

[Backup validado](backup.json), fuera de Git, `/root/backups/jakawi/catalog-batch-cafeterias-20261014/before.dump`. No restaurar toda la base para retirar este lote.

Reversión limitada: [rollback.php](rollback.php), por IDs del manifest y snapshots exactos. Rechaza filas modificadas y dependencias; las claves foráneas también impiden eliminar registros utilizados posteriormente. Elimina en orden landing, pivots, producto, Location y Partner, dentro de una transacción. Nunca borra los tres Partners ni productos anteriores. No se ejecutó reversión ni prueba destructiva en producción.

Para una recuperación autorizada, copiar este directorio a `/tmp/cafe-batch-recovery` del contenedor y ejecutar desde Laravel:

```bash
php /tmp/cafe-batch-recovery/rollback.php /tmp/cafe-batch-recovery
```

El modo predeterminado revierte su transacción sin persistir eliminaciones; `--apply` hace la reversión real. Revisar cambios posteriores y dependencias antes de aplicarla; no resetear secuencias ni restaurar globalmente la base.

## Incidencia concurrente observada al cierre

El snapshot inmediatamente posterior al alta preserva todos los registros anteriores. La comparación posterior al deploy detecta que Benefit 1 (Dossis 43) cambió únicamente su descripción corta y `updated_at` a `2026-10-14 23:52:24 UTC` (19:52:24 Bolivia). Antes: «Una bebida de cafetería disponible + un acompañamiento dulce o salado disponible del día por Bs 35. Pago directo en Dossis 43.» Después: «Una bebida de cafetería + un acompañamiento dulce o salado por Bs 35.»

Los scripts del lote sólo escriben los seis slugs nuevos; la repetición no escribe. El navegador bloquea todas las peticiones de mutación de producto. No se ha establecido de forma independiente el origen de este cambio. Se conservó el valor actual, sin restaurarlo ni atribuirlo al lote. No se declara igualdad global de snapshots al cierre: [diferencia exacta](post-deploy-preservation.json), [catálogo final](final-catalog.json). Los seis registros del lote, Toc Toc, El Re-Pollo, precios, imágenes y estados previos permanecen preservados. PRELAUNCH_MODE sigue true.

## Corrección mínima de privacidad

La ficha nativa de Benefits draft era accesible por URL directa aun con Partner y landing en borrador. Se añadieron guards en `ResolveProductLanding` y `benefitPublicProps`: draft devuelve 404 antes de resolver una landing o renderizar copy público. Los previews administrativos pasan explícitamente `preview=true` y conservan su autenticación. Se preservan los estados no draft y sus reglas anteriores.

Commit de corrección: `df8f543af38664c168d4465aca9d0099e5c3631c`. Pruebas relacionadas: **29 pasan / 662 aserciones** (15 de privacidad/acceso/marketing y 14 de Experiences V2), [pruebas de privacidad](privacy-tests.txt), [regresión](experience-regression-tests.txt). Pint: PASS en los tres archivos. TypeScript, build y guards de manifest/assets: PASS; candidato verificado con transacción READ ONLY. [Deploy único](deploy.json), sin migraciones, app/web/worker coherentes, DB e imgproxy sin recrear. [Privacidad nativa final](http-privacy.json): seis respuestas 404; los 6 previews administrativos se vuelven a verificar sobre el nuevo runtime.

## Cierre verificado

[Navegador final](browser.json): PASS después del deploy, con 12 renders responsive (390/1440), los 6 previews autenticados, 18 rutas privadas con 404 (Partner, landing y ficha nativa), protección del preview ante invitados, ausencia de los borradores en tres listados públicos y carrusel móvil previo funcionando. Sin errores JavaScript ni assets fallidos. Las peticiones de analítica del navegador se respondieron localmente con 204 para evitar telemetría de QA; no se enviaron mutaciones de producto.

[Build y calidad](build-summary.json), [integridad](integrity.json) y [segunda repetición sobre el nuevo LIVE](idempotence-after-deploy.json): PASS. Los precios, estados, sedes y relaciones del lote se conservan. Los seis Partners son borradores; aún no representan participación formalmente aceptada.

[Limpieza previa](qa-cleanup-before-deploy.json): retirados 2 eventos y 45 sesiones QA; quedó temporalmente una sesión administrativa para los checks del release. Se retiró además un touch anónimo de QA ID 36, sin usuario/campaña ni dependencias de conversión o referido. [Limpieza final](qa-cleanup-final.json): sin eventos ni sesiones QA identificadas remanentes. No se retiró atribución comercial ni información de usuarios. No se ejecutaron pruebas destructivas sobre los productos en producción.

Git: datos en `4aca9e4`, corrección en `df8f543`, ambos sincronizados con `origin/main` antes del despliegue. Este informe y los artefactos se guardan en el commit documental final; no requieren otro deploy. Árbol con los cuatro directorios ajenos originalmente sin seguimiento, intactos.

El archivo `runtime-rollback.yaml` conserva las imágenes exactas anteriores. Volver a `effd76e` reintroduce el defecto de privacidad nativa de Benefits draft: no usar ese retorno conservando estos borradores accesibles sin una protección equivalente. La reversión de datos acotada y la corrección hacia delante son alternativas que deben evaluarse según el incidente. Ningún rollback se ejecutó en esta entrega.
