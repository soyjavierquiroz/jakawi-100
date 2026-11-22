#!/usr/bin/env python3
"""Focused COM digital gifts release. LIVE issuance is always off; PRELAUNCH remains on."""
import datetime,fcntl,gzip,hashlib,json,os,pathlib,shutil,subprocess,sys,tarfile,time,urllib.request
os.umask(0o077)
P=pathlib.Path('/opt/sites/jakawi.com')
R=pathlib.Path(os.environ.get('JAKAWI_REPOSITORY','/srv/workspaces/jakawi/com/feat--digital-gifts-v1'))
W=pathlib.Path(os.environ.get('JAKAWI_RELEASE_EVIDENCE','/srv/workspaces/jakawi/com/digital-gifts-v1-evidence'))
BASE='d4f09361b5f2cd6bc0951f4db1d683260431a8ad'
TAG='digital-gifts-v1'
MIGRATION='2026_11_22_230000_create_digital_gifts'
SERVICES=['app','web','worker','scheduler']
NAMES=['jakawi-'+n+'-1' for n in SERVICES]
BOOT='require "vendor/autoload.php";$a=require "bootstrap/app.php";$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();'

def run(args,label,data=None):
 print(label,flush=True)
 p=subprocess.run(args,input=data,env={**os.environ, 'DOCKER_BUILDKIT':'0'},stdout=subprocess.PIPE,stderr=subprocess.PIPE)
 with (W/'operations.private.log').open('ab') as f:f.write((label+'\n').encode()+p.stdout+p.stderr)
 if p.returncode:raise RuntimeError(label+' failed; see operations.private.log')
 return p.stdout

def save(name,obj): (W/name).write_text(json.dumps(obj,indent=2))
def read(name): return json.loads((W/name).read_text())
def git(*args):return run(['git','-C',str(R),*args],'Git '+args[0]).decode().strip()
def remote(ref='refs/heads/main'):return git('ls-remote','origin',ref).split()[0]
def revision():return git('rev-parse','HEAD')
def inspect(names):return json.loads(run(['docker','inspect',*names],'Inspect runtime IDs'))
def compose(file=None):return ['docker','compose','--project-directory',str(P),'--env-file',str(P/'release.env'),'-f',str(file or P/'compose.yaml'),'--profile','production-jobs']
def sql(query):return run(['docker','exec','-i','jakawi-db-1','psql','-U','jakawi','-d','jakawi_final','-XAt','-v','ON_ERROR_STOP=1'],'Read focused database state',('BEGIN READ ONLY;\n'+query+'\nCOMMIT;').encode()).decode()
def php(code):return json.loads(run(['docker','exec','jakawi-app-1','php','-r',BOOT+code],'Verify PRELAUNCH and gifts disabled'))
def flags():
 return php('echo json_encode(["prelaunch"=>config("jakawi.prelaunch_mode"),"gifts"=>config("digital_gifts.enabled",false),"disk"=>config("filesystems.disks.digital-private.driver"),"sales"=>App\\Models\\AppSetting::where("key","gluteo_perfecto_sale_v1")->first()?->value["enabled"]??false]);')
def guarded():
 f=flags();assert f=={'prelaunch':True,'gifts':False,'disk':'s3','sales':False},f
 return f

def disk(minimum=3):
 free=shutil.disk_usage('/srv').free
 assert free>=minimum*1024**3, 'Insufficient disk margin: '+str(free)
 return free

def fingerprint():
 extra={'digital_products':['welcome_gift_enabled','promotional_gift_enabled','gift_pdf_sha256'], 'digital_access_grants':['gift_file_path','gift_file_sha256','gift_product_slug','gift_product_name']}
 statements=[]
 for table in ['memberships','digital_products','digital_orders','digital_payments','digital_access_grants','app_settings']:
  expr='to_jsonb(t)'+''.join(" - '"+x+"'" for x in extra.get(table,[]))
  statements.append("SELECT json_build_object('table','"+table+"','count',count(*),'digest',md5(coalesce(string_agg(h,'' ORDER BY h),''))) FROM (SELECT md5(("+expr+")::text) h FROM "+table+" t) s;")
 return [json.loads(x) for x in sql('\n'.join(statements)).splitlines() if x.startswith('{')]

def protected():
 names=run(['docker','ps','--format','{{.Names}}'],'Read other running service names').decode().splitlines()
 names=[n for n in names if n not in NAMES and not n.startswith('jakawi-test')]
 return {c['Name']:{'id':c['Id'],'image':c['Image'],'started':c['State']['StartedAt']} for c in inspect(names)}

def source_guard():
 assert not git('status','--porcelain')
 assert git('merge-base',BASE,revision())==BASE
 protected_paths=['app/app/Models/Membership.php','app/app/Models/User.php','app/app/Models/DigitalOrder.php','app/app/Models/DigitalPayment.php','app/app/Services/MembershipStatus.php','app/app/Services/MembershipBankCheckout.php','app/app/Services/MembershipPurchaseService.php','app/app/Services/DigitalCheckout.php','app/app/Services/DigitalProductQr.php','app/app/Services/DigitalCollection.php','app/app/Support/PrelaunchOperations.php','app/app/Http/Middleware/ProtectPrelaunchOperations.php','app/composer.lock','app/package-lock.json']
 assert not git('diff',BASE,revision(),'--',*protected_paths)
 assert git('diff','--name-only',BASE,revision(),'--','app/database/')=='app/database/migrations/'+MIGRATION+'.php'
 for path in git('diff','--name-only',BASE,revision()).splitlines():
  assert path.startswith(('app/','docker/new-server/digital-gifts-')),path
 assert remote()==BASE
 assert remote('refs/heads/feat/digital-gifts-v1')==revision()

def build():
 disk(5);source_guard()
 assert read('validation.json')['result']=='PASS' and read('validation.json')['revision']==revision()
 assert read('browser.json')['result']=='PASS'
 assert read('validation.json')['manifest_sha256']==hashlib.sha256((R/'app/public/build/manifest.json').read_bytes()).hexdigest()
 for target in ['app','web']:
  run(['docker','build','--network','none','--memory','768m','--memory-swap','768m','--cpu-period','100000','--cpu-quota','75000','--target',target,'--build-arg','SOURCE_REVISION='+revision(),'-f',str(R/'docker/new-server/digital-gifts-Dockerfile'),'-t','jakawi-'+target+':'+TAG,str(R)],'Build focused '+target+' image')
 disk()
 save('images.json',{target:inspect_image('jakawi-'+target+':'+TAG) for target in ['app','web']})

def inspect_image(name):
 v=json.loads(run(['docker','image','inspect',name],'Verify immutable image '+name))[0]
 assert v['Config']['Labels']['org.opencontainers.image.revision']==revision()
 return v['Id']

def prepare():
 disk(4);source_guard();guarded()
 validation=read('validation.json');assert validation['result']=='PASS' and validation['revision']==revision()
 assert read('browser.json')['result']=='PASS' and read('migration-qa.json')['result']=='PASS'
 state=inspect(NAMES);assert all(c['Config']['Labels']['org.opencontainers.image.revision']==BASE for c in state)
 save('protected-before.json',protected());save('domain-before.json',fingerprint());save('runtime-before.private.json',state)
 backup=pathlib.Path('/var/backups/jakawi.com')/(datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')+'-'+TAG)
 backup.mkdir(mode=0o700)
 for name in ['compose.yaml','release.env','release-control.py']:shutil.copyfile(P/name,backup/name)
 shutil.copyfile(W/'release-control.previous.py',backup/'release-control.previous.py')
 dump=run(['docker','exec','jakawi-db-1','pg_dump','-U','jakawi','-d','jakawi_final','--format=custom','--no-owner','--no-acl'],'Back up COM database')
 (backup/'database.dump').write_bytes(dump)
 run(['docker','exec','-i','jakawi-db-1','pg_restore','--list'],'Validate database backup readability',dump)
 with gzip.open(backup/'runtime-images.tar.gz','wb',compresslevel=3) as output:
  process=subprocess.Popen(['docker','image','save',state[0]['Image'],state[1]['Image']],stdout=subprocess.PIPE)
  shutil.copyfileobj(process.stdout,output);assert process.wait()==0
 with tarfile.open(backup/'runtime-images.tar.gz','r:gz') as a:assert 'manifest.json' in a.getnames()
 images=read('images.json');assert images=={t:inspect_image('jakawi-'+t+':'+TAG) for t in ['app','web']}
 candidate=json.loads((P/'compose.yaml').read_text())
 for service in SERVICES:
  candidate['services'][service]['image']=images['web' if service=='web' else 'app']
  if service!='web':
   assert candidate['services'][service]['environment']['PRELAUNCH_MODE']=='true'
   candidate['services'][service]['environment']['DIGITAL_GIFTS_ENABLED']='false'
 save('compose.candidate.private.json',candidate)
 run(compose(W/'compose.candidate.private.json')+['config','--quiet'],'Validate COM candidate configuration')
 env=(P/'release.env').read_text().replace(state[0]['Image'],images['app']).replace(state[1]['Image'],images['web'])
 (W/'release.env').write_text(env)
 save('release-metadata.json',{'revision':revision(),'base':BASE,'backup':str(backup),'images':images,'disk_available':disk()})
 rollback_plan()

def install(source,dest):
 temp=dest.with_name(dest.name+'.gifts-tmp');shutil.copyfile(source,temp);os.chmod(temp,0o600);os.replace(temp,dest)

def rollback_plan():
 m=read('release-metadata.json');backup=pathlib.Path(m['backup'])
 assert all((backup/x).is_file() for x in ['database.dump','runtime-images.tar.gz','compose.yaml','release.env'])
 original=json.loads((backup/'compose.yaml').read_text())
 for service in ['app','web']:run(['docker','image','inspect',original['services'][service]['image']],'Require retained rollback image '+service)
 run(compose(backup/'compose.yaml')+['config','--quiet'],'Validate rollback configuration')
 save('rollback-plan.json',{'result':'PASS','base':BASE,'backup':str(backup),'command':'/opt/sites/jakawi.com/release-control.py rollback','schema':'Keep additive gift schema; no rights are discarded. Never restore a database over new rows. Gift issuance stays off. QA verified down/up while empty and old runtime compatibility.','private_files':'MinIO unchanged; no file deletion or storage migration'})

def health():
 for path in ['/up','/explorar?type=digital','/productos-digitales/gluteo-perfecto','/l/gluteo-perfecto']:
  for attempt in range(15):
   try:
    with urllib.request.urlopen('https://jakawi.com'+path,timeout=10) as r:assert r.status==200
    break
   except Exception:
    if attempt==14:raise
    time.sleep(1)

def deploy():
 m=read('release-metadata.json');disk();source_guard();guarded()
 assert not (W/'deploy-result.json').exists()
 assert read('validation.json')['result']=='PASS' and read('validation.json')['revision']==m['revision']==revision()
 assert read('browser.json')['result']=='PASS' and read('rollback-plan.json')['result']=='PASS'
 assert fingerprint()==read('domain-before.json') and protected()==read('protected-before.json')
 assert [c['Id'] for c in inspect(NAMES)]==[c['Id'] for c in read('runtime-before.private.json')]
 run(compose(W/'compose.candidate.private.json')+['run','--rm','--no-deps','--user','82:82','app','php','artisan','migrate','--force','--path=database/migrations/'+MIGRATION+'.php'],'Apply only reviewed additive gift migration')
 assert fingerprint()==read('domain-before.json')
 install(W/'compose.candidate.private.json',P/'compose.yaml');install(W/'release.env',P/'release.env')
 run(compose()+['up','-d','--no-deps','--no-build','--pull','never',*SERVICES],'Deploy COM services only')
 save('deploy-result.json',{'revision':m['revision'],'at':datetime.datetime.now(datetime.timezone.utc).isoformat()})
 verify()

def verify():
 m=read('release-metadata.json');state=inspect(NAMES)
 assert all(c['Config']['Labels']['org.opencontainers.image.revision']==m['revision'] and c['State']['Running'] for c in state)
 assert protected()==read('protected-before.json');guarded();health()
 assert fingerprint()==read('domain-before.json')
 assert sql("SELECT count(*) FROM digital_gifts; SELECT count(*) FROM migrations WHERE migration='"+MIGRATION+"';").splitlines()==['BEGIN','0','1','COMMIT']
 changed=git('diff','--name-only',BASE,m['revision']).splitlines()
 checks={p.removeprefix('app/'):hashlib.sha256((R/p).read_bytes()).hexdigest() for p in changed if p.startswith('app/') and p != 'app/.env.example' and not p.startswith('app/tests/')}
 code=BOOT+'$expected=json_decode(stream_get_contents(STDIN),true);foreach($expected as $p=>$h){if(!is_file($p)||!hash_equals($h,hash_file("sha256",$p)))throw new Exception("Runtime source mismatch: ".$p);}echo "PASS";'
 run(['docker','exec','-i','jakawi-app-1','php','-r',code],'Verify changed LIVE sources match reviewed commit',json.dumps(checks).encode())
 save('LIVE.json',{'result':'PASS','revision':m['revision'],'rollback':BASE,'backup':m['backup'],'prelaunch':True,'gifts_enabled':False,'no_live_rights_issued':True,'protected_services_unchanged':True,'disk_available':disk()})

def promote():
 m=read('release-metadata.json');assert read('LIVE.json')['result']=='PASS' and read('LIVE.json')['revision']==m['revision'];guarded()
 assert remote()==BASE;git('merge-base','--is-ancestor',BASE,m['revision'])
 git('push','origin',m['revision']+':refs/heads/main')
 assert remote()==m['revision'];save('promotion.json',{'result':'PASS','main':m['revision'],'after_live_pass':True})

def rollback():
 m=read('release-metadata.json');backup=pathlib.Path(m['backup']);disk();rollback_plan()
 assert protected()==read('protected-before.json')
 install(backup/'compose.yaml',P/'compose.yaml');install(backup/'release.env',P/'release.env')
 run(compose()+['up','-d','--no-deps','--no-build','--pull','never',*SERVICES],'Restore retained COM runtime only')
 health();guarded();assert protected()==read('protected-before.json')
 save('rollback-result.json',{'result':'PASS','revision':BASE,'schema':'additive schema retained; gifts off'})

if __name__=='__main__':
 W.mkdir(exist_ok=True)
 with (P/'.release-control.lock').open('a') as lock:
  fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
  {'build':build,'prepare':prepare,'deploy':deploy,'verify':verify,'rollback-plan':rollback_plan,'promote':promote,'rollback':rollback}[sys.argv[1]]()
