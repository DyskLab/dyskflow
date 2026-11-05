<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/** Builds the admin breadcrumb from the route name, so every page gets one without extra code. */
class Breadcrumbs
{
    public static function current(?string $title): array
    {
        $name = (string) Route::currentRouteName();
        if ($name === 'admin.dashboard') {
            return [['label' => __('Dashboard'), 'url' => null]];
        }

        $crumbs = [['label' => __('Dashboard'), 'url' => route('admin.dashboard')]];
        $parts = str_starts_with($name, 'admin.') ? explode('.', substr($name, 6)) : [];
        $prefix = 'admin.';

        if (($parts[0] ?? '') === 'settings') {
            if ($name === 'admin.settings.index') {
                return [...$crumbs, ['label' => __('Settings'), 'url' => null]];
            }
            $crumbs[] = ['label' => __('Settings'), 'url' => route('admin.settings.index')];
            array_shift($parts);
            $prefix = 'admin.settings.';
        }

        // A screen below a list links back to that list.
        $section = $parts[0] ?? '';
        $label = self::sections()[$section] ?? null;
        if ($label && end($parts) !== 'index' && Route::has($prefix.$section.'.index')) {
            $crumbs[] = ['label' => $label, 'url' => route($prefix.$section.'.index')];
        }

        $crumbs[] = ['label' => $title ?: ($label ?? ''), 'url' => null];

        return $crumbs;
    }

    private static function sections(): array
    {
        return [
            'articles' => __('Articles'),
            'pages' => __('Pages'),
            'media' => __('Media'),
            'comments' => __('Comments'),
            'categories' => __('Categories'),
            'tags' => __('Tags'),
            'users' => __('Users'),
            'roles' => __('Roles'),
            'plugins' => __('Plugins'),
            'logs' => __('Activity log'),
            'backups' => __('Backup'),
            'tools' => __('Import & Export'),
            'update' => __('Update'),
            'notifications' => __('Notifications'),
        ];
    }
}
