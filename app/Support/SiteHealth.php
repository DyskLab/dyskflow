<?php

namespace App\Support;

use App\Models\AdminNotification;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Checks the install can make on its own, without a terminal. Nothing here changes anything. */
class SiteHealth
{
    public const CRITICAL = 'critical';

    public const WARNING = 'warning';

    public const GOOD = 'good';

    private const REQUIRED_EXTENSIONS = ['mbstring', 'openssl', 'fileinfo', 'dom', 'zip'];

    private const OPTIONAL_EXTENSIONS = ['gd', 'curl'];

    /** @return list<array{status: string, title: string, message: string, action: ?array}> Worst first. */
    public static function checks(): array
    {
        $checks = [
            fn () => self::php(),
            fn () => self::extensions(),
            fn () => self::database(),
            fn () => self::migrations(),
            fn () => self::debug(),
            fn () => self::https(),
            fn () => self::siteUrl(),
            fn () => self::writable(),
            fn () => self::disk(),
            fn () => self::uploadLimit(),
            fn () => self::backup(),
            fn () => self::updates(),
            fn () => self::errors(),
            fn () => self::totp(),
            fn () => self::email(),
            fn () => self::searchEngines(),
            fn () => self::privacyPage(),
        ];

        $results = array_map(function ($check) {
            try {
                return $check();
            } catch (Throwable $e) {
                return self::result(self::CRITICAL, __('A check could not run'), str($e->getMessage())->limit(200)->toString());
            }
        }, $checks);

        $order = [self::CRITICAL => 0, self::WARNING => 1, self::GOOD => 2];
        usort($results, fn ($a, $b) => $order[$a['status']] <=> $order[$b['status']]);

        return $results;
    }

    private static function result(string $status, string $title, string $message, ?string $actionLabel = null, ?string $actionUrl = null): array
    {
        return [
            'status' => $status,
            'title' => $title,
            'message' => $message,
            'action' => $actionLabel ? ['label' => $actionLabel, 'url' => $actionUrl] : null,
        ];
    }

    private static function php(): array
    {
        return version_compare(PHP_VERSION, '8.3.0', '>=')
            ? self::result(self::GOOD, __('PHP version'), __('PHP :version meets the requirement of 8.3 or newer.', ['version' => PHP_VERSION]))
            : self::result(self::CRITICAL, __('PHP version'), __('PHP :version is too old. Use PHP 8.3 or newer.', ['version' => PHP_VERSION]));
    }

    private static function extensions(): array
    {
        $database = DB::getDriverName() === 'sqlite' ? 'pdo_sqlite' : 'pdo_mysql';
        $missing = array_values(array_filter([$database, ...self::REQUIRED_EXTENSIONS], fn ($name) => ! extension_loaded($name)));
        if ($missing) {
            return self::result(self::CRITICAL, __('PHP extensions'), __('Missing: :list.', ['list' => implode(', ', $missing)]));
        }

        $optional = array_values(array_filter(self::OPTIONAL_EXTENSIONS, fn ($name) => ! extension_loaded($name)));

        return $optional
            ? self::result(self::WARNING, __('PHP extensions'), __('Optional and missing: :list. Images are not resized without gd.', ['list' => implode(', ', $optional)]))
            : self::result(self::GOOD, __('PHP extensions'), __('All required and optional extensions are on.'));
    }

    private static function database(): array
    {
        DB::connection()->getPdo();

        return self::result(self::GOOD, __('Database'), __('Connected to :driver :version.', [
            'driver' => DB::getDriverName(),
            'version' => DB::connection()->getServerVersion(),
        ]));
    }

    private static function migrations(): array
    {
        $migrator = app('migrator');
        $files = array_keys($migrator->getMigrationFiles(database_path('migrations')));
        $pending = array_diff($files, $migrator->getRepository()->getRan());
        if (! $pending) {
            return self::result(self::GOOD, __('Database tables'), __('All tables are up to date.'));
        }

        $canRun = auth()->user()?->can('updates.manage');

        return self::result(
            self::CRITICAL,
            __('Database tables'),
            __(':count database change(s) have not run, so some features may fail.', ['count' => count($pending)]),
            $canRun ? __('Run now') : null,
            $canRun ? route('admin.settings.update.finish') : null,
        );
    }

    private static function debug(): array
    {
        return config('app.debug')
            ? self::result(self::CRITICAL, __('Debug mode'), __('APP_DEBUG is on, so error pages can show visitors secret details. Turn it off in .env.'))
            : self::result(self::GOOD, __('Debug mode'), __('Debug mode is off.'));
    }

    private static function https(): array
    {
        return request()->isSecure()
            ? self::result(self::GOOD, 'HTTPS', __('This page was loaded over HTTPS.'))
            : self::result(self::WARNING, 'HTTPS', __('This page was loaded without HTTPS, so passwords travel unencrypted. Turn on SSL at your host.'));
    }

    private static function siteUrl(): array
    {
        $configured = rtrim((string) config('app.url'), '/');
        $current = request()->getSchemeAndHttpHost().request()->getBaseUrl();

        return $configured === rtrim($current, '/')
            ? self::result(self::GOOD, __('Site address'), __('The saved address matches this page: :url.', ['url' => $configured]))
            : self::result(self::WARNING, __('Site address'), __('The saved address is :saved, but this page is at :current. Links in email and tasks may point to the wrong place.', ['saved' => $configured, 'current' => $current]));
    }

    private static function writable(): array
    {
        $paths = ['storage' => storage_path(), 'bootstrap/cache' => base_path('bootstrap/cache'), 'public/uploads' => public_path('uploads'), __('main folder') => base_path()];
        $blocked = array_keys(array_filter($paths, fn ($path) => ! is_writable($path)));

        return $blocked
            ? self::result(self::CRITICAL, __('Folder permissions'), __('PHP cannot write to: :list. Uploads, backups or updates will fail.', ['list' => implode(', ', $blocked)]))
            : self::result(self::GOOD, __('Folder permissions'), __('PHP can write to every folder it needs.'));
    }

    private static function disk(): array
    {
        $free = @disk_free_space(base_path());
        if ($free === false) {
            return self::result(self::WARNING, __('Disk space'), __('The host does not report free space.'));
        }

        $label = round($free / 1073741824, 1).' GB';
        $status = match (true) {
            $free < 104857600 => self::CRITICAL,
            $free < 524288000 => self::WARNING,
            default => self::GOOD,
        };

        return self::result($status, __('Disk space'), __(':size free.', ['size' => $label]));
    }

    private static function uploadLimit(): array
    {
        $server = (int) floor(min(ini_parse_quantity(ini_get('upload_max_filesize')), ini_parse_quantity(ini_get('post_max_size'))) / 1048576);
        $setting = (int) setting('media_max_mb', 10);

        return $server < $setting
            ? self::result(self::WARNING, __('Upload limit'), __('Media allows :setting MB, but the server stops uploads at :server MB.', ['setting' => $setting, 'server' => $server]), __('Media settings'), route('admin.settings.edit', 'media'))
            : self::result(self::GOOD, __('Upload limit'), __('The server accepts uploads up to :server MB.', ['server' => $server]));
    }

    private static function backup(): array
    {
        $latest = BackupService::all()->max('created');
        $canManage = auth()->user()?->can('backups.manage');
        $url = $canManage ? route('admin.settings.backups.index') : null;
        $label = $canManage ? __('Backup') : null;

        if (! $latest) {
            return self::result(self::WARNING, __('Backup'), __('No backup exists yet.'), $label, $url);
        }

        return $latest < now()->subDays(30)->timestamp
            ? self::result(self::WARNING, __('Backup'), __('The latest backup is from :date.', ['date' => cms_date(now()->setTimestamp($latest))]), $label, $url)
            : self::result(self::GOOD, __('Backup'), __('The latest backup is from :date.', ['date' => cms_date(now()->setTimestamp($latest))]));
    }

    private static function updates(): array
    {
        $canManage = auth()->user()?->can('updates.manage');
        $url = $canManage ? route('admin.settings.update.index', ['check' => 1]) : null;
        $label = $canManage ? __('Update') : null;

        if (UpdateService::repo() === '') {
            return self::result(self::WARNING, __('Update'), __('No repository is set, so this site cannot update itself.'), $label, $url);
        }

        $available = AdminNotification::where('type', 'update')->first();

        return $available
            ? self::result(self::WARNING, __('Update'), __('Version :version is available', $available->params ?? []), $label, $url)
            : self::result(self::GOOD, __('Update'), __('Version :version from :repo, with no newer version found at the last check.', ['version' => config('cms.version'), 'repo' => UpdateService::repo()]));
    }

    private static function errors(): array
    {
        $count = (int) AdminNotification::where('type', 'error')->where('updated_at', '>=', now()->subDays(7))->sum('count');

        return $count > 0
            ? self::result(self::WARNING, __('Recent errors'), __(':count error(s) in the last 7 days.', ['count' => $count]), __('Notifications'), route('admin.notifications.index', ['f' => ['type' => 'error']]))
            : self::result(self::GOOD, __('Recent errors'), __('No errors in the last 7 days.'));
    }

    private static function totp(): array
    {
        $count = User::where('is_active', true)->whereNull('totp_confirmed_at')->count();
        if ($count === 0) {
            return self::result(self::GOOD, __('Two-step verification'), __('Every active user has it on.'));
        }

        return auth()->user()?->can('users.manage')
            ? self::result(self::WARNING, __('Two-step verification'), __(':count active user(s) have it off.', ['count' => $count]), __('Users'), route('admin.users.index'))
            : self::result(self::WARNING, __('Two-step verification'), __(':count active user(s) have it off.', ['count' => $count]));
    }

    private static function email(): array
    {
        return in_array(config('mail.default'), ['log', 'array'], true)
            ? self::result(self::WARNING, __('Email'), __('Email is only written to a file, not sent.'), __('Email settings'), route('admin.settings.edit', 'email'))
            : self::result(self::GOOD, __('Email'), __('Email is sent with :mailer.', ['mailer' => config('mail.default')]));
    }

    private static function searchEngines(): array
    {
        return setting('seo_noindex')
            ? self::result(self::WARNING, __('Search engines'), __('The whole site is hidden from search engines.'), __('SEO settings'), route('admin.settings.edit', 'seo'))
            : self::result(self::GOOD, __('Search engines'), __('Search engines may index the site.'));
    }

    private static function privacyPage(): array
    {
        $id = setting('privacy_page_id');
        $published = $id && Post::where('type', 'page')->whereKey($id)->live()->exists();

        return $published
            ? self::result(self::GOOD, __('Privacy policy'), __('A published privacy policy is linked in the footer.'))
            : self::result(self::WARNING, __('Privacy policy'), __('No published privacy policy is set.'), __('Privacy guide'), route('admin.settings.privacy.index'));
    }
}
