# OfferConversionTemplate V3 — evidencia

| DRAFT | WIDTH | HERO | DENSITY | HEIGHT BEFORE | HEIGHT AFTER | CTA Y BEFORE | CTA Y AFTER | BLOCKS REMOVED/CONDENSED |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Café STANDARD | 390 | OFFER_FIRST | STANDARD | 4245 | 2865 | 674 | 569 | ValueBlock repetido, 3 introducciones de pasos y 5 FAQ eliminadas; canje en operativo |
| Café STANDARD | 1440 | OFFER_FIRST | STANDARD | 3041 | 1937 | 715 | 604 | ValueBlock repetido, 3 introducciones de pasos y 5 FAQ eliminadas; canje en operativo |
| Café COMPACT | 390 | OFFER_FIRST | COMPACT | 3914 | 2452 | 333 | 471 | Igual + descripción ampliada en disclosure y espaciado COMPACT |
| Café COMPACT | 1440 | OFFER_FIRST | COMPACT | 3311 | 1625 | 186 | 450 | Igual + descripción ampliada en disclosure y espaciado COMPACT |
| Descuento cena | 390 | OFFER_FIRST | STANDARD | 4071 | 2788 | 632 | 624 | Importe del ValueBlock conservado; pasos y FAQ repetidos eliminados |
| Descuento cena | 1440 | OFFER_FIRST | STANDARD | 2955 | 1886 | 710 | 638 | Importe del ValueBlock conservado; pasos y FAQ repetidos eliminados |
| Sunset | 390 | MEDIA_LED | STANDARD | 3674 | 3277 | 873 | 877 | Media editorial 16:10; FAQ reducidas a elección de otra fecha; introducción operativa condensada |
| Sunset | 1440 | MEDIA_LED | STANDARD | 3198 | 2790 | 682 | 710 | Media editorial 16:10; FAQ reducidas a elección de otra fecha; introducción operativa condensada |
| Brunch/cata | 390 | OFFER_FIRST | STANDARD | 3798 | 3346 | 670 | 642 | Fila lugar a ancho completo; FAQ repetidas eliminadas; introducción operativa condensada |
| Brunch/cata | 1440 | OFFER_FIRST | STANDARD | 3280 | 2782 | 808 | 702 | Fila lugar a ancho completo; FAQ repetidas eliminadas; introducción operativa condensada |
| Reto cena | 390 | OFFER_FIRST | STANDARD | 4512 | 3969 | 879 | 867 | Premio antes del CTA; disclosure repetido del premio eliminado; resultado repetido eliminado; FAQ reducidas |
| Reto cena | 1440 | OFFER_FIRST | STANDARD | 4387 | 3678 | 888 | 794 | Premio antes del CTA; disclosure repetido del premio eliminado; resultado repetido eliminado; FAQ reducidas |
| Reto café | 390 | OFFER_FIRST | COMPACT | 3964 | 2990 | 407 | 710 | Premio y cierre antes del CTA; 2 pasos en vez de 3; resultado repetido eliminado; FAQ reducidas y espaciado COMPACT |
| Reto café | 1440 | OFFER_FIRST | COMPACT | 4280 | 2745 | 170 | 554 | Premio y cierre antes del CTA; 2 pasos en vez de 3; resultado repetido eliminado; FAQ reducidas y espaciado COMPACT |

Alturas y CTA Y en CSS px, scroll inicial 0. Fuente: snapshots before.json/after.json, fuentes cargadas, 390×1000 y 1440×1000, America/La_Paz. Las alturas representan disclosures cerrados; su contenido se verificó en el DOM. No se comparan contra navegación de invitado: se conserva la sesión Admin y su estado real (Benefits ya usados).

COMPACT ahora es más corto que STANDARD también en escritorio para el mismo café. Los CTA compactos pueden bajar: antes carecían de valor/recompensa o vigencia delante de la acción. Se recupera esa prioridad sin cambiar el estado del dominio. Sunset mantiene composición editorial y CTA móvil prácticamente en la misma posición.

## Duplicación observada antes

Benefits: hero + título de valor, pasos genéricos + explicación de canje + FAQ. Experiences: fechas/lugar/acceso repetidos en FAQ; preparación genérica adicional. Challenges: premio/disclosure, bloque de resultado, selección/reglas/FAQ y tres pasos redundantes en el caso manual.

## Verificación

14 renders PASS; sin overflow, instalación, errores JS ni intentos de mutación. Headline/emphasis exactos; CTA con la misma etiqueta antes/después, bloqueada al clicar. Reglas, límites, descripción, condiciones, fechas, sesiones y capacidades siguen disponibles en operativo; ninguna verdad eliminada queda sólo en FAQ. Copy de dominio y editorial idénticos antes/después. IMAGE permanece; NONE no genera contenedor; el VSL irrelevante no reaparece.

V3 es código local para review, no está desplegado. HTML/proyección GET obtenidos con Admin verificado en memoria y transacción READ ONLY en la app de producción; AFTER usa Vite local con esos mismos props y medios públicos. Fuentes de producción servidas al navegador local con CORS en el harness, sin cambios en el servidor.
