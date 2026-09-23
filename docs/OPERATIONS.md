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
