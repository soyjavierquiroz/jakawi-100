# Navegación administrativa COM

La navegación compartida de Inertia y conciliación Blade se define en
`app/resources/admin-navigation.json`. El dashboard prioriza Membresías y
Pagos y conciliación y conserva los seis indicadores existentes.

| Grupo | Accesos principales | Herramientas secundarias |
| --- | --- | --- |
| Miembros y pagos | Membresías; Pagos y conciliación | Ventas asistidas: Ventas en efectivo, Solicitudes asistidas, Operadores de ventas |
| Partners y catálogo | Partners; Solicitudes Partner; Sucursales y lugares; Beneficios; Experiencias; Canjes; Revisión editorial | Retos sociales (sin operación activa); Desbloqueos (acceso preexistente conservado) |
| Marketing y territorio | Marketing Landings; Atribución comercial; Ciudades → Gestionar ciudades | Ciudades → Herramientas de expansión → Expansión |
| Configuración avanzada | Reglas de recompensas JP y Partners; Ajustes de puntos JP y registros de Partners; Campañas operativas; Configuración editorial; Pagos a Partners (histórico/incompleto) | Grupo plegado salvo al consultar una de sus páginas |

Comisiones CLUB abre `https://jakawi.club/admin/comisiones` en otra pestaña,
con `noopener noreferrer` y etiqueta de enlace externo. CLUB conserva su propia
autorización. Las redirecciones individuales heredadas a `/afiliados` no cambian.

Pagos a Partners queda dentro de Configuración avanzada, identificado como
histórico/incompleto; `/admin/payouts` y sus permisos,
operaciones preexistentes e historial permanecen. Esta reorganización no
habilita operaciones, activaciones ni generación de solicitudes nuevas.

JP son puntos y se distinguen de los registros monetarios CASH de Partners y
de las comisiones de afiliados gestionadas por CLUB. Sólo cambian estructura,
etiquetas, enlaces y estilos: no rutas, middlewares, roles, permisos, servicios,
modelos, APIs, configuración económica ni persistencia.

En móvil el menú comienza plegado; en escritorio se muestra como barra lateral.
Los grupos secundarios se abren al visitar su destino. Se conservan logo,
Montserrat y tokens coral/crema existentes, sin dependencias nuevas.

Validación: `AdminNavigationTest` recorre los destinos con invitado, usuario sin
rol admin y administrador, incluyendo el acceso histórico de payouts; comprueba
el menú Blade y las redirecciones heredadas. Complementar con TypeScript,
build de producción, navegación real en 390/1440, comparación completa de rutas
y `/up` local/público antes de cerrar el despliegue.
