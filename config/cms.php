<?php

$manifest = json_decode((string) @file_get_contents(base_path('cms.json')), true) ?: [];

return [
    'version' => trim((string) @file_get_contents(base_path('VERSION'))) ?: '0.0.0',
    'repo' => $manifest['repo'] ?? '',
    'vendor_asset' => $manifest['vendor_asset'] ?? 'vendor.zip',

    'capabilities' => [
        'articles.write', 'articles.manage', 'pages.manage', 'terms.manage',
        'comments.manage', 'media.manage', 'settings.manage', 'users.manage',
        'logs.view', 'backups.manage', 'tools.manage', 'updates.manage', 'plugins.manage',
        'appearance.manage', 'code.manage', 'forms.manage',
    ],

    'default_roles' => [
        'administrator' => ['name' => 'Administrator', 'capabilities' => '*'],
        'editor' => ['name' => 'Editor', 'capabilities' => ['articles.write', 'articles.manage', 'pages.manage', 'terms.manage', 'comments.manage', 'forms.manage', 'media.manage']],
        'author' => ['name' => 'Author', 'capabilities' => ['articles.write', 'media.manage']],
    ],

    // Order matters: parents come before the rows that reference them.
    'backup_tables' => ['settings', 'roles', 'users', 'plugins', 'media', 'terms', 'term_translations', 'posts', 'post_translations', 'post_term', 'comments', 'activity_logs', 'file_revisions'],

    // Files and folders an update must never overwrite.
    'update_protected' => ['.env', '.git', '.github', 'storage', 'vendor', 'landing', 'plugins', 'theme', 'public/uploads', 'bootstrap/cache', 'database/database.sqlite', 'cms.json'],

    // Folders the design tools may read and write. Updates never overwrite these.
    'design_roots' => [
        'theme' => base_path('theme'),
        'landing' => base_path('landing'),
        'plugins' => base_path('plugins'),
    ],

    // false ignores everything in theme/, to recover from a broken override.
    'theme_overrides' => env('CMS_THEME_OVERRIDES', true),

    // Editing PHP or Blade gives the same access as the server itself. false turns off
    // PHP, Blade and SVG in Design > Files and Theme for everyone, administrators included.
    'code_editor' => env('CMS_CODE_EDITOR', true),

    // A page cannot use a slug that belongs to a system route.
    'reserved_slugs' => ['admin', 'install', 'blog', 'category', 'tag', 'search', 'feed', 'sitemap.xml', 'robots.txt', '_landing', '_theme', 'locale', 'uploads', 'css', 'js', 'up', 'en', 'id'],

    'upload_blocked' => ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'htaccess', 'htm', 'html', 'svg', 'js', 'sh', 'cgi', 'pl', 'py', 'exe'],
    'upload_choices' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'zip', 'mp3', 'm4a', 'ogg', 'wav', 'mp4', 'webm'],
];
