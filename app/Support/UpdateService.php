<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use ZipArchive;

/**
 * Updates from the latest GitHub release of the public repo:
 *  - source code comes from the release zipball;
 *  - vendor/ comes from the vendor.zip asset, replaced only when composer.lock changed.
 * The migration step runs on the next request so it loads the new code.
 */
class UpdateService
{
    public static function repo(): string
    {
        $repo = trim((string) setting('update_repo', config('cms.repo')));

        return preg_match('#^[\w.-]+/[\w.-]+$#', $repo) ? $repo : '';
    }

    /** @return array{current:string,latest:string,available:bool,notes:string,published_at:?string,url:?string} */
    public static function check(): array
    {
        $repo = self::repo();
        if ($repo === '') {
            throw new RuntimeException(__('Set the public GitHub repository first.'));
        }
        $response = Http::withUserAgent('template-cms')->timeout(20)->acceptJson()->get("https://api.github.com/repos/$repo/releases/latest");
        if (! $response->successful()) {
            throw new RuntimeException(__('Cannot read releases from :repo (HTTP :status).', ['repo' => $repo, 'status' => $response->status()]));
        }
        $latest = ltrim((string) $response->json('tag_name'), 'vV');
        $current = config('cms.version');

        return [
            'current' => $current,
            'latest' => $latest,
            'available' => version_compare($latest, $current, '>'),
            'notes' => (string) $response->json('body'),
            'published_at' => $response->json('published_at'),
            'url' => $response->json('html_url'),
        ];
    }

    public static function run(): string
    {
        @set_time_limit(300);
        $repo = self::repo();
        if ($repo === '') {
            throw new RuntimeException(__('Set the public GitHub repository first.'));
        }
        $release = Http::withUserAgent('template-cms')->timeout(20)->acceptJson()
            ->get("https://api.github.com/repos/$repo/releases/latest")->throw()->json();
        $latest = ltrim((string) ($release['tag_name'] ?? ''), 'vV');
        if (! version_compare($latest, config('cms.version'), '>')) {
            throw new RuntimeException(__('Already on the latest version.'));
        }

        $workspace = storage_path('app/update-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($workspace);
        try {
            BackupService::create(false);

            $sourceZip = $workspace.'/source.zip';
            self::download((string) $release['zipball_url'], $sourceZip);

            $vendorUrl = collect($release['assets'] ?? [])->firstWhere('name', config('cms.vendor_asset'))['browser_download_url'] ?? null;
            $vendorZip = null;
            if ($vendorUrl) {
                $vendorZip = $workspace.'/vendor.zip';
                self::download($vendorUrl, $vendorZip);
            }

            self::apply($sourceZip, $vendorZip, $workspace);
        } finally {
            File::deleteDirectory($workspace);
        }

        return $latest;
    }

    /** Applies downloaded archives. Separate from run() so it can be tested without network access. */
    public static function apply(string $sourceZip, ?string $vendorZip, string $workspace): void
    {
        $sourceDir = $workspace.'/source';
        self::extract($sourceZip, $sourceDir);
        $folders = File::directories($sourceDir);
        $root = count($folders) === 1 && ! file_exists($sourceDir.'/artisan') ? $folders[0] : $sourceDir;
        foreach (['artisan', 'VERSION', 'composer.lock', 'public/index.php'] as $required) {
            if (! file_exists($root.'/'.$required)) {
                throw new RuntimeException(__('The release is not a valid CMS package (missing :file).', ['file' => $required]));
            }
        }

        // vendor is prepared before any file changes, so a bad archive leaves the site untouched.
        $newVendor = null;
        if (self::vendorChanged($root.'/composer.lock')) {
            if (! $vendorZip) {
                throw new RuntimeException(__('Dependencies changed but the release has no :asset.', ['asset' => config('cms.vendor_asset')]));
            }
            self::extract($vendorZip, $workspace.'/vendor');
            $newVendor = $workspace.'/vendor/vendor';
            if (! file_exists($newVendor.'/autoload.php')) {
                throw new RuntimeException(__('The vendor archive is not valid.'));
            }
        }

        self::copyTree($root, base_path());

        if ($newVendor) {
            $old = base_path('vendor.old-'.bin2hex(random_bytes(3)));
            rename(base_path('vendor'), $old);
            rename($newVendor, base_path('vendor'));
            File::deleteDirectory($old);
        }

        foreach (glob(base_path('bootstrap/cache/*.php')) ?: [] as $cached) {
            @unlink($cached);
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    public static function finish(): void
    {
        Artisan::call('migrate', ['--force' => true]);
        // Cached config, routes or views from the old version would hide the new code.
        Artisan::call('optimize:clear');
    }

    private static function vendorChanged(string $newLock): bool
    {
        $installed = trim((string) @file_get_contents(base_path('vendor/.cms-lock')));

        return $installed === '' ? sha1_file($newLock) !== sha1_file(base_path('composer.lock')) : $installed !== sha1_file($newLock);
    }

    private static function download(string $url, string $target): void
    {
        $response = Http::withUserAgent('template-cms')->timeout(280)->withOptions(['sink' => $target])->get($url);
        if (! $response->successful()) {
            throw new RuntimeException(__('Download failed (HTTP :status).', ['status' => $response->status()]));
        }
    }

    private static function extract(string $zipPath, string $target): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException(__('Cannot open the downloaded archive.'));
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if (str_starts_with($name, '/') || str_contains($name, '../')) {
                $zip->close();
                throw new RuntimeException(__('The archive contains an unsafe path.'));
            }
        }
        File::ensureDirectoryExists($target);
        $zip->extractTo($target);
        $zip->close();
    }

    private static function copyTree(string $from, string $to, string $relative = ''): void
    {
        foreach (scandir($from.($relative ? '/'.$relative : '')) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = ltrim($relative.'/'.$item, '/');
            if (in_array($path, config('cms.update_protected'), true)) {
                continue;
            }
            if (is_dir($from.'/'.$path)) {
                File::ensureDirectoryExists($to.'/'.$path);
                self::copyTree($from, $to, $path);
            } else {
                File::copy($from.'/'.$path, $to.'/'.$path);
            }
        }
    }
}
