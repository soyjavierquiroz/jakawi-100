# JAKAWI

La app para vivir más tu ciudad. Descubrimiento de Benefits, Experiences, Unlocks y Challenges; Membership, canjes y participación convierten la oferta local en valor verificable.

Laravel 13 monolítico · PHP 8.4 Docker · React 19 · TypeScript · Inertia 3 · Tailwind 4 · shadcn/ui · PostgreSQL 16.

Producción: https://jakawi.com; salud: https://jakawi.com/up. Producción directa, sin staging/preview operativo. El piloto comercial todavía no comenzó: falta inventario real y preparación comercial.

## Ejecución y validación

Desde la raíz, `app/` contiene Laravel y React, `docker/` las imágenes y `compose.yaml` el runtime. Laravel/Composer se ejecutan con PHP 8.4 Docker. **PHP DEL HOST: NUNCA USAR NI INSPECCIONAR.** No ejecutar el setup del starter kit contra producción.

Comandos de validación, sólo cuando la tarea los autorice:

```bash
./bin/jakawi-test
./bin/jakawi-test php artisan test
./bin/jakawi-test npm run types:check
docker build -f docker/Dockerfile   --target build   -t jakawi-frontend-check .
```

La imagen de verificación es temporal. El baseline conocido de `CampaignTest::admin_can_create_campaign_and_non_admin_cannot` es redirect esperado frente a 403 recibido; no es una regresión nueva.

Empieza por el [índice de documentación](docs/README.md), el [estado actual](docs/CURRENT.md) y los manuales de [funciones](docs/product/MANUAL_DE_FUNCIONES.md) e [inventario](docs/operations/MANUAL_DE_INVENTARIO.md). Desarrollo, backups y política de despliegue: [operaciones](docs/OPERATIONS.md).
