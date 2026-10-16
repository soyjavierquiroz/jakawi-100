# Fix visibilidad Community Floating Bubble — PASS

LIVE `f5e377e184909626bdd0238d2a23f52d46f37f20` sobre `067bf0624b55e9c9dd1584ae1313f51434363093`. Deploy directo; app/web/social-worker alineados, `/up` local y público 200. Rollback registrado en `backups/community-bubble-access-fix-20261015/rollback.sh`, con las imágenes previas protegidas. No requiere restauración de base.

## Causa confirmada

Cochabamba ACTIVE, comunidad habilitada y enlace válido según el validador oficial de formato; token nunca impreso. El registro existente tiene status opened y apertura dentro de 30 días. Explorar entrega communityBubble para Cochabamba con automatic=false; ConsumerLayout monta el componente, pero este retorna antes de programar el temporizador. Reproducción con assets anteriores y props reales: después de 10,5 segundos no hay bubble ni temporizador. La causa es la reutilización incorrecta de elegibilidad promocional, no delay, CSS ni z-index. Autenticación sigue siendo obligatoria: el único endpoint oficial tiene middleware auth y no existe contrato de apertura pública que se pueda reutilizar.

## Corrección

El backend prepara access para la ciudad habilitada, independiente de exposiciones, ventana de 14/30 días y supresión de presentaciones por sesión. Solo declined bloquea el acceso. automatic permanece reservado a la invitación; la bubble no llama presented ni consume exposiciones. self_reported_joined puede abrir mediante el mismo POST oficial opened, preservando su estado. Se mantienen la selección de ciudad activa y la revalidación de configuración al abrir.

La comunicación de acciones incluye ciudad y status: self_reported_joined, opened y snoozed no ocultan el acceso; declined cancela también el temporizador pendiente. El cierre manual conserva la misma clave sessionStorage: no se borró el cierre previo del usuario. Invitados siguen sin bubble porque no hay apertura pública autorizada por el contrato existente. No endpoints ni bypass nuevos.

Delay aleatorio de 3–10 segundos, posición, tamaño, z-index, animación, reduced-motion, theme.css y exclusiones de rutas se conservan. Ningún cambio CSS, DEMO, configuración de ciudad o enlace. Sigue habiendo una sola bubble por pantalla.

## Validación focalizada

16 pruebas/404 aserciones PASS en PHP 8.4 Docker y PostgreSQL aislado. Incluyen frecuencia agotada, fechas recientes y sesión previamente expuesta; acceso autodeclarado y apertura oficial preservando su estado; rechazo/reset, comunidad OFF, invitados y exclusión de root/Admin/perfil. TypeScript PASS; única compilación frontend, manifest de 109 páginas PASS. git diff --check y git show --check PASS.

Navegador candidato y LIVE: 18 controles cada uno a 390/1440; cero errores JS. Props reales del registro antes bloqueado: bubble visible en Explorar, partner y Mi JAKAWI. Tamaño, posición sobre bottom nav, hit test del icono, z-index y opacidad, foco y reduced-motion PASS; sin overflow. Delay real verificado y cero POST de presentación. Cierre/recarga, autodeclaración con apertura, rechazo y OFF PASS; estos estados se simularon solo en memoria del navegador y se comprobaron también en backend mediante los tests aislados. Los controles sintéticos aceleran el temporizador exclusivamente en el harness; la aparición normal usa los 3–10 segundos reales. Aperturas y analítica interceptadas; sin contactar WhatsApp ni escrituras QA productivas.

Snapshot antes/final idéntico: configuración, interacciones, eventos, usuarios y migraciones. PRELAUNCH true y aviso DEMO exacto intactos. No migraciones productivas, suite GOLD, operaciones sobre v2proof ni trabajos ajenos. Evidencia: diagnosis.json, before-browser.json, candidate-browser.json, live-browser.json, tests.txt, release.json y capturas.
