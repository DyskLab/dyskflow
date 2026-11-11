<?php

namespace App\Support\Builder;

use App\Support\Locales;

/**
 * Checks a builder layout (posts.builder). Errors stop a save or a publish; warnings do not.
 * Every message starts with the path of the node and its id, for example
 * "sections[0].columns[1].widgets[2] (w0a14): field "src" is required", so a person or an AI can find it.
 */
class Validator
{
    public const MAX_NODES = 300;

    public const LAYOUTS = ['site', 'blank'];

    /** The page's own CSS and JavaScript, each at most this many bytes. */
    public const MAX_CODE = 65536;

    /** @var list<string> */
    private array $errors = [];

    /** @var list<string> */
    private array $warnings = [];

    /** @var array<string, string> id => path */
    private array $ids = [];

    private int $nodes = 0;

    private int $added = 0;

    /**
     * @return array{spec: array, errors: list<string>, warnings: list<string>}
     *                                                                          The spec comes back with missing ids filled in. Nothing else is changed.
     */
    public static function check(mixed $spec): array
    {
        $validator = new self;
        $spec = $validator->run($spec);

        return ['spec' => $spec, 'errors' => $validator->errors, 'warnings' => $validator->warnings];
    }

    private function run(mixed $spec): array
    {
        if (! is_array($spec) || array_is_list($spec) && $spec !== []) {
            $this->errors[] = 'The layout must be one JSON object.';

            return [];
        }
        if (($spec['version'] ?? null) !== Builder::VERSION) {
            $this->errors[] = '"version" must be '.Builder::VERSION.'.';
        }
        if (isset($spec['layout']) && ! in_array($spec['layout'], self::LAYOUTS, true)) {
            $this->errors[] = '"layout" must be one of: '.implode(', ', self::LAYOUTS).'.';
        }
        foreach (['css', 'js'] as $key) {
            if (! array_key_exists($key, $spec)) {
                continue;
            }
            if (! is_string($spec[$key])) {
                $this->errors[] = "\"$key\" must be text.";
            } elseif (strlen($spec[$key]) > self::MAX_CODE) {
                $this->errors[] = "\"$key\" is longer than ".(self::MAX_CODE / 1024).' KB.';
            }
        }
        if (! is_array($spec['sections'] ?? null) || ! array_is_list($spec['sections'])) {
            $this->errors[] = '"sections" must be a list.';

            return $spec;
        }

        foreach ($spec['sections'] as $i => $section) {
            $spec['sections'][$i] = is_array($section) && ($section['type'] ?? null) === 'container'
                ? $this->container($section, "sections[$i]", 1)
                : $this->section($section, "sections[$i]", 1);
        }
        if ($this->added) {
            $this->warnings[] = $this->added.' section(s), column(s) or widget(s) had no id. Run builder:validate --fix to add them.';
        }
        if ($this->nodes > self::MAX_NODES) {
            $this->errors[] = 'The page has '.$this->nodes.' sections, containers, columns and widgets. The limit is '.self::MAX_NODES.'.';
        }

        return $spec;
    }

    private function section(mixed $section, string $path, int $depth): mixed
    {
        if (! is_array($section)) {
            $this->errors[] = "$path: must be an object.";

            return $section;
        }
        $section = $this->identify($section, $path, 's');
        $at = $this->at($path, $section);
        if (($section['type'] ?? 'section') !== 'section') {
            $this->errors[] = "$at: \"type\" must be \"section\" or \"container\".";
        }
        $this->style($section['style'] ?? [], Tokens::SECTION, $at);
        if (($section['style']['background'] ?? null) === 'image' && ! Widgets::image($section['style']['image'] ?? null)) {
            $this->errors[] = "$at: a background of \"image\" needs \"style.image\": uploads/… or https://…";
        }
        $this->common($section, $at);

        if (! is_array($section['columns'] ?? null) || ! array_is_list($section['columns']) || $section['columns'] === []) {
            $this->errors[] = "$at: \"columns\" must be a list with at least one column.";

            return $section;
        }
        foreach ($section['columns'] as $i => $column) {
            $section['columns'][$i] = $this->column($column, "$path.columns[$i]", $depth);
        }

        return $section;
    }

    /** A flex container: free layout values in "style" (Box), and widgets or containers in "children". */
    private function container(array $node, string $path, int $depth): array
    {
        $node = $this->identify($node, $path, 'x');
        $at = $this->at($path, $node);
        foreach (Box::check($node['style'] ?? null, Box::CONTAINER) as $problem) {
            $this->errors[] = "$at: $problem.";
        }
        if (($node['style']['background'] ?? null) === 'image' && ! Widgets::image($node['style']['image'] ?? null)) {
            $this->errors[] = "$at: a background of \"image\" needs \"style.image\": uploads/… or https://…";
        }
        $this->common($node, $at);
        if (! array_key_exists('children', $node)) {
            $this->errors[] = "$at: \"children\" must be a list (it may be empty).";

            return $node;
        }
        if (! is_array($node['children']) || ! array_is_list($node['children'])) {
            $this->errors[] = "$at: \"children\" must be a list.";

            return $node;
        }
        foreach ($node['children'] as $i => $child) {
            $childPath = "$path.children[$i]";
            if (is_array($child) && ($child['type'] ?? null) === 'container') {
                if ($depth >= Builder::MAX_CONTAINER_DEPTH) {
                    $child = $this->identify($child, $childPath, 'x');
                    $this->errors[] = $this->at($childPath, $child).': containers nest at most '.Builder::MAX_CONTAINER_DEPTH.' levels deep.';
                    $node['children'][$i] = $child;
                } else {
                    $node['children'][$i] = $this->container($child, $childPath, $depth + 1);
                }
            } elseif (is_array($child) && ($child['type'] ?? null) === 'inner-section') {
                $child = $this->identify($child, $childPath, 'i');
                $this->errors[] = $this->at($childPath, $child).': a container holds widgets and containers. Use a container with direction "row" instead of an inner section.';
                $node['children'][$i] = $child;
            } else {
                $node['children'][$i] = $this->widget($child, $childPath, Builder::MAX_DEPTH);
            }
        }

        return $node;
    }

    private function column(mixed $column, string $path, int $depth): mixed
    {
        if (! is_array($column)) {
            $this->errors[] = "$path: must be an object.";

            return $column;
        }
        $column = $this->identify($column, $path, 'c');
        $at = $this->at($path, $column);
        $this->breakpoints($column['width'] ?? null, $at, '"width"');
        foreach (Tokens::byBreakpoint($column['width'] ?? 12) as $breakpoint => $width) {
            if (! in_array($width, Tokens::WIDTHS, true)) {
                $this->errors[] = "$at: \"width\" ($breakpoint) must be a whole number from 1 to 12.";
            }
        }
        if (isset($column['width']) && Tokens::byBreakpoint($column['width']) === []) {
            $this->errors[] = "$at: \"width\" must be a number from 1 to 12, or {\"base\": 6, \"mobile\": 12}.";
        }
        $this->style($column, Tokens::COLUMN, $at);
        $this->common($column, $at);

        if (! is_array($column['widgets'] ?? null) || ! array_is_list($column['widgets'])) {
            $this->errors[] = "$at: \"widgets\" must be a list.";

            return $column;
        }
        foreach ($column['widgets'] as $i => $widget) {
            $column['widgets'][$i] = $this->widget($widget, "$path.widgets[$i]", $depth);
        }

        return $column;
    }

    private function widget(mixed $widget, string $path, int $depth): mixed
    {
        if (! is_array($widget)) {
            $this->errors[] = "$path: must be an object.";

            return $widget;
        }
        $type = $widget['type'] ?? null;
        if ($type === 'inner-section') {
            if ($depth >= Builder::MAX_DEPTH) {
                $widget = $this->identify($widget, $path, 'i');
                $this->errors[] = $this->at($path, $widget).': an inner section cannot hold another inner section.';

                return $widget;
            }
            $widget = $this->section(['type' => 'section'] + $widget, $path, $depth + 1);

            return ['type' => 'inner-section'] + $widget;
        }
        $widget = $this->identify($widget, $path, 'w');
        $at = $this->at($path, $widget);

        if (! is_string($type) || $type === '') {
            $this->errors[] = "$at: \"type\" is required.";

            return $widget;
        }
        $definition = Widgets::get($type);
        if (! $definition) {
            $this->warnings[] = "$at: there is no widget \"$type\". It is kept but not shown.";

            return $widget;
        }

        $this->style($widget, Tokens::WIDGET, $at);
        foreach (Box::check($widget['style'] ?? null, Box::WIDGET) as $problem) {
            $this->errors[] = "$at: $problem.";
        }
        $this->common($widget, $at);
        foreach ($definition['fields'] as $field) {
            $this->field($field, $widget[$field['key']] ?? null, $at, $field['key']);
        }

        return $widget;
    }

    private function field(array $field, mixed $value, string $at, string $name): void
    {
        $label = "field \"$name\"";
        if ($value === null) {
            if (! empty($field['required']) && ! array_key_exists('default', $field)) {
                $this->errors[] = "$at: $label is required.";
            }

            return;
        }

        if (! empty($field['translatable']) && is_array($value) && ! array_is_list($value)) {
            $unknown = array_diff(array_keys($value), array_keys(Locales::ALL));
            if ($unknown) {
                $this->errors[] = "$at: $label has unknown languages: ".implode(', ', $unknown).'. Use '.implode(', ', array_keys(Locales::ALL)).'.';
            }
            $filled = array_filter($value, fn ($text) => is_string($text) && trim($text) !== '');
            if (! empty($field['required']) && ! $filled) {
                $this->errors[] = "$at: $label is required.";
            } elseif ($filled && count($filled) < count(Locales::ALL)) {
                $missing = array_diff(array_keys(Locales::ALL), array_keys($filled));
                $this->warnings[] = "$at: $label has no ".implode(', ', $missing).' text, so it is empty in that language.';
            }
            foreach ($value as $text) {
                $this->scalarField($field, $text, $at, $label);
            }

            return;
        }

        if ($field['type'] === 'repeater') {
            if (! is_array($value) || ! array_is_list($value)) {
                $this->errors[] = "$at: $label must be a list.";

                return;
            }
            if (isset($field['max_items']) && count($value) > $field['max_items']) {
                $this->errors[] = "$at: $label has ".count($value)." items. The limit is {$field['max_items']}.";
            }
            if (isset($field['min_items']) && count($value) < $field['min_items']) {
                $this->errors[] = "$at: $label needs at least {$field['min_items']} items.";
            }
            foreach ($value as $i => $item) {
                if (! is_array($item)) {
                    $this->errors[] = "$at: $label item $i must be an object.";

                    continue;
                }
                foreach ($field['fields'] as $sub) {
                    $this->field($sub, $item[$sub['key']] ?? null, $at, "$name.$i.{$sub['key']}");
                }
            }

            return;
        }

        if ($field['type'] === 'select' && ! empty($field['responsive']) && is_array($value)) {
            $this->breakpoints($value, $at, $label);
            foreach (Tokens::byBreakpoint($value) as $token) {
                $this->scalarField($field, $token, $at, $label);
            }

            return;
        }

        if (! empty($field['required']) && is_string($value) && trim($value) === '') {
            $this->errors[] = "$at: $label is required.";
        }
        $this->scalarField($field, $value, $at, $label);
    }

    private function scalarField(array $field, mixed $value, string $at, string $label): void
    {
        $type = $field['type'];
        if (in_array($type, ['text', 'textarea', 'richtext', 'url', 'image', 'file', 'category'], true) && ! is_string($value)) {
            $this->errors[] = "$at: $label must be text.";

            return;
        }
        if ($type === 'url' && trim($value) !== '' && Widgets::url($value) === null) {
            $this->errors[] = "$at: $label \"$value\" is not allowed. Use https://…, mailto:, tel:, /path or #anchor.";
        }
        if ($type === 'image' && trim($value) !== '' && Widgets::image($value) === null) {
            $this->errors[] = "$at: $label \"$value\" is not allowed. Use uploads/… from the media library or https://….";
        }
        if ($type === 'file' && trim($value) !== '' && Widgets::file($field, $value) === null) {
            $this->errors[] = "$at: $label \"$value\" is not allowed. Use a ".implode(' or ', $field['accept']).' file from uploads/… or https://….';
        }
        if (in_array($type, ['image', 'file'], true) && str_starts_with(ltrim($value, '/'), 'uploads/') && ! str_contains($value, '..') && ! is_file(public_path(ltrim($value, '/')))) {
            $this->warnings[] = "$at: $label points to $value, which does not exist yet.";
        }
        if ($type === 'select' && ! in_array((string) $value, array_map('strval', $field['options']), true)) {
            $this->errors[] = "$at: $label \"".(is_scalar($value) ? $value : gettype($value)).'" must be one of: '.implode(', ', $field['options']).'.';
        }
        if ($type === 'toggle' && ! is_bool($value)) {
            $this->errors[] = "$at: $label must be true or false.";
        }
        if ($type === 'number' && (! is_int($value) && ! is_float($value) || (isset($field['min']) && $value < $field['min']) || (isset($field['max']) && $value > $field['max']))) {
            $this->errors[] = "$at: $label must be a number".(isset($field['min'], $field['max']) ? " from {$field['min']} to {$field['max']}" : '').'.';
        }
    }

    private function style(mixed $style, array $table, string $at): void
    {
        if (! is_array($style)) {
            $this->errors[] = "$at: \"style\" must be an object.";

            return;
        }
        foreach ($table as $key => $token) {
            if (! array_key_exists($key, $style)) {
                continue;
            }
            if ($token['responsive']) {
                $this->breakpoints($style[$key], $at, "\"$key\"");
            }
            $values = $token['responsive'] ? Tokens::byBreakpoint($style[$key]) : (is_array($style[$key]) ? [] : ['base' => $style[$key]]);
            if ($values === [] || array_diff(array_map('strval', $values), array_map('strval', $token['values']))) {
                $this->errors[] = "$at: \"$key\" must be one of: ".implode(', ', $token['values']).($token['responsive'] ? ' (or per screen: {"base": …, "tablet": …, "mobile": …})' : '').'.';
            }
        }
    }

    private function common(array $node, string $at): void
    {
        if (isset($node['class']) && Tokens::customClasses($node['class']) !== trim(preg_replace('/\s+/', ' ', (string) (is_string($node['class']) ? $node['class'] : '')))) {
            $this->errors[] = "$at: \"class\" may hold only class names made of letters, digits, \"-\" and \"_\".";
        }
        if (isset($node['anchor']) && (! is_string($node['anchor']) || ! preg_match('/^[a-z][a-z0-9-]{0,60}$/', $node['anchor']))) {
            $this->errors[] = "$at: \"anchor\" must be lowercase letters, digits and \"-\", starting with a letter.";
        }
        if (isset($node['hide']) && (! is_array($node['hide']) || array_diff(array_keys($node['hide']), array_keys(Tokens::BREAKPOINTS)))) {
            $this->errors[] = "$at: \"hide\" must look like {\"mobile\": true}.";
        }
    }

    /** {"base": …, "phone": …} would silently drop "phone", so unknown screen names are errors. */
    private function breakpoints(mixed $value, string $at, string $label): void
    {
        if (is_array($value) && ($unknown = array_diff(array_keys($value), array_keys(Tokens::BREAKPOINTS)))) {
            $this->errors[] = "$at: $label has unknown screens: ".implode(', ', $unknown).'. Use base, tablet and mobile.';
        }
    }

    private function identify(array $node, string $path, string $prefix): array
    {
        $this->nodes++;
        if (! isset($node['id']) || ! is_string($node['id']) || ! preg_match(Builder::ID_PATTERN, $node['id'])) {
            if (isset($node['id'])) {
                $this->warnings[] = "$path: id \"".(is_scalar($node['id']) ? $node['id'] : '?').'" is not valid and was replaced.';
            }
            do {
                $id = Builder::newId($prefix);
            } while (isset($this->ids[$id]));
            $node = ['id' => $id] + $node;
            $this->added++;
        } elseif (isset($this->ids[$node['id']])) {
            $this->errors[] = "$path ({$node['id']}): the id is also used at {$this->ids[$node['id']]}.";
        }
        $this->ids[$node['id']] ??= $path;

        return $node;
    }

    private function at(string $path, array $node): string
    {
        return "$path ({$node['id']})";
    }
}
