#!/usr/bin/env python3
"""Read-only validation. Never print resolved environment values."""
import json,pathlib,subprocess,sys
root=pathlib.Path(__file__).resolve().parent.parent
try:
 mode=sys.argv[1]; assert mode in ('dev','test')
 prefix='jakawi-vsl-'+mode
 assert pathlib.Path.cwd().resolve()==root
 assert (root/'.git').is_dir() and not (root/'.git/objects/info/alternates').exists()
 env=dict(line.split('=',1) for line in (root/f'.env.vsl-{mode}').read_text().splitlines() if line)
 assert env['APP_ENV']==('local' if mode=='dev' else 'testing')
 assert env['DB_HOST']==('db' if mode=='dev' else 'db-test')
 assert env['DB_DATABASE']==env['POSTGRES_DB']=='jakawi_vsl_'+mode
 assert env['DB_USERNAME']==env['POSTGRES_USER']=='jakawi_vsl_'+mode+'_user'
 assert env['DB_PASSWORD']==env['POSTGRES_PASSWORD'] and len(env['DB_PASSWORD'])>=32
 assert env['DB_URL']=='' and env['DB_CONNECTION']=='pgsql'
 assert env['APP_URL']=='http://127.0.0.1:8093'
 assert env['MEDIA_DISK']==env['FILESYSTEM_DISK']=='public'
 assert env['MAIL_MAILER']=='array'
 for k in ('CRM_SYNC_ENABLED','META_PROVIDER_ENABLED','META_BROWSER_ENABLED','META_CAPI_ENABLED','QR_PAYMENT_ENABLED'): assert env[k]=='false'
 assert (root/f'.env.vsl-{mode}').stat().st_mode & 0o077 == 0
 cmd=['docker','compose','--profile','checks','--project-name',prefix,'--env-file',f'.env.vsl-{mode}','-f',f'compose.vsl-{mode}.yaml','config','--format','json']
 result=subprocess.run(cmd,cwd=root,capture_output=True,text=True)
 assert result.returncode==0
 c=json.loads(result.stdout); assert c['name']==prefix
 expected={'db','app','web','frontend-check'} if mode=='dev' else {'db-test','app-test'}
 assert set(c['services'])==expected
 assert set(c['networks'])==({'isolated','entry'} if mode=='dev' else {'isolated'})
 if mode=='dev':
  entry=c['networks']['entry']; assert entry['name']==prefix+'-entry' and not entry.get('external')
 n=c['networks']['isolated']; assert n['name']==prefix+'-network' and n['internal'] and not n.get('external')
 assert set(c['volumes'])=={'pgdata','uploads'}
 for key,v in c['volumes'].items(): assert v['name']==prefix+'-'+key and not v.get('external')
 for key,s in c['services'].items():
  assert set(s['networks'])==({'isolated','entry'} if key=='web' else {'isolated'})
  assert not s.get('container_name') and not s.get('privileged') and not s.get('network_mode')
  assert s['image']==('postgres:16-alpine' if key.startswith('db') else prefix+'-'+('tools' if key=='frontend-check' else 'web' if key=='web' else 'app')+':local')
  if 'build' in s:
   assert pathlib.Path(s['build']['context']).resolve()==root
   assert s['build']['dockerfile']=='docker/Dockerfile.vsl'
  ports=s.get('ports',[])
  if key=='web':
   assert len(ports)==1 and ports[0]['host_ip']=='127.0.0.1' and str(ports[0]['published'])=='8093' and ports[0]['target']==80
  else: assert not ports
  for v in s.get('volumes',[]):
   assert v['type']=='volume' and v['source'] in ('pgdata','uploads')
   assert v['target'] in ('/var/lib/postgresql/data','/var/www/html/storage/app/public','/var/www/html/public/storage')
  if not key.startswith('db') and key!='web':
   e=s['environment']
   for k in env: assert str(e[k])==env[k]
 print(f'PASS: {prefix}; standalone Compose, dedicated env/resources, no bind mounts, internal network.')
except Exception:
 print('REFUSED: VSL isolation configuration invalid.',file=sys.stderr);sys.exit(78)
