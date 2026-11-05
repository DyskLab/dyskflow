<?php

use App\Models\ActivityLog;
use App\Models\Post;
use App\Models\Setting;
use App\Support\Hooks;
use App\Support\Landing;
use App\Support\Locales;
use App\Support\Plugins;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

function cms_installed(): bool
{
    return file_exists(storage_path('app/.installed'));
}

function setting(string $key, mixed $default = null): mixed
{
    return Setting::read($key, $default);
}

function add_action(string $name, callable $callback, int $priority = 10): void
{
    Hooks::add($name, $callback, $priority);
}

function add_filter(string $name, callable $callback, int $priority = 10): void
{
    Hooks::add($name, $callback, $priority);
}

function do_action(string $name, mixed ...$args): void
{
    Hooks::action($name, ...$args);
}

function apply_filters(string $name, mixed $value, mixed ...$args): mixed
{
    return Hooks::filter($name, $value, ...$args);
}

function plugin_setting(string $slug, string $key, mixed $default = null): mixed
{
    return Plugins::setting($slug, $key, $default);
}

function activity(string $action, ?string $subject = null): void
{
    $user = auth()->user();
    ActivityLog::create([
        'user_id' => $user?->id,
        'user_name' => $user?->name,
        'action' => $action,
        'subject' => $subject ? mb_substr($subject, 0, 250) : null,
        'ip' => request()->ip(),
    ]);
}

/** Dates are stored in UTC and shown in the site's timezone. */
function cms_date(mixed $date, ?string $format = null): string
{
    if (! $date) {
        return '';
    }

    return Carbon::parse($date)
        ->setTimezone(setting('timezone', 'Asia/Jakarta'))
        ->format($format ?: setting('date_format', 'd M Y H:i'));
}

/** Latest articles in the language of this request. */
function cms_posts(int $limit = 3, ?string $category = null): Collection
{
    $locale = app()->getLocale();

    return Post::where('type', 'article')->liveIn($locale)
        ->when($category, fn ($q) => $q->whereHas('categories', fn ($t) => $t->where('slug', $category)))
        ->with('categories')
        ->orderByDesc('published_at')
        ->limit($limit)
        ->get()
        ->each->localize($locale);
}

/** A public page in the language of this request. */
function site_route(string $name, mixed $parameters = []): string
{
    $locale = app()->getLocale();

    return route(Locales::isDefault($locale) ? $name : "$locale.$name", $parameters);
}

function landing_asset(string $path): string
{
    return Landing::assetUrl($path);
}

function cms_asset_url(?string $path): ?string
{
    if (! $path) {
        return null;
    }

    return preg_match('#^https?://#i', $path) ? $path : asset(ltrim($path, '/'));
}

/** Plain-text summary of editor HTML. Block tags separate words, so they become spaces first. */
function cms_excerpt(?string $html, int $limit = 160): string
{
    $spaced = preg_replace('#</?(?:p|br|hr|div|h[1-6]|li|ul|ol|blockquote|pre|figure|figcaption|table|thead|tbody|tr|th|td)\b[^>]*>#i', ' ', (string) $html);

    return str(html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8'))->squish()->limit($limit)->toString();
}

function icon(string $name, int $size = 16): HtmlString
{
    $paths = [
        'dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
        'article' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v5h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/>',
        'page' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v5h6"/>',
        'landing' => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/>',
        'folder' => '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
        'tag' => '<path d="M12.6 2.6A2 2 0 0 0 11.2 2H4a2 2 0 0 0-2 2v7.2a2 2 0 0 0 .6 1.4l8.7 8.7a2.4 2.4 0 0 0 3.4 0l6.6-6.6a2.4 2.4 0 0 0 0-3.4Z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
        'comment' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/>',
        'image' => '<rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3-3a2 2 0 0 0-3 0L6 21"/>',
        'settings' => '<path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
        'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'backup' => '<rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/>',
        'transfer' => '<path d="m16 3 4 4-4 4"/><path d="M20 7H4"/><path d="m8 21-4-4 4-4"/><path d="M4 17h16"/>',
        'refresh' => '<path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M8 16H3v5"/>',
        'globe' => '<circle cx="12" cy="12" r="10"/><path d="M12 2a15 15 0 0 0 0 20 15 15 0 0 0 0-20"/><path d="M2 12h20"/>',
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.300-1.300a2 2 0 0 1 2.100-.4c.9.3 1.800.6 2.800.7a2 2 0 0 1 1.700 2Z"/>',
        'mail' => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.300-4.300"/>',
        'plug' => '<path d="M12 22v-5"/><path d="M9 8V2"/><path d="M15 8V2"/><path d="M18 8v5a4 4 0 0 1-4 4h-4a4 4 0 0 1-4-4V8Z"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.900 4.900 1.400 1.400"/><path d="m17.700 17.700 1.400 1.400"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.300 17.700-1.400 1.400"/><path d="m19.100 4.900-1.400 1.400"/>',
        'moon' => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        'panel' => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 3v18"/>',
        'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.300 21a1.900 1.900 0 0 0 3.400 0"/>',
        'alert' => '<path d="m21.700 18-8-14a2 2 0 0 0-3.500 0l-8 14a2 2 0 0 0 1.800 3h16a2 2 0 0 0 1.700-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'shield' => '<path d="M20 13c0 5-3.500 7.500-8 9-4.500-1.500-8-4-8-9V5l8-3 8 3Z"/>',
        'menu' => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'plus' => '<path d="M5 12h14"/><path d="M12 5v14"/>',
        'pencil' => '<path d="M21.200 6.800a2.800 2.800 0 0 0-4-4L3.800 16.200a2 2 0 0 0-.5.800l-1.300 4.400a.5.5 0 0 0 .6.6l4.400-1.300a2 2 0 0 0 .8-.5Z"/><path d="m15 5 4 4"/>',
        'trash' => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'ban' => '<circle cx="12" cy="12" r="10"/><path d="m4.900 4.900 14.200 14.200"/>',
        'left' => '<path d="m15 18-6-6 6-6"/>',
        'right' => '<path d="m9 18 6-6-6-6"/>',
        'up' => '<path d="m18 15-6-6-6 6"/>',
        'down' => '<path d="m6 9 6 6 6-6"/>',
        'sort' => '<path d="m7 15 5 5 5-5"/><path d="m7 9 5-5 5 5"/>',
        'external' => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>',
        'restore' => '<path d="M3 12a9 9 0 1 0 3-6.700L3 8"/><path d="M3 3v5h5"/>',
        'bold' => '<path d="M6 12h9a4 4 0 0 1 0 8H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h7a4 4 0 0 1 0 8"/>',
        'italic' => '<path d="M19 4h-9"/><path d="M14 20H5"/><path d="M15 4 9 20"/>',
        'list' => '<path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/>',
        'ordered' => '<path d="M10 6h11"/><path d="M10 12h11"/><path d="M10 18h11"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.500-2-1"/>',
        'quote' => '<path d="M16 3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2 1 1 0 0 1 1 1v1a2 2 0 0 1-2 2 1 1 0 0 0-1 1v2a1 1 0 0 0 1 1 6 6 0 0 0 6-6V5a2 2 0 0 0-2-2Z"/><path d="M5 3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2 1 1 0 0 1 1 1v1a2 2 0 0 1-2 2 1 1 0 0 0-1 1v2a1 1 0 0 0 1 1 6 6 0 0 0 6-6V5a2 2 0 0 0-2-2Z"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.500.5l3-3a5 5 0 0 0-7-7l-1.700 1.700"/><path d="M14 11a5 5 0 0 0-7.500-.5l-3 3a5 5 0 0 0 7 7l1.700-1.700"/>',
        'code' => '<path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/>',
        'languages' => '<path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/>',
    ];

    $svg = $paths[$name] ?? '';

    return new HtmlString('<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$svg.'</svg>');
}
