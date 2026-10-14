# Toc Toc — Descubre el café boliviano

Decisión comercial: **CONFIRMED BY JAKAWI**. Experience independiente del Partner `toctoccafe` (ID 3), en Cochabamba. Bs 75 por persona, cuenta JAKAWI y sin membresía, pago directo al Partner, una participación por cuenta, vigencia comercial hasta el 30 de noviembre de 2026 (America/La_Paz).

## Configuración existente

[Producto](../../app/database/catalog/toctoc-experience/product.json) y [landing editorial jakawi-landing-v1](../../app/database/catalog/toctoc-experience/landing.json). `Experience.status=draft`, `review_status=approved`, LandingPresentation `DRAFT`, `default_scope=NONE`, `publish_scope=ALL`. Sin ExperienceSession, sin fechas ni capacidad inventadas. `duration_minutes=null`: los 15–20 minutos propuestos corresponden a la explicación, no a la duración total.

`reservation_method=none` es el estado informativo existente mientras no se conozca un canal verificado. No elegir `jakawi`: la reserva interna exige membresía y no satisface esta oferta. El piloto admite coordinación externa; no hay registro de interés para Experiences. La landing utiliza OFFER_CONVERSION / LIGHT_CONVERSION / MEDIA_LED y componentes nativos. La portada se hereda del perfil existente del Partner, como visual editorial, sin atribuirle documentación de esta sesión ni generar imágenes.

CTA comercial: **QUIERO DESCUBRIRLO**, conservado en cierre y acquisition headline. El contrato no permite sobrescribir acciones ni URLs. La acción real actual es **NO DISPONIBLE**, deshabilitada por el adapter; el preview también deshabilita todas las acciones. No implementar otra mecánica para forzar el CTA. Cuando corresponda activar, el adapter existente usa su etiqueta nativa y el flujo externo de acceso → elección de sesión → Delivery → contacto con el Partner.

El brief se obtiene de LandingPresentationEditor/LandingAiBriefBuilder; se añade `city` desde la ubicación primaria real porque no existen sesiones para inferirla. El contrato omite membresía estructurada para modos informativos/externos: cuenta, no membresía, precio, pago, límite y vigencia constan en las condiciones persistidas. No agregar atributos JSON ni columnas para suplir esa limitación.

Experience externo no posee campos ejecutables para fecha de vigencia o límite por cuenta. La landing solicita acceso, pero el endpoint externo de redirección es público; el Partner debe verificar la cuenta JAKAWI al coordinar y entregar. El Partner debe controlar una participación por cuenta y JAKAWI debe pausar/despublicar al cierre de vigencia; no prometer control automático ni asistencia registrada. El estado draft bloquea la publicación de la landing mediante el Publisher. Admin aún puede cambiar el producto manualmente: no se añade un bloqueo global de publicación.

## Operación de alta

[Script acotado](../../app/scripts/configure-toctoc-experience.php): valida ExperienceRules, exige Partner existente, rechaza sobrescrituras, asocia organizer y guarda la landing mediante AdminLandingPresentationController. Ejecutar con PHP 8.4 Docker desde la raíz Laravel, pasando producto y patch obtenido del parser real `parseLandingJson`. Importar no autoriza publicar. El script guarda únicamente borradores y devuelve IDs/brief. No es seeder ni migración; no cambia Partner, Location, Benefits, configuración ni sesiones.

Backup PostgreSQL custom validado antes del alta; rollback conservador para esta operación: conservar o archivar ambos borradores mediante los editores existentes. No restaurar toda la base para retirar dos borradores. No se requiere deploy de runtime: los datos utilizan las capacidades LIVE actuales. PRELAUNCH_MODE y PrelaunchNotice se preservan.

## Hechos operativos por confirmar antes de activar

- Preparaciones concretas, guía responsable y explicación de 15–20 minutos, bocado dulce y capacidad real de hasta 8 personas.
- Fecha, horario, duración total y lugar de una sesión realizable dentro de la vigencia.
- Canal y responsable de coordinación externa, confirmación de participación y entrega; procedimiento operativo para verificar la cuenta, controlar una participación por cuenta y cerrar al fin de vigencia.

No hay aprobación comercial pendiente.

## Resultado guardado

Experience **1**, landing **5**, Partner **3**. Slug independiente: `toctoccafe-descubre-el-cafe-boliviano`. [Preview Admin autenticado](https://jakawi.com/admin/experiencias/toctoccafe-descubre-el-cafe-boliviano/landings/toctoccafe-descubre-el-cafe-boliviano/preview). Landing pública retirada/no publicada: `/l/toctoccafe-descubre-el-cafe-boliviano` devuelve 404. Endpoint externo de reserva también devuelve 404. La ficha nativa informa indisponibilidad.

[Brief generado](../../artifacts/toctoc-experience/brief.json), [validación](../../artifacts/toctoc-experience/validation.json) y [QA de navegador](../../artifacts/toctoc-experience/browser.json). Mobile 320/375/390/430 y desktop 1440 sin overflow ni imágenes rotas; precio, condiciones y acción deshabilitada verificados. El precio numérico nativo usa ISO `BOB 75`; el cierre y condiciones muestran `Bs 75 por persona`.

TypeScript/build/manifest PASS; importador 14 PASS; prueba específica 1 PASS / 51 assertions. Regresión seleccionada: 110 PASS y una falla previa en Unlock VSL (`LandingPresentationTest::test_video_configuration_is_nullable_and_projected_only_when_unlock_is_revealable`); los archivos de runtime y test involucrados coinciden con el HEAD de partida. No cambiar ese dominio para dar de alta esta Experience. Se detectaron además condiciones preexistentes contradictorias en El Re-Pollo; no se copiaron ni modificaron.

Alta de datos solamente: sin deploy, migraciones ni recreación de servicios. LIVE app/web verificado por labels: `baead48dc695215eb5eb4384c69e96f281fce03a`. El SHA publicado del repositorio para esta configuración será posterior y no debe confundirse con LIVE. Backup validado y método de retiro conservador registrados en la validación.
