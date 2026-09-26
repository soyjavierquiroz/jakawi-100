# Histórico — ejemplos de importación

`catalog-import-example.csv` pertenece al importador anterior basado en Merchant y se conserva únicamente como referencia histórica. No es compatible con el dominio actual ni debe usarse para cargar datos.

Para crear el formato vigente, dentro del contenedor PHP 8.4 use `php artisan jakawi:catalog-v2-template <directorio>` y valide con `php artisan jakawi:import-catalog-v2 <directorio>` (dry-run por defecto). El formato actual usa `partners.csv` y `partner_slug`.
