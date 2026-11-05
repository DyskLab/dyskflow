<?php

/*
 * Plain PHP, loaded before Composer's autoloader. Keeps the "upload and open"
 * flow working on hosting without a terminal:
 *   1. Makes URLs correct when the root .htaccess forwards requests to /public.
 *   2. Creates .env with a fresh APP_KEY.
 *   3. Hands over to the vendor installer when vendor/ is missing.
 */

$cmsRoot = dirname(__DIR__);

foreach (['SCRIPT_NAME', 'PHP_SELF'] as $serverKey) {
    if (isset($_SERVER[$serverKey])) {
        $_SERVER[$serverKey] = preg_replace('#/public/index\.php$#', '/index.php', $_SERVER[$serverKey]);
    }
}

if (! file_exists($cmsRoot.'/.env') && file_exists($cmsRoot.'/.env.example')) {
    $key = 'base64:'.base64_encode(random_bytes(32));
    $env = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.$key, file_get_contents($cmsRoot.'/.env.example'));
    if (@file_put_contents($cmsRoot.'/.env', $env) !== false) {
        @chmod($cmsRoot.'/.env', 0600);
    }
}

if (! file_exists($cmsRoot.'/vendor/autoload.php')) {
    require __DIR__.'/vendor-installer.php';
    exit;
}

unset($cmsRoot, $serverKey, $key, $env);
