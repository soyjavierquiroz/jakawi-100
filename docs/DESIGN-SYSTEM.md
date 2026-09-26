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
