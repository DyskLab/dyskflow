<?php

namespace App\Support\Builder;

use App\Support\HtmlSanitizer;
use App\Support\Locales;
use Illuminate\Support\HtmlString;

/**
 * Every widget is a folder with widget.json (its fields) and view.blade.php (its markup).
 * Core widgets live in resources/views/builder/widgets. Custom ones come from theme/widgets
 * or a plugin through the "builder.widgets" filter. A custom widget cannot replace a core
 * widget of the same name.
 */
class Widgets
{
    public const FIELD_TYPES = ['text', 'textarea', 'richtext', 'url', 'image', 'file', 'select', 'toggle', 'number', 'repeater', 'category'];

    public const URL_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /** What a "file" field may accept, as extensions. */
    public const FILE_TYPES = ['png', 'jpg', 'jpeg', 'webp', 'avif', 'gif', 'mp4', 'webm', 'pdf', 'mp3', 'm4a', 'ogg', 'wav'];

    /** @var array<string, array>|null */
    private static ?array $cache = null;

    /** @var list<string> problems found while reading widget.json files */
    private static array $problems = [];

    /** @return array<string, array> name => definition, for every widget a page can use */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        self::$problems = [];
        $widgets = self::fromFolder(resource_path('views/builder/widgets'), 'core');

        $sources = [];
        $theme = config('cms.design_roots.theme');
        if ($theme) {
            $sources[] = [$theme.'/widgets', 'theme'];
        }
        foreach ((array) apply_filters('builder.widgets', []) as $folder) {
            if (is_string($folder)) {
                $sources[] = [$folder, 'plugin'];
            }
        }

        foreach ($sources as [$folder, $source]) {
            foreach (self::fromFolder($folder, $source) as $name => $definition) {
                if (($widgets[$name]['source'] ?? null) === 'core') {
                    self::$problems[] = "{$source} widget \"{$name}\" has the name of a core widget and is ignored.";

                    continue;
                }
                $widgets[$name] = $definition;
            }
        }

        return self::$cache = $widgets;
    }

    public static function get(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }

    /** @return list<string> */
    public static function problems(): array
    {
        self::all();

        return self::$problems;
    }

    public static function flush(): void
    {
        self::$cache = null;
        self::$problems = [];
    }

    /**
     * The values a widget view receives: defaults filled in, text in the language of this request,
     * rich text cleaned, links and images checked. A bad value becomes empty, never an error.
     */
    public static function props(array $definition, array $node): array
    {
        $locale = app()->getLocale();
        $props = [];
        foreach ($definition['fields'] as $field) {
            $props[$field['key']] = self::resolve($field, $node[$field['key']] ?? ($field['default'] ?? null), $locale);
        }

        return $props;
    }

    public static function resolve(array $field, mixed $value, string $locale): mixed
    {
        if (! empty($field['translatable'])) {
            $value = self::translate($value, $locale);
        }

        return match ($field['type']) {
            'text', 'textarea' => is_scalar($value) ? (string) $value : '',
            'richtext' => new HtmlString(HtmlSanitizer::clean(is_string($value) ? $value : '')),
            'url' => self::url($value) ?? '',
            'image' => self::image($value) ?? '',
            'file' => self::file($field, $value) ?? '',
            'select' => ! empty($field['responsive']) ? self::responsiveOption($field, $value) : self::option($field, $value),
            'toggle' => $value === true || $value === 1 || $value === '1',
            'number' => self::number($field, $value),
            'category' => is_string($value) && preg_match('/^[a-z0-9][a-z0-9_-]*$/', $value) ? $value : '',
            'repeater' => self::repeater($field, $value, $locale),
            default => null,
        };
    }

    /** Text for one language. A plain string is the same in every language; a missing translation is empty. */
    public static function translate(mixed $value, string $locale): mixed
    {
        if (is_array($value) && (array_key_exists($locale, $value) || array_intersect_key($value, Locales::ALL))) {
            return $value[$locale] ?? '';
        }

        return $value;
    }

    /** http(s), mailto, tel, a path on this site, or an #anchor. Anything else, such as javascript:, is refused. */
    public static function url(mixed $value): ?string
    {
        if (! is_string($value) || ($value = trim($value)) === '') {
            return null;
        }
        if (str_starts_with($value, '#') || (str_starts_with($value, '/') && ! str_starts_with($value, '//'))) {
            return $value;
        }
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, self::URL_SCHEMES, true) && ! preg_match('/[\s<>"]/', $value) ? $value : null;
    }

    /** An image in uploads/ (the media library) or at an https address. */
    public static function image(mixed $value): ?string
    {
        if (! is_string($value) || ($value = trim($value)) === '' || str_contains($value, '..') || preg_match('/[\s<>"\']/', $value)) {
            return null;
        }
        if (preg_match('#^https://#i', $value)) {
            return $value;
        }
        $value = ltrim($value, '/');
        if (str_starts_with($value, 'uploads/')) {
            return asset($value);
        }

        return null;
    }

    /** Like an image, but only with an extension from the field's "accept" list, such as ["mp4", "webm"]. */
    public static function file(array $field, mixed $value): ?string
    {
        $url = self::image($value);
        $extension = strtolower(pathinfo((string) parse_url((string) $url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return $url !== null && in_array($extension, (array) ($field['accept'] ?? []), true) ? $url : null;
    }

    private static function option(array $field, mixed $value): mixed
    {
        $options = array_map('strval', $field['options'] ?? []);

        return is_scalar($value) && in_array((string) $value, $options, true) ? $value : ($field['default'] ?? ($field['options'][0] ?? null));
    }

    private static function responsiveOption(array $field, mixed $value): mixed
    {
        if (! is_array($value)) {
            return self::option($field, $value);
        }
        $out = [];
        foreach (Tokens::byBreakpoint($value) as $breakpoint => $token) {
            if (in_array((string) $token, array_map('strval', $field['options'] ?? []), true)) {
                $out[$breakpoint] = $token;
            }
        }
        $out['base'] ??= $field['default'] ?? ($field['options'][0] ?? null);

        return $out;
    }

    private static function number(array $field, mixed $value): int|float
    {
        $number = is_numeric($value) ? $value + 0 : ($field['default'] ?? 0);
        if (isset($field['min'])) {
            $number = max($field['min'], $number);
        }
        if (isset($field['max'])) {
            $number = min($field['max'], $number);
        }

        return $number;
    }

    private static function repeater(array $field, mixed $value, string $locale): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }
        $items = [];
        foreach (array_slice($value, 0, (int) ($field['max_items'] ?? 50)) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $resolved = [];
            foreach ($field['fields'] as $sub) {
                $resolved[$sub['key']] = self::resolve($sub, $item[$sub['key']] ?? ($sub['default'] ?? null), $locale);
            }
            $items[] = $resolved;
        }

        return $items;
    }

    /** @return array<string, array> */
    private static function fromFolder(string $folder, string $source): array
    {
        $widgets = [];
        foreach (glob(rtrim($folder, '/').'/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $name = basename($directory);
            if (! preg_match('/^[a-z][a-z0-9-]{0,40}$/', $name) || is_link($directory)) {
                continue;
            }
            $definition = self::readDefinition($directory, $name, $source, $problem);
            if ($definition) {
                $widgets[$name] = $definition;
            } else {
                self::$problems[] = "{$source} widget \"{$name}\": {$problem}";
            }
        }

        return $widgets;
    }

    private static function readDefinition(string $directory, string $name, string $source, ?string &$problem): ?array
    {
        $problem = null;
        if (! is_file($directory.'/view.blade.php')) {
            $problem = 'view.blade.php is missing.';

            return null;
        }
        $json = json_decode((string) @file_get_contents($directory.'/widget.json'), true);
        if (! is_array($json)) {
            $problem = 'widget.json is missing or is not valid JSON.';

            return null;
        }
        $fields = [];
        foreach ((array) ($json['fields'] ?? []) as $i => $field) {
            $error = self::fieldProblem($field);
            if ($error) {
                $problem = "fields[{$i}]: {$error}";

                return null;
            }
            $fields[] = $field;
        }

        return [
            'name' => $name,
            'label' => is_string($json['label'] ?? null) ? $json['label'] : ucfirst($name),
            'icon' => is_string($json['icon'] ?? null) ? $json['icon'] : 'page',
            'group' => is_string($json['group'] ?? null) ? $json['group'] : ($source === 'core' ? 'basic' : 'custom'),
            'description' => is_string($json['description'] ?? null) ? $json['description'] : '',
            // "single" or "archive": a dynamic widget offered only in that kind of template (Templates).
            'context' => in_array($json['context'] ?? null, ['single', 'archive'], true) ? $json['context'] : null,
            'fields' => $fields,
            'view' => $directory.'/view.blade.php',
            'source' => $source,
            'custom' => $source !== 'core',
        ];
    }

    private static function fieldProblem(mixed $field, bool $nested = false): ?string
    {
        if (! is_array($field) || ! is_string($field['key'] ?? null) || ! preg_match('/^[a-z][a-z0-9_]{0,40}$/', $field['key'])) {
            return 'each field needs a "key" of lowercase letters, digits and "_".';
        }
        if (in_array($field['key'], ['id', 'type', 'class', 'hide', 'anchor', 'style', 'margin'], true)) {
            return "\"{$field['key']}\" is reserved.";
        }
        if (! in_array($field['type'] ?? null, self::FIELD_TYPES, true)) {
            return "\"{$field['key']}\" has an unknown type. Use one of: ".implode(', ', self::FIELD_TYPES).'.';
        }
        if ($field['type'] === 'file' && (! is_array($field['accept'] ?? null) || ! $field['accept'] || array_diff($field['accept'], self::FILE_TYPES))) {
            return "\"{$field['key']}\" is a file and needs \"accept\": extensions such as [\"mp4\", \"webm\"].";
        }
        if ($field['type'] === 'select' && (! is_array($field['options'] ?? null) || ! $field['options'])) {
            return "\"{$field['key']}\" is a select and needs \"options\".";
        }
        if ($field['type'] === 'repeater') {
            if ($nested) {
                return "\"{$field['key']}\": a repeater cannot hold another repeater.";
            }
            if (! is_array($field['fields'] ?? null) || ! $field['fields']) {
                return "\"{$field['key']}\" is a repeater and needs \"fields\".";
            }
            foreach ($field['fields'] as $sub) {
                if ($error = self::fieldProblem($sub, true)) {
                    return $error;
                }
            }
        }

        return null;
    }
}
