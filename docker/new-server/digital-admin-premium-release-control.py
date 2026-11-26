#!/usr/bin/env python3
"""Scoped digital catalog admin release; no schema or catalog data mutations."""
import base64,datetime,fcntl,hashlib,json,os,pathlib,shutil,subprocess,sys,urllib.request
os.umask(0o077)
R=pathlib.Path(os.environ.get('JAKAWI_REPOSITORY','/srv/workspaces/jakawi/com/digital-admin-premium'))
P=pathlib.Path('/opt/sites/jakawi.com')
W=pathlib.Path(os.environ.get('JAKAWI_RELEASE_EVIDENCE','/srv/workspaces/jakawi/com/digital-admin-premium-r3-evidence'))
BASE=os.environ.get('JAKAWI_RUNTIME_BASE','6df9a5b5f40afa7d15f708ebbeb5457f016fef88')
SOURCE_BASE=os.environ.get('JAKAWI_SOURCE_BASE','0c553f2234c0b82c6cf332b56b6e4a55fe956696')
SERVICES=['app','web','worker','scheduler']
IMPORTER=pathlib.Path('/opt/sites/jakawi.store/catalog-tools/import-plr.py')
def run(args,data=None):
 p=subprocess.run(args,input=data,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
 with (W/'operations.private.log').open('ab') as f:f.write(p.stdout+p.stderr)
 if p.returncode:raise RuntimeError('Operation failed: '+args[0]+'; inspect private operations log')
 return p.stdout

def git(*args):return run(['git','-C',str(R),*args]).decode().strip()
def save(name,value):(W/name).write_text(json.dumps(value,indent=2))
def read(name):return json.loads((W/name).read_text())
def compose(file=None):return ['docker','compose','--project-directory',str(P),'--env-file',str(P/'release.env'),'-f',str(file or P/'compose.yaml'),'--profile','production-jobs']
def inspect():return json.loads(run(['docker','inspect',*['jakawi-'+s+'-1' for s in SERVICES]]))
def guard_source():
 assert not git('status','--porcelain')
 assert git('merge-base',BASE,'HEAD')==BASE
 remote=git('ls-remote','origin','refs/heads/main').split()[0]
 assert git('merge-base',remote,'HEAD')==remote
 allowed={'app/routes/console.php', 'app/app/Console/Commands/RefreshDigitalAdminHealth.php', 'app/resources/js/pages/admin/digital/products.css', 'app/tests/Feature/DigitalAdminProductSaveTest.php', 'app/tests/Feature/DigitalSaleActivationTest.php', 'app/app/Http/Controllers/AdminDigitalBulkController.php', 'app/routes/digital.php', 'docs/operations/digital-admin-premium.md', 'app/app/Services/DigitalCollection.php', 'app/app/Services/HostedApps.php', 'app/resources/js/pages/admin/digital/products.tsx', 'app/tests/Feature/DigitalAdminCatalogTest.php', 'docker/new-server/digital-admin-premium-release-control.py', 'app/app/Providers/AppServiceProvider.php', 'app/app/Http/Controllers/AdminDigitalProductController.php', 'app/app/Services/DigitalSaleAvailability.php', 'app/app/Services/DigitalAdminCatalog.php', 'app/app/Services/DigitalAdminSnapshot.php', 'app/app/Services/DigitalProductQr.php'}
 assert set(git('diff','--name-only',SOURCE_BASE,'HEAD').splitlines())<=allowed
 v=read('validation.json');assert v['result']=='PASS' and v['revision']==git('rev-parse','HEAD')
 assert v['manifest_sha256']==hashlib.sha256((R/'app/public/build/manifest.json').read_bytes()).hexdigest()
def build():
 guard_source();images={};revision=git('rev-parse','HEAD')
 for target in ['app','web']:
  tag='jakawi-'+target+':digital-admin-premium'
  run(['env','DOCKER_BUILDKIT=0','docker','build','--network','none','--memory','768m','--memory-swap','768m','--cpu-period','100000','--cpu-quota','75000','--build-arg','SOURCE_REVISION='+revision,'--target',target,'-f',str(R/'docker/new-server/Dockerfile'),'-t',tag,str(R)])
  images[target]=run(['docker','image','inspect',tag,'--format','{{.Id}}']).decode().strip()
 save('images.json',images);print('Build PASS')
def snapshot():
 php=b'''<?php require 'vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();$d=Illuminate\\Support\\Facades\\DB::class;$out=['products'=>$d::table('digital_products')->orderBy('id')->get()];foreach(['digital_orders','digital_payments','digital_access_grants','digital_gifts','app_settings'] as $t)$out[$t]=hash('sha256',json_encode($d::table($t)->orderBy('id')->get()));echo json_encode($out);'''
 state=json.loads(run(['docker','exec','-i','jakawi-app-1','php'],php))
 store=pathlib.Path('/srv/sites/jakawi.store/apps')
 state['store_files']={str(p.relative_to(store)):hashlib.sha256(p.read_bytes()).hexdigest() for p in store.rglob('*') if p.is_file()}
 state['store_runtime']={f:hashlib.sha256((pathlib.Path('/opt/sites/jakawi.store')/f).read_bytes()).hexdigest() for f in ['compose.yaml','nginx.conf','php.ini']}
 return state
def prepare():
 guard_source();state=inspect();assert all(c['Config']['Labels']['org.opencontainers.image.revision']==BASE for c in state)
 assert not (W/'release-metadata.json').exists(), 'Prepare already exists; do not overwrite its baseline.'
 b=pathlib.Path('/var/backups/jakawi.com')/(datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')+'-digital-admin-premium')
 b.mkdir(mode=0o700)
 for name in ['compose.yaml','release.env']:shutil.copyfile(P/name,b/name)
 shutil.copyfile(W/'release-control.previous.py',b/'release-control.py')
 with (b/'database.dump').open('wb') as f:subprocess.run(['docker','exec','jakawi-db-1','pg_dump','-U','jakawi','-d','jakawi_final','--format=custom','--no-owner','--no-acl'],stdout=f,check=True)
 run(['docker','exec','-i','jakawi-db-1','pg_restore','--list'],(b/'database.dump').read_bytes())
 save('runtime-before.private.json',state);save('protected-before.private.json',snapshot())
 candidate=json.loads((P/'compose.yaml').read_text());images=read('images.json')
 for s in SERVICES:
  candidate['services'][s]['image']=images['web' if s=='web' else 'app']
  if 'org.opencontainers.image.revision' in candidate['services'][s].get('labels',{}):candidate['services'][s]['labels']['org.opencontainers.image.revision']=git('rev-parse','HEAD')
 save('compose.candidate.private.json',candidate)
 run(compose(W/'compose.candidate.private.json')+['config','--quiet'])
 env=(P/'release.env').read_text().splitlines()
 (W/'release.env').write_text('\n'.join('APP_IMAGE='+images['app'] if l.startswith('APP_IMAGE=') else 'WEB_IMAGE='+images['web'] if l.startswith('WEB_IMAGE=') else l for l in env)+'\n')
 save('release-metadata.json',{'revision':git('rev-parse','HEAD'),'base':BASE,'backup':str(b),'canonical_base':git('ls-remote','origin','refs/heads/main').split()[0]})
 print('Backup and rollback PASS: '+str(b))
def install(src,dst):
 tmp=dst.with_suffix('.plr-tmp');shutil.copyfile(src,tmp);os.chmod(tmp,0o600);os.replace(tmp,dst)
def deploy():
 guard_source();assert [c['Id'] for c in inspect()]==[c['Id'] for c in read('runtime-before.private.json')]
 install(W/'compose.candidate.private.json',P/'compose.yaml');install(W/'release.env',P/'release.env')
 run(compose()+['up','-d','--no-deps','--no-build','--pull','never',*SERVICES]);print('Deploy PASS')
def verify():
 m=read('release-metadata.json');assert all(c['State']['Running'] and c['Config']['Labels']['org.opencontainers.image.revision']==m['revision'] for c in inspect())
 with urllib.request.urlopen('https://jakawi.com/up',timeout=20) as r:assert r.status==200
 before=read('protected-before.private.json');after=snapshot()
 assert before==after, 'Products, QR settings, prices, orders, rights or STORE changed'
 assert read('validation.json')['manifest_sha256']==run(['docker','exec','jakawi-app-1','sha256sum','public/build/manifest.json']).decode().split()[0]
 assert read('live-smoke.json')['result']=='PASS'
 with urllib.request.urlopen('https://jakawi.com/explorar',timeout=20) as r:assert r.status==200
 save('LIVE.json',{'result':'PASS','revision':m['revision'],'rollback':BASE,'backup':m['backup'],'products_settings_orders_rights_store':'UNCHANGED','explore':'PASS'})
 print('LIVE PASS')
def promote():
 guard_source();verify();git('push','origin','HEAD:refs/heads/main')
 assert git('ls-remote','origin','refs/heads/main').split()[0]==git('rev-parse','HEAD')
 git('update-ref','refs/heads/main',git('rev-parse','HEAD'))
 print('Main promotion PASS')
def rollback():
 b=pathlib.Path(read('release-metadata.json')['backup'])
 for f in ['compose.yaml','release.env']:install(b/f,P/f)
 run(compose()+['up','-d','--no-deps','--no-build','--pull','never',*SERVICES])
 print('Rollback PASS; current metadata, prices, orders and rights retained; no DB restore')
if __name__=='__main__':
 W.mkdir(exist_ok=True)
 with (P/'.release-control.lock').open('a') as f:
  fcntl.flock(f,fcntl.LOCK_EX|fcntl.LOCK_NB)
  {'build':build,'prepare':prepare,'deploy':deploy,'verify':verify,'promote':promote,'rollback':rollback}[sys.argv[1]]()
