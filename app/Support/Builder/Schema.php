<?php

namespace App\Support\Builder;

/**
 * JSON Schema for a builder layout, built from Tokens and every widget.json, so editors and AI
 * tools get completion for widget fields too. docs/builder.schema.json holds the core widgets
 * only, because it is replaced on update; `php artisan builder:schema --all` adds the theme and
 * plugin widgets. builder:validate stays the full check: links, images, ids and limits are not schema rules.
 */
class Schema
{
    private const ID = ['type' => 'string', 'pattern' => '^[a-z][a-z0-9]{2,15}$', 'description' => 'Stable id, unique in the page. Keep it when moving or editing a node.'];

    /** @return array<string, mixed> */
    public static function build(bool $all = false): array
    {
        $widgets = array_filter(Widgets::all(), fn ($w) => $all || $w['source'] === 'core');
        ksort($widgets);
        $names = array_keys($widgets);

        $rules = [[
            'if' => ['properties' => ['type' => ['const' => 'inner-section']]],
            'then' => ['required' => ['columns'], 'properties' => ['columns' => ['type' => 'array', 'items' => ['$ref' => '#/$defs/column']]]],
        ]];
        foreach ($widgets as $name => $widget) {
            $properties = [];
            $required = [];
            foreach ($widget['fields'] as $field) {
                $properties[$field['key']] = self::field($field);
                if (! empty($field['required'])) {
                    $required[] = $field['key'];
                }
            }
            $then = ['properties' => $properties];
            if ($required) {
                $then['required'] = $required;
            }
            $rules[] = ['if' => ['properties' => ['type' => ['const' => $name]]], 'then' => $then];
        }

        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            '$id' => 'https://github.com/DyskLab/dyskflow/docs/builder.schema.json',
            'title' => 'DyskFlow builder page layout',
            'description' => 'Made by php artisan builder:schema. Read and write a page with builder:export and builder:import; builder:validate also checks links, images, ids and limits. See docs/BUILDER.md.',
            'type' => 'object',
            'required' => ['version', 'sections'],
            'properties' => [
                '$schema' => ['type' => 'string'],
                'version' => ['const' => Builder::VERSION],
                'layout' => ['enum' => Validator::LAYOUTS, 'default' => 'site', 'description' => 'site: with the site header and footer. blank: without.'],
                'css' => ['type' => 'string', 'maxLength' => Validator::MAX_CODE, 'description' => 'CSS for this page only, in <head>. Target elements with the classes and anchors set on them, for example .promo { … } or #pricing { … }.'],
                'js' => ['type' => 'string', 'maxLength' => Validator::MAX_CODE, 'description' => 'JavaScript for this page only, at the end of <body>. Not run in the editor preview. Adding or changing it in the admin needs code.manage.'],
                'sections' => ['type' => 'array', 'items' => ['oneOf' => [['$ref' => '#/$defs/container'], ['$ref' => '#/$defs/section']]], 'description' => 'Containers (flexbox, like Elementor) or grid sections (12 columns). Both may be mixed.'],
            ],
            '$defs' => [
                'text' => [
                    'oneOf' => [['type' => 'string'], ['type' => 'object', 'properties' => ['id' => ['type' => 'string'], 'en' => ['type' => 'string']], 'additionalProperties' => false]],
                    'description' => 'The same text in every language, or {"id": "…", "en": "…"}. A missing language is empty.',
                ],
                'container' => [
                    'type' => 'object',
                    'required' => ['type', 'children'],
                    'properties' => self::common() + [
                        'type' => ['const' => 'container'],
                        'style' => ['type' => 'object', 'additionalProperties' => false, 'properties' => self::box(Box::CONTAINER)],
                        'children' => ['type' => 'array', 'items' => ['oneOf' => [['$ref' => '#/$defs/container'], ['$ref' => '#/$defs/widget']]], 'description' => 'Widgets and containers, nested up to '.Builder::MAX_CONTAINER_DEPTH.' levels. Laid out by style.direction (column by default).'],
                    ],
                    'description' => 'A flex container. At the top it is boxed (content centered, 1120px) with section padding; nested ones are full width with no padding. In a row, child containers without a width share the space.',
                ],
                'section' => [
                    'type' => 'object',
                    'required' => ['columns'],
                    'properties' => self::common() + [
                        'type' => ['const' => 'section'],
                        'style' => ['type' => 'object', 'properties' => self::tokens(Tokens::SECTION) + [
                            'image' => ['type' => 'string', 'description' => 'With background "image": uploads/… or https://…'],
                        ]],
                        'columns' => ['type' => 'array', 'minItems' => 1, 'items' => ['$ref' => '#/$defs/column']],
                    ],
                ],
                'column' => [
                    'type' => 'object',
                    'required' => ['widgets'],
                    'properties' => self::common() + [
                        'width' => self::responsive(['enum' => Tokens::WIDTHS]) + ['description' => 'Out of 12. On phones every column is 12 unless mobile says otherwise.'],
                    ] + self::tokens(Tokens::COLUMN) + [
                        'widgets' => ['type' => 'array', 'items' => ['$ref' => '#/$defs/widget']],
                    ],
                ],
                'widget' => [
                    'type' => 'object',
                    'required' => ['type'],
                    'properties' => self::common() + [
                        'type' => ['type' => 'string', 'description' => implode(', ', [...$names, 'inner-section']).'. A type that does not exist is kept and shown as a warning.'],
                    ] + self::tokens(Tokens::WIDGET) + [
                        'style' => ['type' => 'object', 'additionalProperties' => false, 'properties' => self::box(Box::WIDGET), 'description' => 'Size and spacing of the widget, mostly for widgets in a container.'],
                    ],
                    'allOf' => $rules,
                ],
            ],
        ];
    }

    public static function encode(array $schema): string
    {
        return json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    private static function common(): array
    {
        return [
            'id' => self::ID,
            'anchor' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9-]{0,60}$', 'description' => 'HTML id, for links such as #pricing.'],
            'class' => ['type' => 'string', 'description' => 'Class names from the theme CSS.'],
            'hide' => ['type' => 'object', 'properties' => ['base' => ['type' => 'boolean'], 'tablet' => ['type' => 'boolean'], 'mobile' => ['type' => 'boolean']], 'additionalProperties' => false],
        ];
    }

    /** Properties for the free values in Box, with the patterns the validator uses. */
    private static function box(array $table): array
    {
        $properties = [];
        foreach ($table as $key => $definition) {
            $one = match ($definition['type']) {
                'enum' => ['enum' => $definition['values']],
                'length' => ['type' => 'string', 'description' => 'A size: 24px, 50%, 2rem, 100vh'.(! empty($definition['auto']) ? ' or auto' : '').'.'],
                'box' => ['type' => 'string', 'description' => 'One to four sizes, like CSS padding: "24px" or "16px 32px".'],
                'color' => ['type' => 'string', 'description' => '#rrggbb, rgb(…), hsl(…), transparent or a theme color: var(--'.implode('), var(--', Box::THEME_COLORS).').'],
                'image' => ['type' => 'string', 'description' => 'With background "image": uploads/… or https://…'],
            };
            $properties[$key] = $definition['responsive'] ? self::responsive($one) : $one;
        }

        return $properties;
    }

    private static function tokens(array $table): array
    {
        $properties = [];
        foreach ($table as $key => $token) {
            $one = ['enum' => $token['values']];
            $properties[$key] = $token['responsive'] ? self::responsive($one) : $one;
        }

        return $properties;
    }

    private static function responsive(array $one): array
    {
        return ['oneOf' => [$one, ['type' => 'object', 'properties' => ['base' => $one, 'tablet' => $one, 'mobile' => $one], 'additionalProperties' => false]]];
    }

    private static function field(array $field): array
    {
        $schema = match ($field['type']) {
            'text', 'textarea', 'richtext' => ! empty($field['translatable']) ? ['$ref' => '#/$defs/text'] : ['type' => 'string'],
            'url' => ! empty($field['translatable']) ? ['$ref' => '#/$defs/text'] : ['type' => 'string'],
            'image', 'file', 'category' => ['type' => 'string'],
            'toggle' => ['type' => 'boolean'],
            'number' => array_filter(['type' => 'number', 'minimum' => $field['min'] ?? null, 'maximum' => $field['max'] ?? null], fn ($v) => $v !== null),
            'select' => ! empty($field['responsive']) ? self::responsive(['enum' => array_values($field['options'])]) : ['enum' => array_values($field['options'])],
            'repeater' => array_filter([
                'type' => 'array',
                'minItems' => $field['min_items'] ?? null,
                'maxItems' => $field['max_items'] ?? null,
                'items' => ['type' => 'object', 'properties' => array_map(fn ($f) => self::field($f), array_column($field['fields'] ?? [], null, 'key'))],
            ], fn ($v) => $v !== null),
            default => [],
        };
        $notes = array_filter([
            $field['label'] ?? null,
            $field['help'] ?? null,
            match ($field['type']) {
                'url' => 'https://…, mailto:, tel:, /path or #anchor.',
                'image' => 'uploads/… from the media library or https://…',
                'file' => implode(' or ', (array) ($field['accept'] ?? [])).': uploads/… or https://…',
                'richtext' => 'HTML; cleaned before it is shown.',
                'category' => 'A category slug.',
                default => null,
            },
        ]);
        if ($notes) {
            $schema['description'] = implode('. ', array_map(fn ($n) => rtrim($n, '.'), $notes)).'.';
        }
        if (array_key_exists('default', $field)) {
            $schema['default'] = $field['default'];
        }

        return $schema;
    }
}
