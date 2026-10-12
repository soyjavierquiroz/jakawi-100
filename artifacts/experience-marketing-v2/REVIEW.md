# Experience Marketing V2 — revisión local

> Histórico V2: la revisión local actual es [Experience Offer V3](../experience-marketing-v3/REVIEW.md). Estas capturas V2 se conservan como referencia; el preview de source refleja los cambios actuales.

Implementación local; el sitio publicado todavía muestra la versión anterior. Preview Vite: http://127.0.0.1:8778/l/experience. No build, deploy, push ni commit.

## Verdad inspeccionada

Fuente: respuesta real de `/l/qa-experience-marketing-sunset-jakawi`, consulta de solo lectura del Experience `demo-sunset-jakawi` y LandingPresentation ID 3, y lógica existente del presenter/reservas. No se inspeccionaron otros productos.

- Título: Sunset JAKAWI. Tipo: event. Categoría: entertainment.
- Descripción corta: “Música suave y un atardecer compartido.”
- Descripción larga: “Una tarde para encontrarnos en altura con vista a la ciudad.”
- Ciudad: Cochabamba. Lugar: Mirador Cobalto. Dirección: Calle Demo 27, Alalay.
- Próxima sesión: ID 3, 18/10/2026, 13:00–16:00 America/La_Paz. Duración configurada: 180 minutos. Existe una sesión anterior del 27/09/2026; no se promociona como próxima.
- Capacidad de la próxima sesión: 45. No es un conteo de lugares restantes. Disponibilidad real: consultar al partner; no hay conteo verificable en la landing.
- Precio normal: BOB 80.00. Miembro JAKAWI: BOB 60.00. No se añadió una promesa de ahorro ni cambios económicos.
- Estado published. Reserva whatsapp, contacto configurado +59170030001; sin URL ni teléfono adicional. La CTA real lleva a Product Detail, donde se continúa con el partner. No se reserva automáticamente al hacer clic en la landing.
- Reserva previa requerida; términos: “Cupo limitado; reserva previa requerida.” Sin reglas de cancelación adicionales publicadas en esta fixture.
- No se exige membresía para el flujo externo de esta QA. El precio de miembro es un dato publicado, no una concesión de elegibilidad nueva.
- Organiza Trama Viva; provider Brasa Prisma; host Andes Umbral. No se dedujeron servicios incluidos de estos roles ni de la fotografía.
- Media: cover existente `experiences/1/cover/18dd0ac9-5362-476f-b6d0-3eed8737bf97.png`; image_path adicional `experiences/1/cover/9c851cfa-f4d8-4e2e-83cf-ca657ab3dbae.png`. Hero público generado por imgproxy desde el cover. VSL null.
- Presentación: PUBLISHED, default_scope NONE, campaign_key null, noindex true. Hero override apunta al cover, alt Sunset JAKAWI. Eyebrow EXPERIENCIA JAKAWI; headline MÚSICA SUAVE Y UN ATARDECER COMPARTIDO; subheadline Una tarde para encontrarnos en altura con vista a la ciudad.; reward override null; final ¿LISTO PARA VIVIRLO? El filtro editorial existente rechaza la subheadline por su palabra “en”, por lo que la respuesta pública usa la descripción corta. No se cambió ese filtro ni los registros.
- FAQ existente preservada: ¿Cómo reservo? (continúa desde ficha; gestión con partner); ¿Qué día y a qué hora? (18/10/2026 a las 13:00); ¿Dónde es? (Mirador Cobalto · Calle Demo 27, Alalay · Cochabamba).

## Cambio visual

Antes: header de navegación, imagen en contenedor redondeado antes del titular, hero genérico con aside operativo, franja verde de datos y secciones de reserva abiertas.

Después: SUNSET JAKAWI / COCHABAMBA → MÚSICA SUAVE. / COMPARTE EL ATARDECER. → descripción larga real → fotografía dominante → fecha/hora/lugar → precios → QUIERO IR. Solo “COMPARTE EL ATARDECER” lleva énfasis coral. Copy editorial acotada a ID 3 + slug de presentación + slug de Experience; no se impone a otras experiencias.

“Lo que vas a vivir”: “La ciudad, vista desde otra altura.” + música suave, vista a la ciudad, atardecer compartido. Toda afirmación procede de las dos descripciones existentes. No promete alimentos, bebidas, transporte, guía ni amenidades.

Detalles: fecha completa, hora de fin, dirección y capacidad total abajo. Reserva, términos y partners en disclosure nativo. FAQ compartida con preguntas ink, respuestas secundarias y controles verdes scoped. Final ink/cream/coral: “Haz del atardecer tu próximo plan.”

CTA y handlers existentes preservados: guest POST con sessionId para el flujo interno, membership conserva journey intent, native continúa en ficha, reserva existente muestra VER MI RESERVA, unavailable/preview desactivados. Los estados adicionales se revisaron como fixtures visuales, sin crear reservas ni ejecutar transacciones.

Sticky: no implementado. El único comportamiento existente está encapsulado en Challenge; no hay un primitivo compartido. Extraerlo requeriría tocar Challenge, y duplicarlo sería un segundo sistema. Se respetaron ambas restricciones.

## Evidencia de QA

Capturas `experience-{390,430,1440}-{viewport,full,desire,final}.png`, más `experience-no-media-390.png` y `experience-vsl-390.png`. Métricas en browser-report.json.

- 390×844: headline 42.4 px, cuerpo 16 px, datos 14 px, CTA 16 px. Media 350×262.5; CTA y=749.14, alto 52, bottom=801.14. Todo el CTA entra en primera pantalla. Cero cards con radius-card; sin overflow.
- 430×844: media 390×292.5; CTA bottom=831.14, visible completa. Sin overflow.
- 1440×1000: headline 64 px; texto 18 px; media 620.4×620.4. Composición asimétrica con texto/acción a izquierda y foto a derecha. Sin anchos vacíos excesivos ni overflow.
- LIGHT_CONVERSION explícito, sin header de Product UI ni toggle.
- FAQ abre por teclado. Cero pageerrors. Imagen cargada.
- No-media: sin wrapper vacío. VSL: usa LandingVideo/JakawiVslPlayer existente, con una configuración Bunny preexistente usada solo en la fixture local; no se añadió VSL a la QA real ni se probaron analytics de video.
- Precios: texto ink; verde como línea de beneficio confirmado. Coral para acción/énfasis. Yellow no utilizado. Ningún color nuevo.
- TypeScript: `./app/node_modules/.bin/tsc --noEmit -p app/tsconfig.json` PASS.
- `git diff --check` PASS.
- Browser/Vite sobre source actual PASS. Props públicas reales, con endTime y capacity añadidos al fixture local desde los datos DB inspeccionados para representar los dos campos nuevos del presenter. No se ejecutó validación backend PHP en esta fase.
- Docker build NOT RUN INTENTIONALLY. No test:vsl; sin dependencias nuevas.

## Template discovery — análisis, sin engine ni extracciones

| Sección | Clasificación | Evidencia |
|---|---|---|
| MarketingHero | SHOULD BECOME SHARED LATER | Existe hero genérico, pero el orden desire → media → facts → CTA y el desktop editorial necesitan estabilizarse. |
| MarketingMedia | ALREADY SHARED / REUSABLE | LandingVideo/JakawiVslPlayer y JakawiImage; selección opcional local. |
| MarketingFactStrip | SHOULD BECOME SHARED LATER | dl compacto con roles factuales; contenidos de sesión siguen siendo del dominio. |
| MarketingPrimaryCTA | ALREADY SHARED / REUSABLE | MarketingCTA y tracking delegado existentes. |
| MarketingStickyCTA | SHOULD BECOME SHARED LATER | Solo hay implementación local en Challenge; no hay API compartida disponible. |
| MarketingValueBlock | SHOULD BECOME SHARED LATER | Comparación de precios confirmados con acento verde; no requiere cálculo de ahorro. |
| MarketingEditorialSection | SHOULD BECOME SHARED LATER | Statement y puntos abiertos, sin cards; requiere más iteración visual antes de extracción. |
| Reserva/fechas/condiciones/partners | EXPERIENCE-SPECIFIC | Sesiones, gestión externa/interna, elegibilidad, capacidad y estados propios. |
| MarketingFAQ | ALREADY SHARED / REUSABLE | Componente compartido existente; refinamiento visual scoped a Experience. |
| MarketingFinalCTA | SHOULD BECOME SHARED LATER | CTA compartida; composición oscura y texto editorial aún locales. |

Challenge action-driven: reto → premio → pasos → calificación/reglas → participación. Experience desire-driven: momento → ciudad/media → hechos → acción → vivencia → operaciones. Esqueleto común: theme/layout, media opcional, CTA state-aware, contenido editorial, detalles/FAQ y cierre; tracking actual. Challenge-specific: evidencia social, calificación/selección, premio, grant y participación embebida. Experience-specific: sesión/lugar, precios de acceso/miembro, reserva con partner o interna, capacidad y condiciones de asistencia.

## Seguridad

Product Detail, Challenge, Benefit, Unlock, economía, Growth, campaign_key, player internals, rutas, redirects y migraciones sin cambios. El presenter únicamente expone endTime y capacity existentes. LandingPresentation DB sin modificaciones. No PHP host, composer, artisan ni vendor/bin host.

EXPERIENCE MARKETING V2 READY FOR VISUAL REVIEW
