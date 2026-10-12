# Experience Offer V3 — revisión local

V3 conserva la base visual de V2 y cambia la jerarquía a oferta → media → hechos/precios → acción → deseo → operaciones/FAQ → decisión. No está aprobada ni publicada. Sin commit, push, deploy ni Docker build.

Preview local Vite: http://127.0.0.1:8779/l/experience

Capturas: [390 first viewport](experience-390-viewport.png), [430 first viewport](experience-430-viewport.png), [1440 first viewport](experience-1440-viewport.png), [390 offer + objections](experience-390-offer.png), [390 full page](experience-390-full.png). Se conservan las capturas V2 para comparar.

## A) OFFER

- Headline: **3 HORAS PARA VIVIR COCHABAMBA DESDE ARRIBA.** La duración publicada de 180 minutos y la sesión 13:00–16:00 respaldan las tres horas. El tratamiento requiere ID 3, ambos slugs QA, duración 180 y ciudad Cochabamba; no se aplica a otras Experiences.
- Un único énfasis coral: COCHABAMBA. El resto del titular usa ink para controlar la superficie coral.
- Promesa: “Música suave, vista a la ciudad y un atardecer compartido en Mirador Cobalto.”
- Fecha/hora: 18/10/2026, 13:00–16:00, America/La_Paz. Franja compacta: 18 OCT / 13:00–16:00 / Mirador Cobalto. El año permanece en operaciones y FAQ.
- Precio normal: BOB 80. Miembro JAKAWI: BOB 60. Solo se elimina el decimal .00 de presentación; importes originales intactos. Bloque de dos columnas, labels 14 px y precios 30 px en ink. Sin verde, porcentajes ni ahorro realizado.
- CTA: VER FECHAS Y RESERVAR.

## B) IMAGE TRUTH

Solo se visualizaron los dos assets existentes, sin descargar nueva imaginería externa, generar imágenes, crear gallery ni modificar assets del producto:

| Campo | Key existente | Contenido observado |
|---|---|---|
| cover | experiences/1/cover/18dd0ac9-5362-476f-b6d0-3eed8737bf97.png | Atardecer sobre ciudad y montañas; personas brindando con copas/cócteles; mesa con comida |
| image_path | experiences/1/cover/9c851cfa-f4d8-4e2e-83cf-ca657ab3dbae.png | Misma escena de atardecer, brindis y comida |

Las capturas del render imgproxy de ambos assets resultaron idénticas byte a byte. Evidencia: image-audit-cover.png e image-audit-alternate.png.

**Seleccionado: cover autoritativo actual.** Hero override y registros sin cambios. **Riesgo visual explícito:** la imagen puede sugerir comida, bebidas o cena incluidas; la descripción y la oferta no establecen ninguna de esas inclusiones. La alternativa no reduce ese riesgo. No se convirtieron sus elementos visuales en claims.

## C) HERO

Antes V2: “MÚSICA SUAVE. COMPARTE EL ATARDECER.” + copy de ambiente, precios discretos y QUIERO IR.

Después V3: duración y ciudad en headline → promesa específica con venue → misma fotografía dominante → fecha/hora completa/lugar → comparación de precios fuerte → CTA preciso → explicación de reserva y acceso sin membresía.

Se conserva el hero asimétrico desktop, media sin tarjeta exterior, LIGHT_CONVERSION explícito y ausencia de Product UI header/toggle. No se reduce legibilidad para forzar datos dentro del fold.

## D) CTA

- Previous: QUIERO IR.
- New: VER FECHAS Y RESERVAR, para acciones native cuyo label de dominio era QUIERO IR o RESERVAR.
- Actual next action: abrir Product Detail y continuar con el partner. No confirma una reserva al hacer clic.
- Estado existing reservation: VER MI RESERVA intacto.
- Unavailable y preview: desactivados; sin navegación ni POST.
- Membership: ACTIVAR MEMBRESÍA / ELEGIR FECHA Y ACTIVAR MEMBRESÍA intactos.
- Guest del flujo interno: QUIERO IR y POST con experience_session_id intactos.
- Native guest/auth de esta QA externa: VER FECHAS Y RESERVAR.
- Hero y final usan el mismo objeto action. No cambio de destino, handler, sessionId ni reglas del presenter.

## E) OBJECTIONS

Inmediatamente debajo de CTA:

“La reserva continúa en la ficha y se gestiona con el partner.”

“No necesitas membresía para asistir.”

El segundo mensaje se condiciona al reservationMethod externo proporcionado por el presenter. No aparece para el flujo interno de membresía. No hay claims de disponibilidad, inventario restante ni urgencia.

## F) DESIRE

POR QUÉ ESTE PLAN, tres filas editoriales sin cards:

1. MÚSICA SUAVE — El ritmo de una tarde compartida.
2. VISTA A LA CIUDAD — Un encuentro en altura.
3. ATARDECER COMPARTIDO — Una tarde para encontrarnos.

El contenido procede de las descripciones existentes. Se elimina el statement duplicado de V2 y la repetición íntegra de la descripción en esta sección. Invented claims: NO.

## G) OPERATIONS

- Título: ANTES DE RESERVAR.
- Dirección exacta, fecha completa, hora final y capacidad permanecen abajo.
- Wording: “Capacidad de la sesión: 45 personas.” No son 45 lugares restantes.
- Disclosures: Cómo reservar; Condiciones completas; Quién lo hace posible.
- La condición de reserva previa permanece. Para esta fixture se presenta “Reserva previa requerida.” en lugar de promocionar el prefijo “Cupo limitado”; el texto del registro de producto no se modifica y sigue disponible en Product Detail. No se usa como urgencia ni disponibilidad.
- FAQ compartida conserva Cómo reservo / Cuándo / Dónde. Respuestas cerradas inicialmente; preguntas ink, respuestas secundarias, controles circulares verdes funcionales.
- Final dark mantiene “Haz del atardecer tu próximo plan.”; la frase de apoyo ahora pide consultar ficha y continuar la reserva, sin otro párrafo de mood. Mismo CTA preciso.

## H) MOBILE

- 390×844: headline 42.4 px; body 16 px; metadata/objeciones 14 px; CTA 16 px. Imagen 350×262.5. CTA y=760.19, bottom=812.19: completa en primera pantalla.
- 430×844: imagen 390×292.5. CTA y=790.19, bottom=842.19: completa en primera pantalla.
- Primera pantalla: promesa concreta, media dominante, horario, lugar, precios fuertes y acción precisa.
- Captura offer adicional muestra las objeciones inmediatamente debajo del botón.
- Cero cards operativas y cero overflow. La media mantiene 4:3; el ajuste consistió en espaciado vertical.

## I) DESKTOP

1440×1000: headline 64 px, body 18 px, fotografía 620.4×620.4. Izquierda dedicada a promesa, hechos, precios y acción; fotografía grande a derecha. Los precios son parte visible de la oferta, con CTA full-width dentro de la columna. Sin exceso de ancho vacío.

## J) TEMPLATE DISCOVERY — análisis únicamente

READY significa que hay evidencia suficiente para proponer la extracción futura del bloque acotado; no que se haya extraído en V3.

| BLOCK | READY / NEEDS BENEFIT / DOMAIN SPECIFIC | EVIDENCE |
|---|---|---|
| OfferHero | NEEDS BENEFIT | Challenge enfatiza acción/premio; Experience duración/precio/lugar. El orden modular converge, pero Benefit debe comprobar la API antes de fijarla. |
| OptionalMarketingMedia | READY | Ambos renderers ya usan LandingVideo/JakawiImage y una condición de media opcional. V3 valida imagen, VSL y ausencia sin wrapper vacío. |
| MarketingFactStrip | NEEDS BENEFIT | Experience usa fecha/hora/venue; Challenge usa deadline/mecánica. Hace falta confirmar qué facts son universales frente a un payload editorial. |
| MarketingPriceBlock | NEEDS BENEFIT | Experience demuestra precio normal/miembro; Challenge demuestra recompensa, que no debe forzarse a una API de precio. Benefit aportaría la segunda evidencia de comparación económica. |
| MarketingPrimaryCTA | READY | MarketingCTA ya compartido; eventos y action objects state-aware en ambos. Handlers pertenecen al dominio. |
| MarketingStickyCTA | NEEDS BENEFIT | Solo existe comportamiento local en Challenge. Experience no lo duplica ni altera Challenge; falta evidencia de integración compartida. |
| MarketingSteps | READY | Ambos tienen secuencias title/description numeradas. La estructura de pasos es candidata; los pasos y requisitos concretos permanecen en adapters de dominio. |
| MarketingEditorialPoints | NEEDS BENEFIT | Experience tiene razones experienciales; Challenge mezcla pasos, reglas y confidence. Benefit debe confirmar que existe un patrón independiente de pasos/operaciones. |
| MarketingOperationalDetails | DOMAIN SPECIFIC | Reserva, sesiones y capacidad en Experience; evidencia social, calificación y selección en Challenge. Wrapper disclosure reutilizable, pero schema y reglas propios. |
| MarketingFAQ | READY | Ya existe MarketingFAQ compartido; ambos usan preguntas ink/respuestas secundarias y controles funcionales. |
| MarketingFinalCTA | READY | Ambos cierran con superficie ink, supporting copy y el mismo action state-aware del hero. Copy y estado proceden del dominio. |

No engine, nuevas abstracciones compartidas ni extracciones.

## K) TEMPLATE DIRECTION

**Hipótesis preliminar: B — una o dos plantillas de conversión con bloques específicos del dominio.**

Evidencia: ambas páginas necesitan oferta/promesa, media opcional, acción state-aware, reducción de incertidumbre, explicación, FAQ y cierre. Cambia la prioridad: Challenge lleva a participar con premio/calificación; Experience lleva a consultar y reservar una vivencia con precio/fecha/lugar. La mayor divergencia se concentra en la acción y operaciones del dominio, no en un esqueleto completo por producto. Benefit puede confirmar si una plantilla configurable basta o hacen falta dos variantes de jerarquía. No hay evidencia suficiente para cerrar la decisión aún.

## L) SAFETY

Product Detail, economía, reglas/semántica de reserva, Growth taxonomy, campaign_key, player internals, dependencias, migraciones, Challenge, Benefit y Unlock: NO CHANGE.

El presenter añade únicamente datos existentes: durationMinutes/reservationMethod en V3; endTime/capacity ya añadidos en V2. Sus ramas de acción y elegibilidad permanecen intactas. No cambios DB, assets, rutas ni redirects.

## M) VALIDATION

- TypeScript: `./app/node_modules/.bin/tsc --noEmit -p app/tsconfig.json` PASS.
- Vite/browser: PASS en 390, 430 y 1440; cero pageerrors; imagen cargada; FAQ por teclado.
- Opcionales: imagen real, no-media y configuración VSL Bunny de QA preexistente usan los componentes existentes. No se configura VSL en la Experience real.
- Estados visuales: unavailable, reserved, membership, guest, preview, authenticated, selección de fecha/membresía y ausencia de próximas sesiones.
- Handlers en navegador con requests interceptadas localmente: guest conserva POST con sessionId 3; preview/unavailable bloquean acción; membership journey intent se emite una vez entre hero y final. Growth conserva endpoints/kind/location existentes. Ninguna request de prueba llega a una reserva, analytics o membresía reales.
- Evidencia: browser-report.json y action-report.json.
- La revisión Vite consume props de la landing real con los campos expuestos añadidos al fixture desde la verdad autoritativa. No se ejecutó backend PHP ni build para esta fase visual.
- git diff --check: PASS.
- Docker: NOT RUN. No host PHP, composer, artisan, vendor/bin ni test:vsl. Sin commit, push ni deploy.

## N) RESULTADO

EXPERIENCE OFFER V3 READY FOR VISUAL REVIEW
