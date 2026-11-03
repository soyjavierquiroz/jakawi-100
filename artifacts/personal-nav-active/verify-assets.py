import hashlib,json,pathlib,urllib.request
b=pathlib.Path('/srv/workspaces/jakawi/com/feat--personal-nav-active');out=b/'artifacts/personal-nav-active'
m=json.loads((b/'app/public/build/manifest.json').read_text());files=set();visited=set()
def add(key):
 if key in visited:return
 visited.add(key);entry=m[key];files.add(entry['file']);files.update(entry.get('css',[]))
 for dependency in entry.get('imports',[]):add(dependency)
for key in ['resources/js/app.tsx','resources/css/app.css']:add(key)
checks=[]
for path in sorted(files):
 expected=(b/'app/public/build'/path).read_bytes()
 with urllib.request.urlopen('https://jakawi.com/build/'+path,timeout=15) as r:
  assert r.read()==expected;checks.append({'asset':path,'http':r.status,'sha256':hashlib.sha256(expected).hexdigest()})
with urllib.request.urlopen('https://jakawi.com/beneficios',timeout=15) as r:assert r.status==200
with urllib.request.urlopen('https://jakawi.com/mi-jakawi',timeout=15) as r:assert '/login' in r.url
result={'result':'PASS','public_layout_assets':checks,'catalog_http':200,'anonymous_account_requires_login':True}
(out/'public-assets.json').write_text(json.dumps(result,indent=2));print(json.dumps(result,indent=2))
