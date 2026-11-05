<?php

/*
 * Shown when vendor/ is missing. No Laravel is available here, so this is plain PHP.
 * The source is fixed: vendor.zip beside the app, or the release asset of the repo
 * in cms.json. Nothing from the request decides what gets downloaded.
 */

$root = dirname(__DIR__);
$config = json_decode((string) @file_get_contents($root.'/cms.json'), true) ?: [];
$repo = (string) ($config['repo'] ?? '');
$asset = (string) ($config['vendor_asset'] ?? 'vendor.zip');
$localZip = $root.'/vendor.zip';
$lang = (($_GET['lang'] ?? '') === 'en') ? 'en' : 'id';

$text = [
    'id' => [
        'title' => 'Pasang dependensi',
        'intro' => 'Folder vendor belum ada. Unduh sekali dari rilis repo publik, setelah itu wizard instalasi akan dibuka.',
        'source' => 'Sumber',
        'local' => 'vendor.zip di folder aplikasi',
        'install' => 'Pasang sekarang',
        'manual' => 'Jika server tidak bisa mengunduh: taruh vendor.zip di folder utama aplikasi lalu muat ulang halaman ini.',
        'norepo' => 'Repo belum diatur. Isi "repo" di cms.json (contoh: username/template-cms), atau taruh vendor.zip di folder utama aplikasi.',
        'nozip' => 'Ekstensi PHP zip tidak aktif. Aktifkan lewat panel hosting.',
        'fail' => 'Gagal',
    ],
    'en' => [
        'title' => 'Install dependencies',
        'intro' => 'The vendor folder is missing. Download it once from the public repo release, then the install wizard opens.',
        'source' => 'Source',
        'local' => 'vendor.zip in the app folder',
        'install' => 'Install now',
        'manual' => 'If the server cannot download: put vendor.zip in the app root folder and reload this page.',
        'norepo' => 'Repo is not set. Fill "repo" in cms.json (example: username/template-cms), or put vendor.zip in the app root folder.',
        'nozip' => 'The PHP zip extension is disabled. Enable it from your hosting panel.',
        'fail' => 'Failed',
    ],
][$lang];

function cms_vendor_fetch(string $url, ?string $saveTo = null): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $file = $saveTo ? fopen($saveTo, 'wb') : null;
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 280,
            CURLOPT_USERAGENT => 'template-cms',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $file ? curl_setopt($ch, CURLOPT_FILE, $file) : curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($file) {
            fclose($file);
        }
        if ($body === false || $status >= 400) {
            throw new RuntimeException($status ? "HTTP $status: $url" : curl_error($ch));
        }

        return $saveTo ? null : $body;
    }

    if (! ini_get('allow_url_fopen')) {
        throw new RuntimeException('Needs the curl extension or allow_url_fopen.');
    }
    $context = stream_context_create(['http' => ['user_agent' => 'template-cms', 'timeout' => 280]]);
    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        throw new RuntimeException("Cannot download: $url");
    }
    if ($saveTo) {
        file_put_contents($saveTo, $body);

        return null;
    }

    return $body;
}

function cms_vendor_extract(string $zipPath, string $root): void
{
    $zip = new ZipArchive;
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('vendor.zip cannot be opened.');
    }
    $hasAutoload = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
        if (! str_starts_with($name, 'vendor/') || str_contains($name, '../')) {
            $zip->close();
            throw new RuntimeException('vendor.zip has an unexpected path: '.$name);
        }
        $hasAutoload = $hasAutoload || $name === 'vendor/autoload.php';
    }
    if (! $hasAutoload) {
        $zip->close();
        throw new RuntimeException('vendor.zip has no vendor/autoload.php.');
    }
    if (! $zip->extractTo($root)) {
        $zip->close();
        throw new RuntimeException('Cannot write the vendor folder. Check folder permissions.');
    }
    $zip->close();
}

$error = null;
$canInstall = class_exists('ZipArchive') && (file_exists($localZip) || $repo !== '');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $canInstall) {
    @set_time_limit(300);
    try {
        if (file_exists($localZip)) {
            cms_vendor_extract($localZip, $root);
            @unlink($localZip);
        } else {
            if (! preg_match('#^[\w.-]+/[\w.-]+$#', $repo)) {
                throw new RuntimeException('Invalid repo in cms.json.');
            }
            $release = json_decode((string) cms_vendor_fetch("https://api.github.com/repos/$repo/releases/latest"), true);
            $url = null;
            foreach ($release['assets'] ?? [] as $item) {
                if (($item['name'] ?? '') === $asset) {
                    $url = $item['browser_download_url'];
                }
            }
            if (! $url) {
                throw new RuntimeException("The latest release of $repo has no $asset asset.");
            }
            $tmp = $root.'/storage/framework/vendor-download.zip';
            cms_vendor_fetch($url, $tmp);
            cms_vendor_extract($tmp, $root);
            @unlink($tmp);
        }
        header('Location: '.strtok($_SERVER['REQUEST_URI'] ?? '/', '?'));
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$e = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
http_response_code(503);
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($text['title']) ?></title>
<style>
:root{--bg:#fff;--fg:#0a0a0a;--muted:#737373;--border:#e5e5e5;--primary:#171717;--primary-fg:#fafafa;--danger:#dc2626}
@media (prefers-color-scheme:dark){:root{--bg:#0a0a0a;--fg:#fafafa;--muted:#a3a3a3;--border:#262626;--primary:#fafafa;--primary-fg:#171717;--danger:#f87171}}
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:16px;background:var(--bg);color:var(--fg);font:14px/1.5 ui-sans-serif,system-ui,sans-serif}
main{width:100%;max-width:440px;border:1px solid var(--border);border-radius:12px;padding:24px}
h1{font-size:18px;margin:0 0 6px}p{margin:0 0 14px;color:var(--muted)}code{font-size:13px;color:var(--fg)}
button{width:100%;height:36px;border:0;border-radius:8px;background:var(--primary);color:var(--primary-fg);font-weight:500;cursor:pointer}
.err{color:var(--danger)}.lang{display:block;margin-top:14px;text-align:center;color:var(--muted);font-size:13px}
</style>
</head>
<body>
<main>
    <h1><?= $e($text['title']) ?></h1>
    <p><?= $e($text['intro']) ?></p>
    <?php if ($error): ?><p class="err" role="alert"><?= $e($text['fail'].': '.$error) ?></p><?php endif; ?>
    <?php if (! class_exists('ZipArchive')): ?>
        <p class="err"><?= $e($text['nozip']) ?></p>
    <?php elseif (! $canInstall): ?>
        <p class="err"><?= $e($text['norepo']) ?></p>
    <?php else: ?>
        <p><?= $e($text['source']) ?>: <code><?= $e(file_exists($localZip) ? $text['local'] : $repo) ?></code></p>
        <form method="post"><button type="submit"><?= $e($text['install']) ?></button></form>
        <p style="margin-top:14px;font-size:13px"><?= $e($text['manual']) ?></p>
    <?php endif; ?>
    <a class="lang" href="?lang=<?= $lang === 'id' ? 'en' : 'id' ?>"><?= $lang === 'id' ? 'English' : 'Bahasa Indonesia' ?></a>
</main>
</body>
</html>
