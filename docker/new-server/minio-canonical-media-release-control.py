#!/usr/bin/env python3
"""Canonical private MinIO media; verified copies, unchanged domain references and reversible disk switch."""
import datetime,fcntl,gzip,hashlib,json,os,pathlib,shutil,subprocess,sys,tarfile,time,urllib.request
from zoneinfo import ZoneInfo
os.umask(0o077)
P=pathlib.Path(os.environ.get('JAKAWI_LIVE_ROOT','/opt/sites/jakawi.com'))
source_config=P/'source-paths.json'
source_paths=json.loads(source_config.read_text()) if source_config.is_file() else {}
R=pathlib.Path(os.environ.get('JAKAWI_REPOSITORY',source_paths.get('repository','/srv/repos/jakawi.com')))
evidence_path=os.environ.get('JAKAWI_RELEASE_EVIDENCE',source_paths.get('release_evidence'))
if not evidence_path: raise RuntimeError('Set JAKAWI_RELEASE_EVIDENCE for the reviewed release evidence')
W=pathlib.Path(evidence_path)
TAG=os.environ.get('JAKAWI_RELEASE_TAG','minio-canonical-media')
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
 tables=['locations','benefits','benefit_location','experiences','experience_partner','experience_sessions','experience_reservations','redemptions','unlocks','unlock_participations','challenges','challenge_participations','challenge_social_entries','challenge_reward_grants','jp_holds','reward_payouts','membership_bank_imports','membership_bank_movements','jakawi_bank_reconciliations','app_settings','users','identity_subjects','identity_links','partners','partner_user','memberships','membership_purchases','membership_bank_payments','membership_bank_movements','membership_acquisition_origins','commercial_outbox','conversions','reward_transactions','digital_products','digital_orders','digital_payments','digital_access_grants','landing_presentations']
 # Validate table existence without guessing or creating anything.
 existing=call(['docker','exec','jakawi-db-1','psql','-U','jakawi','-d','jakawi_final','-X','-Atc',"SELECT tablename FROM pg_tables WHERE schemaname='public'"],'Read domain table names').decode().splitlines()
 rows=[]
 for table in dict.fromkeys(tables):
  if table not in existing: continue
  expr='to_jsonb(t)::text'
  where = ''
  rows.append("SELECT json_build_object('table','"+table+"','count',count(*),'digest',md5(coalesce(string_agg(h,'' ORDER BY h),''))) FROM (SELECT md5("+expr+") AS h FROM "+table+" t"+where+") f;")
 sql="BEGIN READ ONLY;\n"+'\n'.join(rows)+"\nCOMMIT;"
 raw=call(['docker','exec','-i','jakawi-db-1','psql','-U','jakawi','-d','jakawi_final','-X','-At','-v','ON_ERROR_STOP=1'],'Fingerprint domain rows',sql.encode()).decode()
 (W/filename).write_text(json.dumps([json.loads(line) for line in raw.splitlines() if line.startswith('{')],indent=2))

def remote_ref(ref):
 return call(['git','-C',str(R),'ls-remote','origin',ref],'Verify remote '+ref).decode().split()[0]


BOOT='require "vendor/autoload.php";$a=require "bootstrap/app.php";$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();'

def digital_invariants(required):
 sql="""BEGIN READ ONLY;
 SELECT json_build_object(
 'partners',(SELECT count(*) FROM partners WHERE id=29 AND slug='jakawi-digital'),
 'products',(SELECT count(*) FROM digital_products WHERE id=1 AND slug='gluteo-perfecto' AND partner_id=29 AND status='published' AND delivery_type='pdf'),
 'landings',(SELECT count(*) FROM landing_presentations WHERE id=24 AND slug='gluteo-perfecto' AND status='PUBLISHED'),
 'orders',(SELECT count(*) FROM digital_orders),'payments',(SELECT count(*) FROM digital_payments),'grants',(SELECT count(*) FROM digital_access_grants),
 'sales_enabled',(SELECT count(*) FROM app_settings WHERE key='gluteo_perfecto_sale_v1' AND value->>'enabled'='true'));
 COMMIT;"""
 raw=call(['docker','exec','-i','jakawi-db-1','psql','-U','jakawi','-d','jakawi_final','-XAt','-v','ON_ERROR_STOP=1'],'Verify existing product, landing and LIVE sales disabled',sql.encode()).decode()
 counts=json.loads(next(line for line in raw.splitlines() if line.startswith('{')))
 assert counts['partners']==counts['products']==counts['landings']==1
 assert all(counts[key]==0 for key in ['orders','payments','grants','sales_enabled'])
 assert configuration()['jakawi']['prelaunch_mode'] is True
 (W/('digital-live-state.json' if required else 'digital-before.json')).write_text(json.dumps(counts,indent=2))

def private_files():
 root=pathlib.Path('/srv/sites/jakawi.com/storage/app/digital-private')
 return [{'path':str(p.relative_to(root)),'size':p.stat().st_size,'sha256':hashlib.sha256(p.read_bytes()).hexdigest()} for p in sorted(root.rglob('*')) if p.is_file()]

def digital_snapshot():
 raw=call(['docker','exec','jakawi-db-1','psql','-U','jakawi','-d','jakawi_final','-XAtc',"SELECT json_build_object('product',(SELECT to_jsonb(d) FROM digital_products d WHERE id=1 AND slug='gluteo-perfecto'),'landing',(SELECT to_jsonb(l) FROM landing_presentations l WHERE id=24 AND slug='gluteo-perfecto'))"],'Read the two authorized editorial rows')
 result=json.loads(raw)
 assert result['product'] and result['landing']
 return result

def prepare():
 revision=call(['git','-C',str(R),'rev-parse','HEAD'],'Read candidate SHA').decode().strip()
 assert not call(['git','-C',str(R),'status','--porcelain'],'Check clean source')
 assert remote_ref('refs/heads/main')==(EXPECTED_MAIN or revision)
 call(['git','-C',str(R),'merge-base','--is-ancestor',EXPECTED_MAIN or revision,revision],'Require fast-forward main ancestry')
 assert remote_ref(CANDIDATE_REF)==revision
 validation=json.loads((W/'validation.json').read_text());assert validation['result']=='PASS' and validation['revision']==revision
 assert not call(['git','-C',str(R),'diff',os.environ['JAKAWI_BASE_REVISION'],revision,'--','app/app/Models/DigitalPayment.php','app/app/Models/DigitalAccessGrant.php','app/app/Models/User.php','app/app/Models/Membership.php','app/app/Services/MembershipStatus.php','app/app/Services/MembershipBankCheckout.php','app/composer.lock','app/package-lock.json'],'Require protected economics and configuration unchanged')
 migrations=call(['git','-C',str(R),'diff','--name-only',os.environ['JAKAWI_BASE_REVISION'],revision,'--','app/database/'],'Require no database migrations').decode().splitlines()
 assert migrations==[], 'No migrations authorized'
 changed=call(['git','-C',str(R),'diff','--name-only',os.environ['JAKAWI_BASE_REVISION'],revision],'Require focused digital activation changes only').decode().splitlines()
 allowed={'docker/new-server/minio-canonical-media-release-control.py', 'app/config/filesystems.php', 'app/app/Services/MediaReferences.php', 'app/app/Services/DigitalFiles.php', 'app/app/Console/Commands/SyncDigitalPrivate.php', 'app/tests/Feature/MediaReferenceCoverageTest.php', 'app/tests/Feature/DigitalStorageMigrationTest.php', 'app/tests/Feature/MinioDigitalStorageTest.php', 'app/app/Services/DigitalStorageMigration.php'}
 assert set(changed)<=allowed, changed
 (W/'target-before.private.json').write_text(json.dumps(digital_snapshot()))
 digital_invariants(True)
 state=inspect();assert state[0]['Config']['Labels']['org.opencontainers.image.revision']==os.environ['JAKAWI_BASE_REVISION']
 assert all(i['Config']['Labels']['org.opencontainers.image.revision']==state[0]['Config']['Labels']['org.opencontainers.image.revision'] for i in [state[0],state[2],state[3]])
 assert configuration()['jakawi']['prelaunch_mode'] is True
 assert call(['docker','exec','jakawi-db-1','psql','-U','jakawi','-d','jakawi_final','-XAtc',"SELECT value->>'enabled' FROM app_settings WHERE key='show_demo_banner'"],'Require DEMO OFF').decode().strip()=='false'
 fingerprint('domain-before.json')
 (W/'private-files-before.json').write_text(json.dumps(private_files(),indent=2))
 (W/'state-before.private.json').write_text(json.dumps(state));(W/'config-before.private.json').write_text(json.dumps(configuration()))
 schema=call(['docker','exec','jakawi-db-1','pg_dump','-U','jakawi','-d','jakawi_final','--schema-only','--no-owner','--no-acl'],'Fingerprint existing schema')
 (W/'schema-before.private.sql').write_bytes(schema)
 backup=pathlib.Path('/var/backups/jakawi.com')/(datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')+'-'+TAG);backup.mkdir(mode=0o700)
 shutil.copyfile(W/'target-before.private.json',backup/'editorial-target.private.json')
 for name in ['compose.yaml','release.env','release-control.py']:shutil.copyfile(P/name,backup/name)
 if (W/'release-control.previous.py').is_file():shutil.copyfile(W/'release-control.previous.py',backup/'release-control.previous.py')
 print(stamp(),'Back up immutable images with compression',flush=True)
 with gzip.open(backup/'runtime-images.tar.gz','wb',compresslevel=3) as output:
  process=subprocess.Popen(['docker','image','save',state[0]['Image'],state[1]['Image']],stdout=subprocess.PIPE,stderr=subprocess.PIPE)
  shutil.copyfileobj(process.stdout,output)
  assert process.wait()==0, 'Image backup failed'
 with tarfile.open(backup/'runtime-images.tar.gz','r:gz') as archive: assert 'manifest.json' in archive.getnames()
 dump=call(['docker','exec','jakawi-db-1','pg_dump','-U','jakawi','-d','jakawi_final','--format=custom','--no-owner','--no-acl'],'Read-only database backup')
 (backup/'database.dump').write_bytes(dump)
 call(['docker','exec','-i','jakawi-db-1','pg_restore','--list'],'Validate database backup',dump)
 private_root=pathlib.Path('/srv/sites/jakawi.com/storage/app/digital-private')
 if private_root.exists(): shutil.copytree(private_root,backup/'digital-private')
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
 (W/'rollback-plan.json').write_text(json.dumps({'base':metadata['base'],'backup':str(backup),'schema':'unchanged; mirror MinIO private objects back to local with hash verification before restoring COM images/config; database dump retained, do not overwrite current rows','services':SERVICES,'prelaunch_required':True},indent=2))
 call(compose(W/'compose.candidate.private.json')+['config','--quiet'],'Validate candidate COM configuration')
 call(compose(W/'compose.candidate.private.json')+['--dry-run','up','--no-start','--no-deps','--no-build','--pull','never',*SERVICES],'Check COM-only recreation')
 # Run the candidate command before switching the disk. No database writes, no original deletions.
 raw=call(compose(W/'compose.candidate.private.json')+['run','--rm','--no-deps','--user','82:82','app','php','artisan','digital:sync-private','--apply','--no-interaction'],'Copy existing private files to MinIO and verify hashes')
 migration=json.loads(raw);assert migration['applied'] and all(x['verified'] for x in migration['objects'])
 assert [{k:v for k,v in x.items() if k!='verified'} for x in migration['objects']]==private_files()
 product=json.loads((W/'target-before.private.json').read_text())['product']
 assert any(x['path']==product['file_path'] and x['sha256']==product['file_sha256'] for x in migration['objects']), 'Product PDF hash mismatch'
 (W/'migration.json').write_text(json.dumps(migration,indent=2));shutil.copyfile(W/'migration.json',backup/'minio-objects.json')
 shutil.copyfile(W/'private-files-before.json',backup/'legacy-objects.json')
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
 assert digital_snapshot()==json.loads((W/'target-before.private.json').read_text()), 'Editorial content changed since prepare'
 call(['docker','stop','--timeout','90','jakawi-worker-1','jakawi-scheduler-1'],'Stop COM job consumers')
 call(['docker','stop','--timeout','30','jakawi-web-1','jakawi-app-1'],'Stop COM web and PHP')
 # No domain writes; the private disk switch uses the same immutable logical references.
 install(W/'compose.candidate.private.json',P/'compose.yaml');install(W/'release.env',P/'release.env')
 create=['up','--no-start','--no-deps','--no-build','--pull','never']
 call(compose()+create+['app','web'],'Create COM app/web release')
 call(['docker','start','jakawi-app-1'],'Start COM PHP')
 for task in ['package:discover','config:cache','route:cache','view:cache']:
  call(['docker','exec','--user','82:82','jakawi-app-1','php','artisan',task,'--no-interaction'],'Prepare '+task)
 current_config=configuration();old_config=json.loads((W/'config-before.private.json').read_text())
 digital=current_config['filesystems']['disks']['digital-private']
 assert digital['driver']=='s3' and digital['root']=='digital-private' and digital['visibility']=='private' and digital['serve'] is False
 for key in ['key','secret','region','bucket','endpoint','use_path_style_endpoint']:
  assert digital[key]==old_config['filesystems']['disks']['minio-media'][key]
 old_config['filesystems']['disks']['digital-private']=digital
 assert current_config==old_config,'Unexpected application configuration change'
 # Commercial activation stays off; no database operations or schema migrations.
 assert digital_snapshot()==json.loads((W/'target-before.private.json').read_text())
 digital_invariants(True)
 # Existing database and domain services preserved.
 call(['docker','start','jakawi-web-1'],'Start COM web')
 health()
 call(compose()+create+['worker','scheduler'],'Create COM job services')
 call(['docker','start','jakawi-worker-1','jakawi-scheduler-1'],'Resume COM job consumers')
 result={'revision':m['revision'],'deployed_at':stamp(),'com_only':True,'migrations_run':False,'live_fixtures_created':False,'commercial_sales_enabled':False}
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
 # Test sources are validated in isolated QA and are not production runtime files.
 changed = [name for name in changed if not name.startswith('app/tests/')]
 for name in changed:
  if not (R/name).is_file():
   call(['docker','exec','jakawi-app-1','test','!','-e','/var/www/html/'+name[4:]],'Verify removed application source')
   continue
  expected=hashlib.sha256((R/name).read_bytes()).hexdigest()
  actual=call(['docker','exec','jakawi-app-1','sha256sum','/var/www/html/'+name[4:]],'Check deployed application source').decode().split()[0]
  assert expected==actual
 for container in ['jakawi-app-1','jakawi-web-1']:
  expected=hashlib.sha256((R/'app/public/build/manifest.json').read_bytes()).hexdigest()
  assert call(['docker','exec',container,'sha256sum','/var/www/html/public/build/manifest.json'],'Verify existing frontend manifest').decode().split()[0]==expected
 health()
 raw=call(['docker','exec','--user','82:82','jakawi-app-1','php','artisan','digital:sync-private','--apply','--no-interaction'],'Verify LIVE MinIO hashes against retained originals')
 assert json.loads(raw)==json.loads((W/'migration.json').read_text())
 schema=call(['docker','exec','jakawi-db-1','pg_dump','-U','jakawi','-d','jakawi_final','--schema-only','--no-owner','--no-acl'],'Verify unchanged pre-existing schema')
 stable=lambda b:b'\n'.join(x for x in b.splitlines() if not x.startswith((b'\\restrict ',b'\\unrestrict ')))
 assert stable(schema)==stable((W/'schema-before.private.sql').read_bytes()), 'Unexpected schema change'
 assert private_files()==json.loads((W/'private-files-before.json').read_text()), 'Private digital files changed during release'
 fingerprint('domain-after.json')
 assert json.loads((W/'domain-before.json').read_text())==json.loads((W/'domain-after.json').read_text()), 'Domain rows changed during release'
 assert configuration()['jakawi']['prelaunch_mode'] is True
 transport=json.loads(call(['docker','exec','jakawi-app-1','php','-r','echo json_encode([ini_get("upload_max_filesize"),ini_get("post_max_size")]);'],'Verify PDF transport limits'))
 assert transport==['32M','40M']
 for container,source,target in [('jakawi-app-1','docker/new-server/php.ini','/usr/local/etc/php/conf.d/zz-jakawi.ini'),('jakawi-web-1','docker/new-server/nginx.conf','/etc/nginx/conf.d/default.conf')]:
  actual=call(['docker','exec',container,'sha256sum',target],'Verify COM transport configuration').decode().split()[0]
  assert actual==hashlib.sha256((R/source).read_bytes()).hexdigest()
 digital_invariants(True)
 assert digital_snapshot()==json.loads((W/'target-before.private.json').read_text()), 'Editorial content changed during digital integration release'
 preview=call(['docker','exec','--user','82:82','jakawi-app-1','php','-r',BOOT+'$p=App\\Models\\DigitalProduct::where("slug","gluteo-perfecto")->sole();$l=App\\Models\\LandingPresentation::where("slug","gluteo-perfecto")->sole();$r=Illuminate\\Http\\Request::create("https://jakawi.com/admin/productos-digitales/gluteo-perfecto/preview");$d=app(App\\Services\\DigitalProductLandingPresenter::class)->present($p,$l,$r,true);if(count($d["blocks"])!==11||$d["canBuy"]||$d["product"]["public_price"]!==$p->public_price)throw new RuntimeException("Invalid preview");$e=app(App\\Services\\LandingPresentationEditor::class)->context($p,$r);if($e["type"]!=="DIGITAL")throw new RuntimeException("Invalid editor");echo json_encode(["product_id"=>$p->id,"landing_id"=>$l->id,"blocks"=>count($d["blocks"]),"preview"=>true,"canBuy"=>false,"editor"=>$e["type"]]);'],'Verify real presenter and editor without users or transactions')
 (W/'live-preview.json').write_bytes(preview)
 for path in ['/productos-digitales','/explorar','/login','/productos-digitales/gluteo-perfecto','/l/gluteo-perfecto']:
  with urllib.request.urlopen('https://jakawi.com'+path,timeout=10) as response:assert response.status==200
 result={**json.loads((W/'deploy-result.json').read_text()),'verified_at':stamp(),'up_http':200,'catalog_http':200,'result':'PASS','main_update_pending':remote_ref('refs/heads/main')!=m['revision'],'four_com_services_same_sha':True,'protected_containers_unchanged':True,'preexisting_schema_compatible':True,'schema_changes':False,'digital_sales_blocked':True,'club_unchanged':True,'release_tag':TAG,'changed_application_files_verified':len(changed)}
 (W/'LIVE.json').write_text(json.dumps(result,indent=2));print(json.dumps(result,indent=2))

def promote():
 m=json.loads((W/'release-metadata.json').read_text())
 live=json.loads((W/'LIVE.json').read_text());assert live['revision']==m['revision'] and live['four_com_services_same_sha'] and live['result']=='PASS'
 assert json.loads((W/'live-browser.json').read_text())['result']=='PASS'
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

 if inspect()[0]['Config']['Labels']['org.opencontainers.image.revision']==m['revision']:
  raw=call(['docker','exec','--user','82:82','jakawi-app-1','php','artisan','digital:sync-private','--restore-local','--no-interaction'],'Dry-run legacy compatibility for current private objects')
  (W/'rollback-private-dry-run.json').write_bytes(raw)

def rollback():
 rollback_plan()
 m=json.loads((W/'release-metadata.json').read_text());backup=pathlib.Path(m['backup'])
 call(['docker','stop','--timeout','90','jakawi-worker-1','jakawi-scheduler-1'],'Stop COM candidate consumers')
 call(['docker','stop','--timeout','30','jakawi-web-1'],'Stop COM candidate web')
 call(['docker','exec','--user','82:82','jakawi-app-1','php','artisan','digital:sync-private','--restore-local','--apply','--no-interaction'],'Restore all private object references for legacy code, preserving MinIO originals')
 call(['docker','stop','--timeout','30','jakawi-app-1'],'Stop COM candidate PHP')
 install(backup/'compose.yaml',P/'compose.yaml');install(backup/'release.env',P/'release.env')
 call(compose()+['up','--no-start','--no-deps','--no-build','--pull','never',*SERVICES],'Restore previous COM images')
 call(['docker','start','jakawi-app-1'],'Start previous COM PHP')
 for task in ['package:discover','config:cache','route:cache','view:cache']:
  call(['docker','exec','--user','82:82','jakawi-app-1','php','artisan',task,'--no-interaction'],'Restore previous '+task)
 call(['docker','start','jakawi-web-1'],'Start previous COM web')
 health()
 call(['docker','start','jakawi-worker-1','jakawi-scheduler-1'],'Resume previous COM consumers')
 assert all(i['Config']['Labels']['org.opencontainers.image.revision']==m['base'] for i in inspect()[:4])
 (W/'rollback.json').write_text(json.dumps({'revision':m['base'],'database_restored':False,'schema_unchanged':True,'domain_writes':False,'migrations_run':False},indent=2))

if __name__=='__main__':
 lock=open('/run/lock/jakawi-release.lock','a');fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
 {'prepare':prepare,'deploy':deploy,'verify':verify,'promote':promote,'rollback':rollback,'rollback-plan':rollback_plan}[sys.argv[1]]()
