<?php

namespace App\Support;

use App\Models\Comment;

/** The sidebar and settings cards. The global search reads the same lists, so it never shows more than the user can open. */
class AdminMenu
{
    /** Plugins add items through the cms.admin_menu filter. Items without a group go under Plugins. */
    public static function items(): array
    {
        $user = auth()->user();
        $content = __('Content');
        $taxonomy = __('Taxonomy');
        $access = __('Access');

        return apply_filters('cms.admin_menu', array_values(array_filter([
            ['label' => __('Dashboard'), 'icon' => 'dashboard', 'url' => route('admin.dashboard'), 'match' => 'admin', 'group' => ''],
            $user->can('articles.write') ? ['label' => __('Articles'), 'icon' => 'article', 'url' => route('admin.articles.index'), 'match' => 'admin/articles*', 'group' => $content] : null,
            $user->can('pages.manage') ? ['label' => __('Pages'), 'icon' => 'page', 'url' => route('admin.pages.index'), 'match' => 'admin/pages*', 'group' => $content] : null,
            $user->can('media.manage') ? ['label' => __('Media'), 'icon' => 'image', 'url' => route('admin.media.index'), 'match' => 'admin/media*', 'group' => $content] : null,
            $user->can('comments.manage') ? ['label' => __('Comments'), 'icon' => 'comment', 'url' => route('admin.comments.index'), 'match' => 'admin/comments*', 'group' => $content, 'count' => Comment::where('status', 'pending')->count()] : null,
            $user->can('terms.manage') ? ['label' => __('Categories'), 'icon' => 'folder', 'url' => route('admin.categories.index'), 'match' => 'admin/categories*', 'group' => $taxonomy] : null,
            $user->can('terms.manage') ? ['label' => __('Tags'), 'icon' => 'tag', 'url' => route('admin.tags.index'), 'match' => 'admin/tags*', 'group' => $taxonomy] : null,
            $user->can('users.manage') ? ['label' => __('Users'), 'icon' => 'users', 'url' => route('admin.users.index'), 'match' => 'admin/users*', 'group' => $access] : null,
            $user->can('users.manage') ? ['label' => __('Roles'), 'icon' => 'shield', 'url' => route('admin.roles.index'), 'match' => 'admin/roles*', 'group' => $access] : null,
        ])));
    }

    /** @return array<string, array> items by group label, in the order the groups first appear */
    public static function groups(): array
    {
        $groups = [];
        foreach (self::items() as $item) {
            $groups[$item['group'] ?? __('Plugins')][] = $item;
        }

        return $groups;
    }

    /** Ordered from the site itself, through content, to system maintenance. */
    public static function settingsCards(): array
    {
        $user = auth()->user();
        $sections = SettingsSchema::sections();
        $section = fn (string $key) => [
            'title' => $sections[$key]['title'], 'description' => $sections[$key]['description'],
            'icon' => $sections[$key]['icon'], 'url' => route('admin.settings.edit', $key), 'can' => 'settings.manage',
        ];

        $cards = [
            $section('general'), $section('contact'), $section('email'), $section('seo'), $section('media'), $section('comments'), $section('pages'),
            ['title' => __('Plugins'), 'description' => __('Turn plugins on or off and configure them.'), 'icon' => 'plug', 'url' => route('admin.settings.plugins.index'), 'can' => 'plugins.manage'],
            ['title' => __('Activity log'), 'description' => __('Who changed what, and when.'), 'icon' => 'activity', 'url' => route('admin.settings.logs.index'), 'can' => 'logs.view'],
            ['title' => __('Backup'), 'description' => __('Create, download and restore backups.'), 'icon' => 'backup', 'url' => route('admin.settings.backups.index'), 'can' => 'backups.manage'],
            ['title' => __('Import & Export'), 'description' => __('Move content as a JSON file.'), 'icon' => 'transfer', 'url' => route('admin.settings.tools.index'), 'can' => 'tools.manage'],
            ['title' => __('Update'), 'description' => __('Update the CMS from your public repository.'), 'icon' => 'refresh', 'url' => route('admin.settings.update.index'), 'can' => 'updates.manage'],
        ];

        return apply_filters('cms.settings_cards', array_values(array_filter($cards, fn ($card) => $user->can($card['can']))));
    }
}
