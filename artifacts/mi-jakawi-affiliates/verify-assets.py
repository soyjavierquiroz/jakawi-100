import hashlib,json,pathlib,urllib.request
b=pathlib.Path('/srv/workspaces/jakawi/com/feat--mi-jakawi-affiliates');out=b/'artifacts/mi-jakawi-affiliates'
m=json.loads((b/'app/public/build/manifest.json').read_text());checks=[]
for page in ['mi-jakawi','member-profile']:
 e=m[f'resources/js/pages/{page}.tsx']
 for path in [e['file'],*e.get('css',[])]:
  expected=(b/'app/public/build'/path).read_bytes()
  with urllib.request.urlopen('https://jakawi.com/build/'+path,timeout=15) as r:
   actual=r.read();assert actual==expected
   checks.append({'asset':path,'http':r.status,'sha256':hashlib.sha256(actual).hexdigest()})
with urllib.request.urlopen('https://jakawi.com/mi-jakawi',timeout=15) as r:
 assert '/login' in r.url
result={'result':'PASS','public_assets':checks,'anonymous_mi_jakawi_requires_login':True}
(out/'public-assets.json').write_text(json.dumps(result,indent=2));print(json.dumps(result,indent=2))
