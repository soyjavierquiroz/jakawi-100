#!/usr/bin/env python3
"""Remove PRELAUNCH from COM while preserving individual operating settings and data."""
import datetime,fcntl,json,os,pathlib,shutil,subprocess,sys,urllib.request
os.umask(0o077)
R=pathlib.Path(os.environ.get('JAKAWI_REPOSITORY','/srv/workspaces/jakawi/com/remove-prelaunch'))
P=pathlib.Path('/opt/sites/jakawi.com')
W=pathlib.Path(os.environ.get('JAKAWI_RELEASE_EVIDENCE','/srv/workspaces/jakawi/com/remove-prelaunch-evidence'))
BASE='52be30992d36f201ab583da291a603bd849c1b5c'
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
 assert read('browser.json')['manifest_sha256']==read('validation.json')['manifest_sha256']
 allowed={'app/tests/Feature/DigitalSaleActivationTest.php', 'app/resources/js/types/global.d.ts', 'app/resources/js/pages/admin/resources/benefit-form.tsx', 'app/app/Services/Commercial/CommercialOutbox.php', 'app/resources/js/pages/digital/library.tsx', 'app/resources/js/pages/partner/unlocks/form.tsx', 'app/resources/js/pages/partner/content/form.tsx', 'app/resources/js/pages/prelaunch-blocked.tsx', 'app/bootstrap/app.php', 'app/app/Exceptions/PrelaunchOperationBlocked.php', 'app/resources/js/pages/landing-presentations/adapters/digital-offer-adapter.tsx', 'app/app/Services/ConversionRecorder.php', 'app/app/Http/Controllers/MembershipOfferController.php', 'app/tests/Feature/PartnerOperationsV3aTest.php', 'app/routes/console.php', 'app/tests/Feature/OperationAvailabilityTest.php', 'app/app/Payments/Qr/Providers/FakeQrPaymentGateway.php', 'app/config/jakawi.php', 'app/app/Services/RedemptionService.php', 'app/app/Services/JakawiBankReconciliation.php', 'app/resources/js/components/partner/operation-feedback.tsx', 'app/app/Services/RewardResolver.php', 'app/app/Http/Controllers/LandingPresentationController.php', 'app/routes/club-purchases.php', 'app/app/Services/AdminCatalog.php', 'app/app/Services/ExperienceReservationService.php', 'app/tests/Feature/PrelaunchCommercialContinuityTest.php', 'app/app/Services/UnlockStatusService.php', 'app/app/Services/RewardPayoutService.php', 'app/tests/Feature/PartnerUnlockChallengeV3bTest.php', 'app/app/Services/ExperienceCheckInService.php', 'app/resources/js/pages/landing-presentations/adapters/challenge-offer-adapter.tsx', 'app/app/Services/DigitalGifts.php', 'app/app/Models/DigitalProduct.php', 'app/app/Services/MembershipService.php', 'app/app/Services/JpLedgerService.php', 'app/app/Http/Controllers/DigitalProductController.php', 'app/resources/views/app.blade.php', 'app/resources/js/layouts/public-mode-layout.tsx', 'app/app/Http/Controllers/RedemptionController.php', 'app/app/Services/MembershipPurchaseService.php', 'docker/new-server/remove-prelaunch-release-control.py', 'app/app/Http/Controllers/PublicController.php', 'app/app/Jobs/ExtractMembershipReceipt.php', 'app/app/Services/DigitalSaleAvailability.php', 'app/app/Http/Controllers/DigitalOrderController.php', 'app/resources/js/pages/admin/catalog/index.tsx', 'app/app/Jobs/ReconcileJakawiBank.php', 'app/tests/Feature/SharedDigitalAccountTest.php', 'app/app/Http/Controllers/PartnerUnlockController.php', 'app/app/Services/Commercial/MembershipAcquisition.php', 'app/app/Services/MembershipReceiptVision.php', 'app/tests/Feature/DigitalGiftsTest.php', 'app/resources/js/pages/coming-soon.tsx', 'app/app/Providers/AppServiceProvider.php', 'app/app/Services/MembershipBankImport.php', 'app/resources/js/pages/landing-presentations/adapters/experience-offer-adapter.tsx', 'app/app/Services/MembershipBankCheckout.php', 'app/app/Jobs/RefreshChallengeSocialEntry.php', 'app/tests/Feature/PublicOperationsTest.php', 'app/app/Services/DigitalProductLandingPresenter.php', 'app/app/Http/Middleware/HandleInertiaRequests.php', 'app/app/Http/Controllers/AdminDigitalSalesController.php', 'app/app/Services/ChallengeService.php', 'app/.env.example', 'app/resources/js/pages/partner/unlocks/participations.tsx', 'app/tests/marketing/remove-prelaunch.cjs', 'app/app/Support/PrelaunchOperations.php', 'app/resources/js/layouts/partner-layout.tsx', 'app/app/Http/Middleware/ProtectPrelaunchOperations.php', 'app/app/Services/DigitalCheckout.php', 'app/app/Services/GluteoPerfectoVisibility.php', 'app/tests/Feature/DigitalLandingTest.php', 'docker/new-server/remove-prelaunch-Dockerfile', 'app/app/Services/ClubPurchaseIdentity.php', 'app/app/Services/UnlockParticipationService.php', 'app/resources/js/pages/landing-presentations/unlock.tsx', 'app/resources/js/pages/landing-presentations/adapters/benefit-offer-adapter.tsx', 'app/tests/Feature/PrelaunchOperationsTest.php', 'app/app/Models/DigitalOrder.php', 'app/resources/js/components/prelaunch-notice.tsx', 'app/resources/js/pages/admin/digital/settings.tsx', 'app/config/digital_gifts.php', 'app/resources/js/pages/digital/show.tsx', 'app/tests/Feature/PrelaunchModeTest.php', 'app/app/Console/Commands/ExpireUnlocksCommand.php', 'app/tests/Feature/DigitalCheckoutTest.php', 'app/resources/js/pages/digital/order.tsx', 'app/app/Services/Commercial/CommercialClient.php', 'app/tests/Feature/DigitalComingSoonTest.php', 'app/app/Http/Controllers/ComOriginController.php', 'app/app/Services/Commercial/CommercialDispatcher.php', 'app/resources/js/pages/admin/digital/order.tsx'}
 assert set(git('diff','--name-only',BASE,'HEAD').splitlines())<=allowed
 assert read('validation.json')['manifest_sha256']==__import__('hashlib').sha256((R/'app/public/build/manifest.json').read_bytes()).hexdigest()
def build():
 guard_source();assert shutil.disk_usage('/srv').free>2*1024**3
 revision=git('rev-parse','HEAD');images={}
 for target in ['app','web']:
  tag='jakawi-'+target+':remove-prelaunch'
  run(['env','DOCKER_BUILDKIT=0','docker','build','--network','none','--memory','768m','--build-arg','SOURCE_REVISION='+revision,'--target',target,'-f',str(R/'docker/new-server/remove-prelaunch-Dockerfile'),'-t',tag,str(R)])
  images[target]=run(['docker','image','inspect',tag,'--format','{{.Id}}']).decode().strip()
 save('images.json',images);print('Build PASS')
def prepare():
 guard_source();state=inspect();assert all(c['Config']['Labels']['org.opencontainers.image.revision']==(read('release-metadata.json')['revision'] if (W/'release-metadata.json').exists() else BASE) for c in state)
 if (W/'release-metadata.json').exists():
  backup=pathlib.Path(read('release-metadata.json')['backup'])
 else:
  backup=pathlib.Path('/var/backups/jakawi.com')/(datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')+'-remove-prelaunch')
  backup.mkdir(mode=0o700)
  for name in ['compose.yaml','release.env','release-control.py']:shutil.copyfile(P/name,backup/name);os.chmod(backup/name,0o600)
  with (backup/'database.dump').open('wb') as f:
   subprocess.run(['docker','exec','jakawi-db-1','pg_dump','-U','jakawi','-d','jakawi_final','--format=custom','--no-owner','--no-acl'],stdout=f,check=True)
  run(['docker','exec','-i','jakawi-db-1','pg_restore','--list'],(backup/'database.dump').read_bytes())
  shutil.copyfile(W/'release-control.previous.py',backup/'release-control.py')
  save('runtime-before.private.json',state)
 save('expected-runtime.private.json',state)
 save('landing-before.private.json',snapshot())
 candidate=json.loads((P/'compose.yaml').read_text());images=read('images.json')
 for s in SERVICES:
  candidate['services'][s]['image']=images['web' if s=='web' else 'app']
  for key in ['PRELAUNCH_MODE','DIGITAL_WELCOME_GIFTS_PRELAUNCH_ENABLED']:candidate['services'][s].get('environment',{}).pop(key,None)
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
 save('rollback-plan.json',{'result':'PASS','backup':str(b),'command':'JAKAWI_RELEASE_EVIDENCE='+str(W)+' python3 '+str(pathlib.Path(__file__).resolve())+' rollback','policy':'Restore retained COM images/config. No database restore; preserve current user data.'})
def install(src,dst):
 tmp=dst.with_suffix('.welcome-tmp');shutil.copyfile(src,tmp);os.chmod(tmp,0o600);os.replace(tmp,dst)
def snapshot():
 php=b"<?php require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();$p=App\\Models\\DigitalProduct::where('slug','gluteo-perfecto')->sole();echo json_encode(['product'=>$p->getAttributes(),'landing'=>App\\Models\\LandingPresentation::where('slug','gluteo-perfecto')->sole()->getAttributes(),'prelaunch'=>(bool)config('jakawi.prelaunch_mode'),'can_sell'=>app(App\\Services\\DigitalSaleAvailability::class)->canSell($p),'admin_missing'=>app(App\\Services\\DigitalSaleAvailability::class)->missing($p),'digital_configuration'=>App\\Models\\AppSetting::whereIn('key',[App\\Services\\DigitalCollection::KEY,app(App\\Services\\DigitalProductQr::class)->key($p),App\\Services\\DigitalSaleAvailability::KEY,App\\Services\\DigitalGifts::COURTESY_KEY,'membership_bank_qr'])->orderBy('key')->get()->toArray(),'gifts'=>App\\Models\\DigitalGift::orderBy('id')->get()->toArray(),'membership_payments'=>App\\Models\\MembershipBankPayment::count(),'memberships'=>App\\Models\\Membership::count(),'orders'=>App\\Models\\DigitalOrder::count(),'payments'=>App\\Models\\DigitalPayment::count(),'grants'=>App\\Models\\DigitalAccessGrant::count()]);"
 return json.loads(run(['docker','exec','-i','jakawi-app-1','php'],php))

def deploy():
 guard_source();assert read('rollback-plan.json')['result']=='PASS'
 assert [c['Id'] for c in inspect()]==[c['Id'] for c in read('expected-runtime.private.json')]
 install(W/'compose.candidate.private.json',P/'compose.yaml');install(W/'release.env',P/'release.env')
 run(compose()+['up','-d','--no-deps','--no-build','--pull','never',*SERVICES])
 save('deploy.json',{'revision':read('release-metadata.json')['revision']});print('Deploy PASS')
def verify():
 m=read('release-metadata.json');assert all(c['State']['Running'] and c['Config']['Labels']['org.opencontainers.image.revision']==m['revision'] for c in inspect())
 with urllib.request.urlopen('https://jakawi.com/up',timeout=20) as r:assert r.status==200
 expected=read('validation.json')['manifest_sha256']
 actual=run(['docker','exec','jakawi-app-1','sha256sum','public/build/manifest.json']).decode().split()[0]
 assert actual==expected
 before=read('landing-before.private.json');after=snapshot()
 before.pop('admin_missing',None);after.pop('admin_missing',None)
 assert before.pop('prelaunch') is True
 assert after.pop('prelaunch') is False
 assert before==after
 assert all(not any(v.startswith(('PRELAUNCH_MODE=','DIGITAL_WELCOME_GIFTS_PRELAUNCH_ENABLED=')) for v in c['Config']['Env']) for c in inspect())
 run(['docker','exec','jakawi-app-1','sh','-c','test ! -f app/Support/PrelaunchOperations.php && test ! -f app/Http/Middleware/ProtectPrelaunchOperations.php && test ! -f app/Exceptions/PrelaunchOperationBlocked.php'])
 save('LIVE.json',{'result':'PASS','revision':m['revision'],'rollback':BASE,'backup':m['backup']})
 print('Runtime, assets and health PASS')
def promote():
 guard_source();verify();assert read('live-ux.json')['result']=='PASS'
 git('push','origin','HEAD:refs/heads/main');print('Canonical main promotion PASS')
def rollback():
 rollback_plan();b=pathlib.Path(read('release-metadata.json')['backup'])
 install(b/'compose.yaml',P/'compose.yaml');install(b/'release.env',P/'release.env')
 run(compose()+['up','-d','--no-deps','--no-build','--pull','never',*SERVICES]);print('Rollback PASS; current user data retained')
if __name__=='__main__':
 W.mkdir(exist_ok=True)
 with (P/'.release-control.lock').open('a') as f:
  fcntl.flock(f,fcntl.LOCK_EX|fcntl.LOCK_NB)
  {'build':build,'prepare':prepare,'deploy':deploy,'verify':verify,'rollback':rollback,'promote':promote}[sys.argv[1]]()
