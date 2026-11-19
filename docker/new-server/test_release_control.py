"""Isolated canonical release tests: local Git only; Docker commands never execute."""
import ast
import json
import os
import pathlib
import runpy
import subprocess
import tempfile
import unittest
from unittest.mock import patch

SOURCE = pathlib.Path(__file__).with_name('payments-admin-release-control.py')

class CanonicalReleaseTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(prefix='com-release-test-')
        self.addCleanup(self.tmp.cleanup)
        self.root = pathlib.Path(self.tmp.name)
        self.repo = self.root/'repo'; self.repo.mkdir()
        self.remote = self.root/'remote.git'
        self.live = self.root/'live'; self.live.mkdir()
        self.evidence = self.root/'evidence'; self.evidence.mkdir()
        self.backup = self.root/'backup'; self.backup.mkdir()
        self.git('init', '--bare', str(self.remote), cwd=self.root)
        self.git('init', '-b', 'main')
        self.git('config', 'user.email', 'release@example.test')
        self.git('config', 'user.name', 'Isolated release test')
        (self.repo/'source').write_text('base')
        self.git('add', 'source'); self.git('commit', '-m', 'base')
        self.base = self.git('rev-parse', 'HEAD').strip().decode()
        self.git('remote', 'add', 'origin', str(self.remote))
        self.git('push', 'origin', 'main')
        self.git('checkout', '-b', 'candidate')
        (self.repo/'source').write_text('candidate')
        self.git('commit', '-am', 'candidate')
        self.candidate = self.git('rev-parse', 'HEAD').strip().decode()
        with patch.dict(os.environ, {'JAKAWI_LIVE_ROOT':str(self.live), 'JAKAWI_REPOSITORY':str(self.repo), 'JAKAWI_RELEASE_EVIDENCE':str(self.evidence)}):
            module = runpy.run_path(str(SOURCE), run_name='isolated_release_module')
        self.functions = module
        self.g = module['promote'].__globals__
        self.commands = []
        self.g['call'] = self.call
        self.g['inspect'] = lambda:self.state(self.candidate)
        self.g['health'] = lambda:{'/up':200}
        (self.evidence/'release-metadata.json').write_text(json.dumps({'revision':self.candidate, 'base':self.base, 'expected_main':self.base, 'backup':str(self.backup)}))
        (self.evidence/'LIVE.json').write_text(json.dumps({'revision':self.candidate,'four_com_services_same_sha':True}))
        for f in ['compose.yaml','release.env']:
            (self.live/f).write_text('candidate configuration')
            (self.backup/f).write_text('previous configuration')

    def git(self, *args, cwd=None):
        return subprocess.check_output(['git', *args], cwd=cwd or self.repo, stderr=subprocess.DEVNULL)

    def state(self, sha):
        return [{'State':{'Running':True},'Config':{'Labels':{'org.opencontainers.image.revision':sha}}} for _ in range(4)]

    def call(self, args, label, data=None):
        self.commands.append(args)
        if args[0]=='git':
            self.assertEqual(args[:3], ['git','-C',str(self.repo)])
            self.assertNotIn('--force', args)
            self.assertEqual(self.git('remote','get-url','origin').decode().strip(), str(self.remote))
            return subprocess.check_output(args, stderr=subprocess.DEVNULL)
        self.assertEqual(args[0], 'docker')
        self.assertNotIn('down',args); self.assertNotIn('pg_restore',args)
        if args[:2] == ['docker','start'] and 'jakawi-worker-1' in args:
            self.g['inspect'] = lambda:self.state(self.base)
        return b''

    def test_functions_and_dispatcher_are_top_level(self):
        tree=ast.parse(SOURCE.read_text())
        names={n.name for n in tree.body if isinstance(n,ast.FunctionDef)}
        self.assertTrue({'prepare','deploy','verify','promote','rollback','rollback_plan'} <= names)
        dispatcher=tree.body[-1]
        mappings=[n for n in ast.walk(dispatcher) if isinstance(n,ast.Dict)]
        self.assertTrue(any({getattr(k,'value',None) for k in n.keys} >= {'promote','rollback','rollback-plan'} for n in mappings))
        self.assertTrue(callable(self.functions['promote']) and callable(self.functions['rollback']))

    def test_promote_fast_forwards_local_isolated_remote_only_after_pass(self):
        self.functions['promote']()
        self.assertEqual(self.git('ls-remote','origin','refs/heads/main').decode().split()[0],self.candidate)
        self.assertEqual(self.git('rev-parse','main').decode().strip(),self.candidate)
        self.assertEqual(self.git('rev-parse','origin/main').decode().strip(),self.candidate)
        self.assertTrue(json.loads((self.evidence/'promotion.json').read_text())['after_live_pass'])

    def test_no_pass_prevents_any_push(self):
        (self.evidence/'LIVE.json').write_text(json.dumps({'revision':self.candidate,'four_com_services_same_sha':False}))
        with self.assertRaises(AssertionError): self.functions['promote']()
        self.assertFalse(any('push' in c for c in self.commands))

    def test_concurrent_main_change_prevents_push(self):
        self.git('checkout','main')
        (self.repo/'other').write_text('concurrent change')
        self.git('add','other'); self.git('commit','-m','concurrent')
        self.git('push','origin','main')
        with self.assertRaises(AssertionError): self.functions['promote']()
        self.assertFalse(any('push' in c for c in self.commands))

    def test_rollback_plan_is_dry_run_only(self):
        before={f:(self.live/f).read_bytes() for f in ['compose.yaml','release.env']}
        self.functions['rollback_plan']()
        self.assertEqual(before,{f:(self.live/f).read_bytes() for f in before})
        self.assertEqual(len(self.commands),2)
        self.assertIn('--dry-run',self.commands[-1])
        self.assertEqual(self.commands[-1][-4:],['app','web','worker','scheduler'])

    def test_rollback_uses_only_com_images_config_and_never_database_restore(self):
        self.functions['rollback']()
        self.assertEqual((self.live/'compose.yaml').read_text(),'previous configuration')
        self.assertEqual((self.live/'release.env').read_text(),'previous configuration')
        for c in self.commands:
            self.assertFalse(any(x in ' '.join(c) for x in ['club-app','crm-', 'traefik','minio','v2proof']))
        self.assertFalse(json.loads((self.evidence/'rollback.json').read_text())['database_restored'])

    def test_rollback_health_failure_does_not_resume_consumers(self):
        self.g['health']=lambda:(_ for _ in ()).throw(RuntimeError('isolated health failure'))
        with self.assertRaises(RuntimeError):self.functions['rollback']()
        self.assertFalse(any(c[:2]==['docker','start'] and 'jakawi-worker-1' in c for c in self.commands))

if __name__=='__main__': unittest.main()
