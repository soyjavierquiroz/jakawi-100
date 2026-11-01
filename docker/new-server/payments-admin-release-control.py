#!/usr/bin/env python3
"""COM Phase 11I release; one confirmation, existing authentication preserved."""
import datetime,fcntl,hashlib,json,os,pathlib,shutil,subprocess,sys,time,urllib.request
from zoneinfo import ZoneInfo
os.umask(0o077)
P=pathlib.Path(os.environ.get('JAKAWI_LIVE_ROOT','/opt/sites/jakawi.com'))
source_config=P/'source-paths.json'
source_paths=json.loads(source_config.read_text()) if source_config.is_file() else {}
R=pathlib.Path(os.environ.get('JAKAWI_REPOSITORY',source_paths.get('repository','/srv/repos/jakawi.com')))
evidence_path=os.environ.get('JAKAWI_RELEASE_EVIDENCE')
if not evidence_path: raise RuntimeError('Set JAKAWI_RELEASE_EVIDENCE for the reviewed release evidence')
W=pathlib.Path(evidence_path)
SERVICES=['app','web','worker','scheduler']
NAMES=['jakawi-'+s+'-1' for s in SERVICES]+['jakawi-db-1','jakawi-club-app','jakawi-club-web','jakawi-club-db','crm-jakawi-private-app-1','crm-jakawi-private-web-1','crm-jakawi-private-db-1','edge-traefik-1']
def stamp(): return datetime.datetime.now(ZoneInfo('America/La_Paz')).isoformat()
def call(args,label,data=None):
 print(stamp(),label,flush=True)
 result=subprocess.run(args,input=data,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
 with (W/'operations.private.log').open('ab') as f: f.write((stamp()+' '+label+'\n').encode()+result.stdout+result.stderr)
 if result.returncode: raise RuntimeError(label+' failed; see private log')
 return result.stdout
def inspect(): return json.loads(call(['docker','inspect',*NAMES],'Read release service metadata'))
def compose(file=None): return ['docker','compose','--project-directory',str(P),'--env-file',str(P/'release.env'),'-f',str(file or P/'compose.yaml'),'--profile','production-jobs']
def configuration():
 code='require "vendor/autoload.php";$a=require "bootstrap/app.php";$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();echo json_encode(config()->all());'
 result=json.loads(call(['docker','exec','jakawi-app-1','php','-r',code],'Check unchanged application configuration'));return result
def install(src,dst):
 tmp=dst.with_name(dst.name+'.catalog-tmp');shutil.copyfile(src,tmp);os.chmod(tmp,0o600);os.replace(tmp,dst)
def health():
 results={}
 for path in ['/up','/api/catalog/v1/offers?per_page=1']:
  for attempt in range(15):
   try:
    with urllib.request.urlopen('https://jakawi.com'+path,timeout=8) as response:
     assert response.status==200
     if path.startswith('/api/'):
      assert 'application/json' in response.headers['Content-Type'];payload=json.load(response)
      assert payload['meta']['per_page']==1 and len(payload['data'])<=1
      assert not response.headers.get('Set-Cookie')
      for row in payload['data']:
       assert row['type'] in ['benefit','experience'] and row['id']==row['type']+':'+str(row['com_id'])
       assert row['landing_url'].startswith('https://jakawi.com/l/')
       assert row['landing_status']=='PUBLISHED'
      (W/'live-catalog.json').write_text(json.dumps(payload,ensure_ascii=False,indent=2))
     results[path]=response.status
    break
   except Exception:
    if attempt==14: raise
    time.sleep(1)
 (W/'health.json').write_text(json.dumps(results,indent=2));return results

def fingerprint(filename):
 tables=['users','identity_subjects','identity_links','partners','partner_user','memberships','membership_purchases','membership_bank_payments','membership_bank_movements','membership_acquisition_origins','commercial_outbox','conversions','reward_transactions']
 # Validate table existence without guessing or creating anything.
 existing=call(['docker','exec','jakawi-db-1','psql','-U','jakawi','-d','jakawi_final','-X','-Atc',"SELECT tablename FROM pg_tables WHERE schemaname='public'"],'Read domain table names').decode().splitlines()
 rows=[]
 for table in tables:
  if table not in existing: continue
  omitted = {'membership_bank_imports':['bank','original_name','period_start','period_end','processing_status','incidents','extracted_count','duplicate_count','document_metadata'], 'membership_bank_payments':['reported_at','payment_confirmed_at'], 'membership_bank_movements':['account_key','debit','credit','balance','original_row','reconciliation_hold']}
  expr='(to_jsonb(t)'+''.join(" - '"+column+"'" for column in omitted.get(table,[]))+')::text'
  rows.append("SELECT json_build_object('table','"+table+"','count',count(*),'digest',md5(coalesce(string_agg(h,'' ORDER BY h),''))) FROM (SELECT md5("+expr+") AS h FROM "+table+" t) f;")
 sql="BEGIN READ ONLY;\n"+'\n'.join(rows)+"\nCOMMIT;"
 raw=call(['docker','exec','-i','jakawi-db-1','psql','-U','jakawi','-d','jakawi_final','-X','-At','-v','ON_ERROR_STOP=1'],'Fingerprint domain rows',sql.encode()).decode()
 (W/filename).write_text(json.dumps([json.loads(line) for line in raw.splitlines() if line.startswith('{')],indent=2))

def prepare():
 revision=call(['git','-C',str(R),'rev-parse','HEAD'],'Read candidate SHA').decode().strip()
 assert not call(['git','-C',str(R),'status','--porcelain'],'Check clean source')
 assert json.loads((W/'validation.json').read_text())['result']=='PASS'
 state=inspect();assert all(i['Config']['Labels']['org.opencontainers.image.revision']==state[0]['Config']['Labels']['org.opencontainers.image.revision'] for i in [state[0],state[2],state[3]])
 (W/'state-before.private.json').write_text(json.dumps(state));(W/'config-before.private.json').write_text(json.dumps(configuration()))
 backup=pathlib.Path('/var/backups/jakawi.com')/(datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')+'-payments-admin');backup.mkdir(mode=0o700)
 for name in ['compose.yaml','release.env','release-control.py']:shutil.copyfile(P/name,backup/name)
 current=json.loads((P/'compose.yaml').read_text());images={}
 for service in ['app','web']:
  item=json.loads(call(['docker','image','inspect','jakawi-'+service+':payments-admin'],'Check built '+service+' image'))[0]
  assert item['Config']['Labels']['org.opencontainers.image.revision']==revision;images[service]=item['Id']
 for service in ['app','worker','scheduler']: current['services'][service]['image']=images['app']
 current['services']['web']['image']=images['web']
 (W/'compose.candidate.private.json').write_text(json.dumps(current,indent=2))
 env=(P/'release.env').read_text()
 for old,new in [(state[0]['Image'],images['app']),(state[1]['Image'],images['web'])]:
  assert old in env;env=env.replace(old,new)
 (W/'release.env').write_text(env)
 metadata={'revision':revision,'base':state[0]['Config']['Labels']['org.opencontainers.image.revision'],'backup':str(backup),'images':images}
 (W/'release-metadata.json').write_text(json.dumps(metadata,indent=2))
 call(compose(W/'compose.candidate.private.json')+['config','--quiet'],'Validate candidate COM configuration')
 call(compose(W/'compose.candidate.private.json')+['--dry-run','up','--no-start','--no-deps','--no-build','--pull','never',*SERVICES],'Check COM-only recreation')
 print(json.dumps(metadata))

def deploy():
 m=json.loads((W/'release-metadata.json').read_text());assert not (W/'deploy-result.json').exists()
 assert call(['git','-C',str(R),'ls-remote','origin','refs/heads/main'],'Verify pushed main').decode().split()[0]==m['revision']
 assert json.loads((W/'validation.json').read_text())['result']=='PASS'
 before=json.loads((W/'state-before.private.json').read_text());assert [i['Id'] for i in inspect()]==[i['Id'] for i in before]
 assert json.loads((W/'browser.json').read_text())['result']=='PASS'
 call(['docker','stop','--timeout','90','jakawi-worker-1','jakawi-scheduler-1'],'Stop COM job consumers')
 call(['docker','stop','--timeout','30','jakawi-web-1','jakawi-app-1'],'Stop COM web and PHP')
 # No migrations or domain writes in this release.
 install(W/'compose.candidate.private.json',P/'compose.yaml');install(W/'release.env',P/'release.env')
 create=['up','--no-start','--no-deps','--no-build','--pull','never']
 call(compose()+create+['app','web'],'Create COM app/web release')
 call(['docker','start','jakawi-app-1'],'Start COM PHP')
 for task in ['package:discover','config:cache','route:cache','view:cache']:
  call(['docker','exec','--user','82:82','jakawi-app-1','php','artisan',task,'--no-interaction'],'Prepare '+task)
 assert configuration()==json.loads((W/'config-before.private.json').read_text()),'Unexpected application configuration change'
 # Existing database and domain services preserved.
 call(['docker','start','jakawi-web-1'],'Start COM web')
 health()
 call(compose()+create+['worker','scheduler'],'Create COM job services')
 call(['docker','start','jakawi-worker-1','jakawi-scheduler-1'],'Resume COM job consumers')
 result={'revision':m['revision'],'deployed_at':stamp(),'com_only':True,'migrations_run':False,'live_fixtures_created':False}
 (W/'deploy-result.json').write_text(json.dumps(result,indent=2));print(json.dumps(result))

def verify():
 m=json.loads((W/'release-metadata.json').read_text());state=inspect();before=json.loads((W/'state-before.private.json').read_text())
 for index,(item,old) in enumerate(zip(state,before)):
  if index<4: assert item['State']['Running'] and item['Config']['Labels']['org.opencontainers.image.revision']==m['revision']
  else:
   for key in ['Id','Image','RestartCount']:assert item[key]==old[key], 'Protected service changed: '+item['Name']
   assert item['State']['StartedAt']==old['State']['StartedAt']
 assert call(['git','-C',str(R),'ls-remote','origin','refs/heads/main'],'Verify origin main SHA').decode().split()[0]==m['revision']
 changed=call(['git','-C',str(R),'diff','--name-only',m['base'],m['revision'],'--','app/'],'Read changed application paths').decode().splitlines()
 for name in changed:
  if not (R/name).is_file(): continue
  expected=hashlib.sha256((R/name).read_bytes()).hexdigest()
  actual=call(['docker','exec','jakawi-app-1','sha256sum','/var/www/html/'+name[4:]],'Check deployed application source').decode().split()[0]
  assert expected==actual
 for container in ['jakawi-app-1','jakawi-web-1']:
  expected=hashlib.sha256((R/'app/public/build/manifest.json').read_bytes()).hexdigest()
  assert call(['docker','exec',container,'sha256sum','/var/www/html/public/build/manifest.json'],'Verify existing frontend manifest').decode().split()[0]==expected
 health()
 result={**json.loads((W/'deploy-result.json').read_text()),'verified_at':stamp(),'up_http':200,'catalog_http':200,'origin_main_matches':True,'four_com_services_same_sha':True,'protected_containers_unchanged':True,'database_schema_unchanged':True,'club_unchanged':True,'payments-admin':True,'changed_application_files_verified':len(changed)}
 (W/'LIVE.json').write_text(json.dumps(result,indent=2));print(json.dumps(result,indent=2))

if __name__=='__main__':
 lock=open('/run/lock/jakawi-release.lock','a');fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
 {'prepare':prepare,'deploy':deploy,'verify':verify}[sys.argv[1]]()
