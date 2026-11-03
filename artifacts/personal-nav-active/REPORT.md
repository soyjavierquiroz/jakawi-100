# Marcador activo del menú personal COM

Cambio limitado al header ConsumerLayout, al menú móvil existente y a una función compartida de selección/estilo. No se duplican layouts ni cambian destinos, contenido, autenticación, sesiones, OIDC, membresías, beneficios, canjes, partners, CLUB o CRM. Sin migraciones.

Se consultó sólo la implementación del marcador de CLUB: subrayado de 2 px con separación de 8 px y navegación con separación de 24 px. COM conserva su estructura y espaciado; el activo usa peso 600, #111111 en claro, blanco en oscuro y subrayado #FF5A4F. Los inactivos usan texto secundario sin subrayado. Hover y foco de teclado visibles; aria-current="page" sólo en la opción activa de cada menú responsive.

Selección mediante URL Inertia, sin estado local duplicado. Explorar cubre /explorar y las rutas de beneficios, experiencias y lugares; el resolver público /p/:slug también se reconoce. Mi JAKAWI cubre su sección personal. Perfil cubre /perfil, el alias /mi-jakawi/perfil y el editor legado /settings/profile. Se prioriza el editor antes de Mi JAKAWI y se exigen límites de segmento; rutas administrativas, de partner y prefijos parecidos no coinciden.

TypeScript y build/manifest PASS. 25 comprobaciones focalizadas de rutas PASS, incluidas consultas, barra final, alias y exclusión de rutas ajenas (routes.json). Sin pruebas comerciales ni sesiones reales; navegador y cuenta sintética en PostgreSQL aislado.

Validación visual PASS en 12 combinaciones, sin desbordamientos ni errores JavaScript. Matriz: /beneficios → Explorar, /mi-jakawi → Mi JAKAWI, /perfil → Perfil, en claro/oscuro y 390/1440 px, con Inertia, refresco, enlace directo, Atrás/Adelante, hover y foco de teclado.

Base LIVE: `7a90f30b34040cd96850f8c7058500689c55c3ca`.
SHA LIVE: `ab29f7ce173e30037476cf6d63f0a79dc3377dcf`.
Rama: `feat/personal-nav-active`, publicada. No se mezclan cambios ajenos de origin/main.

Se revisaron visualmente los tres estados en oscuro 1440 y Explorar/Perfil en claro 390. Las capturas representan un entorno aislado con cuenta sintética y catálogo vacío, no disponibilidad real de producción.

LIVE PASS: app/web/worker/scheduler COM con el mismo SHA. Salud local y pública HTTP 200; catálogo público HTTP 200 y Mi JAKAWI anónimo mantiene login. Los JS/CSS del layout y sus dependencias públicos coinciden byte por byte con el build validado. Los 39 contenedores permanentes ajenos a COM conservan ID, imagen y fecha de arranque. Sin migraciones ni sesiones de prueba LIVE. Respaldo de imágenes y reversión preparados en el directorio operativo privado, sin operaciones sobre datos comerciales. Entorno aislado detenido. El commit posterior sólo registra evidencia.
