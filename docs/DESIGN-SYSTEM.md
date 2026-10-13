# Sistema de diseño

JAKAWI es cálido, editorial, urbano y accesible: energía para descubrir, contraste para decidir y tranquilidad para confirmar. El modo oscuro mantiene esa sensación lifestyle cálida; no debe parecer crypto ni neón.

## Fuente de verdad

[`resources/css/theme.css`](../app/resources/css/theme.css) es la única fuente de verdad visual para color. Los componentes consumen tokens semánticos (`background`, `surface`, `surface-muted`, `brand`, `brand-subtle`, `border`, `foreground`, `muted`, `success`, `success-surface`, entre otros). No usar hex, `rgb()`, `rgba()`, `hsl()`, `oklch()` ni colores arbitrarios Tailwind en componentes React o CSS fuera del tema canónico.

Coral es marca/primario/selección. Amarillo es editorial, descubrimiento o acento especial. Negro aporta contraste y una sensación premium. Crema es base. Verde se reserva estrictamente para valor ya recibido, éxito, confirmación y payback, no ahorros estimados.

## Jerarquía y componentes

El CTA principal usa el token de marca; acciones editoriales o de descubrimiento pueden usar el acento amarillo. La fotografía editorial es protagonista: debe ser real, relevante y bien encuadrada; los fallbacks no sustituyen contenido de catálogo de calidad. Las tarjetas de Benefit hacen visible el valor y condiciones; las de Experience comunican actividad, fecha/hora y lugar. No convertirlas en el mismo patrón semántico.

La navegación móvil fija usa Inicio, Explorar, Mi JAKAWI y Perfil. Perfil es personal y humano —avatar, identidad y preferencias—, no una pantalla administrativa. Mantener blancos táctiles de al menos 44–48 px, contraste suficiente en ambos temas, foco visible, texto alternativo en imágenes y estados de carga/error comprensibles.

## Avatar

En móvil se ofrecen cámara (`capture="user"`) y galería. Tras elegir hay recorte con paneo, pinch/zoom, slider y máscara circular; se exporta `avatar.jpg` 1024×1024 JPEG (~0.88). La persistencia usa el pipeline privado de media.

## Marketing Theme Foundation V1

Product UI conserva la preferencia del usuario/sistema y el comportamiento global `.dark`. Marketing UI usa composición explícita local, sin toggle ni cambios de preferencia: `DARK_CONVERSION` (`dark-conversion`) y `LIGHT_CONVERSION` (`light-conversion`).

[MarketingLayout](../app/resources/js/layouts/marketing-layout.tsx) acepta `theme` y resuelve defaults del renderer: Challenge oscuro; Benefit, Experience y Unlock claros. Son defaults actuales, no reglas permanentes. Marketing Landing Admin V1 persiste marketing_theme con esos dos valores permitidos; no hay Landing Engine ni personalización libre.

[theme.css](../app/resources/css/theme.css) contiene referencias fijas a las variantes existentes de la paleta, roles `--marketing-*` y contextos completos `data-marketing-theme` / `data-marketing-surface="light|dark"`. Cada contexto vuelve a enlazar superficie, texto, tarjeta, muted, borde, estados y el puente Tailwind `--color-*`; cambiar sólo background/foreground no basta porque los aliases heredados pueden estar resueltos en otro contexto. Las utilidades `dark:*` de producto excluyen descendientes marketing. El footer y sticky pertenecen al contexto local; el CTA final establece contexto oscuro.

CORAL = ACTION (texto blanco sobre el tratamiento coral de contraste existente `funnel-primary-action`); GREEN = VERIFIED VALUE / SUCCESS (no estimaciones, promesas condicionales ni premios cancelados/revertidos); YELLOW = REWARD / OPPORTUNITY (texto ink, no CTA principal). Coral y verde sobre crema no sirven como texto informativo: usar texto neutral o composición sólida con ink. Metadata usa variantes secondary/muted del contexto. Los bordes de controles marketing (`input`) usan foreground; los divisores decorativos conservan border/border-strong.

La composición funciona con VSL, imagen y sin media donde el renderer lo admite. El player conserva `theme="inherit"` y sus internals/proveedores: hereda el contexto local; no determina el tema de la landing.

## Offer Conversion Template V1

`LandingPresentation + Subject → Domain Adapter → OfferPresentationV1 → OfferConversionTemplate → /l/{slug}`. Una sola plantilla de conversión con bloques compartidos para hero, media opcional, facts `label/value` ordenados, valor `offer|reward`, precios ya formateados, CTA, pasos de cantidad libre, FAQ y cierre oscuro. [Implementación](../app/resources/js/components/marketing/offer/OfferConversionTemplate.tsx). No usa porcentajes, cálculo de ahorro ni `estimated_savings` como valor realizado. El énfasis sólo colorea texto existente dentro del headline, sin HTML arbitrario.

Las opciones frontend `compact`, `editorial` y `plain` preservan densidad, tipografía y colocación de los prototipos aprobados dentro del mismo renderer; no constituyen un registry de plantillas ni tipos de dominio. Se mantienen los temas explícitos DARK_CONVERSION/LIGHT_CONVERSION y los contextos de superficie existentes, aislados de Product UI. Los facts admiten listas vacías o de cualquier longitud con wrapping; media ausente no crea un contenedor vacío. FAQ conserva controles verdes funcionales; ofertas y recompensas pendientes no se presentan como success.

Cada adapter conserva los estados y la acción ligada; hero y cierre usan la misma. Operaciones y anotaciones de dominio se componen mediante slots frontend, sin meter nodos React en el contrato. El sticky permanece local a Challenge. Ver [arquitectura](ARCHITECTURE.md#marketing-landing-template-v1). La verdad del subject es autoritativa: copy editorial no redefine economía/reglas. Marketing Landing Admin V1 persiste una sola plantilla y los dos temas explícitos; no expone personalización libre.


## Marketing Landing Admin V1

El formulario compartido organiza Landing, Mensaje, Media, Atribución, Cierre y Publicación. Benefit/Experience/Challenge seleccionan Offer Conversion; los defaults son Dark Conversion para Challenge y Light Conversion para Experience/Benefit. El tema es marketing explícito, aislado de la preferencia Product UI. Unlock conserva su UI/rendering existente.

Copy es texto plano: un destacado debe estar contenido en headline y se pinta una sola vez con el token de énfasis. El editor no elige colores. Media Imagen/Video/Sin media activa un solo contenido; cambiar de modo conserva hero_path y configuración VSL. Se reutilizan LandingVideoFields y YouTube/MP4/Bunny, framing y player existentes, sin modificar internals.

La verdad del producto aparece como resumen de sólo lectura. No hay inputs de economía, reglas, acción/URL, orden o ocultamiento de condiciones. Preview muestra la presentación guardada en el renderer real, con controles 390/430/Desktop y scroll interno para escritorio; no hay renderer simplificado. Duplicación solicita nuevo nombre/slug y permite revisar campaign_key; la copia queda DRAFT. Ver [arquitectura](ARCHITECTURE.md#marketing-landing-admin-v1).

## Uso real de tokens en el funnel

Brand + Unify Delivery V1 está implementado en el repo, pendiente de deploy. [DeliveryLayout + Delivery](../app/resources/js/components/marketing/delivery.tsx) es una shell fija con marca enlazada al inicio, resultado, instrucciones/acciones de dominio, media opcional existente, cuenta/seguridad secundaria y navegación final. Benefit reutiliza la shell desde ConsumerLayout sin MobileBottomNav duplicado; Experience, Challenge y Unlock desde conversion. Al final: SIGUE VIVIENDO TU CIUDAD → IR A MI JAKAWI (`/mi-jakawi`) / EXPLORAR MÁS (`/explorar`) para el usuario autenticado autorizado.

Coral = acción con texto blanco y el tratamiento de contraste existente. Amarillo = oportunidad, premio o fecha límite con texto ink. Verde = resultado realmente completado o valor confirmado; superficies sólidas usan ink y superficies success-surface usan foreground. Ink/cream = estructura e instrucciones. Foco visible y marca con nombre accesible permanecen obligatorios.

MarketingValueBlock consume el contrato existente: `reward` amarillo, `offer` informativo neutral. No se infiere gratuidad/ahorro desde títulos de Benefit. Fechas/vigencia de dominio usan amarillo. Experience sólo acentúa precio miembro verde cuando ambos precios autoritativos existen y miembro es inferior a regular; no calcula ahorro. Delivery pendiente/externa o compromiso no revelado nunca adopta el tratamiento de éxito confirmado. Delivery omite el aviso flotante de instalación PWA para mantener QR/instrucciones visibles. Challenge mantiene instrucciones, deadline, premio y estado antes del CTA de evidencia y la verificación y premios como oportunidad secundaria.

El significado visual de Landing nace de **hechos derivados del dominio + campos editoriales existentes + reglas semánticas fijas de la plantilla**. Marketing no selecciona colores manualmente. No hay colores arbitrarios, campos Admin nuevos, Delivery CMS/model, selector nuevo ni registry. Publisher, LandingPresentation, presenters/adapters y OfferConversionTemplate V3 conservan su arquitectura y composición. El slot instructionalMedia queda disponible sin contenido ficticio ni campos nuevos. Welcome Courtesy: **FUTURE / OFF / NOT IMPLEMENTED**. Ver [audit actualizado](architecture/MARKETING_SYSTEM_AUDIT.md).
