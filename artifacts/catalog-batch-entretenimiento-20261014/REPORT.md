# JAKAWI · Lote 02 · Experiencias y entretenimiento · Cochabamba

14 de octubre de 2026, hora de Bolivia. **Lote completado en producción como catálogo privado.** Decisión comercial: **CONFIRMED BY JAKAWI**. No acredita aceptación operativa de los establecimientos.

| Partner | Partner ID | Location ID | Producto | Tipo | Producto ID | Precio | Landing ID | Preview |
|---|---:|---:|---|---|---:|---|---:|---|
| Aventura Games Cochabamba | 12 | 11 | Salta con JAKAWI | Benefit | 8 | Bs 55/persona | 12 | [Admin](https://jakawi.com/admin/beneficios/aventura-games-cochabamba-salta-con-jakawi/landings/aventura-games-cochabamba-salta-con-jakawi/preview) |
| Armagedon for Gamers | 13 | 12 | Duelo gamer | Experience | 4 | Bs 45/persona; mínimo 2, Bs 90 | 13 | [Admin](https://jakawi.com/admin/experiencias/armagedon-for-gamers-duelo-gamer/landings/armagedon-for-gamers-duelo-gamer/preview) |

## Persistencia e identidad

Creados: 2 Partners, 2 sedes principales vinculadas a Cochabamba, 1 Benefit, 1 Experience y 2 LandingPresentations: ocho registros principales, más pivots existentes del dominio. Generados: dos briefs válidos y dos landings JSON válidas. Reutilizados en la primera ejecución: 0. Repeticiones: se verificaron las ocho identidades existentes sin escrituras ni duplicados; dos de cada clase Partner, sede, producto y landing quedaron protegidas contra duplicación.

Se reutilizó `jakawi:import-catalog-v2`, `AdminLandingPresentationController::save` y `LandingAiBriefBuilder`, con transacción por Partner, bloqueo transaccional y checkpoint después de cada commit. No se agregaron tablas, migraciones, rutas, componentes ni funciones de runtime. Los scripts de este directorio son herramientas operativas del lote, siguiendo el Lote 01.

Los IDs fueron asignados por la base y resueltos por identidad; no están fijados en la fuente de importación. [Auditoría](audit.json), [duplicados](duplicate-audit.json), [dry-run](dry-run.json), [manifiesto con IDs](manifest.json), [resultados](results.json), [checkpoint](progress.json), [segunda ejecución sin escrituras](idempotence.json), [repetición con verificación completa](idempotence-final.json). La verificación repetida comprueba contenido de Partner, sede, producto y landing; rechaza cambios posteriores en lugar de sobrescribirlos.

Antes de escribir se inspeccionó Git, LIVE, el importador y los contratos reales. LIVE permanece `df8f543af38664c168d4465aca9d0099e5c3631c`, con los guards de privacidad del Lote 01. El estado comercial de partida es [before.json](before.json), no un script histórico de configuración. Incluye Toc Toc actualizado a `2026-10-14 23:34:03 UTC` y el texto actual de Dossis 43 actualizado a `2026-10-14 23:52:24 UTC`. No se restauró ninguna configuración anterior.

## Estados y controles comerciales

Partners, sedes y productos: `draft`. Revisión comercial de productos: `approved`, con notas que distinguen el diseño aprobado por JAKAWI de la operación pendiente. Landings: `DRAFT`, `default_scope=NONE`, `publish_scope=NONE`. Ningún producto fue publicado ni convertido automáticamente en Próximamente.

Benefit 8: `access_mode=authenticated`, sin membresía de pago; `estimated_savings=null`, sin precio estructurado porque ese campo no existe en Benefits. Bs 55 se conserva en descripción, condiciones, presentación y fuente comercial. No se inventó límite por cuenta: `redemption_limit_per_member=null`. Fin de vigencia propuesta: `2026-12-01T03:59:59Z`, equivalente al 30/11/2026 23:59:59 en Bolivia. No equivale a disponibilidad confirmada.

La referencia interna Bs 50 + Bs 15 = Bs 65 está marcada como no verificada únicamente en el manifiesto comercial; no aparece como precio tachado, descuento, ahorro ni comparación pública. Acceso recreativo de dos horas y un par de medias son incluidos propuestos, sujetos a confirmación. No se afirma que todas las atracciones estén incluidas ni se inventan garantías de seguridad.

Experience 4: precio estructurado `regular_price=45.00`, `currency=BOB`, `member_price=null`, `duration_minutes=null`, `reservation_method=none`, `catalog_visibility=hidden`, `operations_confirmed=false`; sin canal operativo. El mínimo propuesto de dos personas y Bs 90 para el grupo mínimo quedan en descripción, condiciones, presentación y manifiesto. El modelo no ofrece un campo de mínimo de participantes ni lo ejecuta sin operación: no se inventaron sesiones ni capacidad para representarlo. No se presentan torneo, premios, plataformas, hardware ni juegos específicos.

Ambos productos prevén cuenta JAKAWI sin membresía de pago y pago directo al Partner únicamente después de activación; JAKAWI no cobra. La vigencia comercial propuesta termina el 30 de noviembre de 2026. Los CTA editoriales solicitados quedan en el cierre; las acciones reales están deshabilitadas. No hay URLs operativas activas.

## Contenido y dirección visual

Se conservaron exactamente los contactos y redes aportados. Aventura tiene teléfono `77444462`, correo y web proporcionados. Armagedon mantiene teléfono, correo y web nulos. Ambas sedes usan las direcciones y Maps entregados, sin coordenadas inventadas; no se infiere WhatsApp ni PIN de canje.

Aventura usa `LIGHT_CONVERSION`, con tono familiar y energético; Armagedon usa `DARK_CONVERSION`, con enfoque social y lúdico. No hay recursos auténticos autorizados aportados: ambas landings usan `media.mode=NONE`. La futura portada 4:5 y sus permisos quedan pendientes. No se utilizaron fotografías genéricas ni imágenes inventadas del parque o de equipos.

El contrato efectivo sólo admite los bloques existentes. Los subtítulos, mensajes secundarios y cierres solicitados están guardados en sus campos editoriales correspondientes; el bloque de adquisición depende del contexto del usuario y no aparece en todos los previews autenticados. Los incluidos, precio, seguridad, participación y prelanzamiento están presentes en la descripción y condiciones renderizadas.

Los briefs provienen del builder real mediante el contexto del Publisher. Se añade Cochabamba desde la sede real porque el builder no la infiere de sedes privadas ni de sesiones inexistentes. No se agregan campos de contrato inventados. Las sedes privadas no se presentan como lugares publicados de ejecución. El template existente conserva algunos textos generales de canje/reserva; los avisos de preview y prelanzamiento dejan explícito que las acciones no están disponibles. Sus enlaces sólo apuntan a `#condiciones` y no habilitan operación.

## Pruebas y preservación

- [Integridad](integrity.json): identidad, contactos, ciudad, relaciones, privacidad y ausencia de operación, PASS.
- [Contratos](contracts.json): dos briefs `jakawi-product-brief-v1` generados por el builder y dos landings `jakawi-landing-v1` validadas con el parser real del repositorio, PASS.
- [Navegador LIVE](browser.json): dos previews autenticados, cuatro renders en 390/1440 px, sin overflow, errores JavaScript ni assets fallidos. Seis rutas públicas de Partner/landing/ficha devuelven 404; invitados son enviados al login al intentar previews; borradores ausentes en Explorar, Benefits y Experiences. Carrusel móvil existente de Dossis 43 funcionando.
- [CTA](action-checks.json): cuatro botones principales con `aria-disabled=true` y destino `#condiciones`; pulsarlos no navega. Precio y advertencias operativas presentes.
- [Revisión visual](visual-review.json): inspección de las cuatro capturas, PASS. Capturas en los dos directorios de Partner: `preview-390.png` y `preview-1440.png`.
- [Preservación](preservation.json): todas las filas anteriores permanecen idénticas inmediatamente después del alta. El [catálogo final](final-catalog.json) coincide íntegramente con el snapshot posterior, incluso después de repeticiones y QA. Toc Toc, El Re-Pollo, Dossis 43 y los seis Partners del Lote 01 quedan intactos. PRELAUNCH_MODE sigue true; no hay cambios de Experiences V2, carrusel ni guards.

Cero sesiones, reservas, canjes y pagos creados por este lote; cero precios de miembros inventados. La base tenía tres canjes anteriores, que se preservaron: no se declara que el catálogo histórico carezca de operaciones. Los snapshots guardan sus huellas y estados, sin códigos ni identidades de usuarios.

No se ejecutó la suite global, build ni deploy porque no hay cambios de código runtime. Se verificó la sintaxis PHP de los scripts operativos. La primera preparación del dry-run necesitó corregir permisos del directorio temporal del contenedor; no produjo escrituras de catálogo. Al reforzar la repetición se ajustó la comparación de enums a los valores persistidos del modelo; tampoco escribió datos.

QA bloqueó mutaciones de producto y respondió localmente las peticiones de analítica del frontend. Se retiraron y verificaron exclusivamente 13 sesiones QA y un evento QA generado en servidor: [limpieza](qa-cleanup.json). Sin eventos ni sesiones QA identificadas remanentes; sin modificaciones de información comercial.

## Pendientes por Partner

**Aventura Games:** confirmar días/horarios, atracciones elegibles, reglas de uso, restricciones reales de edad/estatura/capacidad, supervisión y equipamiento, duración autorizada, medias disponibles, acceso/canje y condiciones de responsabilidad/seguridad. Verificar actualidad y comparabilidad de la referencia de precios antes de comunicar descuentos. Recursos visuales auténticos autorizados pendientes.

**Armagedon:** confirmar equipos, plataformas/juegos, duración, capacidad/puestos, modalidad del duelo, snack, tarifas, horarios, coordinación/reservas/acceso y responsable operativo. Teléfono, correo, web y recursos visuales autorizados no aportados; se mantienen pendientes sin bloquear la propuesta privada.

El detalle se conserva en [pending-operations.md](pending-operations.md). Primero catálogo, después operación: no se solicitó aprobación comercial adicional.

## Recuperación, Git y entrega

[Backup PostgreSQL validado](backup.json), fuera de Git: `/root/backups/jakawi/catalog-batch-entretenimiento-20261014/before.dump`, con SHA-256 y `pg_restore --list` correcto. [Plan de reversión limitada](rollback-plan.md) y [script](rollback.php): por IDs y snapshots exactos, rechazando cambios y dependencias posteriores; nunca restaurar globalmente la base para retirar un producto. No se ejecutó rollback ni ensayo destructivo en producción.

Fuente reproducible: [app/database/catalog/entretenimiento-20261014](../../app/database/catalog/entretenimiento-20261014/README.md). Commit de datos: [7d43da5](https://github.com/soyjavierquiroz/jakawi-100/commit/7d43da5). Este informe, manifiesto resuelto, scripts y evidencia se versionan en el commit documental que contiene este archivo; ambos commits se sincronizan con `origin/main` al cierre. No hubo deploy ni migraciones. Los cuatro directorios de artefactos ajenos inicialmente sin seguimiento se preservan intactos.
