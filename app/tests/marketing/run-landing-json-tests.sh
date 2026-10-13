#!/usr/bin/env bash
set -euo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.."
landing_test_dir=$(mktemp -d)
trap 'rm -rf -- "$landing_test_dir"' EXIT
./node_modules/.bin/tsc resources/js/lib/landing-json.ts resources/js/lib/landing-editor-labels.ts --target ES2022 --module ES2022 --moduleResolution bundler --lib ES2022,DOM --strict --skipLibCheck --outDir "$landing_test_dir"
printf '{"type":"module"}\n' > "$landing_test_dir/package.json"
LANDING_EDITOR_LABELS_MODULE="$landing_test_dir/landing-editor-labels.js" LANDING_JSON_MODULE="$landing_test_dir/landing-json.js" node --test tests/marketing/landing-json.test.mjs
