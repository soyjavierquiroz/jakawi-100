#!/usr/bin/env python3
"""Pruebas técnicas temporales; ningún paquete de QA se instala en LIVE."""
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
from types import SimpleNamespace
import zipfile

spec=importlib.util.spec_from_file_location('plr',Path(__file__).with_name('import-plr.py'))
m=importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)

class ImportTest(unittest.TestCase):
    def fixture(self,root):
        app=root/'bundle'
        app.mkdir()
        (app/'index.php').write_text("<?php $AFP_APP_SLUG='qa_es'; $AFP_LICENSE_KEY='qa'; $AFP_API_URL='https://eva.igorstorm.com/api/render.php'; curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);")
        (app/'config-override.json').write_text(json.dumps({'app':{'name':'QA temporal','lang':'es'}}))
        (app/'manifest.json').write_text(json.dumps({'name':'QA temporal','icons':[{'src':'icon.png'}]}))
        for f in ('icon.png','app.js','sw.js'):
            (app/f).write_bytes(b'QA')
        return app
    def test_all_36_editorial_titles_and_verified_ids(self):
        self.assertEqual(sum(map(len,m.PLR_CATEGORIES.values())),36)
        for category,names in m.PLR_CATEGORIES.items():
            for name in names:
                self.assertEqual(m.category_from_metadata(name, vendor_id='unverified_es'),category,name)
        for vendor,title in m.PLR_VENDOR_TITLES.items():
            self.assertEqual(m.category_from_metadata(title, vendor_id=vendor),m.category_from_metadata(title))
        self.assertEqual(m.category_from_metadata('Acero ABS',vendor_id='barriga_de_aco_es'),'fitness')
        self.assertEqual(m.category_from_metadata('Antiestrés',vendor_id='antiestresse_app_es'),'wellness')
        self.assertIsNone(m.category_from_metadata('Pilates en casa',vendor_id='astrologia_es'))
        self.assertIsNone(m.category_from_metadata('Aplicación desconocida','fitness yoga recetas'))
        self.assertEqual(m.category_from_metadata('Meal Prep Fitness'),'cocina-recetas')
        self.assertEqual(m.category_from_metadata('Jugo Detox'),'cocina-recetas')
        self.assertEqual(m.category_from_metadata('Té Detox'),'nutricion-habitos')

    def test_inspect_uses_real_package_name_and_preserves_manual_metadata_category(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp);app=self.fixture(root)
            (app/'config-override.json').write_text(json.dumps({'app':{'name':'Pastel Gourmet','lang':'es'}}))
            _,_,data=m.inspect(root)
            self.assertEqual(data['category'],'cocina-recetas')
            override=root/'metadata.json';override.write_text(json.dumps({'name':'Nombre propio','category':'wellness'}))
            _,_,data=m.inspect(root,override)
            self.assertEqual(data['category'],'wellness')
            override.write_text(json.dumps({'name':'Nombre propio'}))
            _,_,data=m.inspect(root,override)
            self.assertEqual(data['category'],'cocina-recetas')

    def test_detection_manifest_and_protected_routes(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp)
            app=self.fixture(root)
            source,manifest,data=m.inspect(root)
            self.assertEqual(data['slug'],'qa-es')
            self.assertIsNone(data['description'])
            stage=root/'stage';stage.mkdir()
            m.prepare(source,manifest,data,stage)
            actual=json.loads((stage/'public/manifest.json').read_text())
            self.assertEqual(actual['scope'],'/qa-es/')
            self.assertEqual(actual['id'],'/qa-es/')
            gateway=(stage/'access/gateway.php').read_text()
            self.assertIn("$c['app'] = 'qa-es'",gateway)
            self.assertIn('/srv/apps/qa-es/public/index.php',gateway)
            self.assertNotIn('gluteos',gateway)
            self.assertNotIn('caches.open',(stage/'access/sw.js').read_text())
            self.assertIn('CURLOPT_SSL_VERIFYPEER, true',(stage/'public/index.php').read_text())
    def test_missing_files_language_and_zip_traversal(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp);app=self.fixture(root)
            (app/'app.js').unlink()
            with self.assertRaisesRegex(ValueError,'app.js'): m.inspect(root)
            (app/'app.js').write_text('')
            (app/'config-override.json').write_text(json.dumps({'app':{'lang':'pt'}}))
            with self.assertRaisesRegex(ValueError,'española'): m.inspect(root)
            z=root/'bad.zip'
            with zipfile.ZipFile(z,'w') as f: f.writestr('../escape','bad')
            with self.assertRaisesRegex(ValueError,'insegura'): m.unpack(z,root/'out')
    def test_install_retry_and_refuse_changed_package(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp);source=root/'upload';source.mkdir();self.fixture(source)
            persistent=root/'store';(persistent/'apps').mkdir(parents=True)
            args=SimpleNamespace(check=False,metadata=None)
            def stub(command,**kwargs):
                return '{"product_id":2,"status":"draft","result":"unchanged"}' if 'hosted-app:import' in command else ''
            with patch.object(m,'ROOT',persistent),patch.object(m,'run',stub):
                m.import_one(source,args)
                index=persistent/'apps/qa-es/public/index.php'
                before=index.read_bytes()
                m.import_one(source,args)
                self.assertEqual(before,index.read_bytes())
                self.assertEqual(len(list((persistent/'apps').iterdir())),1)
                (source/'bundle/app.js').write_text('changed')
                with self.assertRaisesRegex(ValueError,'no se sobrescribe'):
                    m.import_one(source,args)
                self.assertEqual(before,index.read_bytes())
    def test_real_metadata_short_description_category_and_original_cover(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp);app=self.fixture(root)
            (app/'config-override.json').write_text(json.dumps({'app':{'name':'Pilates en casa','lang':'es','tagline':'Ejercicios en casa','description':'Descripción original disponible','logoUrl':'https://pub-qa.r2.dev/cover.jpg'}}))
            with patch.object(m,'original_cover',return_value=b'original-cover'):
                _,_,data=m.inspect(root)
            self.assertEqual(data['short_description'],'Ejercicios en casa')
            self.assertEqual(data['description'],'Descripción original disponible')
            self.assertEqual(data['category'],'fitness')
            self.assertEqual(data['cover_source'],'https://pub-qa.r2.dev/cover.jpg')
    def test_adopt_existing_is_read_only_idempotent_and_keeps_protection_bytes(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp);persistent=root/'store';ops=root/'ops';ops.mkdir()
            parent=persistent/'apps/gluteos';parent.mkdir(parents=True)
            self.fixture(parent).rename(parent/'public')
            public=parent/'public'
            (public/'index.php').write_text((public/'index.php').read_text().replace('qa_es','gluteo_perfeito_es'))
            (ops/'access').mkdir()
            for old,new in [('gateway.php','gateway.php'),('access-guard.js','access-guard.js')]:
                (ops/'access'/old).write_bytes((m.HERE/'templates'/new).read_bytes())
            (ops/'access/protected-sw.js').write_text('existing protected worker')
            (ops/'nginx.conf').write_text('''server {
    location = /gluteos { return 308 https://jakawi.store/gluteos/; }
    location = /gluteos/sw.js { alias /opt/store/access/protected-sw.js; }
    location = /gluteos/manifest.json { try_files $uri =404; }
    location = /gluteos/access-guard.js { alias /opt/store/access/access-guard.js; }
    location ~ ^/gluteos/(?:index\\.php|_entry|_check|_health)?$ { fastcgi_param SCRIPT_FILENAME /opt/store/access/gateway.php; }
    include /opt/store/catalog/routes/*.conf;
    location /gluteos/ { return 404; }
    location ~ ^/gluteos/(?:app\\.js|icon-(?:192|512)\\.png)$ { try_files $uri =404; }
}''')
            for d in ['records','routes','apps']:(persistent/'catalog'/d).mkdir(parents=True)
            calls=[]
            def stub(command,**kwargs):
                calls.append((command,kwargs.get('input','')))
                if command[-1]=='php':return json.dumps(dict(id=1,app_key='gluteos',delivery_type='hosted_app',name='Glúteos Perfectos',slug='gluteos-perfectos',status='published'))
                return ''
            before=m.installed_hash(public)
            with patch.object(m,'ROOT',persistent),patch.object(m,'OPS',ops),patch.object(m,'BACKUPS',root/'backups'),patch.object(m,'run',stub):
                m.adopt_existing('gluteos')
                m.adopt_existing('gluteos')
                with self.assertRaisesRegex(ValueError,'ya está adoptado'):m.inspect(public)
            self.assertEqual(m.installed_hash(public),before)
            self.assertEqual(len(list((root/'backups').iterdir())),1)
            self.assertIn('/opt/store/catalog/apps/gluteos/access/gateway.php',(persistent/'catalog/routes/gluteos.conf').read_text())
            self.assertNotIn('location = /gluteos',(ops/'nginx.conf').read_text())
            self.assertEqual((ops/'access/gateway.php').resolve(),persistent/'catalog/apps/gluteos/access/gateway.php')
            self.assertTrue(all('hosted-app:import' not in command for command,_ in calls))
    def test_real_gluteos_cannot_be_duplicated(self):
        with self.assertRaisesRegex(ValueError,'no se duplicará|ya está adoptado'):
            m.inspect(Path('/srv/sites/jakawi.store/apps/gluteos/public'))

if __name__=='__main__': unittest.main()
