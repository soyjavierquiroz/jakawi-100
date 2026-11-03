#!/bin/bash
set -euo pipefail
cd /srv/repos/jakawi.com
export JAKAWI_CHECKOUT="$PWD"
export JAKAWI_PHP_TOOLS_IMAGE=$(docker inspect jakawi-app-1 --format '{{.Image}}')
export PATH="/opt/node-v24.22.1-linux-x64/bin:$PATH"
tool_bin=$(mktemp -d)
trap 'rm -rf "$tool_bin"' EXIT
ln -s "$PWD/docker/new-server/php-docker.sh" "$tool_bin/php"
export PATH="$tool_bin:$PATH"
cd app
php artisan package:discover --no-interaction
for task in types:check build; do
 systemd-run --quiet --wait --pipe --collect --working-directory="$PWD" -p MemoryMax=900M -p MemorySwapMax=0 -p CPUQuota=60% /usr/bin/env PATH="$PATH" HOME="$HOME" NODE_OPTIONS=--max-old-space-size=640 RAYON_NUM_THREADS=1 VITE_APP_NAME=JAKAWI APP_ENV=production JAKAWI_CHECKOUT="$JAKAWI_CHECKOUT" JAKAWI_PHP_TOOLS_IMAGE="$JAKAWI_PHP_TOOLS_IMAGE" npm run "$task"
done
