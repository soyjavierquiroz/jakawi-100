# JAKAWI

**Vive más. Gasta menos.** JAKAWI es la app para descubrir lugares y experiencias en Cochabamba, acceder a beneficios por ser miembro y tener nuevas razones para salir, probar y volver.

## Stack

Laravel 13, PHP 8.4 (Docker), React 19, TypeScript, Inertia 3, Tailwind 4, PostgreSQL 16, Docker Compose, nginx interno y OpenLiteSpeed en el host.

## Desarrollo y pruebas

El código Laravel, Composer y las pruebas se ejecutan exclusivamente en Docker; el PHP 8.1 del host no se usa. Para pruebas aisladas:

```bash
./bin/jakawi-test
```

Esto usa el proyecto Compose `jakawi-test` y la base `jakawi_test`, nunca la base de producción.

## Frontend y Wayfinder

Las rutas y acciones TypeScript de Wayfinder se generan, no se versionan. El
flujo canónico (incluido el chequeo TypeScript y el build de producción) usa
PHP 8.4 dentro de Docker:

```bash
docker build -f docker/Dockerfile --target build -t jakawi-frontend-check .
```

Ese build limpia el route cache, genera Wayfinder con `--with-form`, ejecuta
`tsc --noEmit` y luego compila Vite. No ejecutar `php artisan
wayfinder:generate` con el PHP 8.1 del host.

## Reglas de producción

- Producción entra por OpenLiteSpeed a `127.0.0.1:8080` (`web`); imgproxy por `127.0.0.1:8082`.
- Tras cambiar assets frontend, reconstruir y recrear **`app` y `web` juntos**. Nunca uno solo.
- Tras cambiar `.env`, recrear el contenedor afectado y comprobar su configuración efectiva.
- Nunca ejecutar `migrate:fresh` ni resets destructivos contra producción.

## Documentación

- [Arquitectura](docs/ARCHITECTURE.md)
- [Dominio](docs/DOMAIN.md)
- [Producto](docs/PRODUCT.md)
- [Mapa UX MVP](docs/UX-MVP.md)
- [Arquitectura de conversión MVP](docs/CONVERSION.md)
- [Atribución, referidos y conversiones V1](docs/ATTRIBUTION.md)
- [Sistema de diseño](docs/DESIGN-SYSTEM.md)
- [Media](docs/MEDIA.md)
- [Operaciones](docs/OPERATIONS.md)
- [Incidentes y lecciones](docs/INCIDENTS.md)
- [Roadmap](docs/ROADMAP.md)

Los documentos anteriores [MVP](docs/MVP.md), [STATUS](docs/STATUS.md) y [DEPLOYMENT](docs/DEPLOYMENT.md) son contexto histórico; el handbook anterior prevalece para el estado operativo actual.
