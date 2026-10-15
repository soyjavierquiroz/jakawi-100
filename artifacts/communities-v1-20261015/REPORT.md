# Comunidades JAKAWI V1

2026-10-15 · America/La_Paz. Implementación local. Sin despliegue ni habilitación de producción.

## A. Qué encontré

Laravel 13/PHP 8.4 Docker, React 19/TypeScript/Inertia 3, Tailwind 4/shadcn y PostgreSQL 16. Se reutilizan City, SelectedCity (cookie de ciudad ACTIVE y fallback canónico), el editor Admin de ciudades, users.is_admin, AuditLog, AnalyticsTracker/AnalyticsEvent, el retorno Fortify y los tokens de theme.css. El perfil no es la fuente de ciudad activa. No hay un onboarding independiente: se conserva la continuidad al producto y se presenta la tarjeta al llegar a Inicio/Mi JAKAWI. Inicio de pre-lanzamiento también admite la tarjeta para usuarios autenticados.

## B. Qué implementé

Tres campos en cities, OFF por defecto, y community_interactions con unicidad user/city. Validación de HTTPS/host/ruta canónica de invitación de WhatsApp en configuración y apertura; URL oculta de la serialización general. Configuración en el formulario Admin existente, autorización heredada, auditoría y confirmación de guardado.

Tarjeta reutilizable al final del descubrimiento y acceso permanente en Mi JAKAWI tanto gratuito como con membresía. Presentación al 50% de visibilidad, sin escrituras GET/prefetch. Frecuencia 14 días, pausa por apertura 30 días, máximo tres, supresión por sesión y aislamiento por ciudad. Lock de User serializa también primeras interacciones concurrentes; lock de City revalida configuración. Abrir conserva rechazo/autodeclaración y siempre exige configuración vigente.

Estados not_invited/invited/opened/self_reported_joined/declined. Postergación independiente; declaración y rechazo reversibles sin reiniciar frecuencia. Eventos internos allowlisted con usuario/ciudad/origen/fecha, deduplicados por presentación, transición o ventana de clic; sin teléfonos ni tokens de invitación. No se modifica el CRM ni la lógica comercial de Membership, Benefits, Experiences, Challenges o Unlocks; no se añaden APIs de WhatsApp, proveedores, workers ni dependencias.

## C. Archivos modificados y nuevos

- `app/app/Http/Controllers/AdminCityController.php`
- `app/app/Http/Controllers/CityCommunityController.php`
- `app/app/Http/Controllers/MembershipController.php`
- `app/app/Http/Controllers/PartnerPortalController.php`
- `app/app/Http/Controllers/PublicController.php`
- `app/app/Http/Responses/RegisterResponse.php`
- `app/app/Models/City.php`
- `app/app/Models/CommunityInteraction.php`
- `app/app/Rules/WhatsAppCommunityUrl.php`
- `app/app/Services/AnalyticsTracker.php`
- `app/app/Services/CityCommunity.php`
- `app/config/communities.php`
- `app/config/jakawi.php`
- `app/database/migrations/2026_10_15_000001_add_city_communities.php`
- `app/resources/js/components/city-community-card.tsx`
- `app/resources/js/pages/admin/cities/form.tsx`
- `app/resources/js/pages/admin/cities/show.tsx`
- `app/resources/js/pages/coming-soon.tsx`
- `app/resources/js/pages/mi-jakawi.tsx`
- `app/resources/js/pages/partner/dashboard.tsx`
- `app/resources/js/pages/partner/index.tsx`
- `app/resources/js/pages/welcome.tsx`
- `app/routes/web.php`
- `app/tests/Feature/CityCommunityConcurrencyTest.php`
- `app/tests/Feature/CityCommunityTest.php`
- `app/tests/Support/community-race.php`
- `docs/CURRENT.md`
- `docs/operations/RUNBOOKS.md`
- `docs/product/MANUAL_DE_FUNCIONES.md`

Evidencia adicional en este directorio: browser.mjs, browser.json, capturas PNG y logs de validación. Los artefactos previos de otras tareas se conservaron.

## D. Validaciones

- `./bin/jakawi-test php artisan test`: **715 PASS, 0 FAIL, 10.860 aserciones**, PostgreSQL 16 aislado; incluye 14 pruebas nuevas de Comunidades. Concurrencia con dos procesos sincronizados, sesiones distintas, primera fila: exactamente una exposición y un evento. La prueba confirma fixtures sin transacción y los limpia en finally; evita el down intencionalmente irreversible de migraciones anteriores.
- Docker target build, imagen temporal `jakawi-communities-frontend-check`: TypeScript, compilación y manifest de **109 páginas Inertia PASS**; no se recreó ningún servicio productivo.
- Pint de los nueve archivos PHP nuevos y lint/formato del componente nuevo: PASS.
- PHPStan nivel 7 del código PHP nuevo: PASS. **La ejecución global no pasa**: reporta más de 1.000 diagnósticos, incluidos contratos y tipos en módulos ajenos a este cambio. No se presenta como gate global aprobado ni se amplía esta tarea a corregirlos.
- Browser QA PASS con assets del build final en runtime Docker/PostgreSQL de test: 375, 390, 430 y 1440 px; sin overflow ni errores JS, cuatro entradas de navegación conservadas, una exposición automática por sesión, posponer/descartar, acceso manual, autodeclaración/corrección y error/reintento. Guardado Admin a 390 px con una única confirmación y revisión visual de capturas. Todo tráfico externo bloqueado, sin contactar WhatsApp. Ver browser.json y capturas.
- Checker documental: 74 archivos, 480 destinos relativos, cero rotos; diff whitespace PASS.

## E. Qué queda manual

Autorizar y ejecutar release coherente app/web y la migración, con backup y procedimiento existente. Crear comunidad real en WhatsApp; obtener y verificar el enlace oficial; permitir publicaciones solo a administradores de JAKAWI; revisar privacidad; Admin → Ciudades → Cochabamba → Editar, nombre/enlace/habilitación. No se ha insertado ningún enlace oficial ni habilitado ninguna ciudad real.

## F. Estado y límites

Implementado y probado en Docker/PostgreSQL aislado. Pendiente de despliegue y configuración humana. **No habilitado en producción**. No hubo escrituras de negocio ni migraciones en producción. Sin configuración válida la funcionalidad permanece oculta. El servidor temporal se detuvo y los fixtures se retiraron: cero cuentas QA y cero comunidades habilitadas en jakawi_test (cleanup.json).

Clic ≠ incorporación real: V1 no verifica pertenencia, vigencia ni propiedad del enlace; solo valida su destino y formato. El operador debe comprobarlos en WhatsApp. No hay directorio multiciudad ni gestión de miembros. Mi JAKAWI mantiene la redirección Partner-only preexistente; el índice y dashboard Partner reutilizan la tarjeta permanente en el destino de esa redirección. Los enlaces canónicos admitidos tienen código alfanumérico de 22 caracteres, sin query/fragmento. Revocar un enlace ya compartido requiere actuar también en WhatsApp.
