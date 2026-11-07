# Plugins

Each plugin is a folder: `plugins/<slug>/`. Updates never overwrite this folder. AI tools should read [docs/AI-GUIDE.md](../docs/AI-GUIDE.md) for the full details.

```
plugins/my-plugin/
  plugin.json       required: name, description, version, author, settings
  plugin.php        loaded on every request while the plugin is on
  views/            optional: view('my-plugin::name')
  migrations/       optional: runs when the plugin is turned on
```

A plugin that throws an error while loading is turned off automatically, and the error goes to `storage/logs`.

## Settings

Fields declared in `plugin.json` appear in **Settings > Plugins** without extra code.
Types: `text`, `textarea`, `number`, `email`, `url`, `select` (with `options`), `switch`, `media`.

```php
$value = plugin_setting('my-plugin', 'field_name', 'default');
```

For a custom page, register a route and set `"settings_url": "admin/my-plugin"` in `plugin.json`.

## Hooks

```php
add_action('cms.head', function () { echo '<meta name="x" content="y">'; });
add_filter('cms.content', fn ($html, $post) => $html.'<p>Thanks for reading.</p>');
```

| Hook | Type | Arguments |
| --- | --- | --- |
| `cms.head` | action | Print into `<head>` |
| `cms.body_open` | action | Print after `<body>` |
| `cms.footer` | action | Print before `</body>` |
| `post.saved` | action | `$post` |
| `comment.created` | action | `$comment` |
| `cms.content` | filter | `$html`, `$post` (the result is sanitized after filters run) |
| `cms.admin_menu` | filter | `$items`: `label`, `icon`, `url`, `match`, optional `group` (sidebar group, default Plugins) |
| `cms.settings_cards` | filter | `$cards`: `title`, `description`, `icon`, `url`, `can` |

On a page in the second language, `$post` in `cms.content` holds the translated text for that request. Read it, but do not save it. Link to public pages with `site_route()` or `$post->url()` to stay in the current language.

## Notifications

Show a notification in the admin header. Write the title in English, and only users with the capability see it:

```php
\App\Support\Notifier::send('system', 'Stock for :item is low', ['item' => 'Kopi'], url: 'admin/my-plugin', capability: 'plugins.manage');
```

## Routes

Use the same middleware as the core admin:

```php
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:plugins.manage'])->get('admin/my-plugin', fn () => view('my-plugin::index'));
```
