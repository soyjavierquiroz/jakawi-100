<?php
// Focused COM -> STORE bridge. EvaFunnels renderer remains separate and intact.
header('Cache-Control: private, no-store, max-age=0');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
$c = json_decode(file_get_contents('/opt/store/hosted-app.private.json'), true, 512, JSON_THROW_ON_ERROR);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
function deny(int $code = 403): never {
    http_response_code($code);
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Acceso JAKAWI</title><body><h1>Abre tu aplicación desde JAKAWI</h1><p>Necesitas un acceso vigente.</p><a href="https://jakawi.com/mi-jakawi/productos-digitales">ABRIR MI BIBLIOTECA</a><script>if("serviceWorker" in navigator) navigator.serviceWorker.register("/gluteos/sw.js",{scope:"/gluteos/",updateViaCache:"none"}).then(r=>r.update());</script></body></html>';
    exit;
}
function com(array $data): ?array {
    global $c;
    $h = curl_init($c['com'].'/internal/hosted-app/exchange');
    curl_setopt_array($h, [CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($data+['app'=>$c['app']]),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','Authorization: Bearer '.$c['secret']],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false]);
    $body=curl_exec($h); $status=curl_getinfo($h,CURLINFO_HTTP_CODE); curl_close($h);
    return $status===200 && is_string($body) ? json_decode($body,true) : null;
}
if ($path === '/gluteos/_health') {
    if (!hash_equals('Bearer '.$c['secret'], $_SERVER['HTTP_AUTHORIZATION'] ?? '')) deny(401);
    ob_start(function($html) {
        $bridge = com(['action'=>'check','credential'=>'health-probe']);
        $ok = http_response_code() < 400 && strlen($html)>1000 && str_contains($html,'APP_CONFIG') && is_array($bridge) && array_key_exists('authorized',$bridge);
        http_response_code($ok ? 200 : 503); header('Content-Type: application/json');
        return json_encode(['protected'=>true,'functional'=>$ok]);
    });
    require '/srv/apps/gluteos/public/index.php'; exit;
}
ini_set('session.use_strict_mode','1');
session_name('__Secure-jakawi_gluteos');
session_set_cookie_params(['lifetime'=>28800,'path'=>'/gluteos/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if ($path === '/gluteos/_entry') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SERVER['HTTP_ORIGIN'] ?? '') !== 'https://jakawi.com') deny();
    $pass=$_POST['pass'] ?? '';
    if (!is_string($pass) || !preg_match('/^[a-f0-9]{64}$/D',$pass)) deny();
    $r=com(['action'=>'consume','token'=>$pass]);
    if (empty($r['credential'])) deny();
    session_regenerate_id(true);
    $_SESSION=['credential'=>$r['credential'],'expires'=>time()+28800];
    session_write_close();
    header('Location: /gluteos/',true,303); exit;
}
$credential=$_SESSION['credential'] ?? '';
$expires=$_SESSION['expires'] ?? 0;
session_write_close();
$r = $credential && $expires > time() ? com(['action'=>'check','credential'=>$credential]) : null;
if (($r['authorized'] ?? false) !== true) {
    session_start(); $_SESSION=[]; session_destroy();
    setcookie('__Secure-jakawi_gluteos','',['expires'=>1,'path'=>'/gluteos/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
    deny();
}
if ($path === '/gluteos/_check') { header('Content-Type: application/json'); echo '{"authorized":true}'; exit; }
// Inject only authorization supervision, without rebuilding the generated UI.
ob_start(function($html) {
    $script='<script src="/gluteos/access-guard.js"></script>';
    return str_replace('</head>',$script.'</head>',$html);
});
require '/srv/apps/gluteos/public/index.php';
