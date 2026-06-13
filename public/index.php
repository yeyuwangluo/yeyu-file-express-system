<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

/*
|--------------------------------------------------------------------------
| Preflight: redirect to installer if not ready
|--------------------------------------------------------------------------
|
| Before Laravel boots, we check whether the system is ready to serve
| requests.  If .env is missing or has no APP_KEY, we redirect straight
| to /install so the user never sees a 500 error from an unconfigured
| application.  The check is intentionally simple and dependency-free.
|
*/

$envFile = __DIR__.'/../.env';
$installUri = '/install';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?? '/';

/*
 | Check whether .env exists and has a valid APP_KEY.
 | If missing or empty, create .env with a generated key and redirect to the installer.
 */

if (! file_exists($envFile)) {
    $key = 'base64:'.base64_encode(random_bytes(32));
    file_put_contents($envFile, "APP_ENV=local\nAPP_DEBUG=true\nAPP_KEY={$key}\nAPP_URL=http://localhost\nDB_CONNECTION=sqlite\n");
    header('Location: '.$installUri);
    exit;
}

$envContent = file_get_contents($envFile);
if (preg_match('/^APP_KEY=(.+)$/m', $envContent, $m) && trim($m[1]) === '') {
    // APP_KEY exists but is empty — generate one and write it back.
    $key = 'base64:'.base64_encode(random_bytes(32));
    $envContent = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.$key, $envContent);
    file_put_contents($envFile, $envContent);
}

if (strpos($envContent, 'APP_KEY=') === false) {
    $key = 'base64:'.base64_encode(random_bytes(32));
    file_put_contents($envFile, rtrim($envContent, "\n")."\nAPP_KEY={$key}\n");
    header('Location: '.$installUri);
    exit;
}

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Boot Laravel (may fail gracefully if database is not yet configured)
|--------------------------------------------------------------------------
*/

try {
    $app = require_once __DIR__.'/../bootstrap/app.php';
    $kernel = $app->make(Kernel::class);
    $response = $kernel->handle(
        $request = Request::capture()
    )->send();
    $kernel->terminate($request, $response);
} catch (\Throwable $e) {
    // If Laravel fails to boot (e.g. database not configured), redirect
    // to the installer instead of showing a 500 error.
    if (strpos($path, '/install') === false) {
        header('Location: '.$installUri);
        exit;
    }

    // If we're already on /install and it still fails, show a friendly error.
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><title>初始化中</title></head>';
    echo '<body style="font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f2f2f7">';
    echo '<div style="text-align:center;padding:40px;background:#fff;border-radius:20px;box-shadow:0 8px 32px rgba(0,0,0,.08)">';
    echo '<h2 style="margin:0 0 12px">系统初始化中</h2>';
    echo '<p style="color:#6b7280;margin:0 0 20px">正在准备安装环境，请刷新页面重试。</p>';
    echo '<a href="/install" style="display:inline-block;padding:10px 24px;background:#111827;color:#fff;border-radius:10px;text-decoration:none;font-weight:600">重新进入安装</a>';
    echo '</div></body></html>';
}
