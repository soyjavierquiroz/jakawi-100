#!/bin/sh
# Put a symlink named php to this script in the frontend PATH. PHP stays in Docker.
set -eu
exec docker run --rm --network none --memory 256m --memory-swap 256m --cpus 0.5 \
  --pids-limit 64 --cap-drop ALL --security-opt no-new-privileges \
  --user "$(id -u):$(id -g)" \
  -e APP_ENV=build -e APP_DEBUG=false -e LOG_CHANNEL=stderr \
  -e CACHE_STORE=file -e SESSION_DRIVER=file \
  -e DB_CONNECTION=pgsql -e DB_HOST=127.0.0.1 \
  -v "${JAKAWI_CHECKOUT:?Set absolute repository path}/app:/var/www/html" \
  -w /var/www/html "${JAKAWI_PHP_TOOLS_IMAGE:?Set built dependencies image}" php "$@"
