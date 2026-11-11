<?php

use App\Models\ActivityLog;
use App\Models\Post;
use App\Models\Setting;
use App\Support\Hooks;
use App\Support\Landing;
use App\Support\Locales;
use App\Support\Plugins;
use App\Support\Theme;
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

/** The published privacy policy in the current language, for footers. */
function cms_privacy_page(): ?Post
{
    $id = setting('privacy_page_id');
    if (! $id) {
        return null;
    }

    $locale = app()->getLocale();

    return Post::where('type', 'page')->whereKey($id)->liveIn($locale)->first()?->localize($locale);
}

function landing_asset(string $path): string
{
    return Landing::assetUrl($path);
}

/** A public theme asset, or the core file when the theme has no override. */
function theme_asset(string $path): string
{
    return Theme::assetUrl($path);
}

/** The icon tags for every page: the one chosen in Settings, otherwise the DyskFlow logo. Also runs on the install wizard, before the database exists. */
function cms_favicon(): HtmlString
{
    $custom = cms_installed() ? cms_asset_url(setting('favicon')) : null;

    return new HtmlString($custom
        ? '<link rel="icon" href="'.e($custom).'">'
        : '<link rel="icon" href="'.e(asset('favicon.ico')).'" sizes="32x32"><link rel="icon" type="image/svg+xml" href="'.e(asset('img/logo-mark.svg')).'">');
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

/**
 * The URL of a file in public/ with its modification time, so browsers fetch it again after an
 * update or an edit instead of keeping an old copy (the version number alone does not change).
 */
function asset_v(string $path): string
{
    $file = public_path(ltrim($path, '/'));

    return asset($path).'?v='.(is_file($file) ? filemtime($file) : config('cms.version'));
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
        'lock' => '<rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'heart' => '<path d="M19 14c1.500-1.500 3-3.200 3-5.500A5.500 5.500 0 0 0 16.500 3c-1.800 0-3 .5-4.500 2-1.500-1.500-2.700-2-4.500-2A5.500 5.500 0 0 0 2 8.500c0 2.300 1.500 4 3 5.500l7 7Z"/>',
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
        'play' => '<path d="M6 3v18l15-9Z"/>',
        'arrow-up' => '<path d="m5 12 7-7 7 7"/><path d="M12 19V5"/>',
        'arrow-down' => '<path d="M12 5v14"/><path d="m19 12-7 7-7-7"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'heading' => '<path d="M6 12h12"/><path d="M6 20V4"/><path d="M18 20V4"/>',
        'text' => '<path d="M17 6H3"/><path d="M21 12H3"/><path d="M15 18H3"/>',
        'card' => '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>',
        'video' => '<path d="m16 13 5.200 3.100a.5.5 0 0 0 .8-.4V8.300a.5.5 0 0 0-.8-.4L16 11"/><rect x="2" y="6" width="14" height="12" rx="2"/>',
        'columns' => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M12 3v18"/>',
        'spacer' => '<path d="M12 3v18"/><path d="m8 7 4-4 4 4"/><path d="m8 17 4 4 4-4"/>',
        'divider' => '<path d="M3 12h18"/>',
        'posts' => '<path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.100.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8Z"/>',
        'copy' => '<rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.100 0-2-.9-2-2V4c0-1.100.9-2 2-2h10c1.100 0 2 .9 2 2"/>',
        'palette' => '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.500 2 2 6.500 2 12s4.500 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.640 1.640 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>',
        'star' => '<path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/>',
        'zap' => '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>',
        'rocket' => '<path d="M4.5 16.5c-1.5 1.260-2 5-2 5s3.740-.5 5-2c.710-.840.700-2.130-.090-2.910a2.180 2.180 0 0 0-2.910-.090z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.950A12.880 12.880 0 0 1 22 2c0 2.720-.780 7.500-6 11a22.350 22.350 0 0 1-4 2z"/><path d="M9 12H4s.550-3.030 2-4c1.620-1.080 5 0 5 0"/><path d="M12 15v5s3.030-.550 4-2c1.080-1.620 0-5 0-5"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'chat' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
        'map-pin' => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'calendar' => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        'truck' => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.650a1 1 0 0 0-.220-.624l-3.480-4.350A1 1 0 0 0 17.520 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
        'award' => '<path d="m15.477 12.89 1.515 8.526a.5.5 0 0 1-.81.47l-3.58-2.687a1 1 0 0 0-1.197 0l-3.586 2.686a.5.5 0 0 1-.81-.469l1.514-8.526"/><circle cx="12" cy="8" r="6"/>',
        'gift' => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.500 2.500 0 0 1 0-5A4.800 8 0 0 1 12 8a4.800 8 0 0 1 4.500-5 2.500 2.500 0 0 1 0 5"/>',
        'credit-card' => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
        'cart' => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.050 2.050h2l2.660 12.420a2 2 0 0 0 2 1.580h9.780a2 2 0 0 0 1.950-1.570l1.650-7.430H5.120"/>',
        'home' => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'briefcase' => '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/>',
        'book' => '<path d="M4 19.5v-15A2.500 2.500 0 0 1 6.500 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.500a1 1 0 0 1 0-5H20"/>',
        'coffee' => '<path d="M10 2v2"/><path d="M14 2v2"/><path d="M16 8a1 1 0 0 1 1 1v8a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V9a1 1 0 0 1 1-1h14a4 4 0 1 1 0 8h-1"/><path d="M6 2v2"/>',
        'smile' => '<circle cx="12" cy="12" r="10"/><path d="M8 14s1.500 2 4 2 4-2 4-2"/><line x1="9" x2="9.010" y1="9" y2="9"/><line x1="15" x2="15.010" y1="9" y2="9"/>',
        'target' => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
        'trending-up' => '<polyline points="22 7 13.500 15.500 8.500 10.500 2 17"/><polyline points="16 7 22 7 22 13"/>',
        'thumbs-up' => '<path d="M7 10v12"/><path d="M15 5.880 14 10h5.830a2 2 0 0 1 1.920 2.560l-2.330 8A2 2 0 0 1 17.500 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.760a2 2 0 0 0 1.790-1.110L12 2a3.130 3.130 0 0 1 3 3.880Z"/>',
        'leaf' => '<path d="M11 20A7 7 0 0 1 9.800 6.100C15.500 5 17 4.480 19 2c1 2 2 4.180 2 8 0 5.500-4.780 10-10 10Z"/><path d="M2 21c0-3 1.850-5.360 5.080-6C9.500 14.520 12 13 13 12"/>',
        'instagram' => '<rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.370A4 4 0 1 1 12.630 8 4 4 0 0 1 16 11.370z"/><line x1="17.500" x2="17.510" y1="6.500" y2="6.500"/>',
        'facebook' => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
        'linkedin' => '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect width="4" height="12" x="2" y="9"/><circle cx="4" cy="4" r="2"/>',
        'youtube' => '<path d="M2.500 17a24.120 24.120 0 0 1 0-10 2 2 0 0 1 1.400-1.400 49.560 49.560 0 0 1 16.200 0A2 2 0 0 1 21.500 7a24.120 24.120 0 0 1 0 10 2 2 0 0 1-1.400 1.400 49.550 49.550 0 0 1-16.200 0A2 2 0 0 1 2.500 17"/><path d="m10 15 5-3-5-3z"/>',
        'tiktok' => '<path d="M16 3v1.500A4.500 4.500 0 0 0 20.500 9"/><path d="M16 3h-3.500v12.500a3 3 0 1 1-3-3"/>',
        'whatsapp' => '<path d="M3 21l1.650-3.800a9 9 0 1 1 3.400 2.900L3 21"/><path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1"/>',
        'telegram' => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
        'github' => '<path d="M15 22v-4a4.800 4.800 0 0 0-1-3.500c3 0 6-2 6-5.500.080-1.250-.270-2.480-1-3.500.280-1.150.280-2.350 0-3.500 0 0-1 0-3 1.500-2.640-.500-5.360-.500-8 0C6 2 5 2 5 2c-.300 1.150-.300 2.350 0 3.500A5.403 5.403 0 0 0 4 9c0 3.500 3 5.500 6 5.500-.390.490-.680 1.050-.850 1.650-.170.600-.220 1.230-.150 1.850v4"/><path d="M9 18c-4.510 2-5-2-7-2"/>',
        'x-logo' => '<path d="M4 4l11.733 16h4.267l-11.733-16z"/><path d="M4 20l6.768-6.768m2.460-2.460L20 4"/>',
        'monitor' => '<rect width="20" height="14" x="2" y="3" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/>',
        'tablet' => '<rect width="16" height="20" x="4" y="2" rx="2"/><path d="M12 18h.01"/>',
        'smartphone' => '<rect width="14" height="20" x="5" y="2" rx="2"/><path d="M12 18h.01"/>',
        'undo' => '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>',
        'redo' => '<path d="m15 14 5-5-5-5"/><path d="M20 9H9.5a5.5 5.5 0 0 0 0 11H13"/>',
        'more' => '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>',
        'layers' => '<path d="m12 2 10 5-10 5L2 7Z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/>',
        'languages' => '<path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/>',
    ];

    $svg = $paths[$name] ?? '';

    return new HtmlString('<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$svg.'</svg>');
}
