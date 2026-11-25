#!/usr/bin/env python3
"""Infraestructura STORE para catálogo. No modifica Glúteos ni secretos."""
import datetime
import hashlib
import json
import os
from pathlib import Path
import pwd
import shutil
import subprocess
import yaml  # Present on operational host; importer itself uses only Python stdlib.

ops=Path('/opt/sites/jakawi.store')
root=Path('/srv/sites/jakawi.store')
here=Path(__file__).resolve().parent
for name in ['gateway.php','access-guard.js']:
    if (ops/'access'/name).read_bytes() != (here/'templates'/name).read_bytes():
        raise SystemExit('La integración base cambió; partir de su versión final antes de instalar.')
stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
backup=Path('/var/backups/jakawi.store')/('plr-catalog-'+stamp)
backup.mkdir(mode=0o700,parents=True)
paths=['compose.yaml','nginx.conf','php.ini']
for f in paths: shutil.copy2(ops/f,backup/f)
def run(cmd): subprocess.run(cmd,check=True,stdout=subprocess.DEVNULL)
def hashes():
    return {str(p.relative_to(root)):hashlib.sha256(p.read_bytes()).hexdigest() for p in (root/'apps/gluteos/public').rglob('*') if p.is_file()}
before=hashes()
(backup/'gluteos-sha256.json').write_text(json.dumps(before,indent=2))
for d in ['catalog/routes','catalog/apps','catalog/records']:
    (root/d).mkdir(parents=True,exist_ok=True)
inbox=root/'incoming'
inbox.mkdir(exist_ok=True)
user=pwd.getpwnam('store_upload')
os.chown(inbox,user.pw_uid,user.pw_gid)
os.chmod(inbox,0o750)
shutil.copytree(here,ops/'catalog-tools',dirs_exist_ok=True,ignore=shutil.ignore_patterns('__pycache__'))
link=Path('/usr/local/sbin/jakawi-plr-import')
if not link.exists(): link.symlink_to(ops/'catalog-tools/import-plr.py')
try:
    compose=yaml.safe_load((ops/'compose.yaml').read_text())
    for service in ('web','php'):
        volumes=compose['services'][service]['volumes']
        for mount in ['/srv/sites/jakawi.store/apps:/srv/apps:ro','/srv/sites/jakawi.store/catalog:/opt/store/catalog:ro']:
            if mount not in volumes: volumes.append(mount)
    (ops/'compose.yaml').write_text(yaml.safe_dump(compose,sort_keys=False))
    nginx=(ops/'nginx.conf').read_text()
    include='    include /opt/store/catalog/routes/*.conf;\n'
    if include not in nginx:
        needle='    location ~* \\.(?:php'
        at=nginx.index(needle)
        nginx=nginx[:at]+include+nginx[at:]
    (ops/'nginx.conf').write_text(nginx)
    ini=(ops/'php.ini').read_text().replace(':/srv/apps/gluteos/public:',':/srv/apps:')
    (ops/'php.ini').write_text(ini)
    run(['docker','compose','-f',str(ops/'compose.yaml'),'run','--rm','--no-deps','web','-t'])
    run(['docker','compose','-f',str(ops/'compose.yaml'),'up','-d','--no-deps','web','php'])
    assert hashes()==before, 'Los archivos de Glúteos cambiaron durante la release'
except BaseException:
    for f in paths: shutil.copy2(backup/f,ops/f)
    run(['docker','compose','-f',str(ops/'compose.yaml'),'up','-d','--no-deps','web','php'])
    raise
(backup/'rollback.sh').write_text('''#!/bin/bash
set -euo pipefail
b="$(cd "$(dirname "$0")" && pwd)"
for f in compose.yaml nginx.conf php.ini; do cp "$b/$f" "/opt/sites/jakawi.store/$f"; done
docker compose -f /opt/sites/jakawi.store/compose.yaml up -d --no-deps web php
# Catálogo y apps se conservan; las rutas nuevas quedan deshabilitadas.
''')
os.chmod(backup/'rollback.sh',0o700)
print(json.dumps({'backup':str(backup),'gluteos_files_unchanged':True}))
