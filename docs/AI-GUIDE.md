# DyskFlow: building landing pages and plugins

A guide for AI coding tools, and for people working with them. It stands on its own: read it before writing a landing page or plugin. When this guide and the code disagree, the code wins.

## 1. Rules that apply to every task

1. **Stay in your folder.** A landing page lives only in `landing/<slug>/`. A plugin lives only in `plugins/<slug>/`. Do not edit `app/`, `resources/`, `routes/`, `config/`, `public/`, `database/` or `lang/`. Updates overwrite those folders, and they never overwrite `landing/` or `plugins/`.
2. **No build step.** No Node, npm, Vite, Tailwind compile, Sass or TypeScript on the site. Files are served as written. If you must compile something, do it elsewhere and copy the output in.
3. **No new Composer packages.** `vendor/` comes from a release asset and is replaced by updates, so a package added for one plugin disappears. Use what Laravel already provides.
4. **Escape output.** Use `{{ }}` in Blade and `e()` in PHP. HTML that came from an editor goes through `App\Support\HtmlSanitizer::clean()`.
5. **Keep public pages cacheable.** See [Cookies and caching](#cookies-and-caching).

## 2. Stack

| Part | What it is |
| --- | --- |
| Backend | Laravel 13, PHP 8.3+ |
| Database | MySQL or SQLite. Write migrations that work on both |
| Templates | Blade |
| CSS and JS | Plain files. Admin pages load no CDN assets |
| Design tokens | CSS variables in `public/css/ui.css`: shadcn neutral with an orange-red `--primary`. Dark mode is `.dark` on `<html>` |
| Languages | Indonesian and English. One is the default (the `locale` setting, normally `id`) at unprefixed URLs. The other sits under its prefix, such as `/en/...` |
| Tests | `php artisan test`, on in-memory SQLite |
| Hosting | Often shared hosting, with no terminal, queue worker or cron guaranteed |

## 3. Landing page or plugin?

| You need | Use |
| --- | --- |
| A designed page: homepage, promo, product, contact | Landing page |
| Something added to existing pages: a banner, tracking tag, text after articles | Plugin |
| A database table, admin screen, settings form or form handler | Plugin |
| A designed page with a form that saves data | Both: the landing page holds the markup and posts to a plugin route |
| A different look for the blog, articles or pages of the default theme | A theme override: copy the file to `theme/views/site/...` or `theme/assets/...` (see [DESIGN-TOOLS.md](DESIGN-TOOLS.md)) |

A landing page has no backend of its own: no routes, no controller, no migrations. An article or page written in the admin is content, and it cannot be selected as a landing page.

---

## 4. Landing pages

### Structure

```
landing/<slug>/
  index.blade.php    entry file (or index.html); Blade wins if both exist
  landing.json       title, description, public, menu
  assets/            CSS, JS, images, fonts, video
  lang/id.json       optional: translations for __(), see Languages
```

`<slug>` must match `^[a-z0-9][a-z0-9_-]*$`. A folder that breaks this, or that has no entry file, is ignored without any error.

**Builder page or code page?** Builder pages are pages in the admin with their layout as JSON in the database, not folders here. Read and write them with `php artisan builder:export` and `builder:import`, following [BUILDER.md](BUILDER.md). For a new page, prefer a builder page (`builder:import <slug> page.json --create="Title"`) when the design fits sections, columns and the listed widgets, because people can then keep editing it in the admin. Write a code page in `landing/` when it needs its own markup throughout.

Before writing code for a contact or sign-up form, a countdown, tabs, a carousel or similar, check the widget table in [BUILDER.md](BUILDER.md#widgets): the `form` widget already keeps messages in **Content > Forms** without cookies. Use the brand variables (`--primary`, `--brand-secondary`, `--brand-accent`, `--font-heading`, `--font-body`, `--radius`) in your own CSS so the page follows **Design > Colors & fonts**.

Do not use a slug from `reserved_slugs` in `config/cms.php`: `admin`, `install`, `blog`, `category`, `tag`, `search`, `feed`, `sitemap.xml`, `robots.txt`, `_landing`, `locale`, `uploads`, `css`, `js`, `up`, `en`, `id`. The check only runs for admin pages, so a landing page with one of these slugs gets no error but may be unreachable.

### landing.json

```json
{
    "title": "Spring promo",
    "description": "One short sentence for search results.",
    "public": true,
    "menu": false
}
```

| Key | Effect |
| --- | --- |
| `title` | Page title in `<title>`, the menu label, and the option name in Settings. Defaults to the slug |
| `description` | Meta description |
| `public` | Must be exactly `true` for the page to have its own URL and join the sitemap. Otherwise `/slug` is a 404, unless the page is the homepage |
| `menu` | `true` lists it in the site header. Needs `public: true` |

Invalid JSON is treated as empty, without an error, so the title becomes the slug and the page stays private. On `/slug` and `/en/slug`, `title` and `description` go through `__()`. See [Languages](#languages) for what that means in practice.

### Addresses

| Situation | Result |
| --- | --- |
| Selected as homepage in **Settings > Pages** | Served at `/` and `/en`. `public` is not needed. `/slug` redirects to `/` with a 301 |
| `"public": true` | Served at `/slug` and `/en/slug` |
| A page in the admin has the same slug | The admin page wins and the landing page is hidden |
| Neither | `/slug` is a 404, but its files under `/_landing/<slug>/` still load. Keep nothing private in `assets/` |

### index.blade.php

This is the normal choice. It can read CMS data. This skeleton has the shared site header and footer:

**`landing/promo/index.blade.php`**
```blade
@php
    $t = fn (string $id, string $en) => app()->getLocale() === 'en' ? $en : $id;
    $posts = cms_posts(3);
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {!! $seo !!}
    @include('site.partials.theme-script')
    <link rel="stylesheet" href="{{ asset('css/ui.css') }}?v={{ config('cms.version') }}">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ config('cms.version') }}">
    <link rel="stylesheet" href="{{ landing_asset('assets/style.css') }}">
</head>
<body>
    @include('site.partials.header')

    <main class="site-wrap wide site-main" id="main" tabindex="-1">
        <h1 class="site-title">{{ $t('Promo musim semi', 'Spring promo') }}</h1>
        <p class="muted site-lead">{{ $t('Penawaran terbatas.', 'A limited offer.') }}</p>

        @foreach ($posts as $post)
            <a href="{{ $post->url() }}">{{ $post->title }}</a>
            <span class="muted">{{ cms_date($post->published_at, 'd M Y') }}</span>
        @endforeach
    </main>

    @include('site.partials.footer')
    <script src="{{ asset('js/site.js') }}?v={{ config('cms.version') }}"></script>
</body>
</html>
```

**`landing/promo/landing.json`**
```json
{ "title": "Spring promo", "description": "A limited offer.", "public": true }
```

**`landing/promo/assets/style.css`**
```css
.site-title { color: var(--primary-text); }
```

Requirements:

- Print `{!! $seo !!}` inside `<head>`. It holds `<title>`, the meta description, canonical, Open Graph, JSON-LD, `hreflang` and the plugin `cms.head` output.
- Do not write your own `<title>`, meta description, canonical or `hreflang`.
- Use one `<h1>` per page.
- For a page with no shared header, skip the `ui.css`, `site.css`, `site.js`, theme script and partials, as `landing/example/` does. The CSS variables then do not exist, so define your own colors.

Use the Blade `@{{ }}` escape for `{{ }}` that belongs to a JavaScript library. Keep CSS and JS in `assets/` rather than inline.

### Available in a Blade landing page

| Name | Returns |
| --- | --- |
| `$seo` | The SEO tags to print |
| `landing_asset('assets/x.css')` | URL of a file in this folder, with a version that changes with the file |
| `setting('key', $default)` | A setting (list below) |
| `\App\Support\Locales::text('tagline')` | A text setting in the current language. Also works for `seo_description`. Prefer it over `setting()` for these two |
| `cms_posts(3)`, `cms_posts(3, 'category-slug')` | Latest live articles in the current language |
| `cms_privacy_page()` | The published privacy policy page in the current language, or null |
| `site_route('blog')`, `site_route('page', 'slug')`, `site_route('article', 'slug')` | Public URL in the current language. Use this, not `route()` |
| `$post->url()` | URL of an article or page in the current language |
| `cms_date($date, 'd M Y')` | A date in the site's timezone |
| `cms_excerpt($html, 120)` | Plain-text summary of editor HTML |
| `icon('mail', 20)` | Inline SVG. Names are in `icon()` in `app/helpers.php` |
| `app()->getLocale()` | `id` or `en`, set by the URL |

Article fields from `cms_posts()`: `title`, `slug`, `excerpt`, `content`, `featured_image`, `published_at`, `categories`. Show `featured_image` with `cms_asset_url($post->featured_image)`, and show `content` only through `HtmlSanitizer::clean()`. Never save these models: they hold a translation for this request only.

Settings you can read: `site_title`, `tagline`, `favicon`, `contact_email`, `contact_phone`, `contact_whatsapp`, `contact_address`, `contact_map_url`, `social_instagram`, `social_facebook`, `social_x`, `social_linkedin`, `social_youtube`, `social_tiktok`. See `landing/kontak/` for a page built on them.

Do not print `mail_*` settings. They include credentials.

### index.html

Plain HTML with no CMS data and no language logic, so both languages show the same text.

- It must contain `</head>`. The SEO tags are added before it, so write no `<title>` or meta description.
- Only `href="assets/..."` and `src="assets/..."` (also `./assets/`) are rewritten to work. `srcset`, `poster` and paths inside inline JS or `style` are not rewritten, and they break at `/slug`. Use `<img src>` or put the paths in a CSS file: `url()` inside `assets/*.css` is relative to that file and works.

### Assets

Served from `/_landing/<slug>/...` and cached for one week. Allowed extensions: `css`, `js`, `png`, `jpg`, `jpeg`, `webp`, `avif`, `gif`, `svg`, `ico`, `woff`, `woff2`, `ttf`, `mp4`, `webm`. Anything else returns 404, including `pdf`, `json` and `txt`.

In `index.html` the URLs carry no version, so renaming a changed file is the only way to bypass that cache.

### Languages

One folder serves both languages. For body text, use the `$t` closure from the skeleton: the text then lives in the landing folder and survives updates. `landing/dyskflow/` does this.

Do not add strings to the core `lang/id.json`. **Updates overwrite that file**, so anything you add is lost at the next update. Put them in `landing/<slug>/lang/id.json` instead: the CMS loads that file on every request, and updates never touch `landing/`. This is how `title` and `description` in `landing.json` get translated, since they go through `__()`:

```json
{ "Contact us": "Hubungi kami", "Questions about an order? Write to us.": "Ada pertanyaan soal pesanan? Tulis ke kami." }
```

The strings are available on every page, so give them wording of your own. A key the core already translates keeps the core's translation. The theme can do the same with `theme/lang/id.json`.

### Hooks on landing pages

Only `cms.head` runs, through `$seo`. **`cms.body_open` and `cms.footer` do not run on landing pages**, so plugins that print there show nothing. To support them in a Blade landing page, add these yourself:

```blade
@php do_action('cms.body_open'); @endphp   {{-- right after <body> --}}
@php do_action('cms.footer'); @endphp      {{-- right before </body> --}}
```

### Forms

Landing pages have no backend. Post to a plugin route, or to an external service.

Avoid `@csrf` unless the page needs it: a page with a CSRF token sends cookies and can no longer be cached by a CDN. A form posting to an external service needs no token.

### Landing page checklist

- [ ] Only `landing/<slug>/` changed
- [ ] `{!! $seo !!}` is in `<head>`, with no own `<title>`
- [ ] Exactly one `<h1>`
- [ ] Works at phone width and in dark mode (if it uses the shared CSS)
- [ ] `/slug` and `/en/slug` both show the right language
- [ ] All links use `site_route()` or `$post->url()`
- [ ] No request sent for a missing asset, and no asset type outside the allowed list

---

## 5. Plugins

### Structure

```
plugins/<slug>/
  plugin.json       required: name, description, version, author, settings
  plugin.php        loaded on every request while the plugin is on
  views/            optional: view('<slug>::name')
  migrations/       optional: runs when the plugin is turned on
  src/              optional: your classes, loaded with require_once
  lang/             optional: id.json, see Translations
```

`<slug>` follows the same pattern as a landing page. Plugins are off until turned on in **Settings > Plugins**.

### How a plugin loads

1. `AppServiceProvider::boot()` calls `Plugins::boot()` on every web request and artisan command.
2. Each active plugin's `plugin.php` is loaded with `require_once`. Its route, hook and translation registrations run at that moment.
3. If loading throws, the plugin is turned off and the error goes to the log.
4. **An exception inside a hook callback is not caught.** It turns that page into a 500 for every visitor and the plugin stays on. Wrap anything that can fail, such as a network call, in `rescue(fn () => ...)`.

Keep `plugin.php` cheap, because it runs on every request: register things and return. Run queries inside callbacks. There is no autoloader for plugins, so `require_once` your classes. Give them a namespace, and do not declare global functions: two plugins with the same function name would fatally collide.

### plugin.json

```json
{
    "name": "My plugin",
    "description": "What it does, in one sentence.",
    "version": "1.0.0",
    "author": "Your name",
    "settings": [
        { "name": "message", "label": "Message", "type": "text", "default": "Hello" },
        { "name": "enabled", "label": "Show it", "type": "switch", "default": "1" },
        { "name": "style", "label": "Style", "type": "select", "options": { "a": "Plain", "b": "Bold" } }
    ],
    "settings_url": "admin/my-plugin"
}
```

Field types: `text`, `textarea`, `number`, `email`, `url`, `select`, `switch`, `media`. Do not use `secret`, `password` or `checkboxes`: the generic form cannot save them properly. Optional keys: `help`, `placeholder`, `rows`, `maxlength`.

Facts that cause bugs:

- `default` only fills the form. Until someone saves, `plugin_setting()` returns the fallback in your code, so **repeat the default in code**: `plugin_setting('my-plugin', 'message', 'Hello')`.
- `switch` stores the strings `'1'` and `'0'`. Compare with `=== '1'`.
- `required` is only a browser check. Handle empty values in code.
- `name`, `description`, `label` and `options` are printed as written, with no translation.
- Values are stored as plain text in the `plugins` table. Do not offer a field for secrets without telling the user.
- `settings_url` replaces the automatic form while the plugin is on. Leave it out unless you have a custom page.

### Hooks

```php
add_action('cms.head', function () { echo '<meta name="x" content="y">'; });
add_filter('cms.content', fn ($html, $post) => $html.'<p>Thanks.</p>', 20); // optional priority, lower runs first
```

| Hook | Type | Arguments | Runs on |
| --- | --- | --- | --- |
| `cms.head` | action | none | Public theme and landing pages |
| `cms.body_open` | action | none | Public theme only |
| `cms.footer` | action | none | Public theme only |
| `cms.content` | filter | `$html`, `$post` | Article and page body. The result is sanitized afterwards, so `<script>` is removed |
| `post.saved` | action | `$post` | Saving an article or page in the admin |
| `comment.created` | action | `$comment` | A new public comment |
| `cms.admin_menu` | filter | `$items` | Admin sidebar and global search |
| `cms.settings_cards` | filter | `$cards` | Settings page and global search |

Actions print with `echo` and are not sanitized, so escape everything with `e()`. Filters must return the value. To be seen on landing pages, use `cms.head`.

In `cms.content`, `$post` holds the translated text for this request on a second-language page. Read it, never save it.

### Admin menu and permissions

**Core does not filter what plugins add, so check the permission yourself.** Without the check, every admin user sees the item.

```php
add_filter('cms.admin_menu', function ($items) {
    if (auth()->user()->can('plugins.manage')) {
        $items[] = ['label' => __('My plugin'), 'icon' => 'plug', 'url' => url('admin/my-plugin'), 'match' => 'admin/my-plugin*'];
    }

    return $items;
});
```

Item keys: `label`, `icon`, `url`, `match` (a `request()->is()` pattern), optional `group` (default Plugins) and `count`. A settings card has `title`, `description`, `icon`, `url` and `can`, and needs the same check, because `can` is applied before plugin cards are added.

Capabilities are a fixed list in `config/cms.php`: `articles.write`, `articles.manage`, `pages.manage`, `terms.manage`, `comments.manage`, `media.manage`, `settings.manage`, `users.manage`, `logs.view`, `backups.manage`, `tools.manage`, `updates.manage`, `plugins.manage`. A plugin cannot add one without editing core, so reuse the closest one.

### Routes

```php
use Illuminate\Support\Facades\Route;

// Admin
Route::middleware(['web', 'auth', 'can:plugins.manage'])->get('admin/my-plugin', fn () => view('my-plugin::index'));

// Public form handler: web gives it CSRF protection and a session
Route::middleware(['web', 'throttle:5,1'])->post('my-plugin/subscribe', [MyController::class, 'store']);
```

- **Always include `web`.** Plugin routes are outside the core route group, so without it there is no session, login or CSRF check.
- **Plugin routes are registered before core routes**, so a plugin path beats the same core path, and also beats a page or landing page at that URL. Prefix paths with your slug.
- Name admin routes `admin.<slug>.<action>`. The breadcrumb is built from the route name and the page's `$title`, so there is nothing else to do. Do not add back links.
- Public routes do not get `/en` automatically. For a page that shows text, add `\App\Http\Middleware\SiteLocale::class` and register a second route under the prefix with `SiteLocale::class.':en'`. For a full designed page, a landing page is usually simpler.

### Admin screens

Follow the same patterns as core, because users expect it:

| Need | Use |
| --- | --- |
| Any table | `App\Support\DataTable`, shown with `view('admin.table')`. Never hand-write a table |
| Add or edit form | A right sheet: an action with `sheet`, returning `view('admin.partials.sheet-form')` |
| Confirm before delete | `confirm` on the action. Never `window.confirm`, and no other modals |
| Full custom page | A view with `@extends('admin.layout')` and `@section('content')`. Pass `title` |
| Colors | CSS variables from `ui.css`, such as `var(--primary)`, `var(--muted-foreground)`, and existing classes such as `.btn`, `.card`, `.badge` |
| Change log | `activity('my-plugin.saved', $name)` |
| Dates | Store UTC, show with `cms_date()` |

`DataTable` needs an Eloquent query or a collection. `DB::table()` throws a `TypeError`, so make a small model.

Admin notifications go through `Notifier`. Pass the title in English, without `__()`: it is translated when shown.

```php
\App\Support\Notifier::send('system', 'Stock for :item is low', ['item' => 'Coffee'], url: 'admin/my-plugin', capability: 'plugins.manage');
```

### Database

- Migrations run each time the plugin is turned on. Already-run files are skipped.
- A new migration in an updated plugin only runs after the plugin is turned off and on again.
- Turning a plugin off keeps its tables and settings. There is no uninstall hook, and `down()` is never called. Tell the user if data should be removed by hand.
- Prefix tables with the slug: `faq_items`, not `items`. Prefix migration file names with a unique date, because they share one `migrations` table with core.
- **Backups and Import & Export do not include plugin tables**: `backup_tables` in `config/cms.php` is a fixed list. Tell the user when a plugin stores data they care about.

### Translations

`lang/id.json` is overwritten by updates, so a plugin keeps its own file: write `__('English text')` in code and put `{"English text": "Teks Indonesia"}` in `plugins/<slug>/lang/id.json`. The CMS loads it while the plugin is on; no code is needed (older plugins that call `app('translator')->addJsonPath(__DIR__.'/lang')` still work). Do not use a key equal to a Laravel lang file name, such as `Pagination` or `Validation`.

These strings become available everywhere while the plugin is on, including in landing pages. A key the core already translates keeps the core's translation.

### Cookies and caching

Public pages send no cookies to new visitors and are cached by CDNs for five minutes. For a plugin that means:

- Output in public hooks must be the same for every visitor. No per-user content, and no CSRF tokens in hooks: a token turns off caching on every page.
- A first-time visitor's session cookie is removed unless the page has a CSRF token, so `session()` values do not persist for them on GET requests.
- Do not write to the database on every public page view from a hook. If you must count views, use `DB::table()`, because `$post->increment()` changes `updated_at`, which the sitemap uses as the last change.

### Complete example: FAQ plugin

An admin list with add, edit and delete, a settings field, and output after one page. Copy this structure.

**`plugins/faq/plugin.json`**
```json
{
    "name": "FAQ",
    "description": "Keeps a list of questions and shows them after a chosen page.",
    "version": "1.0.0",
    "author": "DyskFlow",
    "settings": [
        { "name": "page_slug", "label": "Show after the page with this slug", "type": "text", "default": "faq" }
    ]
}
```

**`plugins/faq/migrations/2026_01_01_000000_create_faq_items_table.php`**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_items', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_items');
    }
};
```

**`plugins/faq/src/Item.php`**
```php
<?php

namespace Plugins\Faq;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $table = 'faq_items';

    protected $guarded = ['id'];
}
```

**`plugins/faq/src/AdminController.php`**
```php
<?php

namespace Plugins\Faq;

use App\Http\Controllers\Controller;
use App\Support\DataTable;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index()
    {
        $bulk = route('admin.faq.bulk');

        return view('admin.table', [
            'title' => __('FAQ'),
            'actions' => [['label' => __('New question'), 'sheet' => route('admin.faq.create')]],
            'table' => DataTable::make([
                'id' => 'faq',
                'source' => Item::query(),
                'columns' => [
                    ['key' => 'question', 'label' => __('Question'), 'sort' => true],
                    ['key' => 'updated_at', 'label' => __('Updated'), 'sort' => true, 'render' => fn ($item) => cms_date($item->updated_at)],
                ],
                'search' => ['question', 'answer'],
                'default_sort' => ['question', 'asc'],
                'bulk_url' => $bulk,
                'bulk' => ['delete' => ['label' => __('Delete'), 'danger' => true]],
                'row_actions' => fn ($item) => [
                    ['label' => __('Edit'), 'icon' => 'pencil', 'sheet' => route('admin.faq.edit', $item->id)],
                    ['label' => __('Delete'), 'icon' => 'trash', 'post' => $bulk, 'fields' => ['ids[]' => [$item->id], 'action' => 'delete'], 'danger' => true, 'confirm' => __('This action cannot be undone.')],
                ],
                'empty' => __('No questions yet.'),
            ]),
        ]);
    }

    public function form(?int $id = null)
    {
        $item = $id ? Item::findOrFail($id) : new Item;

        return view('admin.partials.sheet-form', [
            'title' => $id ? __('Edit question') : __('New question'),
            'action' => $id ? route('admin.faq.update', $id) : route('admin.faq.store'),
            'values' => ['question' => $item->question, 'answer' => $item->answer],
            'fields' => [
                ['name' => 'question', 'label' => __('Question'), 'required' => true],
                ['name' => 'answer', 'label' => __('Answer'), 'type' => 'textarea', 'required' => true],
            ],
        ]);
    }

    public function save(Request $request, ?int $id = null)
    {
        $item = $id ? Item::findOrFail($id) : new Item;
        $item->fill($request->validate([
            'question' => 'required|string|max:190',
            'answer' => 'required|string|max:5000',
        ]))->save();
        activity('faq.saved', $item->question);

        return back()->with('success', __('Saved.'));
    }

    public function bulk(Request $request)
    {
        $request->validate(['action' => 'required|in:delete']);
        $count = Item::whereIn('id', $this->ids($request))->delete();
        activity('faq.deleted', (string) $count);

        return back()->with('success', __('Done: :count item(s).', ['count' => $count]));
    }
}
```

**`plugins/faq/plugin.php`**
```php
<?php

use App\Support\Locales;
use Illuminate\Support\Facades\Route;
use Plugins\Faq\AdminController;
use Plugins\Faq\Item;

require_once __DIR__.'/src/Item.php';
require_once __DIR__.'/src/AdminController.php';

add_filter('cms.admin_menu', function ($items) {
    if (auth()->user()->can('plugins.manage')) {
        $items[] = ['label' => __('FAQ'), 'icon' => 'comment', 'url' => route('admin.faq.index'), 'match' => 'admin/faq*'];
    }

    return $items;
});

// The questions have one language, so they show only on the default-language page.
add_filter('cms.content', function ($html, $post) {
    if ($post->type !== 'page' || $post->slug !== plugin_setting('faq', 'page_slug', 'faq') || ! Locales::isDefault(app()->getLocale())) {
        return $html;
    }
    foreach (Item::orderBy('question')->get() as $item) {
        $html .= '<h3>'.e($item->question).'</h3><p>'.nl2br(e($item->answer)).'</p>';
    }

    return $html;
});

Route::middleware(['web', 'auth', 'can:plugins.manage'])->prefix('admin/faq')->name('admin.faq.')->controller(AdminController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('create', 'form')->name('create');
    Route::post('/', 'save')->name('store');
    Route::get('{id}/edit', 'form')->whereNumber('id')->name('edit');
    Route::post('{id}', 'save')->whereNumber('id')->name('update');
    Route::post('bulk', 'bulk')->name('bulk');
});
```

**`plugins/faq/lang/id.json`**
```json
{
    "FAQ": "Tanya jawab",
    "New question": "Pertanyaan baru",
    "Edit question": "Ubah pertanyaan",
    "Question": "Pertanyaan",
    "Answer": "Jawaban",
    "Updated": "Diperbarui",
    "No questions yet.": "Belum ada pertanyaan."
}
```

`plugins/hello-bar/` is a smaller example with settings and one hook.

### Plugin checklist

- [ ] Only `plugins/<slug>/` changed, with no new Composer package
- [ ] `plugin.php` only registers things, and classes are namespaced
- [ ] Routes include `web`, and admin routes include `auth` and `can:...`
- [ ] Menu items and settings cards check the permission
- [ ] Every default in `plugin.json` is repeated in `plugin_setting()`
- [ ] Hook output is escaped and is the same for every visitor
- [ ] Risky work in hooks is wrapped in `rescue()`
- [ ] Tables are prefixed with the slug, and the migration works on MySQL and SQLite
- [ ] The user knows if the data is not covered by backups

---

## 6. Check your work

```bash
php artisan serve
```

```bash
php artisan test
```

For a landing page, open `/slug` and `/en/slug`, or choose it in **Settings > Pages** for the homepage. View the page source and confirm there is one `<title>`.

For a plugin, turn it on in **Settings > Plugins**, then check an admin page, a public page, and the plugin's own screens as a user without the capability. If the plugin switched itself off, read `storage/logs/laravel.log`.

## 7. Prompts to give an AI tool

> Read `docs/AI-GUIDE.md`, then build a landing page in `landing/<slug>/` for: ___. Use the shared site header. Responsive, dark mode, both languages, no build step. Do not edit files outside that folder.

> Read `docs/AI-GUIDE.md`, then build a plugin in `plugins/<slug>/` that: ___. Do not edit core or add Composer packages. Follow the FAQ example and finish with the plugin checklist.
