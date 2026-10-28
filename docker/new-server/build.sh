#!/bin/bash
# Sequential build on this small server. Requires Docker, systemd and Node >=24.11.
set -euo pipefail
cd "$(dirname "$0")/../.."
export JAKAWI_CHECKOUT="$PWD"
export JAKAWI_PHP_TOOLS_IMAGE="${JAKAWI_PHP_TOOLS_IMAGE:-jakawi-build-v1:dependencies}"
export DOCKER_BUILDKIT=0
test -z "$(git status --porcelain)"
revision=$(git rev-parse HEAD)
tag=${RELEASE_TAG:-$revision}
node_bin=${NODE_BIN:?Set directory containing compatible node and npm}
export PATH="$node_bin:$PATH"
node -e 'const [a,b]=process.versions.node.split(".").map(Number); if(a<24||(a===24&&b<11))process.exit(1)'
test ! -e app/.env
test ! -e app/public/hot
docker build --memory 768m --memory-swap 768m --cpu-period 100000 --cpu-quota 75000 \
  --target dependencies -f docker/new-server/Dockerfile -t "$JAKAWI_PHP_TOOLS_IMAGE" .
temp_container=$(docker create "$JAKAWI_PHP_TOOLS_IMAGE")
trap 'docker rm "$temp_container" >/dev/null' EXIT
docker cp "$temp_container:/var/www/html/vendor" app/vendor
tool_bin=$(mktemp -d)
trap 'docker rm "$temp_container" >/dev/null; rm -rf "$tool_bin"' EXIT
ln -s "$PWD/docker/new-server/php-docker.sh" "$tool_bin/php"
export PATH="$tool_bin:$PATH"
cd app
systemd-run --quiet --wait --pipe --collect --working-directory="$PWD" \
  -p MemoryMax=768M -p MemorySwapMax=0 -p CPUQuota=75% \
  /usr/bin/env PATH="$PATH" HOME="$HOME" npm ci --no-audit --no-fund
php artisan package:discover --no-interaction
for task in types:check build; do
  systemd-run --quiet --wait --pipe --collect --working-directory="$PWD" \
    -p MemoryMax=1100M -p MemorySwapMax=0 -p CPUQuota=75% \
    /usr/bin/env PATH="$PATH" HOME="$HOME" NODE_OPTIONS=--max-old-space-size=768 \
    RAYON_NUM_THREADS=1 VITE_APP_NAME=JAKAWI APP_ENV=production \
    JAKAWI_CHECKOUT="$JAKAWI_CHECKOUT" JAKAWI_PHP_TOOLS_IMAGE="$JAKAWI_PHP_TOOLS_IMAGE" npm run "$task"
done
cd ..
for target in app web; do
  docker build --network none --memory 768m --memory-swap 768m --cpu-period 100000 --cpu-quota 75000 \
    --target "$target" --build-arg "SOURCE_REVISION=$revision" \
    -f docker/new-server/Dockerfile -t "jakawi-$target:$tag" .
done
