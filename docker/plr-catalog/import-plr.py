#!/usr/bin/env python3
"""Importador PLR EvaFunnels. Solo stdlib; requiere operador con Docker/root."""
import argparse
import datetime
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
import urllib.request
import urllib.parse

ROOT = Path('/srv/sites/jakawi.store')
OPS = Path('/opt/sites/jakawi.store')
BACKUPS = Path('/var/backups/jakawi.store')
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


def original_cover(url):
    # Only the vendor's published asset domains; no arbitrary internal destinations.
    def allowed(value):
        u=urllib.parse.urlsplit(value)
        host=u.hostname or ''
        if u.scheme != 'https' or u.username or u.password or u.port not in (None,443) or not (host.endswith('.r2.dev') or host == 'eva.igorstorm.com' or host == 'apps.igorstorm.com'):
            raise ValueError('Dominio de portada no reconocido; usar --metadata cover con archivo local.')
    class Redirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self,req,fp,code,msg,headers,newurl):
            allowed(newurl)
            return super().redirect_request(req,fp,code,msg,headers,newurl)
    allowed(url)
    with urllib.request.build_opener(Redirect()).open(urllib.request.Request(url,headers={'User-Agent':'Mozilla/5.0','Referer':'https://jakawi.store/'}),timeout=12) as response:
        data=response.read(10*1024*1024+1)
        if len(data)>10*1024*1024 or not response.headers.get('Content-Type','').startswith('image/'):
            raise ValueError('Portada remota inválida o mayor de 10 MiB.')
        return data


# Editorial titles are exact matches, never keywords from descriptions.
# Only IDs checked against original uploaded packages are pinned here.
PLR_CATEGORIES = {
    'fitness': ['Abdomen de Acero', 'Cardio en Casa', 'Glúteos Perfectos', 'Pilates en Casa', 'Calistenia Sexy', 'Yoga Esencial'],
    'wellness': ['Anti-Ansiedad', 'Anti-Estrés', 'Anti-Insomnio', 'Reiki'],
    'cocina-recetas': ['Pastel Gourmet', 'Brigadeiros Gourmet', 'Dulces sin Azúcar', 'Helados Gourmet', 'Yogur Gourmet', 'Meal Prep Fitness', 'Recetas para Diabéticos', 'Sopa Termogénica', 'Jugo Detox'],
    'nutricion-habitos': ['Dieta Cetogénica', 'Ayuno Intermitente', 'Té Detox'],
    'negocios-marketing': ['Copywriter Pro', 'Experto en Tráfico Pago', 'Freelancer Digital', 'Ganancias con IA', 'Marketing Digital para Principiantes', 'Ventas en WhatsApp'],
    'desarrollo-personal': ['Inteligencia Emocional', 'Venciendo la Procrastinación', 'Rutina Productiva'],
    'espiritualidad-fe': ['App Cristiano', 'Espiritualidad en Acción', 'Lectura Diaria de la Biblia', 'El Poder de la Oración'],
    'astrologia': ['Astrología'],
}
# Real package names that differ from the editorial titles above.
PLR_NAME_ALIASES = {'Acero ABS':'Abdomen de Acero', 'Vientre de acero':'Abdomen de Acero', 'Antiestrés':'Anti-Estrés'}
PLR_VENDOR_TITLES = {
    'gluteo_perfeito_es':'Glúteos Perfectos', 'pilates_app_es':'Pilates en Casa',
    'anti_ansiedade_es':'Anti-Ansiedad', 'antiestresse_app_es':'Anti-Estrés',
    'anti_insonia_es':'Anti-Insomnio', 'astrologia_es':'Astrología',
    'bolo_gourmet_es':'Pastel Gourmet', 'barriga_de_aco_es':'Abdomen de Acero',
}


def editorial_name(name):
    text=unicodedata.normalize('NFKD',name).encode('ascii','ignore').decode().lower()
    return re.sub(r'[^a-z0-9]+',' ',text).strip()


def category_from_metadata(name, description=None, vendor_id=None):
    titles={editorial_name(title):(title,category) for category,names in PLR_CATEGORIES.items() for title in names}
    aliases={editorial_name(alias):editorial_name(title) for alias,title in PLR_NAME_ALIASES.items()}
    normalized=editorial_name(name)
    match=titles.get(aliases.get(normalized,normalized))
    if match is None:
        return None
    # A pinned identifier with a conflicting real title needs editorial review.
    if vendor_id in PLR_VENDOR_TITLES and PLR_VENDOR_TITLES[vendor_id] != match[0]:
        return None
    # Unknown IDs are associated only by an exact real Spanish package title.
    # The existing import registry binds vendor_id + package hash to that product.
    return match[1]


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
    if vendor == 'gluteo_perfeito_es' and (ROOT/'catalog/records/gluteos.json').is_file():
        raise ValueError('Glúteos ya está adoptado en el catálogo: producto COM #1, /gluteos/. No se duplica ni sobrescribe; use --adopt-existing gluteos para comprobarlo.')
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
    cover_source=cover.relative_to(app).as_posix() if cover else None
    cover_bytes=cover.read_bytes() if cover else None
    logo=meta.get('logoUrl','')
    if cover_bytes is None and logo.startswith('https:'):
        try:
            cover_bytes=original_cover(logo)
            cover_source=logo
        except Exception as e:
            print(f'AVISO {slug}: portada remota no disponible ({type(e).__name__}); se conserva icono original como provisional.',file=sys.stderr)
    if cover_bytes is None:
        cover_bytes=icons[-1].read_bytes()
        cover_source=icons[-1].relative_to(app).as_posix()
    if len(cover_bytes)>10*1024*1024:
        raise ValueError('Imagen supera 10 MiB.')
    description=extra.get('description',meta.get('description') or manifest.get('description'))
    short=meta.get('tagline') or manifest.get('description') or description
    short=re.sub(r'<[^>]+>','',short).strip()[:1000] if short else None
    h = hashlib.sha256()
    for p in sorted(app.rglob('*')):
        if p.is_file() and not p.name.startswith('.afp_cache'):
            h.update(p.relative_to(app).as_posix().encode()+b'\0'+p.read_bytes()+b'\0')
    h.update(json.dumps(extra,sort_keys=True).encode())
    data = dict(slug=slug, name=name, language=language, vendor_id=vendor, package_sha256=h.hexdigest(),
                description=description, short_description=short,
                category=extra.get('category') or category_from_metadata(meta.get('name') or manifest.get('name') or '', vendor_id=vendor), cover_source=cover_source,
                cover_base64=base64.b64encode(cover_bytes).decode())
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
        data['fill_missing']=getattr(args,'fill_missing',False)
        safe = {k:v for k,v in data.items() if k not in {'cover_base64','fill_missing'}}
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
        if old.get('cover_source') and com.get('result') != 'created' and 'cover_path' not in com.get('filled',[]):
            safe.pop('cover_source',None)
        old.update(safe)
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


def adopt_existing(slug):
    """One-time adoption of the manually installed first app; never calls a COM writer."""
    if slug != 'gluteos':
        raise ValueError('La adopción puntual solo corresponde a Glúteos existente.')
    catalog=ROOT/'catalog'
    record=catalog/'records/gluteos.json'
    target=catalog/'apps/gluteos'
    public=ROOT/'apps/gluteos/public'
    route=catalog/'routes/gluteos.conf'
    if record.exists():
        data=json.loads(record.read_text())
        if data.get('origin') != 'adopted_existing' or data.get('com',{}).get('product_id') != 1:
            raise ValueError('Registro Glúteos inesperado; no se reemplaza.')
        if data['installed_sha256'] != installed_hash(public):
            raise ValueError('Archivos de Glúteos modificados desde la adopción; revisar explícitamente.')
        for name,digest in data['protection_sha256'].items():
            if hashlib.sha256((target/'access'/name).read_bytes()).hexdigest() != digest:
                raise ValueError('Protección modificada; revisar antes de adoptar otra vez.')
        if route.read_bytes() != (target/'route.conf').read_bytes():
            raise ValueError('Ruta del catálogo modificada; revisar.')
        print(json.dumps(dict(result='unchanged',slug=slug,product_id=1,url=data['url'])))
        return
    if target.exists() or route.exists():
        raise ValueError('Hay preparación previa sin registro; revisar sin sobrescribir.')
    config=json.loads((public/'config-override.json').read_text())['app']
    index=(public/'index.php').read_text()
    vendor=re.search(r"\$AFP_APP_SLUG\s*=\s*['\"]([^'\"]+)['\"]",index)
    if not vendor or vendor[1] != 'gluteo_perfeito_es' or config.get('lang') != 'es':
        raise ValueError('Identificador/idioma de la instalación no coincide con Glúteos español.')
    php="<?php require 'vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();echo json_encode((array)Illuminate\\Support\\Facades\\DB::table('digital_products')->find(1));"
    product=json.loads(run(['docker','exec','-i','jakawi-app-1','php'],input=php))
    if product.get('id') != 1 or product.get('app_key') != slug or product.get('delivery_type') != 'hosted_app':
        raise ValueError('COM #1 no coincide; no se modifica ni crea ningún producto.')
    nginx=(OPS/'nginx.conf').read_text()
    # Extract the existing location blocks verbatim; do not invent new protection rules.
    blocks=[]
    for match in re.finditer(r'^    location [^\n]*gluteos[^\n]*\{',nginx,re.M):
        start=match.start();depth=0;end=None
        for i in range(nginx.index('{',start),len(nginx)):
            if nginx[i]=='{':depth+=1
            if nginx[i]=='}':
                depth-=1
                if depth==0:end=i+1;break
        if end is None:raise ValueError('Bloque Nginx incompleto.')
        blocks.append((start,end,nginx[start:end]))
    if len(blocks)!=7 or 'include /opt/store/catalog/routes/*.conf;' not in nginx:
        raise ValueError('Rutas base diferentes: revisar antes de mover protección.')
    canonical='\n'.join(b[2].strip() for b in blocks)+'\n'
    canonical=canonical.replace('/opt/store/access/gateway.php','/opt/store/catalog/apps/gluteos/access/gateway.php').replace('/opt/store/access/protected-sw.js','/opt/store/catalog/apps/gluteos/access/sw.js').replace('/opt/store/access/access-guard.js','/opt/store/catalog/apps/gluteos/access/access-guard.js')
    for start,end,_ in reversed(blocks):nginx=nginx[:start]+nginx[end:]
    pointer=OPS/'catalog'
    if pointer.exists() or pointer.is_symlink():
        raise ValueError('Alias operativo catalog ya existe; revisar antes de adoptar.')
    stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S%fZ')
    backup=BACKUPS/('adopt-gluteos-'+stamp)
    backup.mkdir(parents=True,mode=0o700)
    shutil.copy2(OPS/'nginx.conf',backup/'nginx.conf')
    shutil.copytree(OPS/'access',backup/'access')
    before=installed_hash(public)
    old_names={'gateway.php':'gateway.php','access-guard.js':'access-guard.js','protected-sw.js':'sw.js'}
    rollback="""#!/usr/bin/env python3
from pathlib import Path
import shutil,subprocess
ops=Path(OPS_PATH);root=Path(ROOT_PATH);backup=Path(BACKUP_PATH)
# Keep canonical files while Nginx's old workers finish in-flight requests.
for name in ['gateway.php','access-guard.js','protected-sw.js']:
 p=ops/'access'/name
 if p.is_symlink():p.unlink()
 shutil.copy2(backup/'access'/name,p)
(ops/'nginx.conf').write_bytes((backup/'nginx.conf').read_bytes())
p=root/'catalog/routes/gluteos.conf'
if p.exists():shutil.move(str(p),str(backup/'reverted-route.conf'))
subprocess.run(['docker','exec','jakawi-store-web-1','nginx','-t'],check=True)
subprocess.run(['docker','exec','jakawi-store-web-1','nginx','-s','reload'],check=True)
p=root/'catalog/records/gluteos.json'
if p.exists():shutil.move(str(p),str(backup/'reverted-record.json'))
# Protection files remain inactive for in-flight requests; public app files are untouched.
p=ops/'catalog'
if p.is_symlink():p.unlink()
print('Rollback protegido PASS; COM, app pública y sesiones PHP conservados.')
""".replace('OPS_PATH',repr(str(OPS))).replace('ROOT_PATH',repr(str(ROOT))).replace('BACKUP_PATH',repr(str(backup)))
    (backup/'rollback.py').write_text(rollback)
    try:
        (target/'access').mkdir(parents=True)
        for old,new in old_names.items():shutil.copy2(OPS/'access'/old,target/'access'/new)
        (target/'route.conf').write_text(canonical)
        permissions(target)
        route.write_text(canonical);os.chmod(route,0o644)
        (OPS/'nginx.conf').write_text(nginx)
        run(['docker','exec','jakawi-store-web-1','nginx','-t'])
        run(['docker','exec','jakawi-store-web-1','nginx','-s','reload'])
        # Compatibility pointers for in-flight workers; one physical canonical source.
        pointer.symlink_to(catalog,target_is_directory=True)
        for old,new in old_names.items():
            legacy=OPS/'access'/old
            legacy.unlink()
            legacy.symlink_to('../catalog/apps/gluteos/access/'+new)
        assert installed_hash(public)==before,'Archivos públicos cambiaron durante adopción'
        data=dict(slug=slug,name=product['name'],language=config['lang'],vendor_id=vendor[1],
                  description=product.get('description'),short_description=product.get('short_description'),
                  category=product.get('category'),cover_path=product.get('cover_path'),
                  origin='adopted_existing',package_sha256=None,installed_sha256=before,
                  state=product['status'],url='https://jakawi.store/gluteos/',
                  com=dict(product_id=1,slug=product['slug'],status=product['status'],result='adopted_read_only'),
                  protection_sha256={name:hashlib.sha256((target/'access'/name).read_bytes()).hexdigest() for name in old_names.values()},
                  adoption_backup=str(backup))
        write_json(record,data)
    except BaseException:
        run(['python3',str(backup/'rollback.py')])
        raise
    print(json.dumps(dict(result='adopted_existing',slug=slug,product_id=1,backup=str(backup),url=data['url']),ensure_ascii=False))


def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('sources',nargs='*',type=Path,help='Uno o varios ZIP/carpetas; permite glob de shell.')
    p.add_argument('--adopt-existing',choices=['gluteos'],help='Adopta la instalación manual existente sin reinstalar ni escribir en COM.')
    p.add_argument('--metadata',type=Path,help='JSON opcional para UNA app: slug/name/description/category/cover; datos reales del propietario.')
    p.add_argument('--fill-missing',action='store_true',help='Completa solo campos vacíos de productos previamente importados; conserva ediciones, precios existentes y autorizaciones.')
    p.add_argument('--check',action='store_true',help='Valida sin instalar, conectar a COM ni modificar servicios.')
    args=p.parse_args()
    if args.adopt_existing and (args.sources or args.metadata or args.fill_missing or args.check):
        p.error('--adopt-existing se usa solo, sin paquetes ni otras operaciones.')
    if not args.sources and not args.adopt_existing:
        p.error('Indique paquetes o --adopt-existing gluteos.')
    if args.metadata and len(args.sources)!=1:
        p.error('--metadata requiere un solo paquete.')
    if not args.check and os.geteuid()!=0:
        p.error('La instalación requiere operador root; la cuenta SFTP solo carga paquetes.')
    ROOT.mkdir(exist_ok=True)
    with open('/tmp/jakawi-plr-import.lock','a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX)
        if args.adopt_existing:
            try:
                adopt_existing(args.adopt_existing)
                return 0
            except Exception as e:
                print('ERROR adopción: '+str(e),file=sys.stderr)
                return 1
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
