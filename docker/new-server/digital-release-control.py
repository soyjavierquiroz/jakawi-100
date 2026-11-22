#!/usr/bin/env python3
"""COM digital MVP release: two additive migrations, one internal Partner, PRELAUNCH on."""
import datetime,fcntl,hashlib,json,os,pathlib,shutil,subprocess,sys,time,urllib.request
from zoneinfo import ZoneInfo
os.umask(0o077)
P=pathlib.Path(os.environ.get('JAKAWI_LIVE_ROOT','/opt/sites/jakawi.com'))
source_config=P/'source-paths.json'
source_paths=json.loads(source_config.read_text()) if source_config.is_file() else {}
R=pathlib.Path(os.environ.get('JAKAWI_REPOSITORY',source_paths.get('repository','/srv/repos/jakawi.com')))
evidence_path=os.environ.get('JAKAWI_RELEASE_EVIDENCE',source_paths.get('release_evidence'))
if not evidence_path: raise RuntimeError('Set JAKAWI_RELEASE_EVIDENCE for the reviewed release evidence')
W=pathlib.Path(evidence_path)
TAG=os.environ.get('JAKAWI_RELEASE_TAG','digital-mvp-v1')
EXPECTED_MAIN=os.environ.get('JAKAWI_EXPECTED_MAIN')
CANDIDATE_REF=os.environ.get('JAKAWI_CANDIDATE_REF','refs/heads/main')
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
 tables=['locations','benefits','benefit_location','experiences','experience_partner','experience_sessions','experience_reservations','redemptions','unlocks','unlock_participations','challenges','challenge_participations','challenge_social_entries','challenge_reward_grants','jp_holds','reward_payouts','membership_bank_imports','membership_bank_movements','jakawi_bank_reconciliations','app_settings','users','identity_subjects','identity_links','partners','partner_user','memberships','membership_purchases','membership_bank_payments','membership_bank_movements','membership_acquisition_origins','commercial_outbox','conversions','reward_transactions']
 # Validate table existence without guessing or creating anything.
 existing=call(['docker','exec','jakawi-db-1','psql','-U','jakawi','-d','jakawi_final','-X','-Atc',"SELECT tablename FROM pg_tables WHERE schemaname='public'"],'Read domain table names').decode().splitlines()
 rows=[]
 for table in dict.fromkeys(tables):
  if table not in existing: continue
  expr='to_jsonb(t)::text'
  rows.append("SELECT json_build_object('table','"+table+"','count',count(*),'digest',md5(coalesce(string_agg(h,'' ORDER BY h),''))) FROM (SELECT md5("+expr+") AS h FROM "+table+" t"+(" WHERE slug <> 'jakawi-digital'" if table=='partners' else '')+") f;")
 sql="BEGIN READ ONLY;\n"+'\n'.join(rows)+"\nCOMMIT;"
 raw=call(['docker','exec','-i','jakawi-db-1','psql','-U','jakawi','-d','jakawi_final','-X','-At','-v','ON_ERROR_STOP=1'],'Fingerprint domain rows',sql.encode()).decode()
 (W/filename).write_text(json.dumps([json.loads(line) for line in raw.splitlines() if line.startswith('{')],indent=2))

def remote_ref(ref):
 return call(['git','-C',str(R),'ls-remote','origin',ref],'Verify remote '+ref).decode().split()[0]

def prepare():
 revision=call(['git','-C',str(R),'rev-parse','HEAD'],'Read candidate SHA').decode().strip()
 assert not call(['git','-C',str(R),'status','--porcelain'],'Check clean source')
 assert remote_ref('refs/heads/main')==(EXPECTED_MAIN or revision)
 call(['git','-C',str(R),'merge-base','--is-ancestor',EXPECTED_MAIN or revision,revision],'Require fast-forward main ancestry')
 assert remote_ref(CANDIDATE_REF)==revision
 assert json.loads((W/'validation.json').read_text())['result']=='PASS'
 state=inspect();assert state[0]['Config']['Labels']['org.opencontainers.image.revision']==os.environ['JAKAWI_BASE_REVISION']
 assert all(i['Config']['Labels']['org.opencontainers.image.revision']==state[0]['Config']['Labels']['org.opencontainers.image.revision'] for i in [state[0],state[2],state[3]])
 assert configuration()['jakawi']['prelaunch_mode'] is True
 assert call(['docker','exec','jakawi-db-1','psql','-U','jakawi','-d','jakawi_final','-XAtc',"SELECT value->>'enabled' FROM app_settings WHERE key='show_demo_banner'"],'Require DEMO OFF').decode().strip()=='false'
 fingerprint('domain-before.json')
 (W/'state-before.private.json').write_text(json.dumps(state));(W/'config-before.private.json').write_text(json.dumps(configuration()))
 schema=call(['docker','exec','jakawi-db-1','pg_dump','-U','jakawi','-d','jakawi_final','--schema-only','--no-owner','--no-acl','--exclude-table=digital_*'],'Fingerprint existing schema')
 (W/'schema-before.private.sql').write_bytes(schema)
 backup=pathlib.Path('/var/backups/jakawi.com')/(datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')+'-'+TAG);backup.mkdir(mode=0o700)
 for name in ['compose.yaml','release.env','release-control.py']:shutil.copyfile(P/name,backup/name)
 if (W/'release-control.previous.py').is_file():shutil.copyfile(W/'release-control.previous.py',backup/'release-control.previous.py')
 call(['docker','image','save','-o',str(backup/'runtime-images.tar'),state[0]['Image'],state[1]['Image']],'Back up previous immutable images')
 dump=call(['docker','exec','jakawi-db-1','pg_dump','-U','jakawi','-d','jakawi_final','--format=custom','--no-owner','--no-acl'],'Read-only database backup')
 (backup/'database.dump').write_bytes(dump)
 call(['docker','exec','-i','jakawi-db-1','pg_restore','--list'],'Validate database backup',dump)
 current=json.loads((P/'compose.yaml').read_text());images={}
 for service in ['app','web']:
  item=json.loads(call(['docker','image','inspect','jakawi-'+service+':'+TAG],'Check built '+service+' image'))[0]
  assert item['Config']['Labels']['org.opencontainers.image.revision']==revision;images[service]=item['Id']
 for service in ['app','worker','scheduler']: current['services'][service]['image']=images['app']
 current['services']['web']['image']=images['web']
 (W/'compose.candidate.private.json').write_text(json.dumps(current,indent=2))
 env=(P/'release.env').read_text()
 for old,new in [(state[0]['Image'],images['app']),(state[1]['Image'],images['web'])]:
  assert old in env;env=env.replace(old,new)
 (W/'release.env').write_text(env)
 metadata={'revision':revision,'base':state[0]['Config']['Labels']['org.opencontainers.image.revision'],'backup':str(backup),'images':images,'expected_main':EXPECTED_MAIN or revision,'candidate_ref':CANDIDATE_REF}
 (W/'release-metadata.json').write_text(json.dumps(metadata,indent=2))
 (W/'rollback-plan.json').write_text(json.dumps({'base':metadata['base'],'backup':str(backup),'schema':'retain additive digital tables; never restore over later writes','services':SERVICES,'prelaunch_required':True},indent=2))
 call(compose(W/'compose.candidate.private.json')+['config','--quiet'],'Validate candidate COM configuration')
 call(compose(W/'compose.candidate.private.json')+['--dry-run','up','--no-start','--no-deps','--no-build','--pull','never',*SERVICES],'Check COM-only recreation')
 print(json.dumps(metadata))

def deploy():
 m=json.loads((W/'release-metadata.json').read_text());assert not (W/'deploy-result.json').exists()
 assert remote_ref(m['candidate_ref'])==m['revision']
 assert remote_ref('refs/heads/main')==m['expected_main']
 assert json.loads((W/'validation.json').read_text())['result']=='PASS'
 before=json.loads((W/'state-before.private.json').read_text());assert [i['Id'] for i in inspect()]==[i['Id'] for i in before]
 assert json.loads((W/'browser.json').read_text())['result']=='PASS'
 fingerprint('domain-predeploy.json')
 assert json.loads((W/'domain-before.json').read_text())==json.loads((W/'domain-predeploy.json').read_text()), 'Domain changed since preparation; do not deploy'
 call(['docker','stop','--timeout','90','jakawi-worker-1','jakawi-scheduler-1'],'Stop COM job consumers')
 call(['docker','stop','--timeout','30','jakawi-web-1','jakawi-app-1'],'Stop COM web and PHP')
 # Only the two reviewed additive digital migrations are authorized.
 install(W/'compose.candidate.private.json',P/'compose.yaml');install(W/'release.env',P/'release.env')
 create=['up','--no-start','--no-deps','--no-build','--pull','never']
 call(compose()+create+['app','web'],'Create COM app/web release')
 call(['docker','start','jakawi-app-1'],'Start COM PHP')
 for task in ['package:discover','config:cache','route:cache','view:cache']:
  call(['docker','exec','--user','82:82','jakawi-app-1','php','artisan',task,'--no-interaction'],'Prepare '+task)
 current_config=configuration();old_config=json.loads((W/'config-before.private.json').read_text())
 digital_disk=current_config['filesystems']['disks'].pop('digital-private')
 assert digital_disk['visibility']=='private' and digital_disk['serve'] is False
 old_digital_disk=old_config['filesystems']['disks'].pop('digital-private',None)
 assert old_digital_disk is None or old_digital_disk==digital_disk
 assert current_config==old_config,'Unexpected application configuration change'
 for migration in ['2026_11_22_000001_create_digital_products.php','2026_11_22_000002_create_digital_sales.php']:
  call(['docker','exec','--user','82:82','jakawi-app-1','php','artisan','migrate','--force','--no-interaction','--path=database/migrations/'+migration],'Apply reviewed additive digital migration '+migration)
 # Existing database and domain services preserved.
 call(['docker','start','jakawi-web-1'],'Start COM web')
 health()
 call(compose()+create+['worker','scheduler'],'Create COM job services')
 call(['docker','start','jakawi-worker-1','jakawi-scheduler-1'],'Resume COM job consumers')
 result={'revision':m['revision'],'deployed_at':stamp(),'com_only':True,'migrations_run':True,'live_fixtures_created':False,'commercial_sales_enabled':False}
 (W/'deploy-result.json').write_text(json.dumps(result,indent=2));print(json.dumps(result))

def verify():
 m=json.loads((W/'release-metadata.json').read_text());state=inspect();before=json.loads((W/'state-before.private.json').read_text())
 for index,(item,old) in enumerate(zip(state,before)):
  if index<4: assert item['State']['Running'] and item['Config']['Labels']['org.opencontainers.image.revision']==m['revision']
  else:
   for key in ['Id','Image','RestartCount']:assert item[key]==old[key], 'Protected service changed: '+item['Name']
   assert item['State']['StartedAt']==old['State']['StartedAt']
 assert remote_ref(m['candidate_ref'])==m['revision']
 assert remote_ref('refs/heads/main') in [m['expected_main'],m['revision']]
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
 schema=call(['docker','exec','jakawi-db-1','pg_dump','-U','jakawi','-d','jakawi_final','--schema-only','--no-owner','--no-acl','--exclude-table=digital_*'],'Verify unchanged pre-existing schema')
 stable=lambda b:b'\n'.join(x for x in b.splitlines() if not x.startswith((b'\\restrict ',b'\\unrestrict ')))
 assert stable(schema)==stable((W/'schema-before.private.sql').read_bytes()), 'Schema changed'
 fingerprint('domain-after.json')
 assert json.loads((W/'domain-before.json').read_text())==json.loads((W/'domain-after.json').read_text()), 'Domain rows changed during release'
 assert configuration()['jakawi']['prelaunch_mode'] is True
 transport=json.loads(call(['docker','exec','jakawi-app-1','php','-r','echo json_encode([ini_get("upload_max_filesize"),ini_get("post_max_size")]);'],'Verify PDF transport limits'))
 assert transport==['32M','40M']
 for container,source,target in [('jakawi-app-1','docker/new-server/php.ini','/usr/local/etc/php/conf.d/zz-jakawi.ini'),('jakawi-web-1','docker/new-server/nginx.conf','/etc/nginx/conf.d/default.conf')]:
  actual=call(['docker','exec',container,'sha256sum',target],'Verify COM transport configuration').decode().split()[0]
  assert actual==hashlib.sha256((R/source).read_bytes()).hexdigest()
 digital=call(['docker','exec','jakawi-db-1','psql','-U','jakawi','-d','jakawi_final','-XAtc',"SELECT json_build_object('products',(SELECT count(*) FROM digital_products),'orders',(SELECT count(*) FROM digital_orders),'payments',(SELECT count(*) FROM digital_payments),'grants',(SELECT count(*) FROM digital_access_grants),'partners',(SELECT count(*) FROM partners WHERE slug='jakawi-digital' AND name='JAKAWI Digital'),'locations',(SELECT count(*) FROM locations l JOIN partners p ON p.id=l.partner_id WHERE p.slug='jakawi-digital'))"],'Verify empty commercial digital state and internal Partner')
 counts=json.loads(digital);assert counts=={'products':0,'orders':0,'payments':0,'grants':0,'partners':1,'locations':0}
 (W/'digital-live-state.json').write_text(json.dumps(counts,indent=2))
 for path in ['/productos-digitales','/explorar','/login']:
  with urllib.request.urlopen('https://jakawi.com'+path,timeout=10) as response:assert response.status==200
 result={**json.loads((W/'deploy-result.json').read_text()),'verified_at':stamp(),'up_http':200,'catalog_http':200,'main_update_pending':remote_ref('refs/heads/main')!=m['revision'],'four_com_services_same_sha':True,'protected_containers_unchanged':True,'preexisting_schema_unchanged':True,'additive_digital_tables':4,'digital_sales_blocked':True,'club_unchanged':True,'release_tag':TAG,'changed_application_files_verified':len(changed)}
 (W/'LIVE.json').write_text(json.dumps(result,indent=2));print(json.dumps(result,indent=2))

def promote():
 m=json.loads((W/'release-metadata.json').read_text())
 live=json.loads((W/'LIVE.json').read_text());assert live['revision']==m['revision'] and live['four_com_services_same_sha']
 assert remote_ref('refs/heads/main')==m['expected_main']
 state=inspect();assert all(i['State']['Running'] and i['Config']['Labels']['org.opencontainers.image.revision']==m['revision'] for i in state[:4])
 call(['git','-C',str(R),'merge-base','--is-ancestor',m['expected_main'],m['revision']],'Require fast-forward promotion')
 call(['git','-C',str(R),'push','origin',m['revision']+':refs/heads/main'],'Fast-forward main after LIVE PASS')
 assert remote_ref('refs/heads/main')==m['revision']
 # Canonical main can be updated separately without disturbing another checkout.
 call(['git','-C',str(R),'update-ref','refs/remotes/origin/main',m['revision']],'Record origin main')
 (W/'promotion.json').write_text(json.dumps({'revision':m['revision'],'main':m['revision'],'runtime':m['revision'],'fast_forward':True,'after_live_pass':True},indent=2))

def rollback_plan():
 m=json.loads((W/'release-metadata.json').read_text());backup=pathlib.Path(m['backup'])
 assert (backup/'compose.yaml').is_file() and (backup/'release.env').is_file()
 call(compose(backup/'compose.yaml')+['config','--quiet'],'Validate previous COM configuration')
 call(compose(backup/'compose.yaml')+['--dry-run','up','--no-start','--no-deps','--no-build','--pull','never',*SERVICES],'Dry-run previous COM images; no mutation')

def rollback():
 rollback_plan()
 m=json.loads((W/'release-metadata.json').read_text());backup=pathlib.Path(m['backup'])
 call(['docker','stop','--timeout','90','jakawi-worker-1','jakawi-scheduler-1'],'Stop COM candidate consumers')
 call(['docker','stop','--timeout','30','jakawi-web-1','jakawi-app-1'],'Stop COM candidate web')
 install(backup/'compose.yaml',P/'compose.yaml');install(backup/'release.env',P/'release.env')
 call(compose()+['up','--no-start','--no-deps','--no-build','--pull','never',*SERVICES],'Restore previous COM images')
 call(['docker','start','jakawi-app-1','jakawi-web-1'],'Start previous COM web')
 health()
 call(['docker','start','jakawi-worker-1','jakawi-scheduler-1'],'Resume previous COM consumers')
 assert all(i['Config']['Labels']['org.opencontainers.image.revision']==m['base'] for i in inspect()[:4])
 (W/'rollback.json').write_text(json.dumps({'revision':m['base'],'database_restored':False,'additive_schema_retained':True,'migrations_run':False},indent=2))

if __name__=='__main__':
 lock=open('/run/lock/jakawi-release.lock','a');fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
 {'prepare':prepare,'deploy':deploy,'verify':verify,'promote':promote,'rollback':rollback,'rollback-plan':rollback_plan}[sys.argv[1]]()
