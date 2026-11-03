import datetime,hashlib,json,os,pathlib,shutil,subprocess,sys,urllib.request
os.umask(0o077)
P=pathlib.Path('/opt/sites/jakawi.com');W=P/'artifacts/superadmin-support';B=pathlib.Path('/srv/repos/jakawi.com')
SERVICES=['app','web','worker','scheduler'];NAMES=['jakawi-'+s+'-1' for s in SERVICES]
def run(args,data=None):
 p=subprocess.run(args,input=data,capture_output=True)
 with (W/'operations.private.log').open('ab') as f:f.write((' '.join(args[:2])+'\n').encode()+p.stdout+p.stderr)
 if p.returncode:raise RuntimeError('Command failed; inspect operations.private.log: '+' '.join(args[:3]))
 return p.stdout
def inspect(names):return json.loads(run(['docker','inspect',*names]))
def containers():return [{k:x[k] for k in ['Id','Name','Image','State']} for x in inspect(run(['docker','ps','-q']).decode().split())]
def compose():return ['docker','compose','--project-directory',str(P),'--env-file',str(P/'release.env'),'-f',str(P/'compose.yaml'),'--profile','production-jobs']
def health():
 for url in ['http://127.0.0.1:8080/up','https://jakawi.com/up']:
  with urllib.request.urlopen(url,timeout=15) as response:assert response.status==200
 return True

def data_snapshot():
 # Entire original rows, receipts, identity and economic evidence are fingerprinted privately.
 sql="""SELECT json_build_object('memberships',(SELECT json_agg(to_jsonb(m) ORDER BY id) FROM memberships m),'tables',(SELECT json_object_agg(tablename, NULL) FROM pg_tables WHERE schemaname='public'));"""
 first=json.loads(run(['docker','exec','-i','jakawi-db-1','psql','-U','jakawi','-d','jakawi_final','-XAt'],sql.encode()))
 tables=[t for t in first['tables'] if any(x in t for x in ['membership','economic','conversion','reward','commission','outbox','evidence','redemption','jp_','crm_','partner','user_profiles','identity_','oauth_']) and t!='memberships']
 for t in tables:
  sql=f'''SELECT md5(COALESCE(jsonb_agg(to_jsonb(t) ORDER BY to_jsonb(t)::text)::text, '[]')) FROM "{t}" t;'''
  first['tables'][t]=run(['docker','exec','-i','jakawi-db-1','psql','-U','jakawi','-d','jakawi_final','-XAt'],sql.encode()).decode().strip()
 first['users_hash']=run(['docker','exec','-i','jakawi-db-1','psql','-U','jakawi','-d','jakawi_final','-XAt'],b"SELECT md5(jsonb_agg(to_jsonb(u)-'is_superadmin' ORDER BY id)::text) FROM users u;").decode().strip()
 first['tables']={t:first['tables'][t] for t in tables}
 return first

def build():
 os.environ['DOCKER_BUILDKIT']='0'
 rev=run(['git','-C',str(B),'rev-parse','HEAD']).decode().strip();assert not run(['git','-C',str(B),'diff','HEAD','--']);assert not run(['git','-C',str(B),'ls-files','--others','--exclude-standard','app'])
 state=inspect(NAMES);base={'app':state[0]['Image'],'web':state[1]['Image']}
 assert state[0]['Config']['Labels']['org.opencontainers.image.revision']==run(['git','-C',str(B),'rev-parse','HEAD^']).decode().strip()
 files=[f[4:] for f in run(['git','-C',str(B),'diff','--name-only',state[0]['Config']['Labels']['org.opencontainers.image.revision'],rev]).decode().splitlines() if f.startswith('app/') and not f.startswith('app/tests/')]
 assert files and 'app/Http/Middleware/SupportMode.php' in files
 df=''
 for target in ['app','web']:
  df+=f'FROM {base[target]} AS {target}\nLABEL org.opencontainers.image.revision="{rev}"\n'
  if target=='app':
   for f in files:df+=f'COPY --chown=www-data:www-data app/{f} /var/www/html/{f}\n'
   df+='RUN rm -f bootstrap/cache/config.php bootstrap/cache/routes-*.php\n'
  df+='COPY app/public/build/ /var/www/html/public/build/\n'
 (W/'Dockerfile').write_text(df)
 for target in ['app','web']:run(['docker','build','--network','none','--memory','512m','--memory-swap','512m','--build-arg','BUILDKIT_INLINE_CACHE=0','--target',target,'-f',str(W/'Dockerfile'),'-t','jakawi-'+target+':superadmin-support',str(B)])
 images={s:json.loads(run(['docker','image','inspect','jakawi-'+s+':superadmin-support']))[0]['Id'] for s in ['app','web']}
 exclusions=' '.join("! -path '"+f+"'" for f in files)
 command=['sh','-lc',f"find app config routes vendor database -type f {exclusions} -exec sha256sum {{}} + | sort | sha256sum"]
 assert run(['docker','run','--rm','--network','none',base['app'],*command])==run(['docker','run','--rm','--network','none',images['app'],*command])
 env=state[0]['Config']['Env'];assert all('\n' not in e for e in env)
 (W/'app.private.env').write_text('\n'.join(env)+'\n')
 (W/'build.json').write_text(json.dumps({'revision':rev,'images':images,'base_images':base,'base_revision':state[0]['Config']['Labels']['org.opencontainers.image.revision']},indent=2))
 print('Candidate images built; protected source and dependencies unchanged.')

def finalize():
 os.environ['DOCKER_BUILDKIT']='0'
 # Final throttle namespaces only: preserve the already validated candidate layers/assets.
 m=json.loads((W/'build.json').read_text());rev=run(['git','-C',str(B),'rev-parse','HEAD']).decode().strip()
 assert not run(['git','-C',str(B),'diff','HEAD','--'])
 assert m['base_revision']==run(['git','-C',str(B),'rev-parse','HEAD^']).decode().strip()
 assert run(['git','-C',str(B),'diff','--name-only',m['revision'],rev,'--','app']).decode().splitlines()==['app/routes/web.php','app/tests/Feature/SupportModeTest.php']
 df=f"FROM {m['images']['app']} AS app\nCOPY --chown=www-data:www-data app/routes/web.php /var/www/html/routes/web.php\nLABEL org.opencontainers.image.revision=\"{rev}\"\nFROM {m['images']['web']} AS web\nLABEL org.opencontainers.image.revision=\"{rev}\"\n"
 (W/'Dockerfile.final').write_text(df)
 for target in ['app','web']:run(['docker','build','--network','none','--memory','512m','--memory-swap','512m','--target',target,'-f',str(W/'Dockerfile.final'),'-t','jakawi-'+target+':superadmin-support',str(B)])
 m['initial_candidate_images']=m['images'];m['images']={s:inspect(['jakawi-'+s+':superadmin-support'])[0]['Id'] for s in ['app','web']};m['revision']=rev
 files=[f[4:] for f in run(['git','-C',str(B),'diff','--name-only',m['base_revision'],rev]).decode().splitlines() if f.startswith('app/') and not f.startswith('app/tests/')]
 exclusions=' '.join("! -path '"+f+"'" for f in files)
 command=['sh','-lc',f"find app config routes vendor database -type f {exclusions} -exec sha256sum {{}} + | sort | sha256sum"]
 assert run(['docker','run','--rm','--network','none',m['base_images']['app'],*command])==run(['docker','run','--rm','--network','none',m['images']['app'],*command])
 (W/'build.json').write_text(json.dumps(m,indent=2));print('Final namespaced throttle routes built; protected source remains identical.')

def prepare():
 m=json.loads((W/'build.json').read_text());before=containers();(W/'containers-before.private.json').write_text(json.dumps(before))
 backup=pathlib.Path('/var/backups/jakawi.com')/(datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')+'-superadmin-support');backup.mkdir(mode=0o700)
 for f in ['compose.yaml','release.env']:shutil.copyfile(P/f,backup/f)
 run(['docker','image','save','-o',str(backup/'runtime-images.tar'),*m['base_images'].values()])
 dump=run(['docker','exec','jakawi-db-1','pg_dump','-U','jakawi','-d','jakawi_final','--format=custom','--no-owner','--no-acl']);(backup/'database.dump').write_bytes(dump)
 run(['docker','exec','-i','jakawi-db-1','pg_restore','--list'],dump)
 m['backup']=str(backup);m['backup_sha256']=hashlib.sha256(dump).hexdigest()
 digest=hashlib.sha256()
 with (backup/'runtime-images.tar').open('rb') as f:
  for chunk in iter(lambda:f.read(1024*1024),b''):digest.update(chunk)
 m['images_backup_sha256']=digest.hexdigest()
 (W/'data-before.private.json').write_text(json.dumps(data_snapshot(),indent=2))
 for s,i in zip(SERVICES,inspect(NAMES)):assert i['Image']==m['base_images']['web' if s=='web' else 'app']
 (W/'release.json').write_text(json.dumps(m,indent=2));print('Backup and COM-only rollback prepared.')

def candidate(args):
 m=json.loads((W/'release.json').read_text())
 return run(['docker','run','--rm','--network','jakawi_private','--memory','256m','--env-file',str(W/'app.private.env'),m['images']['app'],*args])

def schema():
 candidate(['php','artisan','migrate','--force','--path=database/migrations/2026_11_03_000001_create_support_sessions.php'])
 assert data_snapshot()==json.loads((W/'data-before.private.json').read_text()),'Commercial data changed'
 code = """Illuminate\\Support\\Facades\\DB::transaction(function () { $user = App\\Models\\User::lockForUpdate()->findOrFail(1); abort_unless($user->is_admin, 403); if (!$user->is_superadmin) { $user->timestamps=false; $user->forceFill(['is_superadmin'=>true])->save(); App\\Models\\AuditLog::create(['actor_user_id'=>null, 'action'=>'support_superadmin_granted', 'subject_type'=>App\\Models\\User::class, 'subject_id'=>1, 'metadata'=>['authorization'=>'Explicit operator approval in Codex conversation', 'resolved_admin_user_id'=>1, 'previous'=>false, 'current'=>true, 'scope'=>'COM support mode only']]); } });"""
 candidate(['php','artisan','tinker','--execute='+code])
 assert data_snapshot()==json.loads((W/'data-before.private.json').read_text()),'Original user or commercial data changed'
 (W/'schema.json').write_text(json.dumps({'additive_migration':True,'superadmin_grants':[1],'commercial_data_preserved':True,'explicit_operator_authorization':True}))
 print('Support schema applied; explicitly authorized admin #1 granted superadmin with audit. No support sessions created.')

def deploy():
 m=json.loads((W/'release.json').read_text());assert (W/'schema.json').exists()
 assert json.loads((W/'browser.json').read_text())['result']=='PASS'
 assert '13 passed' in (W/'tests-final.log').read_text()
 assert 'Vite manifest PASS' in (W/'frontend-final.log').read_text()
 assert '22 passed' in (W/'regression-final.log').read_text()
 config=json.loads((P/'compose.yaml').read_text());original=json.loads(json.dumps(config))
 for s in SERVICES:config['services'][s]['image']=m['images']['web' if s=='web' else 'app']
 for s in config['services']:
  if s not in SERVICES:assert config['services'][s]==original['services'][s]
  else:assert {k:v for k,v in config['services'][s].items() if k!='image'}=={k:v for k,v in original['services'][s].items() if k!='image'}
 (W/'compose.candidate.private.json').write_text(json.dumps(config,indent=2))
 env=(P/'release.env').read_text()
 for s in ['app','web']:env=env.replace(m['base_images'][s],m['images'][s])
 # Validate manifest before stopping COM services.
 run(['docker','compose','--project-directory',str(P),'--env-file',str(P/'release.env'),'-f',str(W/'compose.candidate.private.json'),'config','--quiet'])
 run(['docker','stop','--timeout','60','jakawi-worker-1','jakawi-scheduler-1'])
 run(['docker','stop','--timeout','30','jakawi-web-1','jakawi-app-1'])
 (P/'compose.yaml').write_text(json.dumps(config,indent=2));(P/'release.env').write_text(env)
 run(compose()+['up','--no-start','--no-deps','--no-build','--pull','never','app','web'])
 run(['docker','start','jakawi-app-1'])
 for task in ['package:discover','config:cache','route:cache','view:cache']:run(['docker','exec','--user','82:82','jakawi-app-1','php','artisan',task,'--no-interaction'])
 run(['docker','start','jakawi-web-1']);health()
 run(compose()+['up','--no-start','--no-deps','--no-build','--pull','never','worker','scheduler']);run(['docker','start','jakawi-worker-1','jakawi-scheduler-1'])
 verify();print('COM app/web/worker/scheduler deployed; health and preservation verified.')

def verify():
 m=json.loads((W/'release.json').read_text());now={x['Name']:x for x in containers()};protected=0
 for old in json.loads((W/'containers-before.private.json').read_text()):
  if old['Name'].lstrip('/') in NAMES or 'jakawi-test' in old['Name']:continue
  item=now[old['Name']];assert all(item[k]==old[k] for k in ['Id','Image']);assert item['State']['StartedAt']==old['State']['StartedAt'];protected+=1
 for i in inspect(NAMES):assert i['State']['Running'] and i['Config']['Labels']['org.opencontainers.image.revision']==m['revision']
 after=data_snapshot();(W/'data-after.private.json').write_text(json.dumps(after,indent=2));assert after==json.loads((W/'data-before.private.json').read_text())
 assert run(['docker','exec','-i','jakawi-db-1','psql','-U','jakawi','-d','jakawi_final','-XAt'],b'SELECT count(*) FROM support_sessions;').decode().strip()=='0'
 assert run(['docker','exec','-i','jakawi-db-1','psql','-U','jakawi','-d','jakawi_final','-XAt'],b'SELECT json_agg(id ORDER BY id) FROM users WHERE is_superadmin;').decode().strip()=='[1]'
 health();(W/'LIVE.json').write_text(json.dumps({'revision':m['revision'],'health':200,'protected_containers_unchanged':protected,'original_memberships_and_economic_data_preserved':True,'superadmin_grants':[1],'live_support_tests':False},indent=2))
def rollback():
 m=json.loads((W/'release.json').read_text());backup=pathlib.Path(m['backup'])
 run(['docker','stop','--timeout','30',*NAMES])
 for f in ['compose.yaml','release.env']:shutil.copyfile(backup/f,P/f)
 run(compose()+['up','--no-start','--no-deps','--no-build','--pull','never','app','web']);run(['docker','start','jakawi-app-1'])
 for task in ['package:discover','config:cache','route:cache','view:cache']:run(['docker','exec','--user','82:82','jakawi-app-1','php','artisan',task,'--no-interaction'])
 run(['docker','start','jakawi-web-1']);health()
 run(compose()+['up','--no-start','--no-deps','--no-build','--pull','never','worker','scheduler']);run(['docker','start','jakawi-worker-1','jakawi-scheduler-1'])
 print('Previous COM images restored; additive support schema and all commercial data preserved.')
if __name__=='__main__':globals()[sys.argv[1]]()
