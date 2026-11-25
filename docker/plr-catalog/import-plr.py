#!/usr/bin/env python3
"""Importador PLR EvaFunnels. Solo stdlib; requiere operador con Docker/root."""
import argparse
import base64
import fcntl
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import stat
import subprocess
import sys
import tempfile
import unicodedata
import zipfile

ROOT = Path('/srv/sites/jakawi.store')
OPS = Path('/opt/sites/jakawi.store')
HERE = Path(__file__).resolve().parent
MAX_BYTES = 512 * 1024 * 1024
SLUG = r'[a-z0-9]+(?:-[a-z0-9]+)*'


def run(args, **kwargs):
    p = subprocess.run(args, capture_output=True, text=True, **kwargs)
    if p.returncode:
        # Never print package source, credentials or Docker configuration.
        raise ValueError(f'Falló {args[0]}: ' + (p.stderr or p.stdout)[-1500:])
    return p.stdout


def write_json(path, value):
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix('.tmp')
    tmp.write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n')
    os.chmod(tmp, 0o640)
    tmp.replace(path)


def unpack(source, dest):
    if source.is_dir():
        files = list(source.rglob('*'))
        if any(p.is_symlink() or not (p.is_dir() or p.is_file()) for p in files):
            raise ValueError('La carpeta contiene enlaces o archivos especiales.')
        if len(files) > 10000 or sum(p.stat().st_size for p in files if p.is_file()) > MAX_BYTES:
            raise ValueError('Paquete excede 10000 archivos o 512 MiB.')
        shutil.copytree(source, dest, dirs_exist_ok=True)
    elif zipfile.is_zipfile(source):
        with zipfile.ZipFile(source) as z:
            seen = set()
            infos = z.infolist()
            if len(infos) > 10000 or sum(i.file_size for i in infos) > MAX_BYTES:
                raise ValueError('ZIP excede 10000 archivos o 512 MiB descomprimidos.')
            for i in infos:
                name = PurePosixPath(i.filename)
                mode = i.external_attr >> 16
                if name.is_absolute() or '..' in name.parts or '\\' in i.filename or stat.S_ISLNK(mode) or (stat.S_IFMT(mode) not in (0,stat.S_IFREG,stat.S_IFDIR)):
                    raise ValueError('ZIP contiene ruta insegura, enlace o archivo especial.')
                if str(name) in seen:
                    raise ValueError('ZIP contiene rutas duplicadas.')
                seen.add(str(name))
            z.extractall(dest)
    else:
        raise ValueError('Se esperaba un ZIP original o una carpeta.')


def local_file(root, name):
    if not isinstance(name, str) or not name or '://' in name or name.startswith(('/', 'data:')):
        raise ValueError('Se requiere ruta local relativa: ' + str(name))
    p = root / name
    if not p.resolve().is_relative_to(root.resolve()) or not p.is_file():
        raise ValueError('Falta archivo local: ' + name)
    return p


def inspect(root, override=None):
    candidates = [p.parent for p in root.rglob('index.php') if '__MACOSX' not in p.parts]
    if len(candidates) != 1:
        raise ValueError(f'Se requiere una sola app por paquete; encontrados {len(candidates)} index.php. Separe las variantes.')
    app = candidates[0]
    for name in ('index.php','app.js','sw.js','manifest.json','config-override.json'):
        if not (app/name).is_file():
            raise ValueError('Falta archivo esencial: ' + name)
    if any(p != app/'index.php' for p in app.rglob('*.php')):
        raise ValueError('PHP adicional requiere revisión explícita; solo se ejecuta index.php.')
    config = json.loads((app/'config-override.json').read_text())
    manifest = json.loads((app/'manifest.json').read_text())
    meta = config.get('app', {})
    language = meta.get('lang') or manifest.get('lang')
    if not isinstance(language,str) or not re.fullmatch(r'es(?:[-_][A-Za-z0-9]+)*', language):
        raise ValueError('No se verificó variante española: app.lang/manifest.lang debe ser es o es-XX.')
    php = (app/'index.php').read_text()
    def variable(name):
        m = re.search(r'\$'+name+r'''\s*=\s*['"]([^'"\n]+)['"]\s*;''', php)
        if not m:
            raise ValueError('Falta identificador/licencia/API EvaFunnels: ' + name)
        return m[1]
    vendor = variable('AFP_APP_SLUG')
    variable('AFP_LICENSE_KEY')  # Deliberately never copied to metadata/logs.
    api = variable('AFP_API_URL')
    if api != 'https://eva.igorstorm.com/api/render.php':
        raise ValueError('Renderizador desconocido; revisar paquete original.')
    if not re.search(r'CURLOPT_SSL_VERIFYPEER\s*,\s*(?:true|false)',php):
        raise ValueError('No se encontró configuración TLS conocida; revisar paquete.')
    extra = json.loads(override.read_text()) if override else {}
    if set(extra) - {'slug','name','description','category','cover'}:
        raise ValueError('Metadatos auxiliares admitidos: slug, name, description, category, cover.')
    slug = extra.get('slug') or re.sub(r'[^a-z0-9]+','-',unicodedata.normalize('NFKD',vendor).encode('ascii','ignore').decode().lower()).strip('-')
    if not re.fullmatch(SLUG, slug) or len(slug)>180 or slug in {'gluteos','gluteo-perfecto','healthz','apps','admin','api'} or vendor == 'gluteo_perfeito_es':
        raise ValueError('Slug reservado/inválido o Glúteos existente; no se duplicará.')
    name = extra.get('name') or meta.get('name') or manifest.get('name')
    if not name:
        raise ValueError('Falta nombre: aportar metadatos del propietario mediante --metadata.')
    icons = [local_file(app,i['src']) for i in manifest.get('icons', [])]
    if not icons:
        raise ValueError('Manifest sin iconos locales; aportar paquete completo.')
    cover = local_file(app, extra['cover']) if extra.get('cover') else None
    if not cover:
        logo = meta.get('logoUrl','')
        if logo and not logo.startswith(('https:', 'http:', 'data:', '/')):
            cover = local_file(app, logo)
    # Icono local como portada provisional, sin inventar una imagen comercial.
    cover = cover or icons[-1]
    if cover.stat().st_size > 10*1024*1024:
        raise ValueError('Imagen supera 10 MiB.')
    h = hashlib.sha256()
    for p in sorted(app.rglob('*')):
        if p.is_file() and not p.name.startswith('.afp_cache'):
            h.update(p.relative_to(app).as_posix().encode()+b'\0'+p.read_bytes()+b'\0')
    h.update(json.dumps(extra,sort_keys=True).encode())
    data = dict(slug=slug, name=name, language=language, vendor_id=vendor, package_sha256=h.hexdigest(),
                description=extra.get('description',meta.get('description') or manifest.get('description')),
                category=extra.get('category'), cover_source=cover.relative_to(app).as_posix(),
                cover_base64=base64.b64encode(cover.read_bytes()).decode())
    return app, manifest, data


def permissions(path):
    for p in [path, *path.rglob('*')]:
        os.chmod(p,0o750 if p.is_dir() else 0o640)
        os.chown(p,0,1000)  # Existing STORE containers' read group; SFTP cannot modify installed apps.


def prepare(app, manifest, data, stage):
    slug = data['slug']
    public = stage/'public'
    shutil.copytree(app,public)
    for p in public.glob('.afp_cache*'):
        p.unlink()
    index = public/'index.php'
    index.write_text(re.sub(r'(CURLOPT_SSL_VERIFYPEER\s*,\s*)false',r'\1true',index.read_text()))
    manifest.update(id=f'/{slug}/',start_url=f'/{slug}/',scope=f'/{slug}/',lang=data['language'])
    write_json(public/'manifest.json',manifest)
    access = stage/'access'
    access.mkdir()
    gateway = (HERE/'templates/gateway.php').read_text().replace('gluteos',slug)
    gateway = gateway.replace("$path = parse_url", "$c['app'] = '"+slug+"';\n$path = parse_url")
    # Additional static content is served only after the same COM authorization.
    assets = [p.relative_to(public).as_posix() for p in public.rglob('*') if p.is_file() and not any(x.startswith('.') for x in p.relative_to(public).parts) and p.suffix.lower() in {'.js','.css','.png','.jpg','.jpeg','.webp','.svg','.woff','.woff2','.mp3','.mp4','.pdf'}]
    assets = [a for a in assets if a not in {'sw.js','access-guard.js'}]
    if any(not re.fullmatch(r'[A-Za-z0-9_./-]+', a) for a in assets):
        raise ValueError('Nombres de assets no compatibles; revisar antes de generar rutas.')
    asset_php = json.dumps(assets,ensure_ascii=True).replace('\\/', '/')
    # JSON string decoded in PHP avoids interpolating package names into source code.
    encoded = base64.b64encode(asset_php.encode()).decode()
    insertion = f'''$asset = substr($path, strlen('/{slug}/'));
if (in_array($asset, json_decode(base64_decode('{encoded}'), true), true)) {{
    $file = '/srv/apps/{slug}/public/'.$asset;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $types = ['js'=>'application/javascript','css'=>'text/css','svg'=>'image/svg+xml','woff'=>'font/woff','woff2'=>'font/woff2'];
    header('Content-Type: '.($types[$ext] ?? mime_content_type($file)));
    readfile($file); exit;
}}
'''
    gateway = gateway.replace('// Inject only authorization supervision',insertion+'// Inject only authorization supervision')
    (access/'gateway.php').write_text(gateway)
    (access/'access-guard.js').write_text((HERE/'templates/access-guard.js').read_text().replace('gluteos',slug))
    worker = f'''// JAKAWI {slug}: red obligatoria, sin contenido offline ni cachés compartidas.
self.addEventListener('install',()=>self.skipWaiting());
self.addEventListener('activate',e=>e.waitUntil(self.clients.claim()));
self.addEventListener('fetch',e=>{{
 const u=new URL(e.request.url);
 if(u.origin===self.location.origin && u.pathname.startsWith('/{slug}/'))
 e.respondWith(fetch(e.request,{{cache:'no-store'}}).catch(()=>new Response('Abre la app desde tu biblioteca JAKAWI con Internet.',{{status:503}})));
}});
'''
    (access/'sw.js').write_text(worker)
    (public/'sw.js').write_text(worker)
    base=f'/opt/store/catalog/apps/{slug}/access'
    route=f'''# Generado desde catálogo técnico. Nunca ejecutar PHP subido directamente.
location = /{slug} {{ return 308 https://jakawi.store/{slug}/; }}
location = /{slug}/sw.js {{ alias {base}/sw.js; add_header Service-Worker-Allowed /{slug}/ always; add_header Cache-Control "no-cache" always; }}
location = /{slug}/access-guard.js {{ alias {base}/access-guard.js; add_header Cache-Control "no-store" always; }}
location = /{slug}/manifest.json {{ alias /srv/apps/{slug}/public/manifest.json; default_type application/manifest+json; add_header Cache-Control "no-cache" always; }}
'''
    # No generic executable/static location: every allowed path is explicit.
    patterns = ['','index.php','_entry','_check','_health',*assets]
    expression = '|'.join(re.escape(p) for p in patterns)
    route+=f'''location ~ ^/{slug}/(?:{expression})$ {{
 include /etc/nginx/fastcgi_params;
 fastcgi_param SCRIPT_FILENAME {base}/gateway.php;
 fastcgi_param HTTPS $php_https;
 fastcgi_param HTTP_PROXY "";
 fastcgi_pass php:9000;
 fastcgi_hide_header X-Powered-By;
}}
location /{slug}/ {{ return 404; }}
'''
    (stage/'route.conf').write_text(route)


def installed_hash(path):
    h=hashlib.sha256()
    for p in sorted(path.rglob('*')):
        if p.is_file() and not p.name.startswith('.afp_cache'):
            h.update(p.relative_to(path).as_posix().encode()+b'\0'+p.read_bytes()+b'\0')
    return h.hexdigest()


def import_one(source, args):
    with tempfile.TemporaryDirectory(prefix='plr-') as tmp:
        extracted=Path(tmp)/'source'
        extracted.mkdir()
        unpack(source,extracted)
        app, manifest, data = inspect(extracted,args.metadata)
        safe = {k:v for k,v in data.items() if k != 'cover_base64'}
        slug=data['slug']
        if args.check:
            print(json.dumps(dict(result='validated_only',**safe),ensure_ascii=False))
            return
        catalog = ROOT/'catalog'
        catalog.mkdir(exist_ok=True)
        record = catalog/'records'/f'{slug}.json'
        target = ROOT/'apps'/slug
        if record.exists():
            old=json.loads(record.read_text())
            if old['package_sha256'] != data['package_sha256']:
                raise ValueError('Paquete/metadatos distintos: no se sobrescribe '+str(target)+'. Requiere revisión y retiro explícito con respaldo.')
            if target.is_dir() and old.get('installed_sha256') != installed_hash(target/'public'):
                raise ValueError('Archivos instalados modificados; revisar antes de reintentar.')
            if not target.is_dir():
                raise ValueError('Instalación registrada ausente; requiere recuperación explícita.')
        else:
            if target.exists():
                raise ValueError('Instalación existente sin registro: '+str(target))
            for other in (catalog/'records').glob('*.json'):
                if json.loads(other.read_text())['vendor_id'] == data['vendor_id']:
                    raise ValueError('Identificador EvaFunnels ya instalado con otro slug.')
            stage=Path(tmp)/'stage'
            stage.mkdir()
            prepare(app,manifest,data,stage)
            # Copy to persistent staging, then atomic rename on the same filesystem.
            staging = catalog/f'.staging-{slug}'
            if staging.exists():
                shutil.rmtree(staging)
            shutil.copytree(stage,staging)
            permissions(staging)
            (catalog/'apps').mkdir(exist_ok=True)
            if (catalog/'apps'/slug).exists():
                raise ValueError('Preparación existente sin registro; revisar '+str(catalog/'apps'/slug))
            staging.rename(catalog/'apps'/slug)
            # App files have exactly the required persistent layout.
            install_stage=ROOT/'apps'/f'.{slug}-install'
            if install_stage.exists():
                raise ValueError('Instalación temporal existente; revisar '+str(install_stage))
            install_stage.mkdir(mode=0o750)
            os.chown(install_stage,0,1000)
            (catalog/'apps'/slug/'public').rename(install_stage/'public')
            install_stage.rename(target)
            safe.update(installed_sha256=installed_hash(target/'public'),state='installed_pending_com',url=f'https://jakawi.store/{slug}/')
            write_json(record,safe)
        # Safe retry after COM/network failure: it only returns the existing product.
        out=run(['docker','exec','-i','jakawi-app-1','php','artisan','hosted-app:import'],input=json.dumps(data))
        try:
            com=json.loads(out.strip())
        except json.JSONDecodeError:
            raise ValueError('COM no devolvió JSON válido; instalación conservada para reintentar.')
        old=json.loads(record.read_text())
        old.update(com=com,state='draft')
        write_json(record,old)
        (catalog/'routes').mkdir(exist_ok=True)
        route=catalog/'routes'/f'{slug}.conf'
        shutil.copyfile(catalog/'apps'/slug/'route.conf',route)
        os.chmod(route,0o644)
        try:
            run(['docker','exec','jakawi-store-web-1','nginx','-t'])
        except Exception:
            route.unlink()
            raise
        run(['docker','exec','jakawi-store-web-1','nginx','-s','reload'])
        print(json.dumps(dict(slug=slug,**com,url=old['url']),ensure_ascii=False))


def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('sources',nargs='+',type=Path,help='Uno o varios ZIP/carpetas; permite glob de shell.')
    p.add_argument('--metadata',type=Path,help='JSON opcional para UNA app: slug/name/description/category/cover; datos reales del propietario.')
    p.add_argument('--check',action='store_true',help='Valida sin instalar, conectar a COM ni modificar servicios.')
    args=p.parse_args()
    if args.metadata and len(args.sources)!=1:
        p.error('--metadata requiere un solo paquete.')
    if not args.check and os.geteuid()!=0:
        p.error('La instalación requiere operador root; la cuenta SFTP solo carga paquetes.')
    ROOT.mkdir(exist_ok=True)
    with open('/tmp/jakawi-plr-import.lock','a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX)
        errors=0
        for source in args.sources:
            try:
                import_one(source,args)
            except Exception as e:
                errors+=1
                print(f'ERROR {source.name}: {e}',file=sys.stderr)
        return 1 if errors else 0

if __name__=='__main__':
    sys.exit(main())
