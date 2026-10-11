#!/usr/bin/env bash
set -euo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.."
vsl_test_dir=$(mktemp -d)
trap 'rm -rf -- "$vsl_test_dir"' EXIT
./node_modules/.bin/tsc resources/js/components/media/jakawi-vsl-player/html5-engine.ts resources/js/components/media/jakawi-vsl-player/youtube-engine.ts resources/js/components/media/jakawi-vsl-player/marketing-presentation.ts resources/js/components/media/jakawi-vsl-player/course-presentation.ts resources/js/components/media/jakawi-vsl-player/video-shortcode.ts resources/js/components/media/jakawi-vsl-player/video-theme.ts --target ES2022 --module ES2022 --moduleResolution bundler --lib ES2022,DOM --strict --skipLibCheck --outDir "$vsl_test_dir"
printf '{"type":"module"}\n' > "$vsl_test_dir/package.json"
VSL_HLS_MODULE="$vsl_test_dir/hls-source.js" node --test tests/media/hls-source.test.mjs
VSL_ENGINE_MODULE="$vsl_test_dir/html5-engine.js" node --test tests/media/jakawi-vsl-player.test.mjs
VSL_YOUTUBE_MODULE="$vsl_test_dir/youtube-engine.js" node --test tests/media/youtube-engine.test.mjs
VSL_PRESENTATION_MODULE="$vsl_test_dir/marketing-presentation.js" node --test tests/media/marketing-presentation.test.mjs
VSL_CROP_MODULE="$vsl_test_dir/youtube-crop.js" node --test tests/media/youtube-crop.test.mjs

VSL_COURSE_MODULE="$vsl_test_dir/course-presentation.js" node --test tests/media/course-presentation.test.mjs

VSL_CONFIG_MODULE="$vsl_test_dir" node --test tests/media/video-configuration.test.mjs
