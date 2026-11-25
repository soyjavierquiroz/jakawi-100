#!/usr/bin/env python3
"""Build a category-only plan from existing, fingerprinted STORE imports. No writes."""
import importlib.util,json,sys
from pathlib import Path
sys.dont_write_bytecode=True
spec=importlib.util.spec_from_file_location('plr',Path(__file__).with_name('import-plr.py'))
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
plan=[]
for p in sorted((m.ROOT/'catalog/records').glob('*.json')):
    record=json.loads(p.read_text())
    if record.get('origin')=='adopted_existing' or record['slug']=='gluteos':continue
    public=m.ROOT/'apps'/record['slug']/'public'
    if record.get('installed_sha256')!=m.installed_hash(public):raise RuntimeError('Installed files changed: '+record['slug'])
    name=json.loads((public/'config-override.json').read_text()).get('app',{}).get('name') or json.loads((public/'manifest.json').read_text()).get('name','')
    category=m.category_from_metadata(name,vendor_id=record['vendor_id'])
    if category:
        plan.append(dict(slug=record['slug'],vendor_id=record['vendor_id'],package_sha256=record['package_sha256'],product_id=record['com']['product_id'],category=category))
print(json.dumps(plan))
