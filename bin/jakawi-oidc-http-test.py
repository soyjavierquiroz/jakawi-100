#!/usr/bin/env python3
"""Exercise an immutable COM image over HTTP, only on the disposable OIDC network."""
import base64, concurrent.futures, hashlib, http.cookiejar, json, os, pathlib, re, secrets, socket, subprocess, sys, tempfile, time
import urllib.error, urllib.parse, urllib.request

NETWORK = 'jakawi-oidc-test'
DB = 'jakawi-oidc-db-test'
CALLBACK = 'https://jakawi.club/auth/jakawi/callback'
NAME = 'jakawi-oidc-http-test'
WEB = 'jakawi-oidc-http-web-test'
os.umask(0o077)

def run(args, **kwargs):
    return subprocess.check_output(args, stderr=subprocess.PIPE, **kwargs)

def b64(data):
    return base64.urlsafe_b64encode(data).decode().rstrip('=')

def unb64(data):
    return base64.urlsafe_b64decode(data + '=' * (-len(data) % 4))

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None

def client():
    return urllib.request.build_opener(NoRedirect(), urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def fetch(opener, url, body=None, headers=None):
    request = urllib.request.Request(url, data=None if body is None else urllib.parse.urlencode(body).encode(), headers=headers or {})
    try:
        response = opener.open(request, timeout=30)
    except urllib.error.HTTPError as e:
        response = e
    return response.code, response.headers, response.read()

def asn1(tag, body):
    size = len(body)
    length = bytes([size]) if size < 128 else bytes([0x80 + (size.bit_length()+7)//8]) + size.to_bytes((size.bit_length()+7)//8, 'big')
    return bytes([tag]) + length + body

def integer(value):
    raw = unb64(value)
    if raw[0] & 128:
        raw = b'\x00' + raw
    return asn1(2, raw)

def main():
    image = sys.argv[1]
    web_image = sys.argv[2]
    state = json.loads(run(['docker', 'inspect', DB]))[0]
    assert list(state['NetworkSettings']['Networks']) == [NETWORK]
    assert state['State']['Health']['Status'] == 'healthy'
    network = json.loads(run(['docker', 'network', 'inspect', NETWORK]))[0]
    assert network['Internal'] is True
    image_state = json.loads(run(['docker', 'image', 'inspect', image]))[0]
    revision = image_state['Config']['Labels'].get('org.opencontainers.image.revision')
    assert revision and revision != 'unknown'
    assert json.loads(run(['docker', 'image', 'inspect', web_image]))[0]['Config']['Labels'].get('org.opencontainers.image.revision') == revision
    # A separate database keeps this check independent of PHPUnit transactions.
    run(['docker', 'exec', DB, 'createdb', '-U', 'jakawi_test_user', 'jakawi_oidc_http'])
    try:
        with tempfile.TemporaryDirectory(prefix='jakawi-oidc-http-') as directory:
            root = pathlib.Path(directory)
            run(['openssl', 'genpkey', '-algorithm', 'RSA', '-pkeyopt', 'rsa_keygen_bits:2048', '-out', str(root/'oauth-private.key')])
            run(['openssl', 'pkey', '-in', str(root/'oauth-private.key'), '-pubout', '-out', str(root/'oauth-public.key')])
            os.chmod(root, 0o755)
            os.chown(root/'oauth-private.key', 82, 82)
            os.chmod(root/'oauth-private.key', 0o400)
            os.chmod(root/'oauth-public.key', 0o444)
            with socket.socket() as sock:
                sock.bind(('127.0.0.1', 0))
                port = sock.getsockname()[1]
            issuer = f'http://127.0.0.1:{port}'
            client_id = run(['python3', '-c', 'import uuid;print(uuid.uuid4())'], text=True).strip()
            secret = secrets.token_urlsafe(48)
            env = {
                'APP_ENV':'testing', 'APP_DEBUG':'false', 'APP_URL':issuer,
                'APP_KEY':'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=',
                'DB_CONNECTION':'pgsql', 'DB_HOST':'db-test', 'DB_DATABASE':'jakawi_oidc_http',
                'DB_USERNAME':'jakawi_test_user', 'DB_PASSWORD':'jakawi_test_password',
                'CACHE_STORE':'file', 'SESSION_DRIVER':'database', 'SESSION_SECURE_COOKIE':'false',
                'QUEUE_CONNECTION':'sync', 'MAIL_MAILER':'array', 'CRM_SYNC_ENABLED':'false',
                'BCRYPT_ROUNDS':'4', 'OIDC_ISSUER':issuer, 'OIDC_KEY_ID':'isolated-http-key',
                'OIDC_KEY_DIRECTORY':'/oidc-fixture', 'OIDC_CLUB_CLIENT_ID':client_id,
                'OIDC_CLUB_CLIENT_SECRET':secret, 'OIDC_CLUB_REDIRECT_URI':CALLBACK,
                'LOG_CHANNEL':'stderr',
            }
            (root/'fixture.env').write_text(''.join(k+'='+v+'\n' for k,v in env.items()))
            (root/'fixture.php').write_text('''<?php
require '/var/www/html/vendor/autoload.php';
$app=require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
use Illuminate\\Support\\Facades\\DB;
if (! app()->environment('testing') || config('database.connections.pgsql.database') !== 'jakawi_oidc_http') exit(78);
if (($argv[1] ?? '') === 'create') {
 App\\Models\\User::query()->forceCreate(['name'=>'Isolated OIDC HTTP','email'=>'oidc-http@example.test','password'=>Illuminate\\Support\\Facades\\Hash::make('isolated-http-password'),'email_verified_at'=>now(),'is_admin'=>true]);
}
$out=[];
foreach (['users','memberships','partner_user','program_enrollments','identity_subjects','identity_links'] as $table) {
 $rows=DB::table($table)->get()->map(fn($r)=>(array)$r)->all();
 $out[$table]=hash('sha256',json_encode($rows));
}
$out['subject']=DB::table('users')->where('email','oidc-http@example.test')->value('global_subject_id');
$out['issued_access_tokens']=DB::table('oauth_access_tokens')->count();
echo json_encode($out);
''')
            run(['docker','run','-d','--name',NAME,'--network',NETWORK,'--network-alias','app','--memory','512m','--cpus','0.75',
                 '--env-file',str(root/'fixture.env'),'-v',str(root)+':/oidc-fixture:ro',image])
            try:
                for task in [['migrate','--force','--no-interaction'],['oidc:register-club'],['config:cache'],['route:cache'],['view:cache']]:
                    run(['docker','exec',NAME,'php','artisan',*task])
                before = json.loads(run(['docker','exec',NAME,'php','/oidc-fixture/fixture.php','create']))
                run(['docker','run','-d','--name',WEB,'--network',NETWORK,'--memory','64m','--cpus','0.25',
                     '-p',f'127.0.0.1:{port}:80',web_image])
                for attempt in range(30):
                    try:
                        if fetch(client(), issuer+'/up')[0] == 200:
                            break
                    except (OSError, urllib.error.URLError):
                        pass
                    time.sleep(0.5)
                else:
                    raise RuntimeError('isolated FPM/nginx did not become healthy')
                checks = []
                def check(condition, label):
                    assert condition, label
                    checks.append(label)
                browser = client()
                state_value, nonce, verifier = b64(secrets.token_bytes(32)), b64(secrets.token_bytes(32)), b64(secrets.token_bytes(48))
                parameters = {'client_id':client_id,'redirect_uri':CALLBACK,'response_type':'code','scope':'openid profile email',
                              'state':state_value,'nonce':nonce,'code_challenge':b64(hashlib.sha256(verifier.encode()).digest()),'code_challenge_method':'S256'}
                auth_url = issuer+'/oauth/authorize?'+urllib.parse.urlencode(parameters)
                status, headers, _ = fetch(browser, auth_url)
                check(status == 302 and urllib.parse.urlparse(headers['Location']).path == '/login', 'guest uses existing COM login')
                status, _, html = fetch(browser, issuer+'/login')
                check(status == 200, 'COM login HTML loads')
                csrf = re.search(rb'name="csrf-token" content="([^"]+)"', html).group(1).decode()
                body = {'email':'oidc-http@example.test','password':'isolated-http-password'}
                check(fetch(browser, issuer+'/login', body)[0] == 419, 'real HTTP CSRF is enforced')
                body['_token'] = csrf
                status, headers, _ = fetch(browser, issuer+'/login', body)
                resumed = urllib.parse.urljoin(issuer, headers.get('Location',''))
                check(status == 302 and urllib.parse.urlparse(resumed).path == '/oauth/authorize'
                      and dict(urllib.parse.parse_qsl(urllib.parse.urlparse(resumed).query)) == parameters, 'real password login resumes OIDC parameters')
                status, headers, _ = fetch(browser, resumed)
                check(status == 302 and headers['Location'].startswith(CALLBACK+'?'), 'authorization code returns to exact CLUB callback')
                check('no-store' in headers.get('Cache-Control',''), 'code response cannot be cached')
                query = dict(urllib.parse.parse_qsl(urllib.parse.urlparse(headers['Location']).query))
                check(query['state'] == state_value, 'authorization returns original state')
                code = query['code']
                def token(code_value, changes=None, basic=False):
                    form = {'grant_type':'authorization_code','client_id':client_id,'client_secret':secret,
                            'redirect_uri':CALLBACK,'code':code_value,'code_verifier':verifier}
                    form.update(changes or {})
                    headers = {'Accept':'application/json'}
                    if basic:
                        headers['Authorization']='Basic '+base64.b64encode((form.pop('client_id')+':'+form.pop('client_secret')).encode()).decode()
                    return fetch(client(), issuer+'/oauth/token', form, headers)
                check(token(code, {'code_verifier':b64(secrets.token_bytes(48))})[0] == 400, 'wrong PKCE is rejected')
                check(token(code, {'redirect_uri':'https://evil.example/callback'})[0] == 400, 'wrong token redirect is rejected')
                check(token(code, {'client_secret':'wrong'})[0] == 401, 'wrong client secret is rejected')
                status, headers, raw = token(code, basic=True)
                check(status == 200, 'confidential Basic client exchanges code with correct PKCE')
                check('no-store' in headers.get('Cache-Control',''), 'tokens cannot be cached')
                tokens = json.loads(raw)
                check('refresh_token' not in tokens, 'no offline or refresh token is issued')
                status, _, raw = fetch(client(), issuer+'/oauth/jwks')
                key = json.loads(raw)['keys'][0]
                check(status == 200 and 'd' not in key and key['kid']=='isolated-http-key', 'JWKS publishes public RSA key and kid')
                rsa = asn1(0x30, integer(key['n'])+integer(key['e']))
                encoded = base64.b64encode(rsa).decode()
                (root/'jwks-public.key').write_text('-----BEGIN RSA PUBLIC KEY-----\n'+'\n'.join(encoded[i:i+64] for i in range(0,len(encoded),64))+'\n-----END RSA PUBLIC KEY-----\n')
                def validate(jwt, expected_nonce):
                    head, payload, signature = jwt.split('.')
                    (root/'signature').write_bytes(unb64(signature))
                    run(['openssl','dgst','-sha256','-verify',str(root/'jwks-public.key'),'-signature',str(root/'signature')],input=(head+'.'+payload).encode())
                    claims = json.loads(unb64(payload))
                    assert json.loads(unb64(head))['alg']=='RS256'
                    assert claims['iss']==issuer and claims['sub']==before['subject'] and claims['nonce']==expected_nonce
                    assert client_id in ([claims['aud']] if isinstance(claims['aud'],str) else claims['aud'])
                    assert claims['exp']>time.time() and claims['iat']<=time.time()
                    return claims
                check(validate(tokens['id_token'], nonce)['email']=='oidc-http@example.test', 'RP verifies JWKS signature issuer audience nonce expiry and global UUID')
                check(token(code)[0] == 400 and token('invalid')[0] == 400, 'used and invalid codes are rejected')
                parameters['prompt']='none'
                parameters['nonce']=b64(secrets.token_bytes(32))
                status, headers, _ = fetch(browser, issuer+'/oauth/authorize?'+urllib.parse.urlencode(parameters))
                check(status==302 and headers['Location'].startswith(CALLBACK+'?'), 'existing COM cookie completes prompt none without a password')
                query = dict(urllib.parse.parse_qsl(urllib.parse.urlparse(headers['Location']).query))
                concurrent_code = query['code']
                with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
                    results = list(pool.map(lambda i: token(concurrent_code.upper() if i == 1 else concurrent_code), range(3)))
                check(sorted(r[0] for r in results)==[200,400,400], 'three concurrent processes issue one token response for one code')
                success = next(json.loads(r[2]) for r in results if r[0]==200)
                validate(success['id_token'], parameters['nonce'])
                after = json.loads(run(['docker','exec',NAME,'php','/oidc-fixture/fixture.php']))
                check(all(before[k]==after[k] for k in before if k!='issued_access_tokens'), 'users passwords roles memberships partners subjects and links remain unchanged')
                check(after['issued_access_tokens']==2, 'database has exactly two successful exchanges')
                status, _, raw = fetch(client(), issuer+'/.well-known/openid-configuration')
                metadata = json.loads(raw)
                check(status==200 and metadata['issuer']==issuer and metadata['code_challenge_methods_supported']==['S256'], 'discovery agrees with actual endpoints and policy')
                # Also verify ordinary login remains usable without an OIDC continuation.
                normal = client()
                _, _, html = fetch(normal, issuer+'/login')
                csrf = re.search(rb'name="csrf-token" content="([^"]+)"', html).group(1).decode()
                status, headers, _ = fetch(normal, issuer+'/login', {'email':'oidc-http@example.test','password':'isolated-http-password','_token':csrf})
                check(status==302 and urllib.parse.urlparse(headers['Location']).path=='/admin', 'ordinary COM admin login preserves its existing destination')
                print(json.dumps({'result':'PASS','image_revision':revision,'checks':checks,'checks_count':len(checks),
                                  'concurrent_statuses':sorted(r[0] for r in results),'fixture_only':True,'production_users_or_sessions_used':False},indent=2))
            finally:
                subprocess.run(['docker','rm','-f',WEB],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
                subprocess.run(['docker','rm','-f',NAME],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    finally:
        subprocess.run(['docker','exec',DB,'dropdb','-U','jakawi_test_user','--force','jakawi_oidc_http'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)

if __name__=='__main__':
    main()
