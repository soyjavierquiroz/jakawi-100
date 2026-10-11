#!/usr/bin/env bash
set -euo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.."
vsl_test_dir=$(mktemp -d)
trap 'rm -rf -- "$vsl_test_dir"' EXIT
./node_modules/.bin/tsc resources/js/components/media/jakawi-vsl-player/html5-engine.ts --target ES2022 --module ES2022 --moduleResolution bundler --lib ES2022,DOM --strict --skipLibCheck --outDir "$vsl_test_dir"
printf '{"type":"module"}\n' > "$vsl_test_dir/package.json"
VSL_ENGINE_MODULE="$vsl_test_dir/html5-engine.js" node --test tests/media/jakawi-vsl-player.test.mjs
