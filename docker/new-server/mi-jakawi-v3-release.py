#!/usr/bin/env python3
"""Mi JAKAWI V3 presentation release; preserve all data, economics and flags."""
import datetime,fcntl,json,os,pathlib,shutil,subprocess,sys,urllib.request
os.umask(0o077)
R=pathlib.Path(os.environ.get('JAKAWI_REPOSITORY','/srv/workspaces/jakawi/com/feat--mi-jakawi-v3'))
P=pathlib.Path('/opt/sites/jakawi.com')
W=pathlib.Path(os.environ.get('JAKAWI_RELEASE_EVIDENCE','/srv/workspaces/jakawi/com/mi-jakawi-v3-evidence'))
BASE='bed49abbc432435fcd2c13cc9cbd41337f6326ef'
SERVICES=['app','web','worker','scheduler']
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
 assert git('ls-remote','origin','refs/heads/main').split()[0]==BASE
 assert read('validation.json')['result']=='PASS'
 assert read('validation.json')['revision']==git('rev-parse','HEAD')
 assert read('browser.json')['result']=='PASS'
 allowed={'app/resources/js/pages/mi-jakawi.tsx','app/resources/css/mi-jakawi.css','app/app/Http/Controllers/MembershipController.php','app/tests/Feature/MiJakawiPresentationTest.php','docker/new-server/mi-jakawi-v3-release.py','docker/new-server/mi-jakawi-v3-Dockerfile'}
 assert set(git('diff','--name-only',BASE,'HEAD').splitlines())<=allowed
 assert read('validation.json')['manifest_sha256']==__import__('hashlib').sha256((R/'app/public/build/manifest.json').read_bytes()).hexdigest()
def build():
 guard_source();assert shutil.disk_usage('/srv').free>5*1024**3
 revision=git('rev-parse','HEAD');images={}
 for target in ['app','web']:
  tag='jakawi-'+target+':mi-jakawi-v3'
  run(['docker','build','--network','none','--memory','768m','--build-arg','SOURCE_REVISION='+revision,'--target',target,'-f',str(R/'docker/new-server/mi-jakawi-v3-Dockerfile'),'-t',tag,str(R)])
  images[target]=run(['docker','image','inspect',tag,'--format','{{.Id}}']).decode().strip()
 save('images.json',images);print('Build PASS')
def prepare():
 guard_source();state=inspect();assert all(c['Config']['Labels']['org.opencontainers.image.revision']==(read('release-metadata.json')['revision'] if (W/'release-metadata.json').exists() else BASE) for c in state)
 if (W/'release-metadata.json').exists():
  backup=pathlib.Path(read('release-metadata.json')['backup'])
 else:
  backup=pathlib.Path('/var/backups/jakawi.com')/(datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')+'-mi-jakawi-v3')
  backup.mkdir(mode=0o700)
  for name in ['compose.yaml','release.env','release-control.py']:shutil.copyfile(P/name,backup/name);os.chmod(backup/name,0o600)
  with (backup/'database.dump').open('wb') as f:
   subprocess.run(['docker','exec','jakawi-db-1','pg_dump','-U','jakawi','-d','jakawi_final','--format=custom','--no-owner','--no-acl'],stdout=f,check=True)
  run(['docker','exec','-i','jakawi-db-1','pg_restore','--list'],(backup/'database.dump').read_bytes())
  save('runtime-before.private.json',state)
 save('expected-runtime.private.json',state)
 candidate=json.loads((P/'compose.yaml').read_text());images=read('images.json')
 for s in SERVICES:
  candidate['services'][s]['image']=images['web' if s=='web' else 'app']
 save('compose.candidate.private.json',candidate)
 run(compose(W/'compose.candidate.private.json')+['config','--quiet'])
 env=(P/'release.env').read_text().replace(state[0]['Image'],images['app']).replace(state[1]['Image'],images['web'])
 (W/'release.env').write_text(env)
 save('release-metadata.json',{'revision':git('rev-parse','HEAD'),'backup':str(backup),'base':BASE,'images':images})
 rollback_plan();print('Backup and rollback validation PASS: '+str(backup))
def rollback_plan():
 m=read('release-metadata.json');b=pathlib.Path(m['backup'])
 assert (b/'database.dump').stat().st_size>0
 run(compose(b/'compose.yaml')+['config','--quiet'])
 for c in read('runtime-before.private.json'):run(['docker','image','inspect',c['Image']])
 save('rollback-plan.json',{'result':'PASS','backup':str(b),'command':'JAKAWI_RELEASE_EVIDENCE='+str(W)+' python3 '+str(pathlib.Path(__file__).resolve())+' rollback','policy':'Restore retained COM images/config only; preserve welcome rights and any user claims. No database restore or schema down. Baseline flags and pending rights preserved.'})
def install(src,dst):
 tmp=dst.with_suffix('.welcome-tmp');shutil.copyfile(src,tmp);os.chmod(tmp,0o600);os.replace(tmp,dst)
def deploy():
 guard_source();assert read('rollback-plan.json')['result']=='PASS'
 assert [c['Id'] for c in inspect()]==[c['Id'] for c in read('expected-runtime.private.json')]
 install(W/'compose.candidate.private.json',P/'compose.yaml');install(W/'release.env',P/'release.env')
 run(compose()+['up','-d','--no-deps','--no-build','--pull','never',*SERVICES])
 save('deploy.json',{'revision':read('release-metadata.json')['revision']});print('Deploy PASS')
def verify():
 m=read('release-metadata.json');assert all(c['State']['Running'] and c['Config']['Labels']['org.opencontainers.image.revision']==m['revision'] for c in inspect())
 with urllib.request.urlopen('https://jakawi.com/up',timeout=20) as r:assert r.status==200
 state=json.loads(run(['docker','exec','-i','jakawi-app-1','php'],(W/'verify-state.php').read_bytes()))
 assert state['result']=='PASS'
 save('live-state.json',state)
 assert state['rights']==read('baseline-state.json')['rights']
 expected=read('validation.json')['manifest_sha256']
 actual=run(['docker','exec','jakawi-app-1','sha256sum','public/build/manifest.json']).decode().split()[0]
 assert actual==expected
 save('LIVE.json',{'result':'PASS','revision':m['revision'],'rollback':BASE,'backup':m['backup']})
 print('Runtime, assets, health and preserved data PASS')
def promote():
 guard_source();verify();assert read('live-ux.json')['result']=='PASS';assert read('live-state.json')['result']=='PASS'
 git('push','origin','HEAD:refs/heads/main');print('Canonical main promotion PASS')
def rollback():
 rollback_plan();b=pathlib.Path(read('release-metadata.json')['backup'])
 install(b/'compose.yaml',P/'compose.yaml');install(b/'release.env',P/'release.env')
 run(compose()+['up','-d','--no-deps','--no-build','--pull','never',*SERVICES]);print('Rollback PASS; rights and baseline flags retained')
if __name__=='__main__':
 W.mkdir(exist_ok=True)
 with (P/'.release-control.lock').open('a') as f:
  fcntl.flock(f,fcntl.LOCK_EX|fcntl.LOCK_NB)
  {'build':build,'prepare':prepare,'deploy':deploy,'verify':verify,'rollback':rollback,'promote':promote}[sys.argv[1]]()
