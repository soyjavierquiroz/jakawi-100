# Operations

## Safety

Production está offline y en maintenance. `app` y `web` permanecen detenidos;
la base puede permanecer encendida. No ejecutar migraciones, writes, deploys,
`artisan up`, `migrate:fresh`, restauraciones ni pruebas destructivas en
producción.

No se usa PHP ni Composer del host. Los comandos de producción, cuando se
autoricen en el futuro, se ejecutan con Docker:

```bash
docker compose exec app php artisan <command>
```

## Testing isolated

Todo test Laravel y todo comando sobre la base de test pasa por:

```bash
./bin/jakawi-test
./bin/jakawi-test php artisan test
./bin/jakawi-test php artisan migrate:fresh --seed
```

El wrapper usa el proyecto Compose `jakawi-test`, servicios `app-test` y
`db-test`, y base `jakawi_test`. Verifica `APP_ENV=testing`, `DB_HOST=db-test`
y `DB_DATABASE=jakawi_test`. Usar `--env=testing` dentro del contenedor de
producción no proporciona aislamiento.

Para detener sólo los servicios de test: `./bin/jakawi-test down`. Para borrar
sólo su volumen aislado: `./bin/jakawi-test clean`.

## Demo Catalog V2

El catálogo ficticio de QA es independiente del importer V2, que continúa rechazando slugs `demo-*`.

```bash
./bin/jakawi-test php artisan jakawi:seed-demo-catalog
./bin/jakawi-test php artisan jakawi:clear-demo-catalog
```

El seed es idempotente. Genera SVG locales determinísticos bajo `demo/partners`, `demo/locations`, `demo/benefits` y `demo/experiences`; son placeholders ficticios, no uploads. Clear elimina sólo el namespace demo. El PIN `123456` es exclusivamente demo, queda hasheado y nunca debe reutilizarse en producción.

## Validation and proxy

`POST /validar` está limitado a 20 solicitudes por minuto por IP mediante
middleware Laravel. Antes de tráfico significativo, el proxy de producción debe
transmitir correctamente la IP cliente a Laravel. No se modifica
OpenLiteSpeed temporalmente durante V2.6.

## Current cutover state

El cutover fresco de producción todavía no se realizó. La prueba de instalación
fresca se hace exclusivamente sobre el stack aislado; producción sigue offline
hasta la preparación y cutover posteriores.

## Useful read-only checks

```bash
docker compose ps
docker compose logs --tail=100 db
docker stats --no-stream
```

Nunca recomendar borrar volúmenes como primer paso de diagnóstico.
# Catalog Importer V2

`php artisan jakawi:catalog-v2-template DIRECTORY` creates the six required UTF-8 CSV files. `php artisan jakawi:import-catalog-v2 DIRECTORY` validates and previews a plan; dry-run is the default. `--apply` writes to the configured database in one transaction, so a failure leaves no partial catalog.

The importer handles supply only: Partners, Locations, Benefits, Experiences, Experience Partners and Experience Sessions. It never imports PINs, media, members, memberships or redemptions. `demo-*` slugs are reserved. Sessions require the non-public operational `reference_key`, making `(experience, reference_key)` idempotent; Admin-created sessions may leave it null.
