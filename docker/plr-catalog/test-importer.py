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
    def test_real_gluteos_cannot_be_duplicated(self):
        with self.assertRaisesRegex(ValueError,'no se duplicará'):
            m.inspect(Path('/srv/sites/jakawi.store/apps/gluteos/public'))

if __name__=='__main__': unittest.main()
