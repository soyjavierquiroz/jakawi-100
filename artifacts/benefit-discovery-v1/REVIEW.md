# JAKAWI — Benefit Discovery V1

Una propuesta local de VALUE → CLARITY → ACTION. Implementación pendiente de revisión; sin publicación. El contrato y los adapters de este informe son análisis, no código implementado.

Preview Vite: http://127.0.0.1:8780/l/benefit

Capturas: [390](benefit-390-viewport.png) · [430](benefit-430-viewport.png) · [1440](benefit-1440-viewport.png) · [oferta 390](benefit-390-offer.png) · [página completa](benefit-390-full.png) · [sin media](benefit-no-media-390.png).

## A) PRECHECK

- HEAD inicial: `76207eada001ce76da8df12f3defc8a90c133479`.
- Prototipos sin commit: Experience V3, con cambios heredados de V2 y sus evidencias de comparación. Cuatro archivos tracked y artifacts V2/V3. Sin cambios ajenos.
- Challenge V4.2 ya estaba en HEAD (`feat(landings): finalize challenge mobile conversion polish`).
- Commit local de preservación: `1bbecc0` — `feat(landings): preserve marketing landing prototypes`.
- Pushed: **NO**. Deploy: **NO**.
- Árbol limpio antes de Benefit: **SÍ**, verificado después del commit.
- Benefit queda en working tree. No segundo commit.

## B) BENEFIT TRUTH

Solo se consultó LandingPresentation ID 2 / slug `qa-benefit-marketing-demo-beneficio-01`, su subject Benefit ID 1 y sus sucursales aplicables. Fuentes: GET local de la landing existente y lectura Eloquent por PHP del contenedor del proyecto; sin PHP host. [Registro y presentación](subject-truth.json), [props de la landing](landing-truth.json).

- Title: **Café de cortesía con tu brunch**; slug `demo-beneficio-01`.
- Partner: **Altura Nube Café**; categoría `cafe`.
- City/location: **Cochabamba**. Altura Nube Cala Cala, Recoleta y Tiquipaya; todas las sucursales aplicables del partner (`applies_to_all_locations=true`). Direcciones respectivas: Calle Demo 11, Cala Cala; Calle Demo 12, Recoleta; Calle Demo 13, Tiquipaya.
- Short description: “Un beneficio para miembros JAKAWI en tu próxima visita.”
- Long description: “Disfruta este detalle pensado para descubrir el espacio con más calma y valor.”
- Benefit type: `free_item`. Mecanismo contractual textual: café de cortesía **con tu brunch**. No se afirma brunch gratuito ni elección libre de cualquier café.
- Price: **no hay precio contractual** ni campos de precio normal/miembro o descuento numérico. `estimated_savings=15.00` existe como estimación; no se usa como valor de oferta ni ahorro realizado.
- Membership: membresía JAKAWI activa requerida. `access_mode=public` permite descubrimiento público; no significa canje sin membresía.
- Validity, America/La_Paz: **25/08/2026 12:21 → 25/03/2027 12:21**. Hero muestra fin completo; inicio permanece en restricciones.
- Limits: `redemption_limit_per_member=null`; no se anuncia uso ilimitado. Sujeto a disponibilidad del lugar y no acumulable con otras promociones.
- Eligibility: usuario autenticado/verificado, membresía activa, beneficio/partner disponibles, sucursal aplicable y habilitada para validar el canje; el servicio comprueba estas condiciones. Ningún grant necesario para esta fixture pública.
- Redemption: revisar ficha, elegir sucursal aplicable y activar al estar listo para usar; se genera código pendiente que valida el partner. Generación no equivale a confirmación.
- State: Benefit `published`; landing `PUBLISHED`; disponibilidad de landing `available=true`. `review_status=draft` también permanece tal cual en el registro. No se convierte disponibilidad de landing en garantía de existencias del local.
- Media: imagen configurada `benefits/1/cover/248fcdcc-bb3b-415a-9809-9a9326e8e08b.png`; misma key en hero_path/image_path. Café sobre mesa, personas y ciudad/montañas al fondo. No se promete método de preparación, tamaño, variedad ni otros elementos de la foto. **VSL null**.
- CTA original guest: `QUIERO ESTE BENEFICIO`, POST `/beneficios/demo-beneficio-01/canjear`. Native miembro: ficha; no miembro autenticado: activar membresía; unavailable: disabled; preview: disabled.
- Conditions exactas: “Válido para membresías activas. Sujeto a disponibilidad del lugar; no acumulable con otras promociones.”
- FAQ existente: cómo usar, membresía, sucursales y vigencia. Se conserva el array del presenter.
- Presentation overrides existentes: eyebrow `BENEFICIO JAKAWI`; headline igual al título; subheadline igual a short description; hero configurado; hero_alt igual al título; reward_display_override null; final_cta_headline `¿LISTO PARA APROVECHARLO?`; VSL null. `default_scope=NONE`, `campaign_key=NULL`. Sin modificar ninguno.

**Límite operativo preexistente:** el handler guest manda POST sin `location_id`; la ruta requiere auth/verified y el controlador exige ese campo. Se preserva el handler por alcance. El prototipo no prueba que el POST produzca un canje tras autenticación ni que retome automáticamente el flujo. Resolver esa continuidad pertenece al dominio; el futuro contrato no debe ocultarla.

## C) HERO

- Offer: café de cortesía con tu brunch, para membresías activas.
- Headline: **TU BRUNCH, CON CAFÉ DE CORTESÍA.**
- Highlight: una única frase coral, **CAFÉ DE CORTESÍA**.
- Partner: **BENEFICIO EN ALTURA NUBE CAFÉ**, antes del titular.
- Subheadline: **Para miembros JAKAWI.**
- Media: imagen autoritativa grande, 4:3 mobile y cuadrada desktop. Sin card ni wrapper vacío.
- Orden: valor/titular → media → mecanismo textual → lugar/vigencia → brunch/membresía → CTA → explicación.
- Tratamiento editorial específico condicionado a presentación ID 2, slug QA, slug del subject y título exacto. Otras fixtures usan el copy existente, sin recibir la oferta del café.

## D) VALUE

- Exact mechanism: `free_item`, café de cortesía condicionado al brunch. Bloque ink “Qué obtienes / Café de cortesía”; condición inmediatamente antes del CTA.
- Invented savings: **NO**. No Bs 15, porcentajes, precio tachado ni importe “ahorrado”.
- Green misuse: **NO**. Oferta en ink/coral/cream. Verde únicamente en controles funcionales circulares de FAQ. No estado redeemed inventado.
- No reward amarillo necesario para esta oferta.

## E) CTA

- Guest label: **CONTINUAR CON MI CUENTA**. Conserva POST, endpoint y payload vacío; autenticación es el siguiente requisito, no confirmación de canje.
- Active member native label: **VER CÓMO CANJEAR**, conserva destino Product Detail.
- Membership: **ACTIVAR MEMBRESÍA**, conserva journey BENEFIT/action REDEEM/resource ID y handler de intent.
- Grant-only, fuera de esta fixture: label/destino **VER MI PREMIO** se conservan.
- Unavailable: razón del dominio y bloqueo intactos. Preview: acción desactivada.
- Hero/final: mismo objeto action state-aware, coral con texto ink. No cambios de handlers, kind ni analytics.
- Fricción cercana: “Continúa con tu cuenta JAKAWI. El canje requiere membresía activa. El partner valida tu código.” No promesa de canje en un clic ni continuidad automática.

## F) HOW TO USE

Se renderizan los pasos existentes, sin fijar su cantidad en el título:

1. **CREA TU CUENTA** — continúa con tu cuenta JAKAWI; membresía activa requerida.
2. **VISITA EL PARTNER** — acércate a una sucursal aplicable de Altura Nube Café.
3. **CANJEA** — activa desde la ficha al estar listo para usar; el partner valida el código.

Source truth: `BenefitMarketingLandingPresenter::present`, sus ramas de usuario/membresía/grant, ruta protegida y `RedemptionController` / `RedemptionService`. Pasos renderizados desde `copy.steps`, sin duplicar reglas en el componente. Cantidad libre.

## G) CONDITIONS

- Essential above: café con brunch, partner, ciudad, fin de vigencia y membresía activa.
- Detailed below: sucursales/direcciones, inicio de vigencia, reglas completas y no acumulación/disponibilidad del lugar. No se eliminan condiciones ni descripción.
- Progressive disclosure: “Vigencia y requisitos” + “Condiciones completas”; enlace a ficha nativa.
- No cards administrativas, inventario ni urgencia. Límite nulo no produce claim de uso ilimitado.

## H) MOBILE

- 390×844: oferta, partner, imagen, ciudad, vigencia, requisito y CTA completo; bottom CTA **812.14 px**.
- 430×844: mismo orden; bottom CTA **818.14 px**.
- First-view clarity: café de cortesía **con brunch**, membresía requerida y acción de cuenta. La explicación posterior puede requerir scroll; no se fuerza todo dentro del fold.
- Overflow: **NO**. Body 16 px; facts/metadata 14 px; CTA 16 px. Sin reducir texto para encajar condiciones.

## I) DESKTOP

- 1440×1000: titular/oferta y acción izquierda; fotografía aproximadamente 620×620 derecha.
- Composición asimétrica, espacio editorial, facts en franja, valor textual dominante. Cierre oscuro con el mismo action.
- Final headline: **TU BRUNCH MERECE ESE CAFÉ.** Apoyo: oferta en partner y requisito de membresía. Sin urgencia.

## J) TEMPLATE EVIDENCE

READY significa evidencia suficiente para el contrato, no extracción realizada. DOMAIN ADAPTER significa que el contenido/comportamiento requiere dominio aunque su contenedor visual pueda reutilizarse. WAIT queda fuera del núcleo V1; Unlock solo hace falta para ampliar esos bloques, no para bloquear el contrato mínimo.

| BLOCK | CLASIFICACIÓN | EVIDENCE |
|---|---|---|
| OfferHero | READY TEMPLATE V1 | Los tres necesitan oferta, explicación, media opcional y acción; cambian énfasis y densidad. Benefit demuestra valor textual sin precio. |
| OptionalMarketingMedia | READY TEMPLATE V1 | Los tres usan LandingVideo/JakawiImage. Experience validó VSL/imagen/ausencia; Benefit valida imagen/ausencia. Sin player nuevo ni wrapper vacío. |
| MarketingFactStrip | READY TEMPLATE V1 | Experience fecha/hora/lugar; Benefit ciudad/vigencia; Challenge plazo/mecánica en su jerarquía. `label/value` de longitud variable funciona; significado/selección lo deriva el adapter. No requiere enumerar facts universales. |
| MarketingValueBlock | READY TEMPLATE V1 | Challenge recompensa pendiente; Benefit café de cortesía condicionado. Ambos necesitan descriptor contractual sin fingir valor realizado; tono reward solo cuando corresponde. |
| MarketingPriceBlock | WAIT / NOT READY | Solo Experience tiene precios contractuales normal/miembro. Benefit tiene estimación, que no sirve como segunda comparación económica. Puede entrar como lista opcional simple; comparadores/ahorro automático esperan otra evidencia, potencialmente Unlock. |
| MarketingPrimaryCTA | READY TEMPLATE V1 | MarketingCTA ya compartido. Action/state y bindings siguen siendo dominio, con límites a overrides de label. |
| MarketingStickyCTA | WAIT / NOT READY | Solo Challenge tiene sticky y condiciones de visibilidad. No se generaliza ni añade a Benefit/Experience; esperar evidencia de otro flujo, potencialmente Unlock. |
| MarketingSteps | READY TEMPLATE V1 | Arrays title/description en los tres; cantidad libre y copy state-aware. Contenedor reutilizable, flujo generado por adapter. |
| MarketingEditorialPoints | WAIT / NOT READY | Experience tiene razones experienciales; Benefit no aporta motivos independientes del valor/reglas y Challenge mezcla explicación/reglas/confianza. No añadir array genérico que duplique facts/steps. |
| MarketingOperationalDetails | DOMAIN ADAPTER | Challenge evidencia/calificación; Experience sesiones/reserva; Benefit sucursal/código/restricciones. Slot de dominio; no schema universal de operaciones. |
| MarketingFAQ | READY TEMPLATE V1 | MarketingFAQ existente; preguntas ink, respuestas secundarias, detalles accesibles por teclado. Ítems derivados por adapter. |
| MarketingFinalCTA | READY TEMPLATE V1 | Los tres: ink, headline/apoyo y misma acción state-aware. Final no posee segundo action. |

## K) OfferPresentationV1 DRAFT

**Análisis solamente.** Payload efímero generado en cada request a partir del subject y editorial existente; no nuevo registro espejo de precios, elegibilidad o reglas. No registry, renderer ni migración. REQUIRED refiere al contrato; las listas pueden estar vacías si el dominio no ofrece ese dato.

| FIELD | TYPE | REQUIRED / OPTIONAL | SOURCE |
|---|---|---|---|
| `hero.eyebrow` | `string` | REQUIRED | DOMAIN fallback / EDITORIAL OVERRIDE; puede presentar partner |
| `hero.headline` | `string` | REQUIRED | DOMAIN fallback / EDITORIAL OVERRIDE validado contra verdad |
| `hero.emphasis` | `text span within headline` | OPTIONAL | EDITORIAL OVERRIDE; una frase, sin repetir headline en otro campo |
| `hero.description` | `string` | REQUIRED | DOMAIN fallback / EDITORIAL OVERRIDE |
| `media` | existing `image config OR LandingVideoConfig` | OPTIONAL | DOMAIN configurado / PRESENTATION selección de asset existente; discriminated union, no ambos simultáneos |
| `facts` | `Array<{label:string,value:string}>` | REQUIRED, puede ser `[]` | DOMAIN-DERIVED; orden/selectores PRESENTATION acotados |
| `value` | `{label:string,description?:string,tone:'offer'|'reward'}` | OPTIONAL | DOMAIN-DERIVED; reward es promesa de premio, nunca verde por ahorro potencial |
| `prices` | `Array<{label:string,formattedAmount:string}>` | OPTIONAL | DOMAIN-DERIVED solo con importes contractuales; no estimated_savings; sin porcentaje derivado |
| `essentialConditions` | `string[]` | REQUIRED, puede ser `[]` | DOMAIN-DERIVED; contenido obligatorio no ocultable por editorial |
| `primaryAction` | `{label,href,kind,disabled}` with existing action semantics | REQUIRED | DOMAIN-DERIVED; label EDITORIAL OVERRIDE acotado al próximo paso real |
| `actionExplanation` | `string` | OPTIONAL | DOMAIN-DERIVED; autenticación, membresía, ficha o validación |
| `steps` | `Array<{title:string,description:string}>` | OPTIONAL | DOMAIN-DERIVED; cantidad libre, sin imponer tres |
| `operationalContent` | opaque typed domain payload / domain component slot | REQUIRED | DOMAIN-DERIVED; renderer genérico no interpreta sesiones, grants, códigos o reglas |
| `faq` | existing `Array<{question,answer}>` | REQUIRED, puede ser `[]` | DOMAIN-DERIVED; editorial solo para preguntas explicativas sin sustituir condiciones |
| `finalCta` | `{headline:string,description:string}` | REQUIRED | DOMAIN fallback / EDITORIAL OVERRIDE; reutiliza primaryAction |
| `appearance` | `{theme:existing MarketingTheme}` | OPTIONAL | PRESENTATION; defaults explícitos, no codifica elegibilidad |

Los handlers no se serializan como funciones en el payload: el adapter/binding React conserva onClick, POST, guards, sessionId/grant si corresponde, journey intents y Growth. `kind` no da permiso al template para deducir una operación. Un objeto action común no implica endpoints o bodies comunes.

Se omiten `highlight` como claim independiente, `editorialPoints`, sticky, ahorro calculado, section tree, order libre y lista de plantillas. No hay necesidad de modelar cada elemento visual antes de extracción. La fecha/partner/price de los facts son proyecciones del subject, no inputs duplicados guardados en LandingPresentation.

## L) DOMAIN ADAPTERS

- **ChallengeAdapter**: domain reward, qualification, selection, deadline, membership/review, rules y estados de participación/grant → value tone reward, facts esenciales, condiciones, steps, FAQ y operationalContent específico. Presentation: eyebrow/headline/subheadline/media/final copy validados. Action binding conserva participar, ver participación/premio y activación elegible; sticky queda local hasta evidencia.
- **ExperienceAdapter**: domain duración, sesiones, horas, lugar/partners, precios contractuales, capacidad, reservationMethod, membership y reserva existente → facts, prices, requisitos, pasos de reserva, FAQ y operaciones de sesiones. Presentation: copy editorial, media y final copy. Action binding conserva guest POST con sessionId, native/external, membership, reserved/unavailable/preview. La promesa de atardecer del prototipo V3 permanece pendiente de la aclaración ya señalada; no se convierte en default genérico.
- **BenefitAdapter**: domain tipo/título contractual, access_mode, partner/sucursales, fechas, límite, terms, disponibilidad y membresía/grant → value tone offer, facts, essentialConditions, pasos por estado, FAQ y operaciones de canje. Excluye estimated_savings de oferta/price. Presentation: valor primero, headline/emphasis/media/final copy compatibles con mecanismo real. Binding mantiene cuenta/membresía/ficha/premio, y deja selección/activación/validación en dominio.

Cada adapter toma verdad del subject/presenters; editorial no redefine números, condiciones, elegibilidad, disponibilidad o next action. Los tratamientos QA hardcoded actuales son evidencia a trasladar a copy editorial validado, no lógica a copiar al template.

## M) TEMPLATE COUNT

**A — una plantilla modular OFFER/CONVERSION.**
Los tres convergen en oferta → claridad → acción → explicación → operaciones/FAQ → decisión.
Value y prices son opcionales; media también, sin exigir versión STORY separada.
El layout puede variar su densidad y orden responsive dentro de la composición.
La diferencia significativa está en los datos, estado, handlers y operaciones del adapter.
No hace falta C ni evidencia actual de un objetivo distinto que justifique B.
Sticky, comparadores y puntos editoriales no bloquean el núcleo mínimo V1.

## N) FUTURE ADMIN

- **AUTO FROM SUBJECT**: mecanismo/recompensa, precios contractuales, partner, sucursales, fechas/horas, capacidad o límites, terms, eligibility/membership, availability, pasos por estado, CTA/destino/payload y FAQ operativa. Solo lectura en presentación; edición real en administración de cada dominio.
- **EDITORIAL INPUT**: eyebrow, headline, énfasis dentro del headline, subheadline, headline/apoyo final, texto explicativo opcional validado. Sin savings/premio/números nuevos que sustituyan verdad. No override libre de CTA que prometa resultado distinto.
- **PRESENTATION OPTIONS**: theme existente, media opcional desde assets/config existente, imagen o VSL, visibilidad de secciones puramente editoriales. Orden acotado dentro de jerarquía estable; no drag/drop ni esconder requisitos/operaciones. Una plantilla inicial: no selector A/B/C ni registry por ahora.
- No Admin implementado, campos nuevos, builder, block renderer, dependencias ni migraciones.

## O) SAFETY

- Economics: **NO CHANGE**.
- Redemption/membership eligibility: **NO CHANGE**.
- Growth / Attribution / Campaign / campaign_key / CRM / JP: **NO CHANGE**.
- Product Detail: **NO CHANGE**.
- Player: **NO CHANGE**; usa LandingVideo existente, sin VSL nuevo configurado.
- Challenge / Experience / Unlock: **NO CHANGE** después del commit de preservación.
- Cambios Benefit: renderer, estilos FAQ scoped y condición del layout para ocultar header Product UI solo en Benefit. Comportamiento Challenge/Experience permanece idéntico.
- Sin DB writes, nuevas imágenes, redirects, nuevas dependencias ni migraciones.

## P) VALIDATION

- TypeScript: **PASS**, `./app/node_modules/.bin/tsc --noEmit -p app/tsconfig.json`.
- Vite/browser: **PASS**, 390/430/1440, cero pageerrors, imagen cargada, sin overflow; FAQ por teclado; sin Product UI header. [Browser report](browser-report.json).
- Estados locales sintetizados a partir de la fixture: guest, native, membership, unavailable, preview y no-media. Hero/final mismos labels; bloqueo disabled conserva ausencia de POST. No se consultaron usuarios reales ni otros Benefits.
- Guest handler: POST interceptado localmente con body `{}`, coherente con handler existente. Ninguna request de prueba llega a canje, membresía o analytics reales. Origin de action guest sustituido únicamente en harness por origen de preview; ruta/payload conservados.
- La primera prueba de POST falló por usar el origen backend distinto del harness. Se corrigió el origen del fixture local y se repitió la validación por ese fallo y el ajuste de copy mobile; sin cambiar rutas/handlers de producto. No iteración de pixel perfection.
- VSL: **no configurado en Benefit**; rama conserva LandingVideo existente, sin afirmar prueba de reproducción Benefit. La evidencia previa Experience/Challenge respalda su reutilización.
- Git diff: **PASS**, `git diff --check`; solo tres archivos de implementación Benefit/shared scopes permitidos y sus artifacts.
- Docker build: **NOT RUN**. Docker se utilizó únicamente para lectura del subject/presentación con el PHP del proyecto; no ejecución/inspección de PHP host, composer/artisan/vendor/bin host.
- No tests backend ni canje end-to-end: fase visual, con limitación guest documentada. No commit de Benefit, push ni deploy.

## Q) RESULTADO

BENEFIT DISCOVERY V1 READY — TEMPLATE CONTRACT EVIDENCE COMPLETE
