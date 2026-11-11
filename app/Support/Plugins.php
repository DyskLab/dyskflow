<?php

namespace App\Support;

use App\Models\Plugin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Throwable;

/** Discovers plugins in plugins/<slug>/ and loads the active ones on each request. */
class Plugins
{
    private static ?array $states = null;

    public static function directory(string $slug): ?string
    {
        if (! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug)) {
            return null;
        }
        $path = base_path('plugins/'.$slug);

        return file_exists($path.'/plugin.json') ? $path : null;
    }

    public static function manifest(string $slug): ?array
    {
        $directory = self::directory($slug);
        if (! $directory) {
            return null;
        }
        $manifest = json_decode((string) file_get_contents($directory.'/plugin.json'), true);

        return is_array($manifest) ? $manifest + ['slug' => $slug, 'settings' => []] : null;
    }

    public static function all(): Collection
    {
        $states = self::states();

        return collect(glob(base_path('plugins/*'), GLOB_ONLYDIR) ?: [])
            ->map(fn ($path) => self::manifest(basename($path)))
            ->filter()
            ->map(fn ($m) => [
                'slug' => $m['slug'],
                'name' => $m['name'] ?? $m['slug'],
                'description' => $m['description'] ?? '',
                'version' => $m['version'] ?? '',
                'author' => $m['author'] ?? '',
                'settings' => $m['settings'],
                'settings_url' => $m['settings_url'] ?? null,
                'active' => (bool) ($states[$m['slug']]->active ?? false),
                'status' => ($states[$m['slug']]->active ?? false) ? 'active' : 'inactive',
            ])
            ->values();
    }

    /** A plugin that fails on load is switched off so it cannot take the whole site down. */
    public static function boot(): void
    {
        foreach (self::states() as $slug => $state) {
            if (! $state->active) {
                continue;
            }
            $directory = self::directory($slug);
            if (! $directory || ! file_exists($directory.'/plugin.php')) {
                continue;
            }
            try {
                View::addNamespace($slug, $directory.'/views');
                // The plugin's own translations, kept in its folder so updates never lose them.
                if (is_dir($directory.'/lang')) {
                    Translations::add($directory.'/lang');
                }
                require_once $directory.'/plugin.php';
            } catch (Throwable $e) {
                Log::error("Plugin [$slug] disabled after an error: ".$e->getMessage());
                self::setActive($slug, false);
            }
        }
    }

    public static function setActive(string $slug, bool $active): void
    {
        $directory = self::directory($slug);
        if (! $directory) {
            return;
        }
        if ($active && is_dir($directory.'/migrations')) {
            Artisan::call('migrate', ['--path' => $directory.'/migrations', '--realpath' => true, '--force' => true]);
        }
        Plugin::query()->updateOrCreate(['slug' => $slug], ['active' => $active]);
        self::$states = null;
    }

    public static function setting(string $slug, string $key, mixed $default = null): mixed
    {
        return self::states()[$slug]->settings[$key] ?? $default;
    }

    public static function saveSettings(string $slug, array $values): void
    {
        Plugin::query()->updateOrCreate(['slug' => $slug], ['settings' => $values]);
        self::$states = null;
    }

    private static function states(): array
    {
        return self::$states ??= Plugin::query()->get()->keyBy('slug')->all();
    }
}
