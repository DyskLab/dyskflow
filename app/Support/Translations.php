<?php

namespace App\Support;

/**
 * lang/id.json is replaced by every update, so landing pages, the theme and plugins keep their
 * own translations in a lang/ folder inside theirs, which updates never touch. Plugins are added
 * by Plugins::boot, only while they are on. A key the core translates keeps the core's text.
 */
class Translations
{
    public static function register(): void
    {
        $roots = config('cms.design_roots', []);
        $folders = array_merge(
            isset($roots['landing']) ? (glob($roots['landing'].'/*/lang', GLOB_ONLYDIR) ?: []) : [],
            isset($roots['theme']) && is_dir($roots['theme'].'/lang') ? [$roots['theme'].'/lang'] : [],
        );
        foreach ($folders as $folder) {
            self::add($folder);
        }
    }

    public static function add(string $folder): void
    {
        app('translator')->addJsonPath($folder);
    }
}
