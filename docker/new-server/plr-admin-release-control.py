#!/usr/bin/env python3
"""Scoped COM PLR editorial release. No STORE runtime or bridge changes."""
import datetime,fcntl,hashlib,json,os,pathlib,shutil,subprocess,sys,urllib.request
os.umask(0o077)
R=pathlib.Path(os.environ.get('JAKAWI_REPOSITORY','/srv/workspaces/jakawi/com/plr-admin-defaults'))
P=pathlib.Path('/opt/sites/jakawi.com')
W=pathlib.Path(os.environ.get('JAKAWI_RELEASE_EVIDENCE','/srv/workspaces/jakawi/com/plr-admin-defaults-evidence'))
BASE='4ee5b8341016019c81016c4322adfba283409ebd'
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
 allowed={'app/app/Http/Controllers/AdminDigitalProductController.php','app/app/Services/DigitalSaleAvailability.php','app/app/Services/DigitalProductQr.php','app/app/Services/HostedAppCatalogImport.php','app/resources/js/pages/admin/digital/product-form.tsx','app/tests/Feature/HostedAppCatalogTest.php','docker/plr-catalog/import-plr.py','docker/plr-catalog/test-importer.py','docker/new-server/plr-admin-release-control.py','docs/operations/plr-catalog.md'}
 assert set(git('diff','--name-only',BASE,'HEAD').splitlines())<=allowed
 v=read('validation.json');assert v['result']=='PASS' and v['revision']==git('rev-parse','HEAD')
 assert v['manifest_sha256']==hashlib.sha256((R/'app/public/build/manifest.json').read_bytes()).hexdigest()
def build():
 guard_source();images={};revision=git('rev-parse','HEAD')
 for target in ['app','web']:
  tag='jakawi-'+target+':plr-admin-defaults'
  run(['env','DOCKER_BUILDKIT=0','docker','build','--network','none','--memory','768m','--memory-swap','768m','--cpu-period','100000','--cpu-quota','75000','--build-arg','SOURCE_REVISION='+revision,'--target',target,'-f',str(R/'docker/new-server/Dockerfile'),'-t',tag,str(R)])
  images[target]=run(['docker','image','inspect',tag,'--format','{{.Id}}']).decode().strip()
 save('images.json',images);print('Build PASS')
def snapshot():
 php=b'''<?php require 'vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();$d=Illuminate\\Support\\Facades\\DB::class;$out=['product1'=>(array)$d::table('digital_products')->find(1)];foreach(['digital_orders','digital_payments','digital_access_grants','digital_gifts','app_settings'] as $t)$out[$t]=hash('sha256',json_encode($d::table($t)->orderBy('id')->get()));echo json_encode($out);'''
 state=json.loads(run(['docker','exec','-i','jakawi-app-1','php'],php))
 store=pathlib.Path('/srv/sites/jakawi.store/apps')
 state['store_files']={str(p.relative_to(store)):hashlib.sha256(p.read_bytes()).hexdigest() for p in store.rglob('*') if p.is_file()}
 state['store_runtime']={f:hashlib.sha256((pathlib.Path('/opt/sites/jakawi.store')/f).read_bytes()).hexdigest() for f in ['compose.yaml','nginx.conf','php.ini']}
 return state
def prepare():
 guard_source();state=inspect();assert all(c['Config']['Labels']['org.opencontainers.image.revision']==BASE for c in state)
 assert IMPORTER.read_bytes()==run(['git','-C',str(R),'show',BASE+':docker/plr-catalog/import-plr.py'])
 assert not (W/'release-metadata.json').exists(), 'Prepare already exists; do not overwrite its baseline.'
 b=pathlib.Path('/var/backups/jakawi.com')/(datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')+'-plr-admin-defaults')
 b.mkdir(mode=0o700)
 for name in ['compose.yaml','release.env','release-control.py']:shutil.copyfile(P/name,b/name)
 shutil.copyfile(IMPORTER,b/'import-plr.py')
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
def enrich():
 guard_source()
 # Existing STORE procedure: update only CLI source; no Nginx/PHP/bridge restart.
 shutil.copyfile(R/'docker/plr-catalog/import-plr.py',IMPORTER);os.chmod(IMPORTER,0o755)
 packages=sorted(pathlib.Path('/srv/sites/jakawi.store/incoming').glob('*.zip'))
 assert packages, 'No original packages for enrichment'
 output=run(['python3',str(IMPORTER),'--fill-missing',*[str(p) for p in packages]])
 (W/'enrichment.jsonl').write_bytes(output)
 # Repeating the same import must leave edited/default values intact.
 repeat=run(['python3',str(IMPORTER),*[str(p) for p in packages]])
 assert all(json.loads(l)['result']=='unchanged' for l in repeat.decode().splitlines())
 (W/'reimport.jsonl').write_bytes(repeat)
 print('Enrichment and idempotence PASS')
def verify():
 m=read('release-metadata.json');assert all(c['State']['Running'] and c['Config']['Labels']['org.opencontainers.image.revision']==m['revision'] for c in inspect())
 with urllib.request.urlopen('https://jakawi.com/up',timeout=20) as r:assert r.status==200
 assert read('protected-before.private.json')==snapshot(), 'Protected product/settings/rights or STORE runtime changed'
 assert read('validation.json')['manifest_sha256']==run(['docker','exec','jakawi-app-1','sha256sum','public/build/manifest.json']).decode().split()[0]
 assert read('live-admin.json')['result']=='PASS'
 save('LIVE.json',{'result':'PASS','revision':m['revision'],'rollback':BASE,'backup':m['backup'],'protected_product1_settings_rights_store':'UNCHANGED','pilates_draft_save':'PASS'})
 print('LIVE PASS')
def promote():
 guard_source();verify();git('push','origin','HEAD:refs/heads/main')
 assert git('ls-remote','origin','refs/heads/main').split()[0]==git('rev-parse','HEAD')
 git('update-ref','refs/heads/main',git('rev-parse','HEAD'))
 print('Main promotion PASS')
def rollback():
 b=pathlib.Path(read('release-metadata.json')['backup'])
 for f in ['compose.yaml','release.env']:install(b/f,P/f)
 shutil.copyfile(b/'import-plr.py',IMPORTER);os.chmod(IMPORTER,0o755)
 run(compose()+['up','-d','--no-deps','--no-build','--pull','never',*SERVICES])
 print('Rollback PASS; current metadata, prices, orders and rights retained; no DB restore')
if __name__=='__main__':
 W.mkdir(exist_ok=True)
 with (P/'.release-control.lock').open('a') as f:
  fcntl.flock(f,fcntl.LOCK_EX|fcntl.LOCK_NB)
  {'build':build,'prepare':prepare,'deploy':deploy,'enrich':enrich,'verify':verify,'promote':promote,'rollback':rollback}[sys.argv[1]]()
