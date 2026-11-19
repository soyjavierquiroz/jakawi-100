#!/usr/bin/env bash
# Reviewed-source overlay on immutable LIVE images; dependencies remain identical.
set -euo pipefail
cd "$(dirname "$0")/../.."
: "${JAKAWI_RELEASE_EVIDENCE:?Set an isolated evidence directory}"
: "${JAKAWI_BASE_REVISION:?Set verified LIVE revision}"
: "${JAKAWI_BASE_APP_IMAGE:?Set immutable app image ID}"
: "${JAKAWI_BASE_WEB_IMAGE:?Set immutable web image ID}"
: "${JAKAWI_RELEASE_TAG:?Set candidate image tag}"
test -z "$(git status --porcelain)"
test -s app/public/build/manifest.json
revision=$(git rev-parse HEAD)
git merge-base --is-ancestor "$JAKAWI_BASE_REVISION" "$revision"
test -z "$(git diff --name-only --diff-filter=D "$JAKAWI_BASE_REVISION" "$revision" -- app)"
mkdir -p "$JAKAWI_RELEASE_EVIDENCE"
python3 - "$revision" <<'PY'
import json,os,pathlib,subprocess,sys
revision=sys.argv[1];e=pathlib.Path(os.environ['JAKAWI_RELEASE_EVIDENCE'])
base=os.environ['JAKAWI_BASE_REVISION'];tag=os.environ['JAKAWI_RELEASE_TAG']
files=subprocess.check_output(['git','diff','--name-only',base,revision,'--','app']).decode().splitlines()
assert files
lines=[]
for target,key in [('app','JAKAWI_BASE_APP_IMAGE'),('web','JAKAWI_BASE_WEB_IMAGE')]:
 image=os.environ[key];assert image.startswith('sha256:')
 state=json.loads(subprocess.check_output(['docker','image','inspect',image]))[0]
 assert state['Config']['Labels']['org.opencontainers.image.revision']==base
 lines += ['FROM '+image+' AS '+target,'LABEL org.opencontainers.image.revision="'+revision+'"']
 if target=='app':
  for f in files:
   assert pathlib.Path(f).is_file() and not any(c.isspace() for c in f)
   lines.append('COPY --chown=www-data:www-data '+f+' /var/www/html/'+f[4:])
  lines.append('RUN rm -f bootstrap/cache/config.php bootstrap/cache/routes-*.php')
 lines.append('COPY app/public/build/ /var/www/html/public/build/')
(e/'Dockerfile').write_text('\n'.join(lines)+'\n')
(e/'build-source.json').write_text(json.dumps({'revision':revision,'base_revision':base,'source':str(pathlib.Path.cwd()),'base_app_image':os.environ['JAKAWI_BASE_APP_IMAGE'],'base_web_image':os.environ['JAKAWI_BASE_WEB_IMAGE'],'files':files},indent=2))
PY
export DOCKER_BUILDKIT=0
for target in app web; do
    docker build --network none --memory 512m --memory-swap 512m --cpu-period 100000 --cpu-quota 50000 --target "$target" -f "$JAKAWI_RELEASE_EVIDENCE/Dockerfile" -t "jakawi-$target:$JAKAWI_RELEASE_TAG" .
done
