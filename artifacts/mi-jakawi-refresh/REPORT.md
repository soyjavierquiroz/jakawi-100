# Mi JAKAWI

Rediseño limitado a `/mi-jakawi`: tarjeta personal única desde MembershipStatus, catálogo COM separado de activación oficial CLUB, beneficios con imagen existente y disponibilidad del detalle. Pionero mantiene acceso actual y vigencia de 15 meses desde el lanzamiento, sin recompra. Estándar presenta vencimiento efectivo. Precio/duración comercial se consultan en CLUB; la página no reproduce una oferta de COM potencialmente desactualizada.

Paleta local a la página, Montserrat, tarjetas blancas en claro y superficies oscuras diferenciadas. WhatsApp secundario compacto con privacidad desplegable; el componente conserva su presentación anterior en otras páginas. Retos vacíos tienen un mensaje y enlace, sin paginación; participaciones conservan datos y navegación. Perfil único en `/perfil`. No cambian autorización de canjes, sesiones/OIDC, tablas, migraciones ni lógica comercial.

Validación en PostgreSQL aislado con cuentas sintéticas, sin sesiones de usuarios reales. Las imágenes de prueba reutilizan una imagen pública existente; los títulos del catálogo de prueba son sintéticos. Capturas de gratuito/Pionero/Estándar/vencido en claro y oscuro, a 390/1440 px. Las capturas no representan disponibilidad ni ofertas nuevas en producción.

PASS previo a despliegue: TypeScript, build y manifest; 12 pruebas/226 aserciones; 16 combinaciones visuales (cuatro estados × dos temas × dos anchos), imágenes cargadas, colores de fondo/tarjeta exactos, sin desbordamientos ni errores JavaScript. Se revisaron visualmente gratuito claro 1440/oscuro 390 y Pionero oscuro 1440. Se verificaron CTAs, ausencia de recompra, retos vacíos y navegación con participaciones. El destino oficial de membresía CLUB responde HTTP 200.
