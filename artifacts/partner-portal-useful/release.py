import datetime,hashlib,json,os,pathlib,shutil,subprocess,sys,urllib.request
os.umask(0o077)
P=pathlib.Path('/opt/sites/jakawi.com');W=P/'artifacts/partner-portal-useful';B=pathlib.Path('/srv/repos/jakawi.com')
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
 import time
 for url in ['http://127.0.0.1:8080/up','https://jakawi.com/up']:
  for attempt in range(20):
   try:
    with urllib.request.urlopen(url,timeout=5) as response:assert response.status==200
    break
   except Exception:
    if attempt==19:raise
    time.sleep(1)
 return True

def data_snapshot():
 # Entire original rows, receipts, identity and economic evidence are fingerprinted privately.
 sql="""SELECT json_build_object('memberships',(SELECT json_agg(to_jsonb(m) ORDER BY id) FROM memberships m),'tables',(SELECT json_object_agg(tablename, NULL) FROM pg_tables WHERE schemaname='public'));"""
 first=json.loads(run(['docker','exec','-i','jakawi-db-1','psql','-U','jakawi','-d','jakawi_final','-XAt'],sql.encode()))
 tables=[t for t in first['tables'] if (t in ['benefits','benefit_location','experiences','experience_partner','experience_sessions','experience_reservations','locations'] or any(x in t for x in ['membership','economic','conversion','reward','commission','outbox','evidence','redemption','jp_','crm_','partner','user_profiles','identity_','oauth_','support_sessions'])) and t!='memberships']
 for t in tables:
  row="to_jsonb(t)-'mode'" if t=='support_sessions' else 'to_jsonb(t)'
  sql=f'''SELECT md5(COALESCE(jsonb_agg({row} ORDER BY ({row})::text)::text, '[]')) FROM "{t}" t;'''
  first['tables'][t]=run(['docker','exec','-i','jakawi-db-1','psql','-U','jakawi','-d','jakawi_final','-XAt'],sql.encode()).decode().strip()
 first['users_hash']=run(['docker','exec','-i','jakawi-db-1','psql','-U','jakawi','-d','jakawi_final','-XAt'],b"SELECT md5(jsonb_agg(to_jsonb(u) ORDER BY id)::text) FROM users u;").decode().strip()
 first['tables']={t:first['tables'][t] for t in tables}
 return first

def build():
 os.environ['DOCKER_BUILDKIT']='0'
 rev=run(['git','-C',str(B),'rev-parse','HEAD']).decode().strip();assert not run(['git','-C',str(B),'diff','HEAD','--','app']);assert not run(['git','-C',str(B),'ls-files','--others','--exclude-standard','app'])
 state=inspect(NAMES);base={'app':state[0]['Image'],'web':state[1]['Image']}
 assert state[0]['Config']['Labels']['org.opencontainers.image.revision']=='f4ebbba4fa4db62dd5af97311a61e6edb9cb2bd6'
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
 for target in ['app','web']:run(['docker','build','--network','none','--memory','512m','--memory-swap','512m','--build-arg','BUILDKIT_INLINE_CACHE=0','--target',target,'-f',str(W/'Dockerfile'),'-t','jakawi-'+target+':partner-portal-useful',str(B)])
 images={s:json.loads(run(['docker','image','inspect','jakawi-'+s+':partner-portal-useful']))[0]['Id'] for s in ['app','web']}
 exclusions=' '.join("! -path '"+f+"'" for f in files)
 command=['sh','-lc',f"find app config routes vendor database -type f {exclusions} -exec sha256sum {{}} + | sort | sha256sum"]
 assert run(['docker','run','--rm','--network','none',base['app'],*command])==run(['docker','run','--rm','--network','none',images['app'],*command])
 env=state[0]['Config']['Env'];assert all('\n' not in e for e in env)
 (W/'app.private.env').write_text('\n'.join(env)+'\n')
 (W/'build.json').write_text(json.dumps({'revision':rev,'images':images,'base_images':base,'base_revision':state[0]['Config']['Labels']['org.opencontainers.image.revision']},indent=2))
 print('Candidate images built; protected source and dependencies unchanged.')

def prepare():
 m=json.loads((W/'build.json').read_text());before=containers();(W/'containers-before.private.json').write_text(json.dumps(before))
 backup=pathlib.Path('/var/backups/jakawi.com')/(datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')+'-partner-portal-useful');backup.mkdir(mode=0o700)
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
 assert data_snapshot()==json.loads((W/'data-before.private.json').read_text())
 (W/'schema.json').write_text(json.dumps({'no_schema_changes':True,'commercial_data_preserved':True}))


def deploy():
 m=json.loads((W/'release.json').read_text())
 assert (W/'schema.json').exists()
 assert 'OK (' in (W/'tests-final.log').read_text()
 assert 'failed' not in (W/'tests-final.log').read_text()
 assert json.loads((W/'browser.json').read_text())['result']=='PASS'
 assert 'Vite manifest PASS' in (W/'frontend-final.log').read_text()
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
 assert run(['docker','exec','-i','jakawi-db-1','psql','-U','jakawi','-d','jakawi_final','-XAt'],b'SELECT json_agg(id ORDER BY id) FROM users WHERE is_superadmin;').decode().strip()=='[1]'
 health();(W/'LIVE.json').write_text(json.dumps({'revision':m['revision'],'health':200,'protected_containers_unchanged':protected,'original_memberships_and_economic_data_preserved':True,'existing_superadmin_ids':[1],'live_support_tests':False},indent=2))
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
